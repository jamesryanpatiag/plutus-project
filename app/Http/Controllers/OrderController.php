<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderStoreRequest;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Enums\OrderStatusEnum;
use App\Models\OrderStatus;
use Log;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = new Order();
        if ($request->start_date) {
            $orders = $orders->where('created_at', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $orders = $orders->where('created_at', '<=', $request->end_date . ' 23:59:59');
        }

        if ($request->search) {
            $keyword = $request->search;
            $orders = $orders->whereHas('customer', function ($query) use ($keyword) {
                $query->where('first_name', 'like', '%'.$keyword.'%')
                    ->orWhere('last_name', 'like', '%'.$keyword.'%');
            });
        }
        $orders = $orders->with(['items.product', 'payments', 'customer'])->latest()->paginate(10);

        $total = $orders->map(function ($i) {
            return $i->total();
        })->sum();
        $receivedAmount = $orders->map(function ($i) {
            return $i->receivedAmount();
        })->sum();

        // return response()->json($orders);

        return view('orders.index', compact('orders', 'total', 'receivedAmount'));
    }

    public function store(OrderStoreRequest $request)
    {
        Log::info("test");
        $order = Order::create([
            'customer_id' => $request->customer_id,
            'user_id' => $request->user()->id,
        ]);

        $cart = $request->user()->cart()->get();
        foreach ($cart as $item) {
            $order->items()->create([
                'price' => $item->price * $item->pivot->quantity,
                'quantity' => $item->pivot->quantity,
                'product_id' => $item->id,
            ]);
            $item->quantity = $item->quantity - $item->pivot->quantity;
            $item->save();
        }
        $request->user()->cart()->detach();
        $order->payments()->create([
            'amount' => $request->amount,
            'user_id' => $request->user()->id,
        ]);

        $freshOrder = Order::find($order->id);

        $total = $freshOrder->items->map(function ($i) {
            return $i->price;
        })->sum();

        $recievedAmount = $freshOrder->payments->map(function ($i) {
            return $i->amount;
        })->sum();

        if ($recievedAmount == 0) {
            $status = OrderStatus::where('name', OrderStatusEnum::ORDER_NOT_PAID)->first();
        } else if ($recievedAmount < $total) {
            $status = OrderStatus::where('name', OrderStatusEnum::ORDER_PARTIAL)->first();
        } else if ($recievedAmount == $total) {
            $status = OrderStatus::where('name', OrderStatusEnum::ORDER_PAID)->first();
        } else if ($recievedAmount > $total) {
            $status = OrderStatus::where('name', OrderStatusEnum::ORDER_CHANGE)->first();
        }

        $freshOrder->order_status_id = $status->id;
        $freshOrder->save();

        return 'success';
    }
    public function partialPayment(Request $request)
    {
        // return $request;
        $orderId = $request->order_id;
        $amount = $request->amount;

        // Find the order
        $order = Order::findOrFail($orderId);

        // Check if the amount exceeds the remaining balance
        $remainingAmount = $order->total() - $order->receivedAmount();
        if ($amount > $remainingAmount) {
            return redirect()->route('orders.index')->withErrors('Amount exceeds remaining balance');
        }

        // Save the payment
        DB::transaction(function () use ($order, $amount) {
            $order->payments()->create([
                'amount' => $amount,
                'user_id' => auth()->user()->id,
            ]);
        });

        return redirect()->route('orders.index')->with('success', 'Partial payment of ' . config('settings.currency_symbol') . number_format($amount, 2) . ' made successfully.');
    }

    public function destroy(Order $order)
    {
        $orderStatus = OrderStatus::where('name', OrderStatusEnum::ORDER_VOID)->first();
        $voidData = Order::find($order->id);
        $voidData->order_status_id = $orderStatus->id;
        $voidData->save();

        return response()->json([
            'success' => true
        ]);
    }
}

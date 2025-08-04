<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Customer;
use App\Models\Product;
use App\Models\OrderStatus;
use Illuminate\Support\Facades\DB;
use App\Enums\OrderStatusEnum;
use Log;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $orders = Order::with(['items', 'payments'])->get();
        $customersCount = Customer::count();
        $orderStatusVoid = OrderStatus::where('name', OrderStatusEnum::ORDER_VOID)->first();
        Log::info($orderStatusVoid);

        $lowStockProducts = Product::where('quantity', '<', 10)->get();

        $bestSellingProducts = DB::table('products')
            ->select('products.*', DB::raw('SUM(order_items.quantity) AS total_sold'))
            ->join('order_items', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotIn('orders.order_status_id', [$orderStatusVoid->id])
            ->groupBy('products.id')
            ->havingRaw('SUM(order_items.quantity) > 10')
            ->get();

        $currentMonthBestSelling = DB::table('products')
            ->select('products.*', DB::raw('SUM(order_items.quantity) AS total_sold'))
            ->join('order_items', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereYear('orders.created_at', date('Y'))
            ->whereNotIn('orders.order_status_id', [$orderStatusVoid->id])
            ->whereMonth('orders.created_at', date('m'))
            ->groupBy('products.id')
            ->havingRaw('SUM(order_items.quantity) > 500')  // Best-selling threshold for the current month
            ->get();

        $pastSixMonthsHotProducts = DB::table('products')
            ->select('products.*', DB::raw('SUM(order_items.quantity) AS total_sold'))
            ->join('order_items', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotIn('orders.order_status_id', [$orderStatusVoid->id])
            ->where('orders.created_at', '>=', now()->subMonths(6))  // Filter for the past 6 months
            ->groupBy('products.id')
            ->havingRaw('SUM(order_items.quantity) > 1000')  // Hot product threshold for past 6 months
            ->get();

        return view('home', [
            'orders_count' => $orders->count(),
            'income' => $orders->whereNotIn('order_status_id', [$orderStatusVoid->id])->map(function ($i) {
                return $i->receivedAmount() > $i->total() ? $i->total() : $i->receivedAmount();
            })->sum(),
            'income_today' => $orders->whereNotIn('order_status_id', [$orderStatusVoid->id])->where('created_at', '>=', date('Y-m-d') . ' 00:00:00')->map(function ($i) {
                return $i->receivedAmount() > $i->total() ? $i->total() : $i->receivedAmount();
            })->sum(),
            'customers_count' => $customersCount,
            'low_stock_products' => $lowStockProducts,
            'best_selling_products' => $bestSellingProducts,
            'current_month_products' => $currentMonthBestSelling,
            'past_months_products' => $pastSixMonthsHotProducts,
        ]);
    }
}

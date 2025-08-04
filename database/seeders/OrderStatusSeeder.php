<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\OrderStatus;

class OrderStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            'order.Not_Paid','order.Partial','order.Paid','order.Void','order.Change'    
        ];

        foreach ($data as $value) {
            OrderStatus::create([
                'name' => $value
            ]);
        }
    }
}

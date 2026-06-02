<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\Orderstatus;

class OrderController extends Controller
{
    public function ordered($uuid)
    {
        $order = Order::where('public_uuid', $uuid)->firstOrFail();
        $orderstatus = Orderstatus::where('name', 'bestellt')->firstOrFail();
        if ($order->orderstatus_id >= $orderstatus->id) {
            return redirect(OrderResource::getUrl('edit', ['record' => $order->id]));
        }
        $order->orderstatus_id = $orderstatus->id;
        $order->save();

        return view('OrderOrdered', ['url' => OrderResource::getUrl('edit', ['record' => $order->id])]);
    }

    public function taken($uuid)
    {
        $order = Order::where('public_uuid', $uuid)->firstOrFail();
        $orderstatus = Orderstatus::where('name', 'genommen')->firstOrFail();
        if ($order->orderstatus_id >= $orderstatus->id) {
            return redirect(OrderResource::getUrl('edit', ['record' => $order->id]));
        }
        $order->orderstatus_id = $orderstatus->id;
        $order->save();

        return view('OrderTaken', ['url' => OrderResource::getUrl('edit', ['record' => $order->id])]);
    }
}

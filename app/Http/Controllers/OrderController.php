<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\Orderstatus;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function ordered($uuid)
    {
        $order = Order::where('public_uuid', $uuid)->firstOrFail();
        $orderstatus = Orderstatus::where("name", "bestellt")->firstOrFail();
        $order->orderstatus_id = $orderstatus->id;
        $order->save();
        return redirect(OrderResource::getUrl('edit', ['record' => $order->id,]));
    }
    public function taken($uuid)
    {
        $order = Order::where('public_uuid', $uuid)->firstOrFail();
        $orderstatus = Orderstatus::where("name", "genommen")->firstOrFail();
        $order->orderstatus_id = $orderstatus->id;
        $order->save();
        return redirect(OrderResource::getUrl('edit', ['record' => $order->id,]));
    }

}


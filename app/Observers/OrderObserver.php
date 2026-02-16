<?php

namespace App\Observers;

use App\Mail\OrderArrived;
use App\Mail\OrderRegistered;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Models\Orderstatus;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        $user = User::find(1);
        Mail::to($user)->send(new OrderRegistered($order));
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        $orderstatusArrived = Orderstatus::where("name", "angekommen")->first();
        if($order->orderstatus_id == $orderstatusArrived->id) {
            $user = User::find($order->user_id);
            Mail::to($user)->send(new OrderArrived($order));
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        //
    }
}

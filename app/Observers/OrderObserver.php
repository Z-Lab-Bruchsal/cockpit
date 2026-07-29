<?php

namespace App\Observers;

use App\Mail\OrderArrived;
use App\Mail\OrderRegistered;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        $order->public_uuid = (string) Str::uuid();
        $order->save();
        // TODO: Activate again
        // $users = Role::where('name', 'ordermanager')->first()->users()->get();
        // foreach ($users as $user) {
        //     Mail::to($user)->send(new OrderRegistered($order));
        // }
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        $hasChanged = $order->getChanges();
        if ($hasChanged && isset($hasChanged['orderstatus_id'])) {
            if ($order->orderstatus->name == 'bestellt') {
                $order->orderdatetime = $order->updated_at;
                $order->saveQuietly();
            }
            if ($order->orderstatus->name == 'angekommen') {
                $user = User::find($order->user_id);
                Mail::to($user)->send(new OrderArrived($order));
            }
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

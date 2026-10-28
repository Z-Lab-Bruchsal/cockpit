<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\Orderstatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;

/**
 * Status pages behind the "Habe bestellt" / "Hab's genommen" buttons in order mails.
 * The links are signed; the page only shows a confirmation button so mail scanners cannot change anything.
 */
class OrderController extends Controller
{
    /**
     * Mail link lifetime; orders can lie around for a while before they arrive or are picked up.
     */
    public const LINK_LIFETIME_DAYS = 30;

    public static function statusUrl(Order $order, string $status): string
    {
        return URL::temporarySignedRoute('orders.status.show', now()->addDays(self::LINK_LIFETIME_DAYS), [
            'order' => $order->public_uuid,
            'status' => $status,
        ]);
    }

    public function show(Order $order, string $status): View
    {
        $orderstatus = Orderstatus::where('name', $status)->firstOrFail();

        return view('orders.status', [
            'order' => $order,
            'status' => $status,
            'isOpen' => $order->orderstatus_id < $orderstatus->id,
            'updateUrl' => URL::temporarySignedRoute('orders.status.update', now()->addHour(), ['order' => $order->public_uuid, 'status' => $status]),
            'cockpitUrl' => OrderResource::getUrl('edit', ['record' => $order->id]),
        ]);
    }

    public function update(Order $order, string $status): RedirectResponse
    {
        $orderstatus = Orderstatus::where('name', $status)->firstOrFail();

        if ($order->orderstatus_id < $orderstatus->id) {
            $order->orderstatus_id = $orderstatus->id;
            $order->save();
        }

        return redirect()
            ->to(self::statusUrl($order, $status))
            ->with('updated', true);
    }
}

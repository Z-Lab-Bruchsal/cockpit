<?php

namespace App\Services\Registrations;

use App\Models\Cart;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Finds the visitor's cart via a cookie holding a random token (only its hash is stored).
 */
class CartManager
{
    public const COOKIE = 'zlab_cart';

    protected const COOKIE_MINUTES = 60 * 24 * 30;

    public function __construct(protected Request $request) {}

    public function current(): ?Cart
    {
        $plainToken = $this->request->cookie(self::COOKIE);

        if (! is_string($plainToken) || $plainToken === '') {
            return null;
        }

        $cart = Cart::query()->where('token_hash', Cart::hashToken($plainToken))->first();

        if ($cart !== null && $cart->contact_id === null && ($contact = $this->loggedInContact()) !== null) {
            $cart->update(['contact_id' => $contact->id]);
        }

        return $cart;
    }

    public function currentOrCreate(): Cart
    {
        if (($cart = $this->current()) !== null) {
            return $cart;
        }

        $plainToken = Str::random(64);

        Cookie::queue(self::COOKIE, $plainToken, self::COOKIE_MINUTES, httpOnly: true, sameSite: 'lax');

        return Cart::query()->create([
            'token_hash' => Cart::hashToken($plainToken),
            'contact_id' => $this->loggedInContact()?->id,
        ]);
    }

    protected function loggedInContact(): ?Contact
    {
        /** @var Contact|null */
        return Auth::guard('contact')->user();
    }
}

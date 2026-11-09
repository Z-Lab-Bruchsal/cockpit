<?php

namespace App\Services\Orders;

use App\Models\Merchant;
use App\Models\MerchantDomain;
use Illuminate\Support\Str;

/**
 * Derives the merchant of an order from its product link, e.g. https://www.reichelt.de/… → Reichelt.
 * Unknown shops are named after their domain; once a merchant is confirmed, its domain is remembered.
 */
class MerchantResolver
{
    /**
     * Second level labels of country domains like amazon.co.uk.
     *
     * @var list<string>
     */
    protected const SECOND_LEVEL_LABELS = ['co', 'com', 'org', 'net', 'gov', 'ac'];

    /**
     * Host of the link in lower case without "www.", or null for links without a host.
     */
    public static function host(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $host = parse_url(Str::contains($url, '://') ? $url : 'https://'.$url, PHP_URL_HOST);

        if (! is_string($host) || ! str_contains($host, '.')) {
            return null;
        }

        return Str::of($host)->lower()->rtrim('.')->replaceStart('www.', '')->toString();
    }

    /**
     * The shop's own domain without subdomains, e.g. "smile.amazon.de" → "amazon.de", "shop.amazon.co.uk" → "amazon.co.uk".
     */
    public static function registrableDomain(?string $url): ?string
    {
        $host = static::host($url);

        if ($host === null) {
            return null;
        }

        $labels = explode('.', $host);
        $length = count($labels) >= 3 && in_array($labels[count($labels) - 2], static::SECOND_LEVEL_LABELS, true) ? 3 : 2;

        return implode('.', array_slice($labels, -$length));
    }

    /**
     * Known merchant for the link: the longest matching domain wins (the host itself or one of its parents).
     */
    public static function forUrl(?string $url): ?Merchant
    {
        $host = static::host($url);

        if ($host === null) {
            return null;
        }

        $labels = explode('.', $host);
        $candidates = [];

        for ($i = 0; $i < count($labels) - 1; $i++) {
            $candidates[] = implode('.', array_slice($labels, $i));
        }

        return MerchantDomain::query()
            ->with('merchant')
            ->whereIn('domain', $candidates)
            ->get()
            ->sortByDesc(fn (MerchantDomain $domain): int => strlen($domain->domain))
            ->first()
            ?->merchant;
    }

    /**
     * Name suggestion for an unknown shop, e.g. "reichelt.de" → "Reichelt", "conrad-electronic.de" → "Conrad-electronic".
     */
    public static function suggestName(?string $url): ?string
    {
        $domain = static::registrableDomain($url);

        return $domain === null ? null : Str::ucfirst(Str::before($domain, '.'));
    }
}

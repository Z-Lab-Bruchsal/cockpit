@php($settings = \App\Models\CompanySetting::current())

@if ($settings->hasBookingLimit())
    <p {{ $attributes->class('text-sm text-gray-600') }}>
        Damit alle Kinder eine Chance haben, sind pro Kind {{ $settings->bookingLimitText() }} buchbar.
    </p>
@endif

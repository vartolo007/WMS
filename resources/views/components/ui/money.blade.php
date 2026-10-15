{{--
    An amount with the company's currency symbol (FR-25): never glue the symbol to a number by hand.
    `amount` is a decimal string / integer, never a float. `symbol` overrides the symbol of the logged-in company.
    The number is isolated left-to-right so digits and separators keep their order inside the RTL page.
--}}
@props(['amount', 'symbol' => null])

@php($symbol ??= auth()->user()?->company?->currency_symbol)

<span {{ $attributes->class('whitespace-nowrap tabular-nums') }}><bdi dir="ltr">{{ \App\Support\Format::money($amount) }}</bdi>@if (filled($symbol)) <span>{{ $symbol }}</span>@endif</span>

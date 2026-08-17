@props(['cents' => 0, 'signed' => false, 'coloured' => false])

@php
    $cents = (int) $cents;

    $classes = match (true) {
        ! $coloured => 'text-neutral-900',
        $cents > 0 => 'text-credit-700',
        $cents < 0 => 'text-debt-700',
        default => 'text-neutral-500',
    };
@endphp

<span {{ $attributes->merge(['class' => "money {$classes}"]) }}>
    {{ \App\Support\Money::format($cents, $signed) }}
</span>

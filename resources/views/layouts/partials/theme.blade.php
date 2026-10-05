{{-- Theme variables (T17). The company colour applies to all its users; guests and the platform owner get the default. --}}
@php($primaryColor = auth()->user()?->company?->primary_color ?? config('wms.default_color'))
<style>
    :root {
        --color-primary: {{ $primaryColor }};
        --color-primary-dark: color-mix(in srgb, var(--color-primary) 85%, black);
        --color-primary-light: color-mix(in srgb, var(--color-primary) 9%, white);
        --color-primary-ring: color-mix(in srgb, var(--color-primary) 35%, white);
    }
</style>

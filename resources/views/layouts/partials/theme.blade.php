{{-- Theme variables. Phase 2 fills the colour from the company settings. --}}
@php($primaryColor = config('wms.default_color'))
<style>
    :root {
        --color-primary: {{ $primaryColor }};
        --color-primary-dark: color-mix(in srgb, var(--color-primary) 80%, black);
        --color-primary-light: color-mix(in srgb, var(--color-primary) 12%, white);
    }
</style>

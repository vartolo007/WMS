{{-- Table frame from design batch 1. Use <x-ui.th> / <x-ui.td> inside; scrolls horizontally on mobile. --}}
<div {{ $attributes->class('overflow-x-auto rounded-[10px] border border-line bg-white') }}>
    <table class="w-full border-collapse text-[15px]">
        {{ $slot }}
    </table>
</div>

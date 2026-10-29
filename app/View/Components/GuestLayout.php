<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class GuestLayout extends Component
{
    /**
     * @param  string|null  $width  Tailwind max-width class of the form column.
     * @param  string|null  $title  Page name shown in the browser tab before the platform name.
     */
    public function __construct(public ?string $width = null, public ?string $title = null) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.guest');
    }
}

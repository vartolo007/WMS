<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * A signed-in page without the sidebar (the suspended-subscription page, design screen 20).
 */
class StandaloneLayout extends Component
{
    /**
     * @param  string|null  $title  Page name for the browser tab.
     */
    public function __construct(public ?string $title = null) {}

    public function render(): View
    {
        return view('layouts.standalone');
    }
}

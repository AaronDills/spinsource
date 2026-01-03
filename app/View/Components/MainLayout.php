<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class MainLayout extends Component
{
    public function __construct(
        public ?string $title = null,
        public bool $transparentHeader = false,
        public bool $showRecentReviews = true,
    ) {
    }

    public function render(): View
    {
        return view('layouts.main');
    }
}

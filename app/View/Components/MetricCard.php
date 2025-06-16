<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;

class MetricCard extends Component
{
    public string $title;
    public string|float|int $value;
    public ?string $icon;
    public ?string $trend;

    public function __construct(string $title, $value, ?string $icon = null, ?string $trend = null)
    {
        $this->title = $title;
        $this->value = $value;
        $this->icon = $icon;
        $this->trend = $trend;
    }

    public function render()
    {
        return view('components.metric-card');
    }
}

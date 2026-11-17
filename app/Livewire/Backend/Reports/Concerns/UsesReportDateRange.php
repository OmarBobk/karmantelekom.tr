<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Reports\Concerns;

use App\Services\Reporting\LocalDateRange;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

/**
 * Shared Istanbul from/to URL state and presets for owner report screens.
 */
trait UsesReportDateRange
{
    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    protected function bootReportDateRange(): void
    {
        $this->from = Str::trim($this->from);
        $this->to = Str::trim($this->to);

        if ($this->from === '' && $this->to === '') {
            $this->showToday();
        }
    }

    public function applyRange(): void
    {
        $this->from = Str::trim($this->from);
        $this->to = Str::trim($this->to);
        $this->afterRangeChanged();
    }

    public function showToday(): void
    {
        $this->from = $this->to = LocalDateRange::today();
        $this->afterRangeChanged();
    }

    public function showYesterday(): void
    {
        $this->from = $this->to = LocalDateRange::yesterday();
        $this->afterRangeChanged();
    }

    public function showLastSevenDays(): void
    {
        [$this->from, $this->to] = LocalDateRange::lastSevenDays();
        $this->afterRangeChanged();
    }

    public function showThisMonth(): void
    {
        [$this->from, $this->to] = LocalDateRange::thisMonth();
        $this->afterRangeChanged();
    }

    /**
     * Reset pagination or other page-local state after the range changes.
     */
    protected function afterRangeChanged(): void
    {
        //
    }
}

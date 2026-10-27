<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\BackUpDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConsoleScheduleTest extends TestCase
{
    public function test_scheduled_tasks_are_registered(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('sitemap:generate')
            ->expectsOutputToContain(BackUpDatabase::class)
            ->assertSuccessful();
    }

    public function test_backup_command_dispatches_the_backup_job(): void
    {
        Queue::fake();

        $this->artisan('back-up-database')
            ->expectsOutput('Database backup job has been dispatched!')
            ->assertSuccessful();

        Queue::assertPushed(BackUpDatabase::class);
    }
}

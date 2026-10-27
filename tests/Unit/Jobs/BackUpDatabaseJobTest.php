<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\BackUpDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class BackUpDatabaseJobTest extends TestCase
{
    private string $backupPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupPath = storage_path('app/testing/backups-'.uniqid('', true));
        mkdir($this->backupPath, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->backupPath.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->backupPath)) {
            rmdir($this->backupPath);
        }

        parent::tearDown();
    }

    public function test_successful_dump_does_not_pass_password_on_the_command_line_and_prunes_old_files(): void
    {
        Process::fake();
        Log::spy();

        $oldBackup = $this->backupPath.DIRECTORY_SEPARATOR.'old.sql';
        file_put_contents($oldBackup, 'old');
        touch($oldBackup, now()->subDays(8)->timestamp);

        $recentBackup = $this->backupPath.DIRECTORY_SEPARATOR.'recent.sql';
        file_put_contents($recentBackup, 'recent');
        touch($recentBackup, now()->subDays(2)->timestamp);

        (new BackUpDatabase($this->backupPath))->handle();

        Process::assertRan(function (PendingProcess $process): bool {
            $command = is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;

            return str_contains($command, 'mysqldump')
                && str_contains($command, '--defaults-extra-file=')
                && ! str_contains($command, '-p')
                && ! str_contains($command, 'password=');
        });

        $this->assertFileDoesNotExist($oldBackup);
        $this->assertFileExists($recentBackup);
        Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => str_contains($message, 'Database backup created:'));
        Log::shouldHaveReceived('info')->with('Database backups pruned: 1');
    }

    public function test_failed_dump_logs_an_error_and_does_not_prune_old_files(): void
    {
        Process::fake([
            '*' => Process::result(errorOutput: 'dump failed', exitCode: 1),
        ]);
        Log::spy();

        $oldBackup = $this->backupPath.DIRECTORY_SEPARATOR.'old.sql';
        file_put_contents($oldBackup, 'old');
        touch($oldBackup, now()->subDays(8)->timestamp);

        (new BackUpDatabase($this->backupPath))->handle();

        $this->assertFileExists($oldBackup);
        Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'Database backup failed:'));
        Log::shouldNotHaveReceived('info', fn (string $message): bool => $message === 'Database backups pruned: 1');
    }
}

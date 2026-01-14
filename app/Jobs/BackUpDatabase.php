<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BackUpDatabase implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Backing up database');

        $connection = Config::get('database.default');
        $database = Config::get("database.connections.{$connection}.database");
        $username = Config::get("database.connections.{$connection}.username");
        $password = Config::get("database.connections.{$connection}.password");
        $host = Config::get("database.connections.{$connection}.host");
        $port = Config::get("database.connections.{$connection}.port", 3306);

        $backupPath = storage_path('app/backups/');

        if (!file_exists($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $backupName = 'database_backup_' . now()->format('Y-m-d_H-i-s') . '.sql';
        $backupFile = $backupPath . $backupName;

        // Build mysqldump command
        $command = sprintf(
            'mysqldump -h %s -P %s -u %s %s %s > %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            $password ? '-p' . escapeshellarg($password) : '',
            escapeshellarg($database),
            escapeshellarg($backupFile)
        );

        // Execute backup
        exec($command, $output, $returnVar);

        if ($returnVar === 0 && file_exists($backupFile)) {
            Log::info('Database backup created: ' . $backupName);
        } else {
            Log::error('Database backup failed: ' . $backupName);
            return;
        }

        // Prune backups older than 7 days
        $backups = glob($backupPath . '*.sql');
        $prunedBackups = 0;

        foreach ($backups as $backup) {
            if (filemtime($backup) < now()->subDays(7)->timestamp) {
                unlink($backup);
                $prunedBackups++;
            }
        }

        Log::info('Database backups pruned: ' . $prunedBackups);
    }
}

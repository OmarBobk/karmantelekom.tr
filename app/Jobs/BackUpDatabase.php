<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class BackUpDatabase implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ?string $backupPath = null) {}

    public function handle(): void
    {
        Log::info('Backing up database');

        $connection = Config::get('database.default');
        $database = Config::get("database.connections.{$connection}.database");
        $username = (string) Config::get("database.connections.{$connection}.username");
        $password = (string) Config::get("database.connections.{$connection}.password");
        $host = (string) Config::get("database.connections.{$connection}.host");
        $port = (string) Config::get("database.connections.{$connection}.port", 3306);

        $backupPath = rtrim($this->backupPath ?? storage_path('app/backups'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $backupName = 'database_backup_'.now()->format('Y-m-d_H-i-s').'.sql';
        $backupFile = $backupPath.$backupName;
        $configFile = $this->writeMysqlClientConfig($host, $port, $username, $password);

        try {
            $result = Process::forever()->run([
                'mysqldump',
                '--defaults-extra-file='.$configFile,
                '--result-file='.$backupFile,
                $database,
            ]);
        } finally {
            if (is_file($configFile)) {
                unlink($configFile);
            }
        }

        if (! $result->successful()) {
            Log::error('Database backup failed: '.$backupName, [
                'error' => $result->errorOutput(),
            ]);

            if (is_file($backupFile)) {
                unlink($backupFile);
            }

            return;
        }

        Log::info('Database backup created: '.$backupName);

        $this->pruneOldBackups($backupPath);
    }

    private function writeMysqlClientConfig(string $host, string $port, string $username, string $password): string
    {
        $configFile = tempnam(sys_get_temp_dir(), 'mysql-backup-');

        $contents = "[client]\n"
            .'host='.$this->quoteMysqlConfigValue($host)."\n"
            .'port='.$this->quoteMysqlConfigValue($port)."\n"
            .'user='.$this->quoteMysqlConfigValue($username)."\n";

        if ($password !== '') {
            $contents .= 'password='.$this->quoteMysqlConfigValue($password)."\n";
        }

        file_put_contents($configFile, $contents);
        chmod($configFile, 0600);

        return $configFile;
    }

    private function quoteMysqlConfigValue(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private function pruneOldBackups(string $backupPath): void
    {
        $backups = glob($backupPath.'*.sql') ?: [];
        $prunedBackups = 0;
        $cutoff = now()->subDays(7)->timestamp;

        foreach ($backups as $backup) {
            if (filemtime($backup) < $cutoff) {
                unlink($backup);
                $prunedBackups++;
            }
        }

        Log::info('Database backups pruned: '.$prunedBackups);
    }
}

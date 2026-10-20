<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    public function create(string $label): ?string
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);
        $name = Str::slug($label).'-'.now()->format('Y-m-d-His');

        if ($driver === 'sqlite') {
            $database = config("database.connections.{$connection}.database");

            if (! is_string($database) || $database === ':memory:' || ! File::exists($database)) {
                return null;
            }

            $path = $directory.'/'.$name.'.sqlite';
            File::copy($database, $path);

            return $this->verify($path);
        }

        if ($driver !== 'mysql') {
            throw new RuntimeException("Automatic backup is not configured for database driver [{$driver}].");
        }

        $config = config("database.connections.{$connection}");
        $path = $directory.'/'.$name.'.sql';
        $process = new Process([
            env('MYSQLDUMP_BINARY', 'mysqldump'),
            '--host='.$config['host'],
            '--port='.(string) $config['port'],
            '--user='.$config['username'],
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--result-file='.$path,
            $config['database'],
        ], null, ['MYSQL_PWD' => (string) $config['password']], null, 300);
        $process->run();

        if (! $process->isSuccessful()) {
            File::delete($path);
            throw new RuntimeException('Database backup failed; import was cancelled. '.$process->getErrorOutput());
        }

        return $this->verify($path);
    }

    private function verify(string $path): string
    {
        clearstatcache(true, $path);

        if (! File::exists($path) || File::size($path) < 32) {
            File::delete($path);
            throw new RuntimeException('Database backup verification failed; import was cancelled.');
        }

        file_put_contents($path.'.sha256', hash_file('sha256', $path).'  '.basename($path).PHP_EOL);

        return $path;
    }
}

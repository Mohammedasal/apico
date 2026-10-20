<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupCommandTest extends TestCase
{
    public function test_manual_backup_command_uses_verified_backup_service(): void
    {
        $source = storage_path('app/test-command-backup-source.sqlite');
        File::ensureDirectoryExists(dirname($source));
        file_put_contents($source, str_repeat('database-content-', 10));
        config([
            'database.default' => 'backup_command_test',
            'database.connections.backup_command_test' => [
                'driver' => 'sqlite',
                'database' => $source,
            ],
        ]);

        $this->artisan('apico:backup-database')
            ->expectsOutputToContain('Verified database backup created:')
            ->expectsOutputToContain('Checksum:')
            ->assertSuccessful();

        $backup = collect(File::glob(storage_path('app/backups/apico-manual-*.sqlite')))->sortDesc()->first();
        $this->assertNotNull($backup);
        $this->assertFileExists($backup.'.sha256');
        $this->assertSame(hash_file('sha256', $source), hash_file('sha256', $backup));

        File::delete([$source, $backup, $backup.'.sha256']);
    }
}

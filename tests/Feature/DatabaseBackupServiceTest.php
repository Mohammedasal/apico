<?php

namespace Tests\Feature;

use App\Services\DatabaseBackupService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupServiceTest extends TestCase
{
    public function test_sqlite_backup_is_copied_and_checksum_is_written(): void
    {
        $source = storage_path('app/test-backup-source.sqlite');
        File::ensureDirectoryExists(dirname($source));
        file_put_contents($source, str_repeat('database-content-', 10));
        config([
            'database.default' => 'backup_test',
            'database.connections.backup_test' => [
                'driver' => 'sqlite',
                'database' => $source,
            ],
        ]);

        $backup = app(DatabaseBackupService::class)->create('test-import');

        $this->assertNotNull($backup);
        $this->assertFileExists($backup);
        $this->assertFileExists($backup.'.sha256');
        $this->assertSame(hash_file('sha256', $source), hash_file('sha256', $backup));

        File::delete([$source, $backup, $backup.'.sha256']);
    }
}

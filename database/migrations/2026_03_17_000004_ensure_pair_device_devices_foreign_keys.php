<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pair_device_devices')) {
            return;
        }

        $database = (string) DB::connection()->getDatabaseName();

        $hasFk = static function (string $column, string $referencedTable) use ($database): bool {
            $result = DB::selectOne(
                'SELECT COUNT(*) AS cnt
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = ?
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?
                   AND REFERENCED_TABLE_NAME = ?',
                [$database, 'pair_device_devices', $column, $referencedTable]
            );

            return ((int) ($result->cnt ?? 0)) > 0;
        };

        if (!$hasFk('pair_device_id', 'pairdevices')) {
            DB::statement(
                'ALTER TABLE `pair_device_devices`
                 ADD CONSTRAINT `fk_pdd_pair_device_id`
                 FOREIGN KEY (`pair_device_id`) REFERENCES `pairdevices` (`Id`) ON DELETE CASCADE'
            );
        }

        if (!$hasFk('controller_id', 'controllers')) {
            DB::statement(
                'ALTER TABLE `pair_device_devices`
                 ADD CONSTRAINT `fk_pdd_controller_id`
                 FOREIGN KEY (`controller_id`) REFERENCES `controllers` (`Id`) ON DELETE CASCADE'
            );
        }

        if (!$hasFk('camera_id', 'cameras')) {
            DB::statement(
                'ALTER TABLE `pair_device_devices`
                 ADD CONSTRAINT `fk_pdd_camera_id`
                 FOREIGN KEY (`camera_id`) REFERENCES `cameras` (`Id`) ON DELETE CASCADE'
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('pair_device_devices')) {
            return;
        }

        $dropIfExists = static function (string $constraintName): void {
            $database = (string) DB::connection()->getDatabaseName();

            $exists = DB::selectOne(
                'SELECT COUNT(*) AS cnt
                 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = ?
                   AND TABLE_NAME = ?
                   AND CONSTRAINT_NAME = ?
                   AND CONSTRAINT_TYPE = ? ',
                [$database, 'pair_device_devices', $constraintName, 'FOREIGN KEY']
            );

            if (((int) ($exists->cnt ?? 0)) > 0) {
                DB::statement("ALTER TABLE `pair_device_devices` DROP FOREIGN KEY `{$constraintName}`");
            }
        };

        $dropIfExists('fk_pdd_pair_device_id');
        $dropIfExists('fk_pdd_controller_id');
        $dropIfExists('fk_pdd_camera_id');
    }
};


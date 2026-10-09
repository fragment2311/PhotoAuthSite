<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pairdevices') || !Schema::hasTable('pair_device_devices')) {
            return;
        }

        Schema::table('pairdevices', function (Blueprint $table): void {
            if (!Schema::hasColumn('pairdevices', 'Controller_member_id')) {
                $table->unsignedBigInteger('Controller_member_id')->nullable()->after('Controller_id');
            }

            if (!Schema::hasColumn('pairdevices', 'Entry_member_id')) {
                $table->unsignedBigInteger('Entry_member_id')->nullable()->after('Entry_camera_id');
            }

            if (!Schema::hasColumn('pairdevices', 'Exit_member_id')) {
                $table->unsignedBigInteger('Exit_member_id')->nullable()->after('Exit_camera_id');
            }
        });

        $database = (string) DB::connection()->getDatabaseName();

        $hasForeignKey = static function (string $constraintName) use ($database): bool {
            $row = DB::selectOne(
                'SELECT COUNT(*) AS cnt
                 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = ?
                   AND TABLE_NAME = ?
                   AND CONSTRAINT_NAME = ?
                   AND CONSTRAINT_TYPE = ?',
                [$database, 'pairdevices', $constraintName, 'FOREIGN KEY']
            );

            return ((int) ($row->cnt ?? 0)) > 0;
        };

        if (!$hasForeignKey('fk_pairdevices_controller_member_id')) {
            DB::statement(
                'ALTER TABLE `pairdevices`
                 ADD CONSTRAINT `fk_pairdevices_controller_member_id`
                 FOREIGN KEY (`Controller_member_id`) REFERENCES `pair_device_devices` (`id`) ON DELETE SET NULL'
            );
        }

        if (!$hasForeignKey('fk_pairdevices_entry_member_id')) {
            DB::statement(
                'ALTER TABLE `pairdevices`
                 ADD CONSTRAINT `fk_pairdevices_entry_member_id`
                 FOREIGN KEY (`Entry_member_id`) REFERENCES `pair_device_devices` (`id`) ON DELETE SET NULL'
            );
        }

        if (!$hasForeignKey('fk_pairdevices_exit_member_id')) {
            DB::statement(
                'ALTER TABLE `pairdevices`
                 ADD CONSTRAINT `fk_pairdevices_exit_member_id`
                 FOREIGN KEY (`Exit_member_id`) REFERENCES `pair_device_devices` (`id`) ON DELETE SET NULL'
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('pairdevices')) {
            return;
        }

        $dropIfExists = static function (string $constraintName): void {
            $database = (string) DB::connection()->getDatabaseName();

            $row = DB::selectOne(
                'SELECT COUNT(*) AS cnt
                 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = ?
                   AND TABLE_NAME = ?
                   AND CONSTRAINT_NAME = ?
                   AND CONSTRAINT_TYPE = ?',
                [$database, 'pairdevices', $constraintName, 'FOREIGN KEY']
            );

            if (((int) ($row->cnt ?? 0)) > 0) {
                DB::statement("ALTER TABLE `pairdevices` DROP FOREIGN KEY `{$constraintName}`");
            }
        };

        $dropIfExists('fk_pairdevices_controller_member_id');
        $dropIfExists('fk_pairdevices_entry_member_id');
        $dropIfExists('fk_pairdevices_exit_member_id');

        Schema::table('pairdevices', function (Blueprint $table): void {
            if (Schema::hasColumn('pairdevices', 'Controller_member_id')) {
                $table->dropColumn('Controller_member_id');
            }

            if (Schema::hasColumn('pairdevices', 'Entry_member_id')) {
                $table->dropColumn('Entry_member_id');
            }

            if (Schema::hasColumn('pairdevices', 'Exit_member_id')) {
                $table->dropColumn('Exit_member_id');
            }
        });
    }
};


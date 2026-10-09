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

        $this->ensureMemberReferenceColumnsOnPairs();
        $this->backfillPairMemberReferencesFromLegacyColumns();
        $this->dropPairDeviceIdFromMembersTable();
        $this->dropLegacyDeviceColumnsFromPairs();
    }

    public function down(): void
    {
        if (!Schema::hasTable('pairdevices') || !Schema::hasTable('pair_device_devices')) {
            return;
        }

        Schema::table('pairdevices', function (Blueprint $table): void {
            if (!Schema::hasColumn('pairdevices', 'Controller_id')) {
                $table->integer('Controller_id')->nullable()->after('Name');
            }

            if (!Schema::hasColumn('pairdevices', 'Entry_camera_id')) {
                $table->unsignedBigInteger('Entry_camera_id')->nullable()->after('Controller_id');
            }

            if (!Schema::hasColumn('pairdevices', 'Exit_camera_id')) {
                $table->unsignedBigInteger('Exit_camera_id')->nullable()->after('Entry_camera_id');
            }
        });

        Schema::table('pair_device_devices', function (Blueprint $table): void {
            if (!Schema::hasColumn('pair_device_devices', 'pair_device_id')) {
                $table->integer('pair_device_id')->nullable()->after('id');
            }
        });

        $database = (string) DB::connection()->getDatabaseName();

        $hasForeignKey = static function (string $table, string $constraintName) use ($database): bool {
            $row = DB::selectOne(
                'SELECT COUNT(*) AS cnt
                 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = ?
                   AND TABLE_NAME = ?
                   AND CONSTRAINT_NAME = ?
                   AND CONSTRAINT_TYPE = ?',
                [$database, $table, $constraintName, 'FOREIGN KEY']
            );

            return ((int) ($row->cnt ?? 0)) > 0;
        };

        if (!$hasForeignKey('pair_device_devices', 'fk_pdd_pair_device_id')) {
            DB::statement(
                'ALTER TABLE `pair_device_devices`
                 ADD CONSTRAINT `fk_pdd_pair_device_id`
                 FOREIGN KEY (`pair_device_id`) REFERENCES `pairdevices` (`Id`) ON DELETE CASCADE'
            );
        }

        $pairs = DB::table('pairdevices')
            ->select(['Id', 'Controller_member_id', 'Entry_member_id', 'Exit_member_id'])
            ->get();

        foreach ($pairs as $pair) {
            $pairId = (int) ($pair->Id ?? 0);
            if ($pairId < 1) {
                continue;
            }

            $controllerMemberId = (int) ($pair->Controller_member_id ?? 0);
            $entryMemberId = (int) ($pair->Entry_member_id ?? 0);
            $exitMemberId = (int) ($pair->Exit_member_id ?? 0);

            $controllerId = null;
            if ($controllerMemberId > 0) {
                $controllerId = DB::table('pair_device_devices')->where('id', $controllerMemberId)->value('controller_id');
                DB::table('pair_device_devices')->where('id', $controllerMemberId)->update(['pair_device_id' => $pairId]);
            }

            $entryCameraId = null;
            if ($entryMemberId > 0) {
                $entryCameraId = DB::table('pair_device_devices')->where('id', $entryMemberId)->value('camera_id');
                DB::table('pair_device_devices')->where('id', $entryMemberId)->update(['pair_device_id' => $pairId]);
            }

            $exitCameraId = null;
            if ($exitMemberId > 0) {
                $exitCameraId = DB::table('pair_device_devices')->where('id', $exitMemberId)->value('camera_id');
                DB::table('pair_device_devices')->where('id', $exitMemberId)->update(['pair_device_id' => $pairId]);
            }

            DB::table('pairdevices')
                ->where('Id', $pairId)
                ->update([
                    'Controller_id' => $controllerId,
                    'Entry_camera_id' => $entryCameraId,
                    'Exit_camera_id' => $exitCameraId,
                ]);
        }
    }

    private function ensureMemberReferenceColumnsOnPairs(): void
    {
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

    private function backfillPairMemberReferencesFromLegacyColumns(): void
    {
        if (!Schema::hasColumn('pairdevices', 'Controller_id')
            || !Schema::hasColumn('pair_device_devices', 'pair_device_id')) {
            return;
        }

        $pairs = DB::table('pairdevices')
            ->select([
                'Id',
                'Controller_id',
                'Entry_camera_id',
                'Exit_camera_id',
                'Controller_member_id',
                'Entry_member_id',
                'Exit_member_id',
            ])
            ->get();

        foreach ($pairs as $pair) {
            $pairId = (int) ($pair->Id ?? 0);
            if ($pairId < 1) {
                continue;
            }

            $controllerMemberId = (int) ($pair->Controller_member_id ?? 0);
            $entryMemberId = (int) ($pair->Entry_member_id ?? 0);
            $exitMemberId = (int) ($pair->Exit_member_id ?? 0);

            if ($controllerMemberId < 1 && (int) ($pair->Controller_id ?? 0) > 0) {
                $controllerMemberId = (int) (DB::table('pair_device_devices')
                    ->where('pair_device_id', $pairId)
                    ->where('controller_id', (int) $pair->Controller_id)
                    ->value('id') ?? 0);
            }

            if ($entryMemberId < 1 && (int) ($pair->Entry_camera_id ?? 0) > 0) {
                $entryMemberId = (int) (DB::table('pair_device_devices')
                    ->where('pair_device_id', $pairId)
                    ->where('camera_id', (int) $pair->Entry_camera_id)
                    ->value('id') ?? 0);
            }

            if ($exitMemberId < 1 && (int) ($pair->Exit_camera_id ?? 0) > 0) {
                $exitMemberId = (int) (DB::table('pair_device_devices')
                    ->where('pair_device_id', $pairId)
                    ->where('camera_id', (int) $pair->Exit_camera_id)
                    ->value('id') ?? 0);
            }

            DB::table('pairdevices')
                ->where('Id', $pairId)
                ->update([
                    'Controller_member_id' => $controllerMemberId > 0 ? $controllerMemberId : null,
                    'Entry_member_id' => $entryMemberId > 0 ? $entryMemberId : null,
                    'Exit_member_id' => $exitMemberId > 0 ? $exitMemberId : null,
                ]);
        }
    }

    private function dropPairDeviceIdFromMembersTable(): void
    {
        if (!Schema::hasColumn('pair_device_devices', 'pair_device_id')) {
            return;
        }

        $database = (string) DB::connection()->getDatabaseName();

        $foreignKeys = DB::select(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$database, 'pair_device_devices', 'pair_device_id']
        );

        foreach ($foreignKeys as $fk) {
            $constraint = (string) ($fk->CONSTRAINT_NAME ?? '');
            if ($constraint !== '') {
                DB::statement("ALTER TABLE `pair_device_devices` DROP FOREIGN KEY `{$constraint}`");
            }
        }

        $indexes = DB::select(
            'SELECT DISTINCT INDEX_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND INDEX_NAME <> ? ',
            [$database, 'pair_device_devices', 'pair_device_id', 'PRIMARY']
        );

        foreach ($indexes as $idx) {
            $indexName = (string) ($idx->INDEX_NAME ?? '');
            if ($indexName !== '') {
                DB::statement("ALTER TABLE `pair_device_devices` DROP INDEX `{$indexName}`");
            }
        }

        Schema::table('pair_device_devices', function (Blueprint $table): void {
            if (Schema::hasColumn('pair_device_devices', 'pair_device_id')) {
                $table->dropColumn('pair_device_id');
            }
        });
    }

    private function dropLegacyDeviceColumnsFromPairs(): void
    {
        $this->dropForeignKeysForColumn('pairdevices', 'Controller_id');
        $this->dropForeignKeysForColumn('pairdevices', 'Entry_camera_id');
        $this->dropForeignKeysForColumn('pairdevices', 'Exit_camera_id');

        Schema::table('pairdevices', function (Blueprint $table): void {
            if (Schema::hasColumn('pairdevices', 'Controller_id')) {
                $table->dropColumn('Controller_id');
            }

            if (Schema::hasColumn('pairdevices', 'Entry_camera_id')) {
                $table->dropColumn('Entry_camera_id');
            }

            if (Schema::hasColumn('pairdevices', 'Exit_camera_id')) {
                $table->dropColumn('Exit_camera_id');
            }
        });
    }

    private function dropForeignKeysForColumn(string $table, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        $database = (string) DB::connection()->getDatabaseName();

        $foreignKeys = DB::select(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$database, $table, $column]
        );

        foreach ($foreignKeys as $fk) {
            $constraintName = (string) ($fk->CONSTRAINT_NAME ?? '');
            if ($constraintName !== '') {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraintName}`");
            }
        }
    }
};


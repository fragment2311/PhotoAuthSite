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

        $this->ensureSingleMemberLinkColumn();
        $this->backfillSingleMemberLink();
        $this->dropPairDeviceIdColumnFromMembers();
        $this->dropLegacyPairColumns();
    }

    public function down(): void
    {
        // Обратная миграция не требуется для текущего этапа.
    }

    private function ensureSingleMemberLinkColumn(): void
    {
        Schema::table('pairdevices', function (Blueprint $table): void {
            if (!Schema::hasColumn('pairdevices', 'IdPairDeviceDevices')) {
                $table->unsignedBigInteger('IdPairDeviceDevices')->nullable()->after('Name');
            }
        });

        $database = (string) DB::connection()->getDatabaseName();
        $exists = DB::selectOne(
            'SELECT COUNT(*) AS cnt
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = ? ',
            [$database, 'pairdevices', 'fk_pairdevices_id_pair_device_devices', 'FOREIGN KEY']
        );

        if (((int) ($exists->cnt ?? 0)) === 0) {
            DB::statement(
                'ALTER TABLE `pairdevices`
                 ADD CONSTRAINT `fk_pairdevices_id_pair_device_devices`
                 FOREIGN KEY (`IdPairDeviceDevices`) REFERENCES `pair_device_devices` (`id`) ON DELETE SET NULL'
            );
        }
    }

    private function backfillSingleMemberLink(): void
    {
        $hasPairDeviceIdOnMembers = Schema::hasColumn('pair_device_devices', 'pair_device_id');

        $select = ['Id'];
        foreach ([
            'IdPairDeviceDevices',
            'Controller_member_id',
            'Entry_member_id',
            'Exit_member_id',
            'Controller_id',
            'Entry_camera_id',
            'Exit_camera_id',
        ] as $column) {
            if (Schema::hasColumn('pairdevices', $column)) {
                $select[] = $column;
            }
        }

        $pairs = DB::table('pairdevices')->select($select)->get();

        foreach ($pairs as $pair) {
            $pairId = (int) ($pair->Id ?? 0);
            if ($pairId < 1) {
                continue;
            }

            $existingMemberId = (int) ($pair->IdPairDeviceDevices ?? 0);
            $controllerId = null;
            $cameraId = null;

            foreach (['Controller_member_id', 'Entry_member_id', 'Exit_member_id'] as $memberColumn) {
                $mid = (int) ($pair->{$memberColumn} ?? 0);
                if ($mid < 1) {
                    continue;
                }

                $row = DB::table('pair_device_devices')
                    ->where('id', $mid)
                    ->first(['controller_id', 'camera_id']);

                if (!$row) {
                    continue;
                }

                if ($controllerId === null && !empty($row->controller_id)) {
                    $controllerId = (int) $row->controller_id;
                }

                if ($cameraId === null && !empty($row->camera_id)) {
                    $cameraId = (int) $row->camera_id;
                }
            }

            if ($controllerId === null && !empty($pair->Controller_id)) {
                $controllerId = (int) $pair->Controller_id;
            }

            if ($cameraId === null && !empty($pair->Entry_camera_id)) {
                $cameraId = (int) $pair->Entry_camera_id;
            }

            if ($cameraId === null && !empty($pair->Exit_camera_id)) {
                $cameraId = (int) $pair->Exit_camera_id;
            }

            if (($controllerId === null || $cameraId === null) && $hasPairDeviceIdOnMembers) {
                $rows = DB::table('pair_device_devices')
                    ->where('pair_device_id', $pairId)
                    ->orderBy('id')
                    ->get(['controller_id', 'camera_id']);

                foreach ($rows as $row) {
                    if ($controllerId === null && !empty($row->controller_id)) {
                        $controllerId = (int) $row->controller_id;
                    }
                    if ($cameraId === null && !empty($row->camera_id)) {
                        $cameraId = (int) $row->camera_id;
                    }
                }
            }

            $payload = [
                'controller_id' => $controllerId,
                'camera_id' => $cameraId,
            ];

            if ($controllerId === null && $cameraId === null) {
                DB::table('pairdevices')->where('Id', $pairId)->update(['IdPairDeviceDevices' => null]);
                continue;
            }

            if ($existingMemberId > 0 && DB::table('pair_device_devices')->where('id', $existingMemberId)->exists()) {
                DB::table('pair_device_devices')->where('id', $existingMemberId)->update($payload);
                $memberId = $existingMemberId;
            } else {
                $memberId = (int) DB::table('pair_device_devices')->insertGetId($payload);
            }

            DB::table('pairdevices')->where('Id', $pairId)->update(['IdPairDeviceDevices' => $memberId]);
        }
    }

    private function dropPairDeviceIdColumnFromMembers(): void
    {
        if (!Schema::hasColumn('pair_device_devices', 'pair_device_id')) {
            return;
        }

        $this->dropForeignKeysForColumn('pair_device_devices', 'pair_device_id');
        $this->dropIndexesForColumn('pair_device_devices', 'pair_device_id');

        Schema::table('pair_device_devices', function (Blueprint $table): void {
            if (Schema::hasColumn('pair_device_devices', 'pair_device_id')) {
                $table->dropColumn('pair_device_id');
            }
        });
    }

    private function dropLegacyPairColumns(): void
    {
        foreach ([
            'Controller_member_id',
            'Entry_member_id',
            'Exit_member_id',
            'Controller_id',
            'Entry_camera_id',
            'Exit_camera_id',
        ] as $column) {
            $this->dropForeignKeysForColumn('pairdevices', $column);
        }

        Schema::table('pairdevices', function (Blueprint $table): void {
            foreach ([
                'Controller_member_id',
                'Entry_member_id',
                'Exit_member_id',
                'Controller_id',
                'Entry_camera_id',
                'Exit_camera_id',
            ] as $column) {
                if (Schema::hasColumn('pairdevices', $column)) {
                    $table->dropColumn($column);
                }
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
            $constraint = (string) ($fk->CONSTRAINT_NAME ?? '');
            if ($constraint !== '') {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
            }
        }
    }

    private function dropIndexesForColumn(string $table, string $column): void
    {
        $database = (string) DB::connection()->getDatabaseName();
        $indexes = DB::select(
            'SELECT DISTINCT INDEX_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND INDEX_NAME <> ? ',
            [$database, $table, $column, 'PRIMARY']
        );

        foreach ($indexes as $idx) {
            $indexName = (string) ($idx->INDEX_NAME ?? '');
            if ($indexName !== '') {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
            }
        }
    }
};


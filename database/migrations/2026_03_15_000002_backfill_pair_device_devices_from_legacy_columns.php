<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pairdevices') || !Schema::hasTable('pair_device_devices')) {
            return;
        }

        $pairs = DB::table('pairdevices')
            ->select(['Id', 'Controller_id', 'Entry_camera_id', 'Exit_camera_id'])
            ->get();

        $now = now();

        foreach ($pairs as $pair) {
            $pairId = (int) ($pair->Id ?? 0);
            if ($pairId < 1) {
                continue;
            }

            // Если по паре уже есть записи в новой таблице — не дублируем.
            $exists = DB::table('pair_device_devices')
                ->where('pair_device_id', $pairId)
                ->exists();

            if ($exists) {
                continue;
            }

            $rows = [];

            if (!empty($pair->Controller_id)) {
                $rows[] = [
                    'pair_device_id'   => $pairId,
                    'device_type'      => 'controller',
                    'controller_id'    => (int) $pair->Controller_id,
                    'camera_id'        => null,
                    'role'             => 'controller',
                    'sort_order'       => 10,
                    'IsActive'         => 1,
                    'Last_modified_at' => $now,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }

            if (!empty($pair->Entry_camera_id)) {
                $rows[] = [
                    'pair_device_id'   => $pairId,
                    'device_type'      => 'camera',
                    'controller_id'    => null,
                    'camera_id'        => (int) $pair->Entry_camera_id,
                    'role'             => 'entry',
                    'sort_order'       => 20,
                    'IsActive'         => 1,
                    'Last_modified_at' => $now,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }

            if (!empty($pair->Exit_camera_id)) {
                $rows[] = [
                    'pair_device_id'   => $pairId,
                    'device_type'      => 'camera',
                    'controller_id'    => null,
                    'camera_id'        => (int) $pair->Exit_camera_id,
                    'role'             => 'exit',
                    'sort_order'       => 30,
                    'IsActive'         => 1,
                    'Last_modified_at' => $now,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }

            if (!empty($rows)) {
                DB::table('pair_device_devices')->insert($rows);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('pair_device_devices')) {
            return;
        }

        DB::table('pair_device_devices')
            ->whereIn('role', ['controller', 'entry', 'exit'])
            ->delete();
    }
};


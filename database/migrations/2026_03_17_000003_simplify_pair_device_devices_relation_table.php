<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pair_device_devices')) {
            return;
        }

        Schema::table('pair_device_devices', function (Blueprint $table): void {
            // Удаляем производные поля, оставляем только поля связи
            if (Schema::hasColumn('pair_device_devices', 'device_type')) {
                $table->dropColumn('device_type');
            }
            if (Schema::hasColumn('pair_device_devices', 'role')) {
                $table->dropColumn('role');
            }
            if (Schema::hasColumn('pair_device_devices', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
            if (Schema::hasColumn('pair_device_devices', 'IsActive')) {
                $table->dropColumn('IsActive');
            }
            if (Schema::hasColumn('pair_device_devices', 'Last_modified_at')) {
                $table->dropColumn('Last_modified_at');
            }
            if (Schema::hasColumn('pair_device_devices', 'created_at')) {
                $table->dropColumn('created_at');
            }
            if (Schema::hasColumn('pair_device_devices', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('pair_device_devices')) {
            return;
        }

        Schema::table('pair_device_devices', function (Blueprint $table): void {
            if (!Schema::hasColumn('pair_device_devices', 'device_type')) {
                $table->enum('device_type', ['controller', 'camera'])->default('camera')->after('pair_device_id');
            }
            if (!Schema::hasColumn('pair_device_devices', 'role')) {
                $table->string('role', 32)->nullable()->after('camera_id');
            }
            if (!Schema::hasColumn('pair_device_devices', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(100)->after('role');
            }
            if (!Schema::hasColumn('pair_device_devices', 'IsActive')) {
                $table->boolean('IsActive')->default(1)->after('sort_order');
            }
            if (!Schema::hasColumn('pair_device_devices', 'Last_modified_at')) {
                $table->dateTime('Last_modified_at')->useCurrent()->after('IsActive');
            }
            if (!Schema::hasColumn('pair_device_devices', 'created_at')) {
                $table->timestamp('created_at')->nullable()->after('Last_modified_at');
            }
            if (!Schema::hasColumn('pair_device_devices', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });
    }
};


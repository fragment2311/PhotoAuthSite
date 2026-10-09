<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'IdWebusers')) {
                $table->unsignedBigInteger('IdWebusers')->nullable()->after('PassCam');
                $table->index('IdWebusers', 'idx_settings_idwebusers');
            }

            if (!Schema::hasColumn('settings', 'ChangedAt')) {
                $table->timestamp('ChangedAt')->nullable()->after('IdWebusers');
                $table->index('ChangedAt', 'idx_settings_changed_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'ChangedAt')) {
                $table->dropIndex('idx_settings_changed_at');
                $table->dropColumn('ChangedAt');
            }

            if (Schema::hasColumn('settings', 'IdWebusers')) {
                $table->dropIndex('idx_settings_idwebusers');
                $table->dropColumn('IdWebusers');
            }
        });
    }
};


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('location', function (Blueprint $table): void {
            if (!Schema::hasColumn('location', 'LocationDate')) {
                $table->date('LocationDate')->nullable()->after('LocationName');
            }
        });
    }

    public function down(): void
    {
        Schema::table('location', function (Blueprint $table): void {
            if (Schema::hasColumn('location', 'LocationDate')) {
                $table->dropColumn('LocationDate');
            }
        });
    }
};


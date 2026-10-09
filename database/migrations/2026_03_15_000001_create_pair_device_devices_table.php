<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // На случай, если предыдущий запуск миграции упал после CREATE TABLE,
        // но до создания FK (MySQL DDL не всегда откатывается транзакционно).
        Schema::dropIfExists('pair_device_devices');

        Schema::create('pair_device_devices', function (Blueprint $table) {
            $table->id();
            // В pairdevices первичный ключ Id имеет тип INT (не unsigned bigint)
            $table->integer('pair_device_id');

            // В проекте устройства физически разделены на 2 таблицы: controllers и cameras
            $table->enum('device_type', ['controller', 'camera']);
            $table->integer('controller_id')->nullable();
            $table->unsignedBigInteger('camera_id')->nullable();

            $table->string('role', 32)->comment('Роль устройства в паре: controller, entry, exit, reserve и т.д.');
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->boolean('IsActive')->default(1);
            $table->dateTime('Last_modified_at')->useCurrent();
            $table->timestamps();

            // Внешние ключи в соответствии с фактической схемой БД
            $table->foreign('pair_device_id')->references('Id')->on('pairdevices')->onDelete('cascade');
            $table->foreign('controller_id')->references('id')->on('controllers')->onDelete('cascade');
            $table->foreign('camera_id')->references('id')->on('cameras')->onDelete('cascade');

            // Индексы / ограничения от дублей
            $table->unique(['pair_device_id', 'controller_id'], 'pair_device_controller_unique');
            $table->unique(['pair_device_id', 'camera_id'], 'pair_device_camera_unique');
            $table->index(['pair_device_id', 'role', 'sort_order'], 'pair_device_role_sort_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pair_device_devices');
    }
};

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
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('employee_id', 100);
            $table->string('type', 20);
            $table->date('attendance_date');
            $table->dateTime('recorded_at');
            $table->decimal('latitude', 11, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('country', 120)->nullable();
            $table->string('photo')->nullable();
            $table->string('watermarked_photo')->nullable();
            $table->json('device_information')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'attendance_date']);
            $table->index(['attendance_date', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};

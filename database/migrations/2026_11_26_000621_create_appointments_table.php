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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('follow_up_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->string('purpose', 30);
            $table->string('status', 20)->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'status']);
            $table->index(['employee_id', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};

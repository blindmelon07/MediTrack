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
        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->string('reminder_type', 40);
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->date('sent_on');
            $table->json('channels');
            $table->timestamps();

            // A reminder goes out at most once per subject per day, even if the command runs twice.
            $table->unique(['reminder_type', 'subject_type', 'subject_id', 'sent_on'], 'reminder_logs_once_per_day_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
    }
};

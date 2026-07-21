<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('teacher_email');
            $table->string('teacher_name');
            $table->string('student_google_user_id');
            $table->string('student_directory_user_id')->nullable();
            $table->string('student_email')->nullable();
            $table->string('student_name')->nullable();
            $table->string('course_id');
            $table->string('course_name')->nullable();
            $table->string('result'); // success | failure
            $table->text('failure_reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('result');
            $table->index('teacher_email');
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->timestamp('occurred_at_utc')->nullable()->after('ip_address');
            $table->text('user_agent')->nullable()->after('occurred_at_utc');
            $table->uuid('correlation_id')->nullable()->after('user_agent');
            $table->string('failure_code')->nullable()->after('failure_reason');

            $table->index('correlation_id');
            $table->index('failure_code');
            $table->index('occurred_at_utc');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['correlation_id']);
            $table->dropIndex(['failure_code']);
            $table->dropIndex(['occurred_at_utc']);
            $table->dropColumn([
                'occurred_at_utc',
                'user_agent',
                'correlation_id',
                'failure_code',
            ]);
        });
    }
};

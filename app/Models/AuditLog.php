<?php

namespace App\Models;

use App\Enums\AuditResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'teacher_user_id',
        'teacher_email',
        'teacher_name',
        'student_google_user_id',
        'student_directory_user_id',
        'student_email',
        'student_name',
        'course_id',
        'course_name',
        'result',
        'failure_reason',
        'failure_code',
        'ip_address',
        'occurred_at_utc',
        'user_agent',
        'correlation_id',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Audit logs are append-only and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Audit logs are append-only and cannot be deleted.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result' => AuditResult::class,
            'occurred_at_utc' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }
}

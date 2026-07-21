<?php

namespace App\Enums;

enum RoleName: string
{
    case Teacher = 'teacher';
    case SystemAdministrator = 'system_administrator';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::Teacher => 'Teacher',
            self::SystemAdministrator => 'System Administrator',
            self::Auditor => 'Auditor',
        };
    }
}

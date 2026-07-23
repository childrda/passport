<?php

namespace App\Filament\Resources\AuditLogs;

use App\Enums\AuditResult;
use App\Enums\AuditFailureCode;
use App\Enums\RoleName;
use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'ADMIN';

    protected static ?string $navigationLabel = 'Audit logs';

    protected static ?string $modelLabel = 'audit log';

    protected static ?string $pluralModelLabel = 'audit logs';

    protected static ?int $navigationSort = 30;

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user !== null && (
            $user->hasRole(RoleName::SystemAdministrator)
            || $user->hasRole(RoleName::Auditor)
        );
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('occurred_at_utc')->label('Occurred (UTC)')->dateTime()->placeholder('—'),
                TextEntry::make('created_at')->label('Recorded at')->dateTime(),
                TextEntry::make('result')->badge()
                    ->color(fn (AuditResult $state): string => $state === AuditResult::Success ? 'success' : 'danger')
                    ->formatStateUsing(fn (AuditResult $state): string => $state->label()),
                TextEntry::make('failure_code')->label('Failure code')->placeholder('—'),
                TextEntry::make('correlation_id')->label('Correlation ID')->placeholder('—'),
                TextEntry::make('teacher_name')->label('Teacher'),
                TextEntry::make('teacher_email')->label('Teacher email'),
                TextEntry::make('student_name')->label('Student')->placeholder('—'),
                TextEntry::make('student_email')->label('Canonical student email')->placeholder('—'),
                TextEntry::make('roster_email')->label('Roster email')->placeholder('—'),
                TextEntry::make('student_google_user_id')->label('Student Google user ID'),
                TextEntry::make('student_directory_user_id')->label('Directory user ID')->placeholder('—'),
                TextEntry::make('course_name')->label('Course')->placeholder('—'),
                TextEntry::make('course_id')->label('Course ID'),
                TextEntry::make('failure_reason')->label('Failure message')->placeholder('—')->columnSpanFull(),
                TextEntry::make('ip_address')->label('IP address')->placeholder('—'),
                TextEntry::make('user_agent')->label('User agent')->placeholder('—')->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('occurred_at_utc')
                    ->label('UTC')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('result')
                    ->badge()
                    ->color(fn (AuditResult $state): string => $state === AuditResult::Success ? 'success' : 'danger')
                    ->formatStateUsing(fn (AuditResult $state): string => $state->label()),
                TextColumn::make('failure_code')
                    ->label('Code')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('teacher_email')
                    ->label('Teacher')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('student_email')
                    ->label('Student')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('course_name')
                    ->label('Course')
                    ->placeholder(fn (AuditLog $record): string => $record->course_id)
                    ->wrap(),
                TextColumn::make('failure_reason')
                    ->label('Failure message')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('correlation_id')
                    ->label('Correlation')
                    ->limit(8)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('result')
                    ->options([
                        AuditResult::Success->value => AuditResult::Success->label(),
                        AuditResult::Failure->value => AuditResult::Failure->label(),
                    ]),
                SelectFilter::make('failure_code')
                    ->label('Failure code')
                    ->options(collect(AuditFailureCode::cases())->mapWithKeys(
                        fn (AuditFailureCode $code): array => [$code->value => $code->label()]
                    )->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAuditLogs::route('/'),
        ];
    }
}

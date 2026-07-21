<?php

namespace App\Filament\Resources\AuditLogs;

use App\Enums\AuditResult;
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

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

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
                TextEntry::make('created_at')->label('Date / time')->dateTime(),
                TextEntry::make('result')->badge()
                    ->color(fn (AuditResult $state): string => $state === AuditResult::Success ? 'success' : 'danger')
                    ->formatStateUsing(fn (AuditResult $state): string => $state->label()),
                TextEntry::make('teacher_name')->label('Teacher'),
                TextEntry::make('teacher_email')->label('Teacher email'),
                TextEntry::make('student_name')->label('Student')->placeholder('—'),
                TextEntry::make('student_email')->label('Student email')->placeholder('—'),
                TextEntry::make('student_google_user_id')->label('Student Google user ID'),
                TextEntry::make('student_directory_user_id')->label('Directory user ID')->placeholder('—'),
                TextEntry::make('course_name')->label('Course')->placeholder('—'),
                TextEntry::make('course_id')->label('Course ID'),
                TextEntry::make('failure_reason')->label('Failure reason')->placeholder('—')->columnSpanFull(),
                TextEntry::make('ip_address')->label('IP address')->placeholder('—'),
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
                TextColumn::make('result')
                    ->badge()
                    ->color(fn (AuditResult $state): string => $state === AuditResult::Success ? 'success' : 'danger')
                    ->formatStateUsing(fn (AuditResult $state): string => $state->label()),
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
                    ->label('Failure reason')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),
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

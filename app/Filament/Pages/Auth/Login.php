<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Validation\ValidationException;

/**
 * Google OAuth is the only authentication path — no local email/password form.
 */
class Login extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    /**
     * @return array<\Filament\Actions\Action|\Filament\Actions\ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [];
    }

    public function getFormContentComponent(): Component
    {
        // Keep a non-submitting schema shell so Livewire still mounts `form`, but
        // never expose credential fields or an authenticate submit control.
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->visible(false);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                RenderHook::make(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE),
                $this->getFormContentComponent(),
                RenderHook::make(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER),
            ]);
    }

    public function authenticate(): ?LoginResponse
    {
        throw ValidationException::withMessages([
            'data.email' => 'Sign in with Google using your '.config('reset.staff_domain').' account.',
        ]);
    }
}

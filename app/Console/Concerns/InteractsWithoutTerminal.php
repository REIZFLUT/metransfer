<?php

namespace App\Console\Concerns;

use Illuminate\Support\Str;

/**
 * Helpers for commands that must also work in non-interactive environments
 * such as the Artisan console of the Plesk Laravel Toolkit (no TTY).
 */
trait InteractsWithoutTerminal
{
    /**
     * Whether Laravel Prompts can ask questions (same rule Laravel uses).
     */
    protected function canPrompt(): bool
    {
        return $this->laravel->runningUnitTests()
            ? $this->input->isInteractive()
            : $this->input->isInteractive() && defined('STDIN') && stream_isatty(STDIN);
    }

    protected function generatePassword(): string
    {
        return Str::password(24);
    }

    protected function showGeneratedPassword(string $email, string $password): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('E-Mail', $email);
        $this->components->twoColumnDetail('Passwort', "<options=bold>{$password}</>");
        $this->newLine();
        $this->components->warn('Das Passwort wird nur jetzt angezeigt – bitte sicher notieren und nach dem ersten Login unter Einstellungen > Sicherheit ändern.');
    }
}

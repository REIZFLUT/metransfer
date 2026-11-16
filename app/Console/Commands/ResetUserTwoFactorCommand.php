<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetUserTwoFactorCommand extends Command
{
    protected $signature = 'user:reset-2fa
        {email : E-Mail-Adresse des Benutzers}
        {--passkeys : Zusätzlich alle Passkeys des Benutzers entfernen}';

    protected $description = 'Zwei-Faktor-Authentifizierung (und optional Passkeys) eines Benutzers zurücksetzen';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->components->error('Benutzer nicht gefunden.');

            return self::FAILURE;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->components->info("Zwei-Faktor-Authentifizierung für {$user->email} wurde deaktiviert.");

        if ($this->option('passkeys')) {
            $count = $user->passkeys()->delete();
            $this->components->info("{$count} Passkey(s) entfernt.");
        }

        return self::SUCCESS;
    }
}

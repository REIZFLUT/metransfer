<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;

class ChangeUserPasswordCommand extends Command
{
    protected $signature = 'user:password
        {email : E-Mail-Adresse des Benutzers}
        {--password= : Neues Passwort (nur für Skripte; sonst interaktiv abfragen)}';

    protected $description = 'Das Passwort eines Benutzers ändern';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->components->error('Benutzer nicht gefunden.');

            return self::FAILURE;
        }

        $newPassword = $this->option('password');

        if (! $newPassword) {
            $newPassword = password(label: 'Neues Passwort', required: true);

            if ($newPassword !== password(label: 'Passwort bestätigen', required: true)) {
                $this->components->error('Die Passwörter stimmen nicht überein.');

                return self::FAILURE;
            }
        }

        $validator = Validator::make(
            ['password' => $newPassword],
            ['password' => ['required', 'string', Password::default()]],
        );

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first('password'));

            return self::FAILURE;
        }

        $user->forceFill(['password' => $newPassword])->save();

        $this->components->info("Passwort für {$user->email} wurde geändert.");

        return self::SUCCESS;
    }
}

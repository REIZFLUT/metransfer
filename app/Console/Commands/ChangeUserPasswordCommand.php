<?php

namespace App\Console\Commands;

use App\Console\Concerns\InteractsWithoutTerminal;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;

class ChangeUserPasswordCommand extends Command
{
    use InteractsWithoutTerminal;

    protected $signature = 'user:password
        {email : E-Mail-Adresse des Benutzers}
        {--password= : Neues Passwort (ohne Angabe: interaktiv abfragen bzw. sicheres Passwort erzeugen)}';

    protected $description = 'Das Passwort eines Benutzers ändern';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->components->error('Benutzer nicht gefunden.');

            return self::FAILURE;
        }

        $newPassword = $this->option('password');
        $generated = false;

        if (! $newPassword && $this->canPrompt()) {
            $newPassword = password(label: 'Neues Passwort', required: true);

            if ($newPassword !== password(label: 'Passwort bestätigen', required: true)) {
                $this->components->error('Die Passwörter stimmen nicht überein.');

                return self::FAILURE;
            }
        } elseif (! $newPassword) {
            $newPassword = $this->generatePassword();
            $generated = true;
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

        if ($generated) {
            $this->showGeneratedPassword($user->email, $newPassword);
        }

        return self::SUCCESS;
    }
}

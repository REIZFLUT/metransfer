<?php

namespace App\Console\Commands;

use App\Console\Concerns\InteractsWithoutTerminal;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateUserCommand extends Command
{
    use InteractsWithoutTerminal;

    protected $signature = 'user:create
        {--name= : Name des Benutzers}
        {--email= : E-Mail-Adresse des Benutzers}
        {--password= : Passwort (ohne Angabe: interaktiv abfragen bzw. sicheres Passwort erzeugen)}';

    protected $description = 'Einen neuen Benutzer anlegen';

    public function handle(): int
    {
        $interactive = $this->canPrompt();
        $name = $this->option('name');
        $email = $this->option('email');
        $password = $this->option('password');

        if (! $interactive && (! $name || ! $email)) {
            $this->components->error('Ohne Terminal bitte Name und E-Mail angeben:');
            $this->line('  php artisan user:create --name="Dein Name" --email=du@example.com');
            $this->line('  (Ohne --password wird ein sicheres Passwort erzeugt und angezeigt.)');

            return self::FAILURE;
        }

        $name = $name ?: text(label: 'Name', required: true);

        $email = $email ?: text(
            label: 'E-Mail-Adresse',
            required: true,
            validate: fn (string $value) => $this->validateEmail($value),
        );

        $generated = false;

        if (! $password) {
            if ($interactive) {
                $password = $this->askPassword();
            } else {
                $password = $this->generatePassword();
                $generated = true;
            }
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
                'password' => ['required', 'string', Password::default()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->components->info("Benutzer {$user->email} wurde angelegt.");

        if ($generated) {
            $this->showGeneratedPassword($user->email, $password);
        }

        return self::SUCCESS;
    }

    protected function validateEmail(string $value): ?string
    {
        $validator = Validator::make(
            ['email' => $value],
            ['email' => ['email', 'max:255', Rule::unique(User::class)]],
        );

        return $validator->errors()->first('email') ?: null;
    }

    protected function askPassword(): string
    {
        while (true) {
            $password = password(label: 'Passwort', required: true);
            $confirmation = password(label: 'Passwort bestätigen', required: true);

            if ($password === $confirmation) {
                return $password;
            }

            $this->components->error('Die Passwörter stimmen nicht überein.');
        }
    }
}

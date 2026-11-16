<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

class DeleteUserCommand extends Command
{
    protected $signature = 'user:delete
        {email : E-Mail-Adresse des Benutzers}
        {--force : Ohne Rückfrage löschen}';

    protected $description = 'Einen Benutzer inklusive aller Transfers und Dateien löschen';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->components->error('Benutzer nicht gefunden.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! confirm(
            label: "Benutzer {$user->email} mit {$user->transfers()->count()} Transfer(s) endgültig löschen?",
            default: false,
        )) {
            $this->components->warn('Abgebrochen.');

            return self::FAILURE;
        }

        $user->delete();

        $this->components->info("Benutzer {$user->email} wurde gelöscht.");

        return self::SUCCESS;
    }
}

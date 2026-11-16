<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ListUsersCommand extends Command
{
    protected $signature = 'user:list';

    protected $description = 'Alle Benutzer anzeigen';

    public function handle(): int
    {
        $users = User::query()->withCount(['passkeys', 'transfers'])->orderBy('id')->get();

        if ($users->isEmpty()) {
            $this->components->info('Es sind noch keine Benutzer angelegt. Nutze `php artisan user:create`.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'E-Mail', '2FA', 'Passkeys', 'Transfers', 'Angelegt'],
            $users->map(fn (User $user) => [
                $user->id,
                $user->name,
                $user->email,
                $user->two_factor_confirmed_at ? 'ja' : 'nein',
                $user->passkeys_count,
                $user->transfers_count,
                $user->created_at?->format('d.m.Y H:i'),
            ]),
        );

        return self::SUCCESS;
    }
}

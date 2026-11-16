<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SendTestMailCommand extends Command
{
    protected $signature = 'mail:test {email : Empfänger-Adresse}';

    protected $description = 'Eine Testmail sofort (ohne Queue) verschicken, um die Mail-Einstellungen zu prüfen';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->components->error('Ungültige E-Mail-Adresse.');

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $settings = config("mail.mailers.{$mailer}", []);

        $this->components->twoColumnDetail('Mailer', $mailer);

        if (($settings['transport'] ?? null) === 'smtp') {
            $this->components->twoColumnDetail('Server', ($settings['host'] ?? '?').':'.($settings['port'] ?? '?'));
            $this->components->twoColumnDetail('Verschlüsselung', (string) ($settings['scheme'] ?? 'automatisch (STARTTLS)'));
            $this->components->twoColumnDetail('Benutzer', (string) ($settings['username'] ?? '–'));
        }

        $this->components->twoColumnDetail('Absender', config('mail.from.address').' ('.config('mail.from.name').')');
        $this->components->twoColumnDetail('Empfänger', $email);

        if ($mailer === 'log') {
            $this->components->warn('MAIL_MAILER=log: Die Mail wird nur ins Log geschrieben, nicht verschickt.');
        }

        try {
            Mail::to($email)->send(
                (new Mailable)
                    ->subject(config('app.name').' – Testmail')
                    ->html('<p>Diese Testmail wurde von '.e(config('app.name')).' ('.e(config('app.url')).') verschickt. Die Mail-Einstellungen funktionieren.</p>'),
            );
        } catch (Throwable $exception) {
            $this->components->error('Versand fehlgeschlagen: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Testmail wurde verschickt.');

        return self::SUCCESS;
    }
}

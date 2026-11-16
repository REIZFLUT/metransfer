<?php

namespace Tests\Feature;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendTestMailCommandTest extends TestCase
{
    public function test_test_mail_is_sent_immediately()
    {
        Mail::fake();

        $this->artisan('mail:test', ['email' => 'olli@example.com'])
            ->expectsOutputToContain('Testmail wurde verschickt')
            ->assertSuccessful();

        Mail::assertSent(Mailable::class, fn (Mailable $mail) => $mail->hasTo('olli@example.com'));
        Mail::assertNothingQueued();
    }

    public function test_invalid_address_is_rejected()
    {
        Mail::fake();

        $this->artisan('mail:test', ['email' => 'kaputt'])->assertFailed();

        Mail::assertNothingSent();
    }
}

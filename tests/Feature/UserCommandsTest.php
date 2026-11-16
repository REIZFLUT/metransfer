<?php

namespace Tests\Feature;

use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_created_from_the_console()
    {
        $this->artisan('user:create', [
            '--name' => 'Olli',
            '--email' => 'olli@example.com',
            '--password' => 'super-secret-1',
        ])->assertSuccessful();

        $user = User::where('email', 'olli@example.com')->firstOrFail();

        $this->assertSame('Olli', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('super-secret-1', $user->password));
    }

    public function test_user_create_rejects_duplicate_email()
    {
        User::factory()->create(['email' => 'olli@example.com']);

        $this->artisan('user:create', [
            '--name' => 'Olli',
            '--email' => 'olli@example.com',
            '--password' => 'super-secret-1',
        ])->assertFailed();

        $this->assertSame(1, User::count());
    }

    public function test_user_create_prompts_interactively()
    {
        $this->artisan('user:create')
            ->expectsQuestion('Name', 'Olli')
            ->expectsQuestion('E-Mail-Adresse', 'olli@example.com')
            ->expectsQuestion('Passwort', 'super-secret-1')
            ->expectsQuestion('Passwort bestätigen', 'super-secret-1')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'olli@example.com']);
    }

    public function test_user_create_without_terminal_generates_a_password()
    {
        $this->artisan('user:create', [
            '--name' => 'Olli',
            '--email' => 'olli@example.com',
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('Passwort')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'olli@example.com']);
    }

    public function test_user_create_without_terminal_requires_name_and_email()
    {
        $this->artisan('user:create', ['--no-interaction' => true])
            ->expectsOutputToContain('--name=')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_user_delete_without_terminal_requires_force()
    {
        $user = User::factory()->create();

        $this->artisan('user:delete', ['email' => $user->email, '--no-interaction' => true])
            ->expectsOutputToContain('--force')
            ->assertFailed();

        $this->assertModelExists($user);
    }

    public function test_password_can_be_generated_without_terminal()
    {
        $user = User::factory()->create();
        $old = $user->password;

        $this->artisan('user:password', ['email' => $user->email, '--no-interaction' => true])
            ->assertSuccessful();

        $this->assertNotSame($old, $user->fresh()->password);
    }

    public function test_password_can_be_changed()
    {
        $user = User::factory()->create();

        $this->artisan('user:password', ['email' => $user->email, '--password' => 'new-password-1'])
            ->assertSuccessful();

        $this->assertTrue(Hash::check('new-password-1', $user->fresh()->password));
    }

    public function test_two_factor_can_be_reset()
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->artisan('user:reset-2fa', ['email' => $user->email])->assertSuccessful();

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_user_can_be_listed()
    {
        User::factory()->create(['email' => 'olli@example.com']);

        $this->artisan('user:list')->expectsOutputToContain('olli@example.com')->assertSuccessful();
    }

    public function test_deleting_a_user_removes_transfers_and_files()
    {
        Storage::fake('local');

        $transfer = Transfer::factory()->published()->create();
        Storage::disk('local')->put($transfer->storageDirectory().'/file', 'data');

        $this->artisan('user:delete', ['email' => $transfer->user->email, '--force' => true])
            ->assertSuccessful();

        $this->assertModelMissing($transfer);
        Storage::disk('local')->assertMissing($transfer->storageDirectory().'/file');
    }
}

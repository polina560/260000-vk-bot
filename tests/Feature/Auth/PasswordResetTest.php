<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Exception;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @throws Exception
     */
    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $this
            ->postJson(URL::route('password.email'), ['email' => 'nonexistent@example.com'])
            ->assertJsonValidationErrors(['email']);
        $user = User::factory()->create();

        $this->postJson(URL::route('password.email'), ['email' => $user->email])
            ->assertSuccessful();

        $this->postJson(URL::route('password.store'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertJsonValidationErrors(['email']);

        Notification::assertSentTo($user, ResetPassword::class, function (object $notification) use ($user): true {
            $response = $this->postJson(URL::route('password.store'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertStatus(200);

            return true;
        });
    }
}

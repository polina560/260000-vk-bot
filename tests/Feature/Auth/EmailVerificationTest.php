<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Exception;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1((string) $user->email),
            ],
        );

        $response = $this->actingAs($user)->getJson($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $response
            ->assertStatus(200)
            ->assertJson(['status' => 'Успешно подтверждено']);
    }

    /**
     * @throws Exception
     */
    public function test_email_send_new_verification_email(): void
    {
        Notification::fake();
        $url = URL::route('verification.send');
        $user = User::factory()->unverified()->create();
        $response = $this->actingAs($user)->postJson($url);
        $response->assertStatus(200);
        $response->assertJson(['status' => 'verification-link-sent']);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_email_send_new_verification_email_error(): void
    {
        Notification::fake();
        $url = URL::route('verification.send');
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson($url);
        $response->assertRedirect('/dashboard');
        Notification::assertNothingSent();
    }

    public function test_already_verified_email(): void
    {
        $user = User::factory()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1((string) $user->email),
            ],
        );

        $response = $this->actingAs($user)->getJson($verificationUrl);

        $response
            ->assertStatus(200)
            ->assertJson(['status' => 'Уже подтверждено']);
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1('wrong-email'),
            ],
        );

        $response = $this->actingAs($user)->getJson($verificationUrl);

        $response->assertStatus(403);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}

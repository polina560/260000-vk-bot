<?php

namespace Tests\Unit;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoginRequestRateLimitTest extends TestCase
{
    public function test_login_is_rate_limited_after_5_attempts(): void
    {
        $request = new LoginRequest(['email' => 'test@example.com', 'password' => 'password']);
        RateLimiter::shouldReceive('tooManyAttempts')
            ->with($request->throttleKey(), 5)
            ->andReturn(true);

        RateLimiter::shouldReceive('availableIn')
            ->with($request->throttleKey())
            ->andReturn(120); // 120 секунд

        $this->expectException(ValidationException::class);

        $this->expectExceptionMessage(trans('auth.throttle', [
            'seconds' => 120,
            'minutes' => 2,
        ]));

        $request->ensureIsNotRateLimited();
    }
}

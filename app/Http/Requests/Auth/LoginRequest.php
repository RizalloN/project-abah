<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS_PER_IDENTITY = 5;
    private const MAX_ATTEMPTS_PER_IP = 30;
    private const DECAY_SECONDS = 900;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pn' => ['required', 'string', 'min:4', 'max:32', 'regex:/^[A-Za-z0-9._-]+$/'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        $isRateLimited = $this->isRateLimited();
        $user = $this->validUserForCredentials();

        if ($user === null) {
            if ($isRateLimited) {
                $this->throwRateLimitedValidationException();
            }

            $this->hitRateLimiters();

            throw ValidationException::withMessages([
                'pn' => 'PN atau password salah.',
            ]);
        }

        $this->clearRateLimiters();

        return $user;
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (!$this->isRateLimited()) {
            return;
        }

        if ($this->validUserForCredentials() === null) {
            $this->throwRateLimitedValidationException();
        }
    }

    private function isRateLimited(): bool
    {
        return RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS_PER_IDENTITY)
            || RateLimiter::tooManyAttempts($this->ipThrottleKey(), self::MAX_ATTEMPTS_PER_IP);
    }

    /**
     * Let the real account owner recover from a stale throttle lock.
     */
    private function validUserForCredentials(): ?User
    {
        $user = User::query()->where('pn', $this->normalizedPn())->first();

        if (!$user || !in_array((string) ($user->role ?? ''), ['admin', 'user'], true)) {
            return null;
        }

        try {
            if (!Hash::check((string) $this->input('password'), (string) $user->password)) {
                return null;
            }
        } catch (RuntimeException) {
            return null;
        }

        return $user;
    }

    private function throwRateLimitedValidationException(): void
    {
        event(new Lockout($this));

        $seconds = max(
            RateLimiter::availableIn($this->throttleKey()),
            RateLimiter::availableIn($this->ipThrottleKey())
        );

        throw ValidationException::withMessages([
            'pn' => 'Terlalu banyak percobaan. Coba lagi dalam ' . max(1, (int) ceil($seconds / 60)) . ' menit.',
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return 'login:identity:' . Str::transliterate(Str::lower($this->normalizedPn()) . '|' . $this->ip());
    }

    public function ipThrottleKey(): string
    {
        return 'login:ip:' . Str::transliterate((string) $this->ip());
    }

    private function normalizedPn(): string
    {
        return trim((string) $this->input('pn'));
    }

    private function hitRateLimiters(): void
    {
        RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
        RateLimiter::hit($this->ipThrottleKey(), self::DECAY_SECONDS);
    }

    private function clearRateLimiters(): void
    {
        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->ipThrottleKey());
    }

}

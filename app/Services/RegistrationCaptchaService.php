<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RegistrationCaptchaService
{
    private const SESSION_KEY = 'registration_captchas';
    private const LIFETIME_MINUTES = 10;
    private const MAX_ACTIVE_CAPTCHAS = 5;

    public function issue(Request $request): array
    {
        [$question, $answer] = $this->makeQuestion();
        $token = Str::random(40);
        $now = now()->timestamp;

        $captchas = collect($request->session()->get(self::SESSION_KEY, []))
            ->filter(fn (array $captcha) => ($captcha['expires_at'] ?? 0) >= $now)
            ->take(-1 * (self::MAX_ACTIVE_CAPTCHAS - 1))
            ->all();

        $captchas[$token] = [
            'answer_hash' => $this->answerHash($token, $answer),
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES)->timestamp,
        ];

        $request->session()->put(self::SESSION_KEY, $captchas);

        return [
            'token' => $token,
            'question' => $question,
            'expires_in' => self::LIFETIME_MINUTES * 60,
        ];
    }

    public function check(Request $request, ?string $token, mixed $answer): bool
    {
        if (! is_string($token) || strlen($token) !== 40 || ! is_numeric($answer)) {
            return false;
        }

        $captchas = $request->session()->get(self::SESSION_KEY, []);
        $captcha = $captchas[$token] ?? null;

        // Каждую капчу можно проверить только один раз.
        unset($captchas[$token]);
        $request->session()->put(self::SESSION_KEY, $captchas);

        if (! is_array($captcha) || ($captcha['expires_at'] ?? 0) < now()->timestamp) {
            return false;
        }

        return hash_equals(
            (string) ($captcha['answer_hash'] ?? ''),
            $this->answerHash($token, (int) $answer)
        );
    }

    private function makeQuestion(): array
    {
        $operation = random_int(0, 2);

        if ($operation === 0) {
            $left = random_int(2, 12);
            $right = random_int(1, 9);

            return ["{$left} + {$right} = ?", $left + $right];
        }

        if ($operation === 1) {
            $left = random_int(6, 18);
            $right = random_int(1, $left - 1);

            return ["{$left} − {$right} = ?", $left - $right];
        }

        $left = random_int(2, 9);
        $right = random_int(2, 6);

        return ["{$left} × {$right} = ?", $left * $right];
    }

    private function answerHash(string $token, int $answer): string
    {
        return hash_hmac('sha256', $token . '|' . $answer, (string) config('app.key'));
    }
}

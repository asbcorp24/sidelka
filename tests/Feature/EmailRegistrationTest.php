<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_contains_custom_captcha(): void
    {
        $response = $this->get(route('register'));

        $response
            ->assertOk()
            ->assertSee('Проверка: решите пример')
            ->assertSee('captcha_token', false)
            ->assertSee('captcha_answer', false);
    }

    public function test_registration_rejects_invalid_captcha(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Тестовый клиент',
            'email' => 'client@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'client',
            'city' => 'Москва',
            'captcha_token' => str_repeat('x', 40),
            'captcha_answer' => 42,
            'website' => '',
        ]);

        $response->assertSessionHasErrors('captcha_answer');
        $this->assertDatabaseMissing('users', ['email' => 'client@example.test']);
    }

    public function test_user_can_register_with_valid_captcha_and_receives_verification_email(): void
    {
        Notification::fake();

        $page = $this->get(route('register'));
        [$token, $answer] = $this->extractCaptcha($page->getContent());

        $response = $this->post(route('register.store'), [
            'name' => 'Тестовый клиент',
            'email' => 'Client@Example.Test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'client',
            'phone' => '+7 900 000-00-00',
            'city' => 'Москва',
            'captcha_token' => $token,
            'captcha_answer' => $answer,
            'website' => '',
        ]);

        $user = User::where('email', 'client@example.test')->firstOrFail();

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        $this->get(route('client.dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_signed_link_verifies_email_and_opens_dashboard(): void
    {
        $user = User::factory()->unverified()->create([
            'role' => 'client',
            'city' => 'Москва',
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertRedirect(route('client.dashboard'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    private function extractCaptcha(string $html): array
    {
        preg_match('/id="captcha-token" value="([^"]+)"/', $html, $tokenMatch);
        preg_match('/id="captcha-question"[^>]*>([^<]+)</u', $html, $questionMatch);

        $this->assertNotEmpty($tokenMatch[1] ?? null, 'Captcha token was not found.');
        $this->assertNotEmpty($questionMatch[1] ?? null, 'Captcha question was not found.');

        $question = trim(html_entity_decode($questionMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        preg_match('/^(\d+)\s*([+−×])\s*(\d+)/u', $question, $parts);

        $this->assertCount(4, $parts, 'Captcha question has an unexpected format.');

        $left = (int) $parts[1];
        $right = (int) $parts[3];
        $answer = match ($parts[2]) {
            '+' => $left + $right,
            '−' => $left - $right,
            '×' => $left * $right,
        };

        return [$tokenMatch[1], $answer];
    }
}

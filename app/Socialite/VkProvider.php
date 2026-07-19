<?php

namespace App\Socialite;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class VkProvider extends AbstractProvider implements ProviderInterface
{
    protected $scopeSeparator = ',';

    protected $scopes = ['email'];

    protected array $tokenPayload = [];

    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://oauth.vk.com/authorize', $state);
    }

    protected function getTokenUrl()
    {
        return 'https://oauth.vk.com/access_token';
    }

    public function getAccessTokenResponse($code)
    {
        $response = parent::getAccessTokenResponse($code);
        $this->tokenPayload = $response;

        return $response;
    }

    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get('https://api.vk.com/method/users.get', [
            'query' => [
                'user_ids' => $this->tokenPayload['user_id'] ?? null,
                'fields' => 'photo_200,screen_name',
                'v' => '5.199',
                'access_token' => $token,
            ],
        ]);

        $payload = json_decode((string) $response->getBody(), true);
        $user = $payload['response'][0] ?? [];
        $user['email'] = $this->tokenPayload['email'] ?? null;

        return $user;
    }

    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id' => $user['id'] ?? null,
            'nickname' => $user['screen_name'] ?? null,
            'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            'email' => $user['email'] ?? null,
            'avatar' => $user['photo_200'] ?? null,
        ]);
    }
}

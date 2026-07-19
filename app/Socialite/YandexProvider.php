<?php

namespace App\Socialite;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class YandexProvider extends AbstractProvider implements ProviderInterface
{
    protected $scopeSeparator = ' ';

    protected $scopes = ['login:email', 'login:info', 'login:avatar'];

    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://oauth.yandex.ru/authorize', $state);
    }

    protected function getTokenUrl()
    {
        return 'https://oauth.yandex.ru/token';
    }

    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get('https://login.yandex.ru/info', [
            'headers' => [
                'Authorization' => 'OAuth ' . $token,
                'Accept' => 'application/json',
            ],
            'query' => [
                'format' => 'json',
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id' => $user['id'] ?? null,
            'nickname' => $user['login'] ?? null,
            'name' => trim(($user['real_name'] ?? '') ?: (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))),
            'email' => $user['default_email'] ?? null,
            'avatar' => $user['default_avatar_id'] ?? null
                ? 'https://avatars.yandex.net/get-yapic/' . $user['default_avatar_id'] . '/islands-200'
                : null,
        ]);
    }
}

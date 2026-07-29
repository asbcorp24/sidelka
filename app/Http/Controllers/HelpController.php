<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class HelpController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $defaultAudience = match (true) {
            $user?->isAdmin() => 'admin',
            $user?->isCrm() => 'crm',
            $user?->isCaregiver() => 'caregiver',
            $user?->isClient() => 'client',
            default => 'guest',
        };

        $allowedAudiences = match (true) {
            $user?->isAdmin() => ['admin', 'crm', 'caregiver', 'client', 'guest'],
            $user?->isCrm() => ['crm'],
            $user?->isCaregiver() => ['caregiver'],
            $user?->isClient() => ['client'],
            default => ['guest'],
        };

        $requestedAudience = (string) $request->query('role', $defaultAudience);
        $audience = in_array($requestedAudience, $allowedAudiences, true)
            ? $requestedAudience
            : $defaultAudience;

        return view('help.index', [
            'audience' => $audience,
            'allowedAudiences' => $allowedAudiences,
            'audienceLabels' => [
                'guest' => 'Гость и регистрация',
                'client' => 'Клиент',
                'caregiver' => 'Сиделка',
                'crm' => 'CRM-сотрудник',
                'admin' => 'Администратор',
            ],
        ]);
    }
}

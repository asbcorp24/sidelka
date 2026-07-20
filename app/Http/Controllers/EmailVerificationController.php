<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route($this->redirectRoute($request->user()));
        }

        return view('auth.verify-email', [
            'user' => $request->user(),
        ]);
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->fulfill();
        }

        return redirect()
            ->route($this->redirectRoute($request->user()))
            ->with('status', 'Email подтвержден. Регистрация завершена.');
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route($this->redirectRoute($request->user()));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'Новая ссылка подтверждения отправлена на ваш email.');
    }

    private function redirectRoute(User $user): string
    {
        if ($user->isAdmin()) {
            return 'admin.dashboard';
        }

        if ($user->isCaregiver()) {
            return 'caregiver.dashboard';
        }

        return 'client.dashboard';
    }
}

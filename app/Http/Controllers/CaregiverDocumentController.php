<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceNotification;
use App\Models\UserDocument;
use App\Services\CaregiverDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CaregiverDocumentController extends Controller
{
    public function __construct(private CaregiverDocumentService $documentService)
    {
    }

    public function index(Request $request): View
    {
        $query = UserDocument::query()
            ->whereHas('user', fn ($user) => $user->where('role', 'caregiver'))
            ->with(['user', 'verifiedBy'])
            ->orderByRaw('expires_at IS NULL, expires_at ASC');

        if ($request->filled('status')) {
            if ($request->input('status') === 'expired') {
                $query->whereDate('expires_at', '<', today());
            } elseif ($request->input('status') === 'expiring') {
                $query->whereBetween('expires_at', [today(), today()->addDays(30)]);
            } elseif ($request->input('status') === 'unverified') {
                $query->where('verification_status', '!=', UserDocument::STATUS_VERIFIED);
            }
        }

        return view('crm.caregiver-documents', [
            'documents' => $query->paginate(40)->withQueryString(),
            'stats' => [
                'expired' => UserDocument::whereHas('user', fn ($q) => $q->where('role', 'caregiver'))->whereDate('expires_at', '<', today())->count(),
                'expiring' => UserDocument::whereHas('user', fn ($q) => $q->where('role', 'caregiver'))->whereBetween('expires_at', [today(), today()->addDays(30)])->count(),
                'blocking' => UserDocument::whereHas('user', fn ($q) => $q->where('role', 'caregiver'))->where('is_required', true)->where('blocks_assignments', true)->count(),
            ],
        ]);
    }

    public function update(Request $request, UserDocument $userDocument): RedirectResponse
    {
        abort_unless($userDocument->user?->isCaregiver(), 404);

        $data = $request->validate([
            'verification_status' => ['required', Rule::in([
                UserDocument::STATUS_PENDING,
                UserDocument::STATUS_VERIFIED,
                UserDocument::STATUS_REJECTED,
            ])],
            'is_required' => ['nullable', 'boolean'],
            'blocks_assignments' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $userDocument->update([
            'verification_status' => $data['verification_status'],
            'is_required' => (bool) ($data['is_required'] ?? false),
            'blocks_assignments' => (bool) ($data['blocks_assignments'] ?? false),
            'expires_at' => $data['expires_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'verified_at' => $data['verification_status'] === UserDocument::STATUS_VERIFIED ? now() : null,
            'verified_by_id' => $data['verification_status'] === UserDocument::STATUS_VERIFIED ? $request->user()->id : null,
            'reminder_30_at' => null,
            'reminder_14_at' => null,
            'reminder_3_at' => null,
            'expired_task_at' => null,
        ]);

        if (in_array($data['verification_status'], [UserDocument::STATUS_VERIFIED, UserDocument::STATUS_REJECTED], true)) {
            $this->documentService->completeDocumentTasks($userDocument);

            MarketplaceNotification::create([
                'user_id' => $userDocument->user_id,
                'type' => 'document.' . $data['verification_status'],
                'title' => $data['verification_status'] === UserDocument::STATUS_VERIFIED
                    ? 'Документ подтверждён'
                    : 'Документ отклонён',
                'body' => 'Документ «' . $userDocument->title . '» '
                    . ($data['verification_status'] === UserDocument::STATUS_VERIFIED
                        ? 'проверен сотрудником площадки.'
                        : 'отклонён. Причина: ' . ($data['notes'] ?: 'не указана.')),
                'data' => ['url' => '/cabinet/caregiver/legal'],
            ]);
        }

        return back()->with('status', 'Статус документа и правила допуска обновлены.');
    }
}

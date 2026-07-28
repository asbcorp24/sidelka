<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\UserDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContractController extends Controller
{
    public function caregiverLegal(Request $request)
    {
        $user = $request->user()->load(['contractProfile', 'documents']);
        abort_unless($user->isCaregiver(), 403);

        return view('contracts.profile', [
            'user' => $user,
            'roleLabel' => 'сиделки',
            'contractPreviewRoute' => route('contracts.caregiver.preview'),
            'documentTypeOptions' => UserDocument::caregiverDocumentOptions(),
            'documentStatusLabels' => UserDocument::STATUS_LABELS,
        ]);
    }

    public function clientLegal(Request $request)
    {
        $user = $request->user()->load(['contractProfile', 'documents']);
        abort_unless($user->isClient(), 403);

        return view('contracts.profile', [
            'user' => $user,
            'roleLabel' => 'клиента',
            'contractPreviewRoute' => route('contracts.client.preview'),
            'documentTypeOptions' => UserDocument::TYPE_LABELS,
            'documentStatusLabels' => UserDocument::STATUS_LABELS,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'legal_full_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'passport_series' => ['nullable', 'string', 'max:255'],
            'passport_number' => ['nullable', 'string', 'max:255'],
            'passport_issued_by' => ['nullable', 'string', 'max:255'],
            'passport_issued_at' => ['nullable', 'date'],
            'passport_department_code' => ['nullable', 'string', 'max:255'],
            'registration_address' => ['nullable', 'string', 'max:255'],
            'residence_address' => ['nullable', 'string', 'max:255'],
            'contract_city' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:255'],
            'inn' => ['nullable', 'string', 'max:255'],
            'snils' => ['nullable', 'string', 'max:255'],
            'tax_status' => ['nullable', 'string', 'max:255'],
            'is_self_employed' => ['nullable', 'boolean'],
            'bank_recipient_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_bik' => ['nullable', 'string', 'max:255'],
            'bank_account' => ['nullable', 'string', 'max:255'],
            'card_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $user->contractProfile()->updateOrCreate(
            ['user_id' => $user->id],
            array_merge($data, [
                'is_self_employed' => (bool) ($data['is_self_employed'] ?? false),
            ])
        );

        return back()->with('status', 'Договорные данные сохранены.');
    }

    public function storeDocument(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'document_type' => ['required', 'string', 'max:255', Rule::in(array_keys(UserDocument::TYPE_LABELS))],
            'title' => ['nullable', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'scan' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'verification_status' => ['nullable', Rule::in(array_keys(UserDocument::STATUS_LABELS))],
            'notes' => ['nullable', 'string'],
        ]);

        $typeOptions = $user->isCaregiver()
            ? UserDocument::caregiverDocumentOptions()
            : UserDocument::TYPE_LABELS;
        $selectedType = $data['document_type'];
        $selectedConfig = is_array($typeOptions[$selectedType] ?? null)
            ? $typeOptions[$selectedType]
            : ['label' => UserDocument::TYPE_LABELS[$selectedType] ?? $selectedType, 'required' => false, 'blocks_assignments' => false];

        $filePath = null;
        $originalName = null;
        $mimeType = null;
        $fileSize = null;

        if ($request->hasFile('scan')) {
            $file = $request->file('scan');
            $filePath = $file->store('document-scans/' . $user->id, 'public');
            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $fileSize = $file->getSize();
        }

        $user->documents()->create([
            'document_type' => $selectedType,
            'title' => $data['title'] ?: ($selectedConfig['label'] ?? (UserDocument::TYPE_LABELS[$selectedType] ?? $selectedType)),
            'document_number' => $data['document_number'] ?? null,
            'file_path' => $filePath,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'issued_at' => $data['issued_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'verification_status' => $data['verification_status'] ?? UserDocument::STATUS_UPLOADED,
            'is_required' => (bool) ($selectedConfig['required'] ?? false),
            'blocks_assignments' => $user->isCaregiver() ? (bool) ($selectedConfig['blocks_assignments'] ?? false) : false,
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('status', 'Документ добавлен.');
    }

    public function downloadDocument(Request $request, UserDocument $document): BinaryFileResponse
    {
        $user = $request->user();
        $allowed = $user->id === $document->user_id
            || $user->isAdmin()
            || $user->hasStaffPermission('crm.documents.manage');

        abort_unless($allowed, 403);
        abort_unless($document->file_path, 404);

        return Storage::disk('public')->download(
            $document->file_path,
            $document->original_name ?? basename($document->file_path)
        );
    }

    public function caregiverAgreement(Request $request)
    {
        $user = $request->user()->load('contractProfile');
        abort_unless($user->isCaregiver(), 403);

        return $this->downloadPdf(
            'contracts.caregiver-agreement',
            [
                'user' => $user,
                'profile' => $user->contractProfile,
                'agreementNumber' => 'CG-' . now()->format('Ymd') . '-' . $user->id,
                'agreementDate' => now(),
            ],
            'caregiver-agreement-' . $user->id . '.pdf'
        );
    }

    public function clientAgreement(Request $request)
    {
        $user = $request->user()->load('contractProfile');
        abort_unless($user->isClient(), 403);

        return $this->downloadPdf(
            'contracts.client-agreement',
            [
                'user' => $user,
                'profile' => $user->contractProfile,
                'agreementNumber' => 'CL-' . now()->format('Ymd') . '-' . $user->id,
                'agreementDate' => now(),
            ],
            'client-agreement-' . $user->id . '.pdf'
        );
    }

    public function orderAgreement(Request $request, Order $order)
    {
        $order->load(['client.contractProfile', 'caregiver.contractProfile', 'services', 'scheduleSlots']);
        $this->authorizeOrderAccess($request, $order);
        abort_unless($order->client && $order->caregiver, 422, 'Для договора по заказу нужна выбранная сиделка.');

        return $this->downloadPdf(
            'contracts.order-agreement',
            [
                'order' => $order,
                'client' => $order->client,
                'caregiver' => $order->caregiver,
                'clientProfile' => $order->client->contractProfile,
                'caregiverProfile' => $order->caregiver->contractProfile,
                'agreementNumber' => 'ORD-' . $order->id . '-' . now()->format('Ymd'),
                'agreementDate' => now(),
            ],
            'order-agreement-' . $order->id . '.pdf'
        );
    }

    public function workAct(Request $request, Order $order)
    {
        $order->load(['client.contractProfile', 'caregiver.contractProfile', 'services', 'scheduleSlots']);
        $this->authorizeOrderAccess($request, $order);
        abort_unless($order->client && $order->caregiver, 422, 'Для акта по заказу нужна выбранная сиделка.');

        return $this->downloadPdf(
            'contracts.work-act',
            [
                'order' => $order,
                'client' => $order->client,
                'caregiver' => $order->caregiver,
                'clientProfile' => $order->client->contractProfile,
                'caregiverProfile' => $order->caregiver->contractProfile,
                'actNumber' => 'ACT-' . $order->id . '-' . now()->format('Ymd'),
                'actDate' => now(),
                'hoursWorked' => max(1, $order->starts_at->diffInHours($order->ends_at)),
                'totalAmount' => max(1, $order->starts_at->diffInHours($order->ends_at)) * $order->hourly_budget,
            ],
            'work-act-' . $order->id . '.pdf'
        );
    }

    private function authorizeOrderAccess(Request $request, Order $order): void
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin() || $order->client_id === $user->id || $order->caregiver_id === $user->id,
            403
        );
    }

    private function downloadPdf(string $view, array $data, string $filename)
    {
        return Pdf::loadView($view, $data)
            ->setPaper('a4')
            ->download($filename);
    }
}

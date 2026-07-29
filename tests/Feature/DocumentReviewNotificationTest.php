<?php

namespace Tests\Feature;

use App\Models\CrmTask;
use App\Models\MarketplaceNotification;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentReviewNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_review_is_assigned_shown_and_completed_with_notifications(): void
    {
        Storage::fake('public');

        $coordinator = User::factory()->create([
            'role' => 'crm',
            'staff_role' => 'coordinator',
            'staff_active' => true,
            'email_verified_at' => now(),
        ]);

        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($caregiver)
            ->post(route('contracts.document.store'), [
                'document_type' => UserDocument::TYPE_PASSPORT,
                'document_number' => '92 00 123456',
                'scan' => UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $document = UserDocument::firstOrFail();
        $task = CrmTask::where('source_type', UserDocument::class)
            ->where('source_id', $document->id)
            ->firstOrFail();

        $this->assertSame(UserDocument::STATUS_PENDING, $document->fresh()->verification_status);
        $this->assertSame($coordinator->id, $task->assigned_to_id);
        $this->assertSame('open', $task->status);
        $this->assertDatabaseHas('marketplace_notifications', [
            'user_id' => $coordinator->id,
            'type' => 'document.review_assigned',
            'read_at' => null,
        ]);

        $this->actingAs($coordinator)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Проверить документ сиделки');

        $this->actingAs($coordinator)
            ->patch(route('crm.caregiver-documents.update', $document), [
                'verification_status' => UserDocument::STATUS_VERIFIED,
                'is_required' => 1,
                'blocks_assignments' => 1,
                'notes' => 'Данные и скан проверены.',
            ])
            ->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame(UserDocument::STATUS_VERIFIED, $document->verification_status);
        $this->assertSame($coordinator->id, $document->verified_by_id);
        $this->assertSame('completed', $task->fresh()->status);
        $this->assertDatabaseHas('marketplace_notifications', [
            'user_id' => $caregiver->id,
            'type' => 'document.verified',
            'read_at' => null,
        ]);

        $this->actingAs($caregiver)
            ->get(route('contracts.document.download', $document))
            ->assertOk();

        $notification = MarketplaceNotification::where('user_id', $caregiver->id)
            ->where('type', 'document.verified')
            ->firstOrFail();

        $this->actingAs($caregiver)
            ->get(route('notifications.open', $notification))
            ->assertRedirect(route('caregiver.legal'));
        $this->assertNotNull($notification->fresh()->read_at);
    }
}

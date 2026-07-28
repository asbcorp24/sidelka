<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDocument extends Model
{
    use HasFactory;

    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';

    public const TYPE_PASSPORT = 'passport';
    public const TYPE_SNILS = 'snils';
    public const TYPE_INN = 'inn';
    public const TYPE_MEDICAL_BOOK = 'medical_book';
    public const TYPE_MEDICAL_CERTIFICATE = 'medical_certificate';
    public const TYPE_EDUCATION_DIPLOMA = 'education_diploma';
    public const TYPE_NURSING_CERTIFICATE = 'nursing_certificate';
    public const TYPE_SELF_EMPLOYED_CERTIFICATE = 'self_employed_certificate';
    public const TYPE_BANK_DETAILS = 'bank_details';
    public const TYPE_POWER_OF_ATTORNEY = 'power_of_attorney';
    public const TYPE_OTHER = 'other';

    public const STATUS_LABELS = [
        self::STATUS_UPLOADED => 'Загружен',
        self::STATUS_PENDING => 'На проверке',
        self::STATUS_VERIFIED => 'Проверен',
        self::STATUS_REJECTED => 'Отклонен',
    ];

    public const TYPE_LABELS = [
        self::TYPE_PASSPORT => 'Паспорт',
        self::TYPE_SNILS => 'СНИЛС',
        self::TYPE_INN => 'ИНН',
        self::TYPE_MEDICAL_BOOK => 'Медицинская книжка',
        self::TYPE_MEDICAL_CERTIFICATE => 'Медицинская справка',
        self::TYPE_EDUCATION_DIPLOMA => 'Диплом / образование',
        self::TYPE_NURSING_CERTIFICATE => 'Сертификат по уходу',
        self::TYPE_SELF_EMPLOYED_CERTIFICATE => 'Самозанятость / налоговый статус',
        self::TYPE_BANK_DETAILS => 'Реквизиты для выплат',
        self::TYPE_POWER_OF_ATTORNEY => 'Доверенность',
        self::TYPE_OTHER => 'Другое',
    ];

    protected $fillable = [
        'user_id', 'document_type', 'title', 'document_number', 'file_path', 'original_name',
        'mime_type', 'file_size', 'issued_at', 'expires_at', 'verification_status', 'is_required',
        'blocks_assignments', 'verified_at', 'verified_by_id', 'reminder_30_at', 'reminder_14_at',
        'reminder_3_at', 'expired_task_at', 'notes',
    ];

    protected $casts = [
        'issued_at' => 'date', 'expires_at' => 'date', 'verified_at' => 'datetime',
        'reminder_30_at' => 'datetime', 'reminder_14_at' => 'datetime',
        'reminder_3_at' => 'datetime', 'expired_task_at' => 'datetime',
        'is_required' => 'boolean', 'blocks_assignments' => 'boolean',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function verifiedBy(): BelongsTo { return $this->belongsTo(User::class, 'verified_by_id'); }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->document_type] ?? $this->title ?? $this->document_type;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->verification_status] ?? $this->verification_status;
    }

    public static function caregiverDocumentOptions(): array
    {
        return [
            self::TYPE_PASSPORT => ['label' => self::TYPE_LABELS[self::TYPE_PASSPORT], 'required' => true, 'blocks_assignments' => true, 'expires' => false],
            self::TYPE_SNILS => ['label' => self::TYPE_LABELS[self::TYPE_SNILS], 'required' => true, 'blocks_assignments' => false, 'expires' => false],
            self::TYPE_INN => ['label' => self::TYPE_LABELS[self::TYPE_INN], 'required' => true, 'blocks_assignments' => false, 'expires' => false],
            self::TYPE_MEDICAL_BOOK => ['label' => self::TYPE_LABELS[self::TYPE_MEDICAL_BOOK], 'required' => true, 'blocks_assignments' => true, 'expires' => true],
            self::TYPE_MEDICAL_CERTIFICATE => ['label' => self::TYPE_LABELS[self::TYPE_MEDICAL_CERTIFICATE], 'required' => false, 'blocks_assignments' => false, 'expires' => true],
            self::TYPE_EDUCATION_DIPLOMA => ['label' => self::TYPE_LABELS[self::TYPE_EDUCATION_DIPLOMA], 'required' => false, 'blocks_assignments' => false, 'expires' => false],
            self::TYPE_NURSING_CERTIFICATE => ['label' => self::TYPE_LABELS[self::TYPE_NURSING_CERTIFICATE], 'required' => false, 'blocks_assignments' => false, 'expires' => true],
            self::TYPE_SELF_EMPLOYED_CERTIFICATE => ['label' => self::TYPE_LABELS[self::TYPE_SELF_EMPLOYED_CERTIFICATE], 'required' => false, 'blocks_assignments' => false, 'expires' => true],
            self::TYPE_BANK_DETAILS => ['label' => self::TYPE_LABELS[self::TYPE_BANK_DETAILS], 'required' => true, 'blocks_assignments' => false, 'expires' => false],
            self::TYPE_OTHER => ['label' => self::TYPE_LABELS[self::TYPE_OTHER], 'required' => false, 'blocks_assignments' => false, 'expires' => false],
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isBefore(today());
    }

    public function blocksCaregiver(): bool
    {
        return $this->is_required
            && $this->blocks_assignments
            && ($this->verification_status !== 'verified' || $this->isExpired());
    }
}

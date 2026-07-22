<?php

namespace App\Support;

final class CrmPermissions
{
    public const ROLE_LABELS = [
        'operator' => 'Оператор',
        'coordinator' => 'Координатор',
        'supervisor' => 'Супервайзер',
        'accountant' => 'Бухгалтер',
        'lawyer' => 'Юрист',
        'manager' => 'Руководитель',
    ];

    public const PERMISSION_LABELS = [
        'crm.requests.manage' => 'Заявки, звонки и клиенты',
        'crm.schedules.manage' => 'Подбор и графики сиделок',
        'crm.documents.manage' => 'Документы и допуски сиделок',
        'crm.disputes.manage' => 'Споры и претензии по сменам',
        'crm.incidents.manage' => 'Инциденты и безопасность',
        'crm.finance.manage' => 'Выплаты и комиссии',
        'crm.contracts.manage' => 'Договоры и юридические документы',
        'crm.analytics.view' => 'Аналитика руководителя',
        'crm.staff.manage' => 'Сотрудники и права',
    ];

    public const ROLE_PERMISSIONS = [
        'operator' => [
            'crm.requests.manage',
        ],
        'coordinator' => [
            'crm.requests.manage',
            'crm.schedules.manage',
            'crm.documents.manage',
        ],
        'supervisor' => [
            'crm.requests.manage',
            'crm.schedules.manage',
            'crm.documents.manage',
            'crm.disputes.manage',
            'crm.incidents.manage',
        ],
        'accountant' => [
            'crm.finance.manage',
        ],
        'lawyer' => [
            'crm.contracts.manage',
            'crm.disputes.manage',
        ],
        'manager' => [
            'crm.requests.manage',
            'crm.schedules.manage',
            'crm.documents.manage',
            'crm.disputes.manage',
            'crm.incidents.manage',
            'crm.finance.manage',
            'crm.contracts.manage',
            'crm.analytics.view',
            'crm.staff.manage',
        ],
    ];

    public static function forRole(?string $role): array
    {
        return self::ROLE_PERMISSIONS[$role ?? ''] ?? [];
    }
}

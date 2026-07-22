<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CrmLandingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasStaffPermission('crm.requests.manage')) {
            return app(CrmController::class)->dashboard($request);
        }

        $routes = [
            'crm.finance.manage' => 'crm.finance.index',
            'crm.contracts.manage' => 'crm.contracts.index',
            'crm.disputes.manage' => 'crm.shift-disputes.index',
            'crm.incidents.manage' => 'crm.incidents.index',
            'crm.documents.manage' => 'crm.caregiver-documents.index',
            'crm.analytics.view' => 'crm.analytics.index',
        ];

        foreach ($routes as $permission => $route) {
            if ($user->hasStaffPermission($permission)) {
                return redirect()->route($route);
            }
        }

        abort(403, 'Для сотрудника не назначены рабочие разрешения.');
    }
}

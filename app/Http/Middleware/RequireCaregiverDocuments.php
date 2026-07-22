<?php

namespace App\Http\Middleware;

use App\Models\CaregiverProfile;
use App\Services\CaregiverDocumentService;
use Closure;
use Illuminate\Http\Request;

class RequireCaregiverDocuments
{
    public function __construct(private CaregiverDocumentService $documents)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $profile = $request->route('caregiverProfile');
        $caregiver = $profile instanceof CaregiverProfile ? $profile->user : $request->user();

        if ($caregiver?->isCaregiver()) {
            $this->documents->assertEligible($caregiver);
        }

        return $next($request);
    }
}

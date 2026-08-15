<?php

namespace App\Http\Controllers\Staff;

use App\Application\Kindergarten\StripeConnectService;
use App\Domain\Staff\StaffRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CreateStripeConnectOnboardingLinkRequest;
use App\Models\Kindergarten;
use App\Models\KindergartenStaff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class StripeConnectController extends Controller
{
    public function onboardingLink(CreateStripeConnectOnboardingLinkRequest $request, StripeConnectService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedOwner($request);

        if ($staff === null) {
            return $this->forbiddenRoleResponse();
        }

        $kindergarten = $this->resolveKindergarten($staff);

        try {
            return response()->json($service->issueOnboardingLink(
                $kindergarten,
                $request->string('return_url')->toString(),
                $request->string('refresh_url')->toString(),
            ));
        } catch (RuntimeException $exception) {
            Log::error('Stripe API error for kindergarten', [
                'kindergarten_id' => $kindergarten->id,
                'message' => $exception->getMessage(),
            ]);

            return $this->stripeApiErrorResponse();
        }
    }

    public function status(Request $request, StripeConnectService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedOwner($request);

        if ($staff === null) {
            return $this->forbiddenRoleResponse();
        }

        $kindergarten = $this->resolveKindergarten($staff);

        try {
            return response()->json($service->getStatus($kindergarten));
        } catch (RuntimeException $exception) {
            Log::error('Stripe API error for kindergarten', [
                'kindergarten_id' => $kindergarten->id,
                'message' => $exception->getMessage(),
            ]);

            return $this->stripeApiErrorResponse();
        }
    }

    public function salesAvailability(Request $request, StripeConnectService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedOwner($request);

        if ($staff === null) {
            return $this->forbiddenRoleResponse();
        }

        $kindergarten = $this->resolveKindergarten($staff);

        try {
            return response()->json($service->getSalesAvailability($kindergarten));
        } catch (RuntimeException $exception) {
            Log::error('Stripe API error for kindergarten', [
                'kindergarten_id' => $kindergarten->id,
                'message' => $exception->getMessage(),
            ]);

            return $this->stripeApiErrorResponse();
        }
    }

    private function resolveAuthenticatedOwner(Request $request): ?KindergartenStaff
    {
        $staff = $request->user('staff');

        if (! $staff instanceof KindergartenStaff) {
            return null;
        }

        if ($staff->role !== StaffRole::Owner) {
            return null;
        }

        return $staff;
    }

    private function resolveKindergarten(KindergartenStaff $staff): Kindergarten
    {
        return Kindergarten::query()->findOrFail($staff->kindergarten_id);
    }

    private function forbiddenRoleResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Staff role forbidden',
            'code' => 'STAFF_ROLE_FORBIDDEN',
        ], 403);
    }

    private function stripeApiErrorResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Stripe API error',
            'code' => 'STRIPE_API_ERROR',
        ], 502);
    }
}

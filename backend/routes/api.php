<?php

use App\Http\Controllers\Guardian\AuthController as GuardianAuthController;
use App\Http\Controllers\PublicApi\ChildInvitationPublicController;
use App\Http\Controllers\PublicApi\StaffInvitationPublicController;
use App\Http\Controllers\Staff\AuthController;
use App\Http\Controllers\Staff\ChildClassController;
use App\Http\Controllers\Staff\ChildController;
use App\Http\Controllers\Staff\ChildInvitationController;
use App\Http\Controllers\Staff\GuardianLinkController;
use App\Http\Controllers\Staff\StaffInvitationController;
use App\Http\Controllers\Staff\StaffMemberController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/health', function (): JsonResponse {
    return response()->json([
        'status' => 'ok',
    ]);
});

Route::prefix('/staff/auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:staff');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:staff');
});

Route::prefix('/staff')->middleware('auth:staff')->group(function (): void {
    Route::post('/children', [ChildController::class, 'store']);
    Route::get('/children', [ChildController::class, 'index']);
    Route::get('/children/{childId}', [ChildController::class, 'show']);
    Route::patch('/children/{childId}', [ChildController::class, 'update']);
    Route::patch('/children/{childId}/status', [ChildController::class, 'status']);

    Route::post('/child-classes', [ChildClassController::class, 'store']);
    Route::get('/child-classes', [ChildClassController::class, 'index']);
    Route::get('/child-classes/{childClassId}', [ChildClassController::class, 'show']);
    Route::patch('/child-classes/{childClassId}', [ChildClassController::class, 'update']);
    Route::delete('/child-classes/{childClassId}', [ChildClassController::class, 'destroy']);

    Route::post('/children/{childId}/invitations', [ChildInvitationController::class, 'store']);
    Route::get('/children/{childId}/invitations', [ChildInvitationController::class, 'index']);
    Route::get('/invitations/{invitationId}/print', [ChildInvitationController::class, 'print']);
    Route::post('/invitations/{invitationId}/revoke', [ChildInvitationController::class, 'revoke']);
    Route::post('/invitations/{invitationId}/reissue', [ChildInvitationController::class, 'reissue']);

    Route::get('/children/{childId}/guardian-links', [GuardianLinkController::class, 'index']);
    Route::post('/guardian-links/{linkId}/unlink', [GuardianLinkController::class, 'unlink']);
    Route::post('/guardian-links/{linkId}/restore', [GuardianLinkController::class, 'restore']);

    Route::post('/staff-invitations', [StaffInvitationController::class, 'store']);
    Route::get('/staff-invitations', [StaffInvitationController::class, 'index']);
    Route::post('/staff-invitations/{invitationId}/revoke', [StaffInvitationController::class, 'revoke']);

    Route::get('/staff-members', [StaffMemberController::class, 'index']);
    Route::get('/staff-members/{staffId}', [StaffMemberController::class, 'show']);
    Route::patch('/staff-members/{staffId}/role', [StaffMemberController::class, 'role']);
    Route::post('/staff-members/{staffId}/deactivate', [StaffMemberController::class, 'deactivate']);
    Route::post('/staff-members/{staffId}/reactivate', [StaffMemberController::class, 'reactivate']);
});

Route::prefix('/public')->group(function (): void {
    Route::get('/invitations/{rawToken}', [ChildInvitationPublicController::class, 'preview'])
        ->middleware('throttle:60,1');
    Route::post('/invitations/{rawToken}/accept', [ChildInvitationPublicController::class, 'accept'])
        ->middleware('throttle:20,1');

    Route::prefix('/staff-invitations')->group(function (): void {
        Route::get('/{rawToken}', [StaffInvitationPublicController::class, 'preview'])
            ->middleware('throttle:60,1');
        Route::post('/{rawToken}/accept', [StaffInvitationPublicController::class, 'accept'])
            ->middleware('throttle:20,1');
    });
});

Route::prefix('/guardian/auth')->group(function (): void {
    Route::post('/login', [GuardianAuthController::class, 'login'])
        ->middleware('throttle:20,1');
    Route::post('/refresh', [GuardianAuthController::class, 'refresh'])
        ->middleware('throttle:20,1');
    Route::post('/logout', [GuardianAuthController::class, 'logout'])
        ->middleware('auth:guardian');
    Route::post('/email/verification-notification', [GuardianAuthController::class, 'verificationNotification'])
        ->middleware('auth:guardian');
    Route::get('/email/verify/{id}/{hash}', [GuardianAuthController::class, 'verifyBySignedUrl'])
        ->middleware('signed')
        ->name('verification.verify');
});

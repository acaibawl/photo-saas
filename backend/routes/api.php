<?php

use App\Http\Controllers\PublicApi\StaffInvitationPublicController;
use App\Http\Controllers\Staff\AuthController;
use App\Http\Controllers\Staff\ChildClassController;
use App\Http\Controllers\Staff\ChildController;
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

    Route::post('/staff-invitations', [StaffInvitationController::class, 'store']);
    Route::get('/staff-invitations', [StaffInvitationController::class, 'index']);
    Route::post('/staff-invitations/{invitationId}/revoke', [StaffInvitationController::class, 'revoke']);

    Route::get('/staff-members', [StaffMemberController::class, 'index']);
    Route::get('/staff-members/{staffId}', [StaffMemberController::class, 'show']);
    Route::patch('/staff-members/{staffId}/role', [StaffMemberController::class, 'role']);
    Route::post('/staff-members/{staffId}/deactivate', [StaffMemberController::class, 'deactivate']);
    Route::post('/staff-members/{staffId}/reactivate', [StaffMemberController::class, 'reactivate']);
});

Route::prefix('/public/staff-invitations')->group(function (): void {
    Route::get('/{rawToken}', [StaffInvitationPublicController::class, 'preview'])
        ->middleware('throttle:60,1');
    Route::post('/{rawToken}/accept', [StaffInvitationPublicController::class, 'accept'])
        ->middleware('throttle:20,1');
});

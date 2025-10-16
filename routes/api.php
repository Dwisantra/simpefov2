<?php

use App\Http\Controllers\PostController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FeatureRequestController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FeatureRequestCommentController;
use App\Http\Controllers\Admin\FeatureRequestGitlabController;
use App\Http\Controllers\Manager\JangmedPriorityController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserVerificationController;
use App\Http\Controllers\GitlabWebhookController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/public/units', [UnitController::class, 'publicIndex']);
Route::post('/gitlab/webhook', [GitlabWebhookController::class, 'handle']);

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

// Route::resource('posts', PostController::class);

Route::middleware(['auth:sanctum', 'sanctum.timeout'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/update-kode-sign', [AuthController::class, 'updateKodeSign']);
    Route::post('/users/{user}/verify', [UserVerificationController::class, 'verify']);

    Route::get('/units', [UnitController::class, 'index']);
    Route::post('/units', [UnitController::class, 'store']);
    Route::put('/units/{unit}', [UnitController::class, 'update']);
    Route::delete('/units/{unit}', [UnitController::class, 'destroy']);
    Route::get('/admin/users', [UserManagementController::class, 'index']);
    Route::put('/admin/users/{user}', [UserManagementController::class, 'update']);
    Route::get('/feature-requests', [FeatureRequestController::class, 'index']);
    Route::get('/feature-requests/monitoring', [FeatureRequestController::class, 'monitoring']);
    Route::post('/feature-requests', [FeatureRequestController::class, 'store'])
        ->middleware('ensure-requester');
    Route::get('/feature-requests/{featureRequest}', [FeatureRequestController::class, 'show']);
    Route::put('/feature-requests/{featureRequest}', [FeatureRequestController::class, 'update']);
    Route::delete('/feature-requests/{featureRequest}', [FeatureRequestController::class, 'destroy']);
    Route::post('/feature-requests/{featureRequest}/gitlab', [FeatureRequestGitlabController::class, 'sync']);
    Route::get('/feature-requests/{featureRequest}/attachment', [FeatureRequestController::class, 'downloadAttachment'])
        ->name('api.feature-requests.attachment');
    Route::post('/feature-requests/{featureRequest}/approve', [ApprovalController::class, 'approve']);
    Route::post('/feature-requests/{featureRequest}/comments', [FeatureRequestCommentController::class, 'store']);
    Route::get(
        '/feature-requests/{featureRequest}/comments/{comment}/attachment',
        [FeatureRequestCommentController::class, 'downloadAttachment']
    )->name('api.feature-requests.comments.attachment');

    Route::get('/manager/jangmed/priorities', [JangmedPriorityController::class, 'index']);
    Route::patch('/manager/jangmed/priorities/{featureRequest}', [JangmedPriorityController::class, 'update']);
});

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApprovalValidationController;

Route::get('/approval/validate/{code}', [ApprovalValidationController::class, 'showValidationPage'])->name('approval.validate-link');
Route::post('/approval/validate/{code}', [ApprovalValidationController::class, 'submitValidation'])->name('approval.submit-validation');

Route::view('/', 'welcome');

Route::view('/{any}', 'welcome')
    ->where('any', '^(?!api|storage|approval).*$');

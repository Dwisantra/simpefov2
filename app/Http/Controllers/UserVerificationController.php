<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\UserVerifiedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class UserVerificationController extends Controller
{
    public function verify(Request $request, User $user)
    {
        if (UserRole::tryFromMixed($request->user()->level ?? $request->user()->role)?->value !== UserRole::ADMIN->value) {
            abort(403, 'Hanya admin yang dapat memverifikasi akun.');
        }

        if (! $user->is_verified) {
            $user->forceFill(['verified_at' => now()])->save();

            if ($user->email) {
                $initialPassword = null;

                if (! empty($user->initial_password)) {
                    try {
                        $initialPassword = Crypt::decryptString($user->initial_password);
                    } catch (DecryptException $exception) {
                        report($exception);
                    }
                }

                $loginUrl = rtrim(config('app.url'), '/') . '/login';

                $user->notify(new UserVerifiedNotification($initialPassword, $loginUrl));

                if ($initialPassword !== null) {
                    $user->forceFill(['initial_password' => null])->save();
                }
            }
        }

        return response()->json([
            'message' => 'Pengguna berhasil diverifikasi.',
            'user' => $user->fresh(['unit']),
        ]);
    }
}

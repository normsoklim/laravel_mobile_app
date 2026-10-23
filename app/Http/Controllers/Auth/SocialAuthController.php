<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\SocialAuthService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SocialAuthController extends Controller
{
    public function __construct(
        private SocialAuthService $socialAuthService
    ) {}

    public function socialLogin(Request $request)
    {
        $request->validate([
            'id_token' => ['required', 'string'],
            'provider' => ['required', 'in:google,facebook'],
        ]);

        $user = $this->socialAuthService->authenticate(
            $request->id_token,
            $request->provider
        );

        $token = $user->createToken('flutter')->plainTextToken;

        return response()->json([
            'message' => 'Social login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }
}

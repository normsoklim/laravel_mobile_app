<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerifyEmailCode;
use App\Mail\PasswordResetOtp;
use App\Http\Requests\Auth\GoogleLoginRequest;
use App\Http\Requests\Auth\FacebookLoginRequest;
use App\Services\Auth\GoogleAuthService;
use App\Services\Auth\FacebookAuthService;
use App\Services\CloudinaryService;
use Laravel\Sanctum\PersonalAccessToken;


class AuthController extends Controller
{
    // Token lifetimes in hours. These are used to set the expiration of the
    public const REMEMBER_ME_LIFETIME_HOURS = 24 * 30;
    public const SESSION_LIFETIME_HOURS = 8;

    //
    public function profile(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'message' => 'success',
            'user' => $user
        ]);
    }

    /**
     * Return the currently authenticated user.
     *
     * Used by the Flutter client to validate a restored ("Remember me")
     * session before it lets the customer into the app.
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'success',
            'user' => $request->user(),
        ]);
    }
    public function register(Request $request)
    {

        //validate form
        $request->validate([
            'email' => 'nullable|email|unique:users,email|required_without:phone',
            'phone' => 'nullable|string|unique:users,phone|required_without:email',
            'password' => 'required|string|min:8',
        ]);

        // Email is required for email verification
        if (!$request->email) {
            return response()->json([
                'message' => 'Email is required for registration because email verification is enabled.'
            ], 422);
        }

        // Generate 4-digit OTP
        $code = (string) random_int(1000, 9999);

        // Create user
        $user = User::create([
            'full_name' => $request->full_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),

            'email_verified_at' => null,
            'email_verification_code' => $code,
            'email_verification_expires_at' => now()->addMinutes(10),

            'is_active' => true,
        ]);

        // Send OTP
        Mail::to($user->email)->send(
            new VerifyEmailCode($code)
        );

        return response()->json([
            'message' => 'Registration successful. Please check your email for the verification code.',
            'user_id' => $user->id,
        ], 201);
    }
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'code' => 'required|digits:4',
        ]);

        $user = User::find($request->user_id);

        // Already verified
        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email is already verified.'
            ], 400);
        }

        // No OTP
        if (!$user->email_verification_code) {
            return response()->json([
                'message' => 'Verification code not found.'
            ], 400);
        }

        // OTP expired
        if (
            !$user->email_verification_expires_at ||
            now()->greaterThan($user->email_verification_expires_at)
        ) {
            return response()->json([
                'message' => 'Verification code has expired.'
            ], 400);
        }

        // Wrong OTP
        if ($request->code !== $user->email_verification_code) {
            return response()->json([
                'message' => 'Invalid verification code.'
            ], 400);
        }

        // Verify email
        $user->update([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
        ]);

        return response()->json([
            'message' => 'Email verified successfully. You can now login.'
        ]);
    }
    public function resendVerificationCode(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::find($request->user_id);

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email is already verified.'
            ], 400);
        }

        // Generate new OTP
        $code = (string) random_int(1000, 9999);

        $user->update([
            'email_verification_code' => $code,
            'email_verification_expires_at' => now()->addMinutes(10),
        ]);

        // Send new OTP
        Mail::to($user->email)->send(
            new VerifyEmailCode($code)
        );

        return response()->json([
            'message' => 'A new verification code has been sent to your email.'
        ]);
    }
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required_without:email|string',
            'email' => 'required_without:login|string',
            'password' => 'required|string',
            // "Remember me" is optional so older clients keep working; it
            // defaults to a session-only login (8 hour token).
            'remember_me' => 'sometimes|boolean',
        ]);

        $loginIdentifier = $request->login ?? $request->email;
        $rememberMe = $request->boolean('remember_me');

        // Search by email OR phone
        $user = User::where('email', $loginIdentifier)
            ->orWhere('phone', $loginIdentifier)
            ->first();

        // Invalid credentials
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid login credentials.'
            ], 401);
        }

        // Check account status
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Your account is inactive.'
            ], 403);
        }

        // Check email verification
        if (!$user->email_verified_at) {
            return response()->json([
                'message' => 'Please verify your email before logging in.'
            ], 403);
        }

        // Create the token with the lifetime that matches the "Remember me"
        // choice: 30 days when it is ticked, 8 hours when it is not.
        $expiresAt = $this->tokenExpiryFor($rememberMe);

        $token = $user->createToken('auth_token', ['*'], $expiresAt);

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->format(DATE_ATOM),
            'remember_me' => $rememberMe,
        ]);
    }

    /**
     * The moment the token issued for this login stops being valid.
     */
    protected function tokenExpiryFor(bool $rememberMe): \DateTimeInterface
    {
        return now()->addHours(
            $rememberMe
                ? self::REMEMBER_ME_LIFETIME_HOURS
                : self::SESSION_LIFETIME_HOURS
        );
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        }
        return response()->json([
            'message' => 'Logout successful',
        ], 200);
    }
    public function logoutAllDevice(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json([
            'message' => 'User logged out successfully',
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'full_name' => 'nullable|string|max:255',
            'nick_name' => 'nullable|string|max:255',

            'email' => [
                'nullable',
                'email',
                'unique:users,email,' . $user->id,
            ],

            'phone' => [
                'nullable',
                'string',
                'unique:users,phone,' . $user->id,
            ],

            'dob' => 'nullable|date',
            'gender' => 'nullable|string|in:male,female,Male,Female',
        ]);

        $gender = $request->gender ? strtolower($request->gender) : null;

        $user->update([
            'full_name' => $request->full_name,
            'nick_name' => $request->nick_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'dob' => $request->dob,
            'gender' => $gender,
        ]);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user,
        ]);
    }
    public function updateAvatar(Request $request, CloudinaryService $cloudinary)
    {
        $user = $request->user();

        $request->validate([
            'avatar' => 'required|image|max:2048', // 2 MB max
        ]);

        $uploadedFile = $request->file('avatar');

        // Upload to Cloudinary and store the secure URL on the user record.
        // Pass the real temp file path (string) — the raw cloudinary_php SDK
        // does not accept a Laravel UploadedFile object directly.
        $avatarUrl = $cloudinary->uploadAndGetUrl(
            $uploadedFile->getRealPath(),
            [],
            'tinh_shop/avatars'
        );

        $user->update([
            'avatar' => $avatarUrl,
        ]);

        return response()->json([
            'message' => 'Avatar updated successfully.',
            'user' => $user,
        ]);
    }
    public function createPin(Request $request)
    {
        $request->validate([
            'pin' => ['required', 'digits:4', 'confirmed'],
        ]);

        $user = $request->user();

        if ($user->pin_hash) {
            return response()->json([
                'message' => 'PIN already exists. Please change your PIN instead.',
            ], 400);
        }

        $user->pin_hash = Hash::make($request->pin);
        $user->pin_created_at = now();
        $user->save();

        return response()->json([
            'message' => 'PIN created successfully.',
        ]);
    }

    public function verifyPin(Request $request)
    {
        $request->validate([
            'pin' => ['required', 'digits:4'],
        ]);

        $user = $request->user();

        if (!$user->pin_hash) {
            return response()->json([
                'message' => 'PIN not set. Please create a PIN first.',
            ], 400);
        }

        if (!Hash::check($request->pin, $user->pin_hash)) {
            return response()->json([
                'message' => 'Invalid PIN.',
            ], 401);
        }

        return response()->json([
            'message' => 'PIN verified successfully.',
        ]);
    }

    public function changePin(Request $request)
    {
        $request->validate([
            'pin' => ['required', 'digits:4', 'confirmed'],
        ]);

        $user = $request->user();

        if (!$user->pin_hash) {
            return response()->json([
                'message' => 'PIN not set. Please create a PIN first.',
            ], 400);
        }

        $user->pin_hash = Hash::make($request->pin);
        $user->pin_created_at = now();
        $user->save();

        return response()->json([
            'message' => 'PIN changed successfully.',
        ]);
    }

    public function refreshToken(Request $request)
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();

        // Keep the lifetime of the session that is being refreshed: a
        // remembered session (30 days) stays remembered, a session-only
        // login keeps its original expiry.
        $expiresAt = $currentToken instanceof PersonalAccessToken
            ? $currentToken->expires_at
            : null;

        if ($currentToken instanceof PersonalAccessToken) {
            $currentToken->delete();
        }

        $token = $user->createToken('auth_token', ['*'], $expiresAt);

        // Return the new token
        return response()->json([
            'message' => 'Token refreshed successfully',
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt?->format(DATE_ATOM),
        ]);
    }
    public function deleteAccount(Request $request)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'User not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $request->user()->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ], Response::HTTP_OK);
    }
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string'],
        ]);

        if (!$request->email && !$request->phone) {
            return response()->json([
                'message' => 'Please enter your email or phone number.',
            ], 422);
        }

        if ($request->email && $request->phone) {
            return response()->json([
                'message' => 'Please enter either email or phone number, not both.',
            ], 422);
        }

        $user = $request->email
            ? User::where('email', $request->email)->first()
            : User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Account not found.',
            ], 404);
        }

        $otp = (string) random_int(1000, 9999);

        $user->password_reset_otp = $otp;
        $user->password_reset_otp_expires_at = now()->addMinutes(10);
        $user->save();

        if ($request->email) {
            Mail::to($user->email)->send(
                new PasswordResetOtp($otp)
            );
        }

        return response()->json([
            'message' => 'OTP sent successfully.',
            'user_id' => $user->id,
        ]);
    }
    public function verifyPasswordResetOtp(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'integer'],
            'otp' => ['required', 'digits:4'],
        ]);

        $user = User::find($request->user_id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        if (!$user->password_reset_otp) {
            return response()->json([
                'message' => 'No OTP request found.',
            ], 400);
        }

        if (
            !$user->password_reset_otp_expires_at ||
            now()->greaterThan($user->password_reset_otp_expires_at)
        ) {
            return response()->json([
                'message' => 'OTP has expired.',
            ], 400);
        }

        if ($user->password_reset_otp !== $request->otp) {
            return response()->json([
                'message' => 'Invalid OTP.',
            ], 400);
        }

        return response()->json([
            'message' => 'OTP verified successfully.',
            'user_id' => $user->id,
        ]);
    }
    public function resetPassword(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'integer'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::find($request->user_id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        if (!$user->password_reset_otp) {
            return response()->json([
                'message' => 'Please request a new OTP.',
            ], 400);
        }

        if (
            !$user->password_reset_otp_expires_at ||
            now()->greaterThan($user->password_reset_otp_expires_at)
        ) {
            return response()->json([
                'message' => 'OTP has expired. Please request a new OTP.',
            ], 400);
        }

        $user->password = Hash::make($request->password);
        $user->password_reset_otp = null;
        $user->password_reset_otp_expires_at = null;
        $user->save();

        return response()->json([
            'message' => 'Password reset successfully.',
        ]);
    }
    public function google(
        GoogleLoginRequest $request,
        GoogleAuthService $googleAuthService
    ) {
        $user = $googleAuthService->authenticate(
            $request->id_token
        );

        // Social logins follow the same "Remember me" policy as the
        // email/password login.
        $rememberMe = $request->boolean('remember_me');
        $expiresAt = $this->tokenExpiryFor($rememberMe);

        $token = $user->createToken(
            'auth_token',
            ['*'],
            $expiresAt
        )->plainTextToken;

        return response()->json([
            'message' => 'Google login successful.',
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->format(DATE_ATOM),
            'remember_me' => $rememberMe,
        ]);
    }

    public function facebook(
        FacebookLoginRequest $request,
        FacebookAuthService $facebookAuthService
    ) {
        $user = $facebookAuthService->authenticate(
            $request->access_token
        );

        // Social logins follow the same "Remember me" policy as the
        // email/password login.
        $rememberMe = $request->boolean('remember_me');
        $expiresAt = $this->tokenExpiryFor($rememberMe);

        $token = $user->createToken(
            'auth_token',
            ['*'],
            $expiresAt
        )->plainTextToken;

        return response()->json([
            'message' => 'Facebook login successful.',
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->format(DATE_ATOM),
            'remember_me' => $rememberMe,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;

/**
 * Manages the FCM device tokens that belong to the authenticated user.
 *
 * A token identifies an app installation, not a person, so it is stored
 * uniquely and re-associated with the current user on every sync. This
 * keeps push delivery correct after re-login, token refresh, or account
 * switch on the same device.
 */
class DeviceTokenController extends Controller
{
    /**
     * POST /api/device-tokens
     * Register (or re-assign) the device's FCM token to the current user.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['nullable', 'in:android,ios'],
        ]);

        DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Device token registered.',
        ]);
    }

    /**
     * DELETE /api/device-tokens/{token}
     * Disassociate a device token — called on logout so the device
     * stops receiving push for the previous user.
     */
    public function destroy(Request $request, string $token)
    {
        DeviceToken::where('token', $token)->delete();

        return response()->json([
            'message' => 'Device token removed.',
        ]);
    }
}

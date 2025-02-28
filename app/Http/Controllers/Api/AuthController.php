<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VerificationCode;
use App\Notifications\VerificationCodeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);
        $credentials = $request->only('email', 'password');

        // Attempt to authenticate and get the token
        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized access.',
                'data' => [],
            ], 401);
        }

        // Explicitly set the token and retrieve the user
        $user = JWTAuth::setToken($token)->user();
        if (!$user) {
            // Log the issue for debugging (optional)
            \Log::error('User not found after JWT attempt', ['credentials' => $credentials]);
            return response()->json([
                'status' => 500,
                'message' => 'Unable to retrieve authenticated user.',
                'data' => [],
            ], 500);
        }

        // Check user properties
        if ($user->blocked) {
            return response()->json([
                'status' => 403,
                'message' => 'You\'re blocked.',
                'data' => [],
            ], 403);
        }
        if (!$user->email_verified_at) {
            return response()->json([
                'status' => 403,
                'message' => 'Please verify your email.',
                'data' => [],
            ], 403);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Operation successful.',
            'data' => ['token' => $token],
        ], 200);
    }

    // The rest of your methods remain unchanged
    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone_number' => 'nullable|string|max:20',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'birthday' => 'nullable|date',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'state' => $request->state,
            'country' => $request->country,
            'birthday' => $request->birthday,
            'password' => Hash::make($request->password),
            'role' => 'user',
            'blocked' => false,
        ]);

        $code = rand(100000, 999999);
        VerificationCode::create([
            'email' => $user->email,
            'code' => $code,
            'type' => 'email_verification',
            'expires_at' => now()->addMinutes(10),
        ]);

        Notification::send($user, new VerificationCodeNotification($code));

        return response()->json([
            'status' => 201,
            'message' => 'Verification code sent to your email.',
            'data' => [],
        ], 201);
    }

    public function verifyEmail(Request $request)
    {
        $request->validate(['email' => 'required|email', 'code' => 'required|string']);

        $verification = VerificationCode::where('email', $request->email)
                                        ->where('code', $request->code)
                                        ->where('type', 'email_verification')
                                        ->where('expires_at', '>', now())
                                        ->first();

        if (!$verification) {
            return response()->json([
                'status' => 400,
                'message' => 'Invalid or expired code.',
                'data' => [],
            ], 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->email_verified_at = now();
        $user->save();

        $verification->delete();

        $token = JWTAuth::fromUser($user);
        return response()->json([
            'status' => 200,
            'message' => 'Email verified successfully.',
            'data' => ['token' => $token],
        ], 200);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'status' => 404,
                'message' => 'Email not found.',
                'data' => [],
            ], 404);
        }

        $code = rand(100000, 999999);
        VerificationCode::create([
            'email' => $user->email,
            'code' => $code,
            'type' => 'password_reset',
            'expires_at' => now()->addMinutes(10),
        ]);

        Notification::send($user, new VerificationCodeNotification($code, 'password_reset'));

        return response()->json([
            'status' => 200,
            'message' => 'Password reset link sent to your email.',
            'data' => [],
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $verification = VerificationCode::where('email', $request->email)
                                        ->where('code', $request->code)
                                        ->where('type', 'password_reset')
                                        ->where('expires_at', '>', now())
                                        ->first();

        if (!$verification) {
            return response()->json([
                'status' => 400,
                'message' => 'Invalid or expired code.',
                'data' => [],
            ], 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        $verification->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Password reset successfully.',
            'data' => [],
        ], 200);
    }

    public function logout()
    {
        auth('api')->logout();
        return response()->json([
            'status' => 200,
            'message' => 'Logged out successfully.',
            'data' => [],
        ], 200);
    }

    public function blockUser(Request $request)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);
        if (auth('api')->user()->role !== 'admin') {
            return response()->json([
                'status' => 403,
                'message' => 'Forbidden action.',
                'data' => [],
            ], 403);
        }

        $user = User::find($request->user_id);
        $user->blocked = true;
        $user->save();

        return response()->json([
            'status' => 200,
            'message' => 'User blocked successfully.',
            'data' => [],
        ], 200);
    }
}
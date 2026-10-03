<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResendOtpRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Mail\SendOtpMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    /**
     * Register a new user and dispatch OTP code for email verification.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $otp = (string) random_int(100000, 999999);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile_number' => $validated['mobile_number'] ?? null,
            'password' => $validated['password'],
            'email_verification_status' => false,
            'account_status' => 'active',
            'role' => $validated['role'] ?? 'user',
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($user->email)->send(
            new SendOtpMail($otp, 'Email Verification')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'User registered successfully. An OTP has been sent to your email for verification.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'mobile_number' => $user->mobile_number,
                    'role' => $user->role,
                    'email_verification_status' => $user->email_verification_status,
                    'account_status' => $user->account_status,
                    'created_at' => $user->created_at,
                ],
            ],
        ], 201);
    }

    /**
     * Verify user email address with OTP code and issue Sanctum token.
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found with the provided email address.',
            ], 404);
        }

        if (! $user->otp_code || $user->otp_code !== $validated['otp']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid OTP code provided.',
            ], 400);
        }

        if (! $user->otp_expires_at || Carbon::parse($user->otp_expires_at)->isPast()) {
            return response()->json([
                'status' => 'error',
                'message' => 'OTP code has expired. Please request a new one.',
            ], 400);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_verification_status' => true,
            'otp_code' => null,
            'otp_expires_at' => null,
        ])->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Email verified successfully.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
            ],
        ], 200);
    }

    /**
     * Resend the verification OTP code to the user's email.
     */
    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = User::where('email', $validated['email'])->first();

        if ($user->email_verification_status) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email is already verified.',
            ], 400);
        }

        $otp = (string) random_int(100000, 999999);

        $user->forceFill([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ])->save();

        Mail::to($user->email)->send(
            new SendOtpMail($otp, 'Email Verification Resend')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'A new OTP has been sent to your email address.',
        ], 200);
    }

    /**
     * Authenticate user with Email or Mobile Number and password.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['login'])
            ->orWhere('mobile_number', $validated['login'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials provided.',
            ], 401);
        }

        if (! $user->email_verification_status) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please verify your email before logging in.',
            ], 403);
        }

        if ($user->account_status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => "Your account is {$user->account_status}. Please contact support.",
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
            ],
        ], 200);
    }

    /**
     * Send OTP code for password reset.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'No account found with the provided email address.',
            ], 404);
        }

        if ($user->account_status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => "Your account is {$user->account_status}. Password reset is not permitted.",
            ], 403);
        }

        $otp = (string) random_int(100000, 999999);

        $user->forceFill([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ])->save();

        Mail::to($user->email)->send(
            new SendOtpMail($otp, 'Password Reset')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Password reset OTP has been sent to your email.',
        ], 200);
    }

    /**
     * Reset user password using OTP.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'No account found with the provided email address.',
            ], 404);
        }

        if (! $user->otp_code || $user->otp_code !== $validated['otp']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid OTP code provided.',
            ], 400);
        }

        if (! $user->otp_expires_at || Carbon::parse($user->otp_expires_at)->isPast()) {
            return response()->json([
                'status' => 'error',
                'message' => 'OTP code has expired. Please request a new password reset OTP.',
            ], 400);
        }

        $user->forceFill([
            'password' => $validated['password'],
            'otp_code' => null,
            'otp_expires_at' => null,
        ])->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Password has been reset successfully. You can now login with your new password.',
        ], 200);
    }

    /**
     * Retrieve authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $request->user(),
            ],
        ], 200);
    }

    /**
     * Log out authenticated user by revoking current Sanctum token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully and token revoked.',
        ], 200);
    }
}

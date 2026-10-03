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
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    /**
     * Register a new user and dispatch OTP code for email verification.
     */
    #[OA\Post(
        path: '/api/v1/auth/register',
        summary: 'Register a new user',
        description: "Registers a new user account with default 'user' role and sends an OTP code for email verification.",
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'mobile_number', type: 'string', example: '9876543210', nullable: true),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'SecurePass123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'SecurePass123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User registered successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'User registered successfully. An OTP has been sent to your email for verification.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'user', type: 'object'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->only(['name', 'email', 'mobile_number', 'password']);

        $otp = (string) random_int(100000, 999999);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile_number' => $data['mobile_number'] ?? null,
            'password' => $data['password'],
            'email_verification_status' => false,
            'account_status' => 'active',
            'role' => 'user',
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
    #[OA\Post(
        path: '/api/v1/auth/verify-otp',
        summary: 'Verify email with OTP',
        description: 'Verifies the user email address using the 6-digit OTP code and returns a Sanctum access token.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'otp'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'otp', type: 'string', example: '123456'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Email verified successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Email verified successfully.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'token', type: 'string', example: '1|abcdef...'),
                                new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                                new OA\Property(property: 'user', type: 'object'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid or expired OTP'),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $data = $request->only(['email', 'otp']);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found with the provided email address.',
            ], 404);
        }

        if (! $user->otp_code || $user->otp_code !== $data['otp']) {
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
    #[OA\Post(
        path: '/api/v1/auth/resend-otp',
        summary: 'Resend email verification OTP',
        description: 'Generates and sends a new OTP code to unverified user email.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'New OTP sent',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'A new OTP has been sent to your email address.'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Email already verified'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $data = $request->only(['email']);
        $user = User::where('email', $data['email'])->first();

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
    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'User login',
        description: 'Authenticates user using email or mobile number and password. Requires verified email and active account.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['login', 'password'],
                properties: [
                    new OA\Property(property: 'login', type: 'string', description: 'Email or Mobile Number', example: 'john@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'SecurePass123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Login successful.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'token', type: 'string', example: '1|abcdef...'),
                                new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                                new OA\Property(property: 'user', type: 'object'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Invalid credentials'),
            new OA\Response(response: 403, description: 'Email unverified or account inactive/suspended'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->only(['login', 'password']);

        $user = User::where('email', $data['login'])
            ->orWhere('mobile_number', $data['login'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
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
    #[OA\Post(
        path: '/api/v1/auth/forgot-password',
        summary: 'Forgot password',
        description: 'Dispatches a 6-digit OTP code to the user email for password reset.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password reset OTP sent',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Password reset OTP has been sent to your email.'),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Account is not active'),
            new OA\Response(response: 404, description: 'Account not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $data = $request->only(['email']);

        $user = User::where('email', $data['email'])->first();

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

        Mail::mailer('log')->to($user->email)->send(
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
    #[OA\Post(
        path: '/api/v1/auth/reset-password',
        summary: 'Reset password',
        description: 'Resets user password with valid unexpired OTP code.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'otp', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'otp', type: 'string', example: '123456'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'NewSecurePass123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'NewSecurePass123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password reset successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Password has been reset successfully. You can now login with your new password.'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid or expired OTP'),
            new OA\Response(response: 404, description: 'Account not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->only(['email', 'otp', 'password']);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'No account found with the provided email address.',
            ], 404);
        }

        if (! $user->otp_code || $user->otp_code !== $data['otp']) {
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
            'password' => $data['password'],
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
    #[OA\Get(
        path: '/api/v1/auth/me',
        summary: 'Get authenticated user profile',
        description: 'Returns the profile data of the currently authenticated user.',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authenticated user details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'user', type: 'object'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
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
    #[OA\Post(
        path: '/api/v1/auth/logout',
        summary: 'User logout',
        description: 'Revokes the current Sanctum access token.',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logged out successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Logged out successfully and token revoked.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully and token revoked.',
        ], 200);
    }
}

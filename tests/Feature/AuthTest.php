<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\SendOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_successfully(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'mobile_number' => '1234567890',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'User registered successfully. An OTP has been sent to your email for verification.',
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'mobile_number',
                        'role',
                        'email_verification_status',
                        'account_status',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'mobile_number' => '1234567890',
            'role' => 'user',
            'email_verification_status' => false,
            'account_status' => 'active',
        ]);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertNotNull($user->otp_code);
        $this->assertEquals(6, strlen($user->otp_code));
        $this->assertNotNull($user->otp_expires_at);

        Mail::assertSent(SendOtpMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->otp === $user->otp_code;
        });
    }

    public function test_registration_validation_errors(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_user_cannot_register_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Duplicate',
            'email' => 'john@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_verify_email_with_valid_otp(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'jane@example.com',
            'otp_code' => '654321',
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'email' => 'jane@example.com',
            'otp' => '654321',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Email verified successfully.',
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user',
                ],
            ]);

        $user->refresh();
        $this->assertTrue($user->email_verification_status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->otp_code);
        $this->assertNull($user->otp_expires_at);
    }

    public function test_verify_otp_fails_with_invalid_otp(): void
    {
        User::factory()->unverified()->create([
            'email' => 'jane@example.com',
            'otp_code' => '654321',
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'email' => 'jane@example.com',
            'otp' => '111111',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid OTP code provided.',
            ]);
    }

    public function test_verify_otp_fails_when_otp_expired(): void
    {
        User::factory()->unverified()->create([
            'email' => 'jane@example.com',
            'otp_code' => '654321',
            'otp_expires_at' => now()->subMinutes(1),
        ]);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'email' => 'jane@example.com',
            'otp' => '654321',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'OTP code has expired. Please request a new one.',
            ]);
    }

    public function test_user_can_resend_otp_successfully(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'resend@example.com',
            'otp_code' => '111222',
            'otp_expires_at' => now()->subMinutes(5),
        ]);

        $response = $this->postJson('/api/v1/auth/resend-otp', [
            'email' => 'resend@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'A new OTP has been sent to your email address.',
            ]);

        $user->refresh();
        $this->assertNotEquals('111222', $user->otp_code);
        $this->assertEquals(6, strlen($user->otp_code));
        $this->assertTrue($user->otp_expires_at->isFuture());

        Mail::assertSent(SendOtpMail::class, function ($mail) use ($user) {
            return $mail->hasTo('resend@example.com') && $mail->otp === $user->otp_code;
        });
    }

    public function test_resend_otp_fails_if_email_already_verified(): void
    {
        User::factory()->create([
            'email' => 'verified@example.com',
            'email_verification_status' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/resend-otp', [
            'email' => 'verified@example.com',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Email is already verified.',
            ]);
    }

    public function test_resend_otp_fails_for_non_existent_email(): void
    {
        $response = $this->postJson('/api/v1/auth/resend-otp', [
            'email' => 'ghost@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login_with_email(): void
    {
        $user = User::factory()->create([
            'email' => 'active@example.com',
            'password' => Hash::make('SecretPass123'),
            'email_verification_status' => true,
            'account_status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'active@example.com',
            'password' => 'SecretPass123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Login successful.',
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user',
                ],
            ]);
    }

    public function test_user_can_login_with_mobile_number(): void
    {
        $user = User::factory()->create([
            'mobile_number' => '9876543210',
            'password' => Hash::make('SecretPass123'),
            'email_verification_status' => true,
            'account_status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => '9876543210',
            'password' => 'SecretPass123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Login successful.',
            ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'active@example.com',
            'password' => Hash::make('SecretPass123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'active@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid credentials provided.',
            ]);
    }

    public function test_login_fails_when_email_unverified(): void
    {
        User::factory()->create([
            'email' => 'unverified@example.com',
            'password' => Hash::make('SecretPass123'),
            'email_verification_status' => false,
            'account_status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'unverified@example.com',
            'password' => 'SecretPass123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Please verify your email before logging in.',
            ]);
    }

    public function test_login_fails_when_account_inactive_or_suspended(): void
    {
        User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => Hash::make('SecretPass123'),
            'email_verification_status' => true,
            'account_status' => 'suspended',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'suspended@example.com',
            'password' => 'SecretPass123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Your account is suspended. Please contact support.',
            ]);
    }

    public function test_forgot_password_sends_otp(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'account_status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Password reset OTP has been sent to your email.',
            ]);

        $user->refresh();
        $this->assertNotNull($user->otp_code);
        $this->assertEquals(6, strlen($user->otp_code));
        $this->assertNotNull($user->otp_expires_at);

        Mail::assertSent(SendOtpMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->otp === $user->otp_code;
        });
    }

    public function test_reset_password_with_valid_otp(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'otp_code' => '998877',
            'otp_expires_at' => now()->addMinutes(10),
            'password' => Hash::make('OldPassword123'),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'otp' => '998877',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Password has been reset successfully. You can now login with your new password.',
            ]);

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123', $user->password));
        $this->assertNull($user->otp_code);
        $this->assertNull($user->otp_expires_at);
    }

    public function test_reset_password_fails_with_invalid_otp(): void
    {
        User::factory()->create([
            'email' => 'reset@example.com',
            'otp_code' => '998877',
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'otp' => '123123',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid OTP code provided.',
            ]);
    }

    public function test_authenticated_user_can_access_me_endpoint(): void
    {
        $user = User::factory()->create([
            'email_verification_status' => true,
            'account_status' => 'active',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                    ],
                ],
            ]);
    }

    public function test_unauthenticated_request_to_me_endpoint_returns_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_logout_and_revoke_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Logged out successfully and token revoked.',
            ]);

        $this->assertCount(0, $user->tokens);

        // Reset in-memory guard cache and verify revoked token is rejected
        $this->app['auth']->forgetGuards();

        $subsequentResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');

        $subsequentResponse->assertStatus(401);
    }

    public function test_user_can_register_as_vendor(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Vendor Store',
            'email' => 'vendor@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'role' => 'vendor',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.role', 'vendor');

        $this->assertDatabaseHas('users', [
            'email' => 'vendor@example.com',
            'role' => 'vendor',
        ]);
    }

    public function test_user_cannot_register_with_admin_role(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Admin Attempt',
            'email' => 'admin@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'role' => 'admin',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_user_model_role_helper_methods(): void
    {
        $user = User::factory()->create();
        $this->assertTrue($user->isUser());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isVendor());
        $this->assertTrue($user->hasRole('user'));
        $this->assertTrue($user->hasRole(UserRole::USER));

        $admin = User::factory()->admin()->create();
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isUser());
        $this->assertTrue($admin->hasRole('admin'));

        $vendor = User::factory()->vendor()->create();
        $this->assertTrue($vendor->isVendor());
        $this->assertFalse($vendor->isAdmin());
        $this->assertTrue($vendor->hasRole('vendor'));
    }

    public function test_role_middleware_authorizes_correct_role(): void
    {
        Route::get('/api/test-admin-only', function () {
            return response()->json(['message' => 'welcome admin']);
        })->middleware(['auth:sanctum', 'role:admin']);

        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/test-admin-only');

        $response->assertStatus(200)
            ->assertJson(['message' => 'welcome admin']);
    }

    public function test_role_middleware_blocks_unauthorized_role(): void
    {
        Route::get('/api/test-admin-route', function () {
            return response()->json(['message' => 'welcome admin']);
        })->middleware(['auth:sanctum', 'role:admin']);

        $user = User::factory()->create();
        $token = $user->createToken('user_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/test-admin-route');

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Forbidden. You do not have permission to access this resource.',
            ]);
    }
}

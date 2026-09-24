<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('secret123'),
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login successful',
            ])
            ->assertJsonPath('data.user.email', 'test@example.com')
            ->assertJsonPath('data.user.role', 'admin')
            ->assertJsonMissing(['password']);

        $this->assertNotNull($response->json('data.token'));
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('secret123'),
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials',
            ]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'password' => Hash::make('secret123'),
            'role' => UserRole::STUDENT,
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Account is inactive',
            ]);
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_authenticated_user_can_retrieve_profile_via_me(): void
    {
        $student = Student::create([
            'student_number' => '2026099',
            'name' => 'Student Profile',
            'gender' => Gender::MALE,
        ]);

        $user = User::create([
            'name' => 'Student User',
            'email' => 'student@example.com',
            'password' => Hash::make('secret123'),
            'role' => UserRole::STUDENT,
            'student_id' => $student->id,
            'is_active' => true,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.email', 'student@example.com')
            ->assertJsonPath('data.role', 'student')
            ->assertJsonPath('data.student.student_number', '2026099')
            ->assertJsonMissing(['password']);
    }

    public function test_unauthenticated_user_cannot_access_me(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_logout_and_token_is_invalidated(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('secret123'),
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logout successful',
            ]);

        $this->assertCount(0, $user->fresh()->tokens);

        app('auth')->forgetGuards();

        $afterLogout = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $afterLogout->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    }

    public function test_role_authorization_for_admin_coach_and_student(): void
    {
        $coach = Coach::create([
            'employee_number' => 'EMP999',
            'name' => 'Coach Profile',
        ]);

        $studentUser = User::create([
            'name' => 'Student User',
            'email' => 'student.role@example.com',
            'password' => Hash::make('secret123'),
            'role' => UserRole::STUDENT,
            'is_active' => true,
        ]);

        $coachUser = User::create([
            'name' => 'Coach User',
            'email' => 'coach.role@example.com',
            'password' => Hash::make('secret123'),
            'role' => UserRole::COACH,
            'coach_id' => $coach->id,
            'is_active' => true,
        ]);

        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin.role@example.com',
            'password' => Hash::make('secret123'),
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $studentToken = $studentUser->createToken('token')->plainTextToken;
        $coachToken = $coachUser->createToken('token')->plainTextToken;
        $adminToken = $adminUser->createToken('token')->plainTextToken;

        // 1. Student tries to access admin route -> 403 Forbidden, student route -> 200 OK
        $this->withHeader('Authorization', 'Bearer ' . $studentToken)
            ->getJson('/api/v1/admin/test')
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $studentToken)
            ->getJson('/api/v1/student/test')
            ->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Student authorized']);

        app('auth')->forgetGuards();

        // 2. Coach accesses coach route -> 200 OK, admin route -> 403 Forbidden
        $this->withHeader('Authorization', 'Bearer ' . $coachToken)
            ->getJson('/api/v1/coach/test')
            ->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Coach authorized']);

        $this->withHeader('Authorization', 'Bearer ' . $coachToken)
            ->getJson('/api/v1/admin/test')
            ->assertStatus(403);

        app('auth')->forgetGuards();

        // 3. Admin accesses admin route -> 200 OK
        $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->getJson('/api/v1/admin/test')
            ->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Admin authorized']);
    }

    public function test_super_admin_bypasses_role_restriction(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin.role@example.com',
            'password' => Hash::make('secret123'),
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $token = $superAdmin->createToken('token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/coach/test')
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/test')
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/student/test')
            ->assertStatus(200);
    }

    public function test_login_rate_limiting_is_enforced(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'ratelimit@example.com',
                'password' => 'wrongpassword',
            ])->assertStatus(401);
        }

        // 7th attempt within 1 minute should be throttled (429 Too Many Requests)
        $this->postJson('/api/v1/auth/login', [
            'email' => 'ratelimit@example.com',
            'password' => 'wrongpassword',
        ])->assertStatus(429);
    }
}

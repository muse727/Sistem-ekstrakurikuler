<?php

namespace Tests\Feature\Api\V1;

use App\Enums\CoachRole;
use App\Enums\DayOfWeek;
use App\Enums\Gender;
use App\Models\AcademicYear;
use App\Models\Coach;
use App\Models\Extracurricular;
use App\Models\ExtracurricularSchedule;
use App\Models\Student;
use App\Models\Venue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_retrieve_academic_years(): void
    {
        AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/academic-years');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Academic years retrieved successfully',
            ])
            ->assertJsonPath('data.0.name', '2026/2027')
            ->assertJsonPath('data.0.is_active', true);
    }

    public function test_can_retrieve_students_with_separate_nis_and_nisn(): void
    {
        Student::create([
            'student_number' => '2026001',
            'nisn' => '0081234567',
            'name' => 'Ahmad Rizky',
            'gender' => Gender::MALE,
            'date_of_birth' => '2010-05-14',
            'class_name' => 'X-IPA-1',
            'phone' => '081234567890',
            'email' => 'ahmad@example.com',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/students');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.0.student_number', '2026001')
            ->assertJsonPath('data.0.nisn', '0081234567')
            ->assertJsonPath('data.0.gender', 'male');
    }

    public function test_can_retrieve_coaches(): void
    {
        Coach::create([
            'employee_number' => 'EMP202001',
            'name' => 'Bambang Sudirman',
            'phone' => '081555666777',
            'email' => 'bambang@example.com',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/coaches');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.0.name', 'Bambang Sudirman')
            ->assertJsonPath('data.0.employee_number', 'EMP202001');
    }

    public function test_can_retrieve_venues_with_coordinates_and_radius(): void
    {
        Venue::create([
            'name' => 'Lapangan Basket',
            'address' => 'Area Olahraga',
            'latitude' => -6.2088000,
            'longitude' => 106.8456000,
            'radius_meters' => 50,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/venues');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.0.name', 'Lapangan Basket')
            ->assertJsonPath('data.0.latitude', -6.2088)
            ->assertJsonPath('data.0.longitude', 106.8456)
            ->assertJsonPath('data.0.radius_meters', 50);
    }

    public function test_can_retrieve_extracurriculars_with_relationships_and_integer_fee(): void
    {
        $ay = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $coach1 = Coach::create([
            'employee_number' => 'EMP01',
            'name' => 'Coach 1',
            'is_active' => true,
        ]);

        $coach2 = Coach::create([
            'employee_number' => 'EMP02',
            'name' => 'Coach 2',
            'is_active' => true,
        ]);

        $venue = Venue::create([
            'name' => 'GOR',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meters' => 50,
        ]);

        $ekskul = Extracurricular::create([
            'academic_year_id' => $ay->id,
            'name' => 'Basket',
            'code' => 'BSK',
            'description' => 'Ekskul basket',
            'fee_amount' => 250000,
            'quota' => 30,
            'is_active' => true,
        ]);

        $ekskul->coaches()->attach([
            $coach1->id => ['role' => CoachRole::PRIMARY->value],
            $coach2->id => ['role' => CoachRole::ASSISTANT->value],
        ]);

        ExtracurricularSchedule::create([
            'extracurricular_id' => $ekskul->id,
            'venue_id' => $venue->id,
            'day_of_week' => DayOfWeek::FRIDAY,
            'start_time' => '15:30:00',
            'end_time' => '17:30:00',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/extracurriculars');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.0.name', 'Basket')
            ->assertJsonPath('data.0.code', 'BSK')
            ->assertJsonPath('data.0.fee_amount', 250000)
            ->assertJsonPath('data.0.quota', 30)
            ->assertJsonPath('data.0.academic_year.name', '2026/2027')
            ->assertJsonPath('data.0.coaches.0.name', 'Coach 1')
            ->assertJsonPath('data.0.coaches.1.name', 'Coach 2')
            ->assertJsonPath('data.0.schedules.0.day_of_week', 'friday')
            ->assertJsonPath('data.0.schedules.0.venue.name', 'GOR');
    }

    public function test_duplicate_student_number_is_rejected(): void
    {
        Student::create([
            'student_number' => '2026001',
            'name' => 'Student 1',
            'gender' => Gender::MALE,
        ]);

        $this->expectException(QueryException::class);

        Student::create([
            'student_number' => '2026001',
            'name' => 'Student 2',
            'gender' => Gender::FEMALE,
        ]);
    }

    public function test_duplicate_nisn_is_rejected_when_present(): void
    {
        Student::create([
            'student_number' => '2026001',
            'nisn' => '0081234567',
            'name' => 'Student 1',
            'gender' => Gender::MALE,
        ]);

        $this->expectException(QueryException::class);

        Student::create([
            'student_number' => '2026002',
            'nisn' => '0081234567',
            'name' => 'Student 2',
            'gender' => Gender::FEMALE,
        ]);
    }

    public function test_duplicate_extracurricular_code_within_same_academic_year_is_rejected(): void
    {
        $ay = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);

        Extracurricular::create([
            'academic_year_id' => $ay->id,
            'name' => 'Basket A',
            'code' => 'BSK',
            'fee_amount' => 200000,
        ]);

        $this->expectException(QueryException::class);

        Extracurricular::create([
            'academic_year_id' => $ay->id,
            'name' => 'Basket B',
            'code' => 'BSK',
            'fee_amount' => 250000,
        ]);
    }

    public function test_duplicate_coach_extracurricular_relationship_is_rejected(): void
    {
        $ay = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);

        $coach = Coach::create([
            'name' => 'Coach 1',
        ]);

        $ekskul = Extracurricular::create([
            'academic_year_id' => $ay->id,
            'name' => 'Basket',
            'code' => 'BSK',
            'fee_amount' => 200000,
        ]);

        $ekskul->coaches()->attach($coach->id, ['role' => CoachRole::PRIMARY->value]);

        $this->expectException(QueryException::class);

        $ekskul->coaches()->attach($coach->id, ['role' => CoachRole::ASSISTANT->value]);
    }
}

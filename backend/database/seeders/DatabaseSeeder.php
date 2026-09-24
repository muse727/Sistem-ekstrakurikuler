<?php

namespace Database\Seeders;

use App\Enums\CoachRole;
use App\Enums\DayOfWeek;
use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Coach;
use App\Models\Extracurricular;
use App\Models\ExtracurricularSchedule;
use App\Models\Student;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Academic Year (1 record)
        $academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        // 2. Students (3 records)
        $student1 = Student::create([
            'student_number' => '2026001',
            'nisn' => '0081234567',
            'name' => 'Ahmad Rizky Pratama',
            'gender' => Gender::MALE,
            'date_of_birth' => '2010-05-14',
            'class_name' => 'X-IPA-1',
            'phone' => '081234567890',
            'email' => 'ahmad.rizky@student.school.id',
            'is_active' => true,
        ]);

        $student2 = Student::create([
            'student_number' => '2026002',
            'nisn' => '0087654321',
            'name' => 'Siti Nurhaliza',
            'gender' => Gender::FEMALE,
            'date_of_birth' => '2010-08-22',
            'class_name' => 'X-IPA-2',
            'phone' => '081298765432',
            'email' => 'siti.nurhaliza@student.school.id',
            'is_active' => true,
        ]);

        $student3 = Student::create([
            'student_number' => '2026003',
            'nisn' => null, // NISN nullable test
            'name' => 'Budi Santoso',
            'gender' => Gender::MALE,
            'date_of_birth' => '2011-01-10',
            'class_name' => 'X-IPS-1',
            'phone' => '081311223344',
            'email' => 'budi.santoso@student.school.id',
            'is_active' => true,
        ]);

        // 3. Coaches (2 records)
        $coach1 = Coach::create([
            'employee_number' => 'EMP202001',
            'name' => 'Bambang Sudirman, S.Pd.',
            'phone' => '081555666777',
            'email' => 'bambang.coach@school.id',
            'is_active' => true,
        ]);

        $coach2 = Coach::create([
            'employee_number' => 'EMP202102',
            'name' => 'Dewi Lestari, S.Or.',
            'phone' => '081777888999',
            'email' => 'dewi.coach@school.id',
            'is_active' => true,
        ]);

        // 4. Venues (2 records)
        $venue1 = Venue::create([
            'name' => 'Lapangan Basket Utama',
            'address' => 'Jl. Pendidikan No. 12, Area Olahraga Barat',
            'latitude' => -6.2088000,
            'longitude' => 106.8456000,
            'radius_meters' => 50,
            'is_active' => true,
        ]);

        $venue2 = Venue::create([
            'name' => 'GOR Serbaguna',
            'address' => 'Jl. Pendidikan No. 12, Gedung B',
            'latitude' => -6.2090000,
            'longitude' => 106.8460000,
            'radius_meters' => 75,
            'is_active' => true,
        ]);

        // 5. Extracurriculars (2 records)
        $ekskul1 = Extracurricular::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Bola Basket',
            'code' => 'BSK',
            'description' => 'Ekstrakurikuler olahraga bola basket untuk mengembangkan bakat dan fisik.',
            'fee_amount' => 250000, // Rp250.000 dalam integer
            'quota' => 30,
            'is_active' => true,
        ]);

        $ekskul2 = Extracurricular::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Pramuka',
            'code' => 'PRM',
            'description' => 'Ekstrakurikuler kepramukaan pembentukan karakter dan kedisiplinan.',
            'fee_amount' => 100000, // Rp100.000 dalam integer
            'quota' => null, // Unlimited capacity
            'is_active' => true,
        ]);

        // 6. Coach relationships
        $ekskul1->coaches()->attach([
            $coach1->id => ['role' => CoachRole::PRIMARY->value],
            $coach2->id => ['role' => CoachRole::ASSISTANT->value],
        ]);

        $ekskul2->coaches()->attach([
            $coach2->id => ['role' => CoachRole::PRIMARY->value],
        ]);

        // 7. Schedules
        ExtracurricularSchedule::create([
            'extracurricular_id' => $ekskul1->id,
            'venue_id' => $venue1->id,
            'day_of_week' => DayOfWeek::FRIDAY,
            'start_time' => '15:30:00',
            'end_time' => '17:30:00',
            'is_active' => true,
        ]);

        ExtracurricularSchedule::create([
            'extracurricular_id' => $ekskul2->id,
            'venue_id' => $venue2->id,
            'day_of_week' => DayOfWeek::SATURDAY,
            'start_time' => '08:00:00',
            'end_time' => '11:00:00',
            'is_active' => true,
        ]);

        // 8. Development Seed Users (T003)
        $defaultPassword = Hash::make('password123');

        // Super Admin
        User::create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@example.test',
            'password' => $defaultPassword,
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        // Admin
        User::create([
            'name' => 'School Admin',
            'email' => 'admin@example.test',
            'password' => $defaultPassword,
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        // Coach
        User::create([
            'name' => 'Coach Bambang',
            'email' => 'coach@example.test',
            'password' => $defaultPassword,
            'role' => UserRole::COACH,
            'coach_id' => $coach1->id,
            'is_active' => true,
        ]);

        // Student
        User::create([
            'name' => 'Ahmad Rizky',
            'email' => 'student@example.test',
            'password' => $defaultPassword,
            'role' => UserRole::STUDENT,
            'student_id' => $student1->id,
            'is_active' => true,
        ]);

        // Inactive User (for testing/safety check)
        User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.test',
            'password' => $defaultPassword,
            'role' => UserRole::STUDENT,
            'is_active' => false,
        ]);
    }
}

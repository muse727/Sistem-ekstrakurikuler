export type UserRole = 'super_admin' | 'admin' | 'coach' | 'student';

export interface StudentProfile {
  id: number;
  student_number: string;
  nisn: string | null;
  name: string;
  gender: 'male' | 'female';
  class_name: string;
}

export interface CoachProfile {
  id: number;
  employee_number: string | null;
  name: string;
  phone: string | null;
  email: string | null;
}

export interface User {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  is_active: boolean;
  last_login_at: string | null;
  student?: StudentProfile;
  coach?: CoachProfile;
  created_at: string;
  updated_at: string;
}

export interface AuthResponse {
  success: boolean;
  message: string;
  data: {
    user: User;
    token: string;
  };
}

export interface ProfileResponse {
  success: boolean;
  message: string;
  data: User;
}

export interface LoginCredentials {
  email: string;
  password: string;
}

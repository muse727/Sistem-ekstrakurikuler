export interface Meta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number | null;
  to: number | null;
}

export interface PaginatedResponse<T> {
  success: boolean;
  message: string;
  data: T[];
  meta: Meta;
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
}

export interface SingleResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export interface AcademicYear {
  id: number;
  name: string;
  start_date: string;
  end_date: string;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface Student {
  id: number;
  student_number: string;
  nisn: string | null;
  name: string;
  gender: 'male' | 'female';
  date_of_birth: string | null;
  class_name: string | null;
  phone: string | null;
  email: string | null;
  is_active: boolean;
}

export interface Coach {
  id: number;
  employee_number: string | null;
  name: string;
  phone: string | null;
  email: string | null;
  is_active: boolean;
}

export interface Venue {
  id: number;
  name: string;
  address: string | null;
  latitude: number | null;
  longitude: number | null;
  radius_meters: number;
  is_active: boolean;
}

export type DayOfWeek =
  | 'monday'
  | 'tuesday'
  | 'wednesday'
  | 'thursday'
  | 'friday'
  | 'saturday'
  | 'sunday';

export type CoachRole = 'primary' | 'assistant';

export interface ExtracurricularCoach extends Coach {
  pivot?: {
    role: CoachRole;
  };
}

export interface Schedule {
  id: number;
  extracurricular_id: number;
  venue_id: number | null;
  venue?: Venue | null;
  day_of_week: DayOfWeek;
  start_time: string;
  end_time: string;
  is_active: boolean;
}

export interface Extracurricular {
  id: number;
  academic_year_id: number;
  academic_year?: AcademicYear | null;
  name: string;
  code: string;
  description: string | null;
  fee_amount: number;
  quota: number | null;
  is_active: boolean;
  coaches?: ExtracurricularCoach[];
  schedules?: Schedule[];
}

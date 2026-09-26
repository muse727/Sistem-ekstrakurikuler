export type SessionStatus = 'scheduled' | 'open' | 'completed' | 'cancelled';
export type AttendanceStatus = 'hadir' | 'izin' | 'sakit' | 'alpa';

export interface VenueSummary {
  id: number;
  name: string;
  address?: string | null;
  latitude?: number | null;
  longitude?: number | null;
  radius_meters?: number | null;
}

export interface SessionItem {
  id: number;
  extracurricular_id: number;
  extracurricular?: { id: number; name: string; code?: string } | null;
  academic_year_id: number;
  academic_year?: { id: number; name: string } | null;
  venue_id?: number | null;
  venue?: VenueSummary | null;
  session_date: string;
  start_time?: string | null;
  end_time?: string | null;
  status: SessionStatus;
  topic?: string | null;
  notes?: string | null;
  has_check_in?: boolean | null;
  check_in_count?: number | null;
  attendance_count?: number | null;
  attendances_count?: number | null;
}

export interface CheckIn {
  id: number;
  session_id: number;
  coach_id: number;
  coach?: { id: number; name: string } | null;
  latitude: number | null;
  longitude: number | null;
  accuracy_meters: number | null;
  distance_from_venue_meters: number | null;
  device_captured_at?: string | null;
  server_received_at?: string | null;
  status?: string;
  photo_url?: string | null;
}

export interface AttendanceItem {
  id: number;
  session_id: number;
  session?: SessionItem | null;
  student_id: number;
  student?: { id: number; name: string; student_number?: string } | null;
  registration_id: number;
  status: AttendanceStatus;
  notes?: string | null;
  recorded_by?: number | null;
  recorded_at?: string | null;
  updated_by?: number | null;
  updated_at?: string | null;
}

export interface PaginatedResponse<T> {
  success: boolean;
  message: string;
  data: T[];
  meta: { current_page: number; per_page: number; total: number; last_page: number; from: number | null; to: number | null };
}

export interface SingleResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export function sessionStatusLabel(s: SessionStatus): string {
  switch (s) {
    case 'scheduled': return 'Terjadwal';
    case 'open': return 'Dibuka';
    case 'completed': return 'Selesai';
    case 'cancelled': return 'Dibatalkan';
    default: return s;
  }
}

export function attendanceStatusLabel(s: AttendanceStatus): string {
  switch (s) {
    case 'hadir': return 'Hadir';
    case 'izin': return 'Izin';
    case 'sakit': return 'Sakit';
    case 'alpa': return 'Alpa';
    default: return s;
  }
}

export function apiErrorMessage(e: unknown, fallback: string): string {
  const err = e as { response?: { data?: { message?: string }; status?: number } };
  const status = err.response?.status;
  const msg = err.response?.data?.message;
  if (status === 401) return 'Sesi berakhir, silakan login kembali.';
  if (status === 403) return msg || 'Akses ditolak untuk peran kamu.';
  if (status === 404) return 'Data tidak ditemukan.';
  if (status === 409) return msg || 'Konflik: sudah check-in / sudah ada.';
  if (status === 422) return msg || 'Validasi gagal, periksa input.';
  if (status && status >= 500) return 'Kesalahan server, coba lagi.';
  return msg || fallback;
}

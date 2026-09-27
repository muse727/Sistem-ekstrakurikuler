export type EvaluationPeriod = 'midterm' | 'final';
export type EvaluationStatus = 'draft' | 'published';

export interface EvaluationItem {
  id: number;
  student_id: number;
  student?: { id: number; name: string; student_number?: string } | null;
  registration_id: number;
  extracurricular_id: number;
  extracurricular?: { id: number; name: string; code?: string } | null;
  academic_year_id: number;
  academic_year?: { id: number; name: string } | null;
  evaluator_id: number;
  evaluator?: { id: number; name: string } | null;
  evaluation_period: EvaluationPeriod;
  attendance_score: number | null;
  activity_score: number | null;
  skill_score: number | null;
  discipline_score: number | null;
  final_score: number | null;
  notes?: string | null;
  status: EvaluationStatus;
  evaluated_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface AttendanceSummary {
  total_sessions: number;
  hadir: number;
  izin: number;
  sakit: number;
  alpa: number;
  percentage: number;
}

export interface EligibleRegistration {
  id: number;
  student_id: number;
  student?: { id: number; name: string; student_number?: string } | null;
  extracurricular_id: number;
  extracurricular?: { id: number; name: string } | null;
  academic_year_id: number;
  academic_year?: { id: number; name: string } | null;
  status: string;
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

export function evaluationPeriodLabel(p: EvaluationPeriod): string {
  return p === 'midterm' ? 'Tengah Semester' : p === 'final' ? 'Akhir Semester' : p;
}

export function evaluationStatusLabel(s: EvaluationStatus): string {
  return s === 'draft' ? 'Draf' : s === 'published' ? 'Dipublikasi' : s;
}

export function previewFinal(a?: string, b?: string, c?: string, d?: string): number | null {
  const nums = [a, b, c, d].map((v) => (v === undefined || v === '' ? null : Number(v)));
  if (nums.some((n) => n === null || Number.isNaN(n))) return null;
  const [at, ac, sk, di] = nums as number[];
  return Math.round((at * 0.25 + ac * 0.25 + sk * 0.3 + di * 0.2) * 100) / 100;
}

export function apiErrorMessage(e: unknown, fallback: string): string {
  const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> }; status?: number } };
  const status = err.response?.status;
  const msg = err.response?.data?.message;
  const errs = err.response?.data?.errors;
  if (errs) {
    const first = Object.values(errs).flat()[0];
    if (first) return first;
  }
  if (status === 401) return 'Sesi berakhir, silakan login kembali.';
  if (status === 403) return msg || 'Akses ditolak untuk peran kamu.';
  if (status === 404) return 'Data tidak ditemukan.';
  if (status === 409) return msg || 'Duplikat: evaluasi periode ini sudah ada.';
  if (status === 422) return msg || 'Validasi gagal, periksa input.';
  if (status && status >= 500) return 'Kesalahan server, coba lagi.';
  return msg || fallback;
}

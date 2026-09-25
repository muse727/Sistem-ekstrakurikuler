import type {
  AcademicYear,
  Coach,
  Extracurricular,
  Meta,
} from '../admin/types';

export type RegistrationStatus =
  | 'draft'
  | 'submitted'
  | 'approved'
  | 'rejected'
  | 'active'
  | 'cancelled';

export interface Registration {
  id: number;
  student_id: number;
  student?: {
    id: number;
    student_number: string;
    name: string;
    class_name: string | null;
  } | null;
  extracurricular_id: number;
  extracurricular?: Extracurricular | null;
  academic_year_id: number;
  academic_year?: AcademicYear | null;
  status: RegistrationStatus;
  submitted_at: string | null;
  approved_at: string | null;
  rejected_at: string | null;
  cancelled_at: string | null;
  rejection_reason: string | null;
  approved_by: number | null;
  rejected_by: number | null;
  cancelled_by: number | null;
  created_at?: string;
  updated_at?: string;
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

export type { AcademicYear, Coach, Extracurricular };

export function statusLabel(s: RegistrationStatus): string {
  switch (s) {
    case 'draft': return 'Draft';
    case 'submitted': return 'Diajukan';
    case 'approved': return 'Disetujui';
    case 'rejected': return 'Ditolak';
    case 'active': return 'Aktif';
    case 'cancelled': return 'Dibatalkan';
    default: return s;
  }
}

export function apiErrorMessage(e: unknown, fallback: string): string {
  const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> }; status?: number } };
  const status = err.response?.status;
  const msg = err.response?.data?.message;
  if (status === 401) return 'Sesi berakhir, silakan login kembali.';
  if (status === 403) return msg || 'Akses ditolak untuk peran kamu.';
  if (status === 404) return 'Data tidak ditemukan.';
  if (status === 409) return msg || 'Konflik: data sudah ada / kuota penuh.';
  if (status === 422) return msg || 'Validasi gagal, periksa input.';
  if (status && status >= 500) return 'Kesalahan server, coba lagi.';
  return msg || fallback;
}

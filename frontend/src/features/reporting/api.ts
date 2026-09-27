import api from '../../lib/api';

export interface ReportParams {
  page?: number;
  per_page?: number;
  search?: string;
  academic_year_id?: number;
  extracurricular_id?: number;
  date_from?: string;
  date_to?: string;
  status?: string;
  [key: string]: unknown;
}

function toParams(p?: ReportParams) {
  const out: Record<string, string | number> = {};
  if (!p) return out;
  for (const [k, v] of Object.entries(p)) {
    if (v === undefined || v === null || v === '') continue;
    out[k] = v as string | number;
  }
  return out;
}

export interface DashboardResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export interface ReportListResponse<T> {
  success: boolean;
  message: string;
  data: {
    scope: unknown;
    summary?: unknown;
    items: T[];
  };
  meta: { current_page: number; per_page: number; total: number; last_page: number; from: number | null; to: number | null };
}

export const reportingApi = {
  adminDashboard: (p?: ReportParams) =>
    api.get<DashboardResponse<unknown>>('/admin/dashboard', { params: toParams(p) }).then((r) => r.data),
  membership: (p?: ReportParams) =>
    api.get<ReportListResponse<unknown>>('/admin/reports/membership', { params: toParams(p) }).then((r) => r.data),
  payments: (p?: ReportParams) =>
    api.get<ReportListResponse<unknown>>('/admin/reports/payments', { params: toParams(p) }).then((r) => r.data),
  attendance: (p?: ReportParams) =>
    api.get<ReportListResponse<unknown>>('/admin/reports/attendance', { params: toParams(p) }).then((r) => r.data),
  evaluations: (p?: ReportParams) =>
    api.get<ReportListResponse<unknown>>('/admin/reports/evaluations', { params: toParams(p) }).then((r) => r.data),
  extracurriculars: (p?: ReportParams) =>
    api.get<{ success: boolean; message: string; data: { scope: unknown; items: unknown[] } } & { meta: ReportListResponse<unknown>['meta'] }>(
      '/admin/reports/extracurriculars', { params: toParams(p) },
    ).then((r) => r.data),
  coachDashboard: (p?: ReportParams) =>
    api.get<DashboardResponse<unknown>>('/coach/dashboard', { params: toParams(p) }).then((r) => r.data),
  studentDashboard: (p?: ReportParams) =>
    api.get<DashboardResponse<unknown>>('/student/dashboard', { params: toParams(p) }).then((r) => r.data),
};

export function apiErrorMessage(e: unknown, fallback: string): string {
  const err = e as { response?: { data?: { message?: string }; status?: number } };
  const status = err.response?.status;
  const msg = err.response?.data?.message;
  if (status === 401) return 'Sesi berakhir, silakan login kembali.';
  if (status === 403) return msg || 'Akses ditolak untuk peran kamu.';
  if (status === 404) return 'Data tidak ditemukan.';
  if (status && status >= 500) return 'Kesalahan server, coba lagi.';
  return msg || fallback;
}

export function formatRupiah(n: number): string {
  return 'Rp' + Number(n || 0).toLocaleString('id-ID');
}

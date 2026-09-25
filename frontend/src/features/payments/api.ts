import api from '../../lib/api';
import type { Invoice, PaginatedResponse, SingleResponse } from './types';

export interface PaymentListParams {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
  academic_year_id?: number;
  extracurricular_id?: number;
  student_id?: number;
}

function toParams(p?: PaymentListParams) {
  const out: Record<string, string | number> = {};
  if (!p) return out;
  for (const [k, v] of Object.entries(p)) {
    if (v === undefined || v === null || v === '') continue;
    out[k] = v as string | number;
  }
  return out;
}

function authDownloadUrl(path: string): string {
  const base = (import.meta.env.VITE_API_URL as string | undefined) || 'http://localhost:8000/api/v1';
  const token = localStorage.getItem('auth_token') || '';
  const sep = path.includes('?') ? '&' : '?';
  void token;
  return `${base}${path}${sep}`;
}

export const studentPaymentsApi = {
  list: (p?: PaymentListParams) =>
    api.get<PaginatedResponse<Invoice>>('/student/payments', { params: toParams(p) }).then((r) => r.data),
  get: (id: number) =>
    api.get<SingleResponse<Invoice>>(`/student/payments/${id}`).then((r) => r.data),
  uploadProof: (id: number, file: File) => {
    const form = new FormData();
    form.append('proof', file);
    return api.post<SingleResponse<Invoice>>(`/student/payments/${id}/proof`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }).then((r) => r.data);
  },
  fileUrl: (invoiceId: number, proofId: number) =>
    authDownloadUrl(`/student/payments/${invoiceId}/proofs/${proofId}/file`),
};

export const adminPaymentsApi = {
  list: (p?: PaymentListParams) =>
    api.get<PaginatedResponse<Invoice>>('/admin/payments', { params: toParams(p) }).then((r) => r.data),
  get: (id: number) =>
    api.get<SingleResponse<Invoice>>(`/admin/payments/${id}`).then((r) => r.data),
  verify: (id: number) =>
    api.post<SingleResponse<Invoice>>(`/admin/payments/${id}/verify`).then((r) => r.data),
  reject: (id: number, rejection_reason: string) =>
    api.post<SingleResponse<Invoice>>(`/admin/payments/${id}/reject`, { rejection_reason }).then((r) => r.data),
  fileUrl: (invoiceId: number, proofId: number) =>
    authDownloadUrl(`/admin/payments/${invoiceId}/proofs/${proofId}/file`),
};

export async function downloadProofFile(url: string, filename: string): Promise<void> {
  const token = localStorage.getItem('auth_token') || '';
  const res = await fetch(url, { headers: token ? { Authorization: `Bearer ${token}` } : {} });
  if (!res.ok) throw new Error(`Gagal mengunduh (${res.status})`);
  const blob = await res.blob();
  const obj = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = obj;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(obj);
}

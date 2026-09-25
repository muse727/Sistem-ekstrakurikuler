export type InvoiceStatus = 'unpaid' | 'pending_verification' | 'paid' | 'rejected' | 'cancelled';
export type VerificationStatus = 'approved' | 'rejected';

export interface PaymentVerification {
  id: number;
  status: VerificationStatus;
  verified_by: number | null;
  verifier_name?: string | null;
  verified_at: string | null;
  rejection_reason: string | null;
}

export interface PaymentProof {
  id: number;
  original_filename: string;
  mime_type: string;
  file_size: number;
  uploaded_at: string | null;
  submitted_at: string | null;
  latest_verification?: PaymentVerification | null;
  verifications?: PaymentVerification[];
}

export interface InvoiceRegistrationSummary {
  id: number;
  status: string;
  extracurricular?: { id: number; name: string; code: string } | null;
  academic_year?: { id: number; name: string } | null;
  student?: { id: number; name: string; student_number: string } | null;
}

export interface Invoice {
  id: number;
  invoice_number: string;
  amount: number;
  status: InvoiceStatus;
  issued_at: string | null;
  due_at: string | null;
  paid_at: string | null;
  registration_id: number;
  registration?: InvoiceRegistrationSummary | null;
  latest_proof?: PaymentProof | null;
  latest_verification?: PaymentVerification | null;
  rejection_reason?: string | null;
  proofs?: PaymentProof[];
}

export interface PaginatedResponse<T> {
  success: boolean;
  message: string;
  data: T[];
  meta: { current_page: number; per_page: number; total: number; last_page: number; from: number | null; to: number | null };
  links: { first: string | null; last: string | null; prev: string | null; next: string | null };
}

export interface SingleResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export function invoiceStatusLabel(s: InvoiceStatus): string {
  switch (s) {
    case 'unpaid': return 'Belum Bayar';
    case 'pending_verification': return 'Menunggu Verifikasi';
    case 'paid': return 'Lunas';
    case 'rejected': return 'Ditolak';
    case 'cancelled': return 'Dibatalkan';
    default: return s;
  }
}

export function formatRupiah(n: number): string {
  return 'Rp' + Number(n || 0).toLocaleString('id-ID');
}

export function apiErrorMessage(e: unknown, fallback: string): string {
  const err = e as { response?: { data?: { message?: string }; status?: number } };
  const status = err.response?.status;
  const msg = err.response?.data?.message;
  if (status === 401) return 'Sesi berakhir, silakan login kembali.';
  if (status === 403) return msg || 'Akses ditolak untuk peran kamu.';
  if (status === 404) return 'Data tidak ditemukan.';
  if (status === 409) return msg || 'Konflik: sudah diverifikasi / sudah ada.';
  if (status === 422) return msg || 'Validasi gagal, periksa input.';
  if (status && status >= 500) return 'Kesalahan server, coba lagi.';
  return msg || fallback;
}

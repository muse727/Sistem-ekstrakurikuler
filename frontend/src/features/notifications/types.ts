export interface AppNotification {
  id: number;
  type: string;
  title: string;
  message: string;
  reference_type: string | null;
  reference_id: number | null;
  read_at: string | null;
  created_at: string | null;
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

export function notificationTypeLabel(t: string): string {
  switch (t) {
    case 'registration_submitted': return 'Registrasi masuk';
    case 'registration_approved': return 'Registrasi disetujui';
    case 'registration_rejected': return 'Registrasi ditolak';
    case 'payment_proof_submitted': return 'Bukti bayar masuk';
    case 'payment_proof_approved': return 'Pembayaran disetujui';
    case 'payment_proof_rejected': return 'Bukti bayar ditolak';
    case 'evaluation_published': return 'Evaluasi terbit';
    default: return t;
  }
}

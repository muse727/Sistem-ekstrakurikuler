import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { studentRegistrationsApi } from '../api';
import { apiErrorMessage, statusLabel } from '../types';
import type { Registration } from '../types';

export function MyRegistrationDetailPage() {
  const { id } = useParams<{ id: string }>();
  const [item, setItem] = useState<Registration | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [cancelling, setCancelling] = useState(false);

  const load = async () => {
    if (!id) return;
    setLoading(true); setError(null);
    try {
      const res = await studentRegistrationsApi.get(Number(id));
      setItem(res.data);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal memuat detail')); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(); }, [id]);

  const cancel = async () => {
    if (!item) return;
    if (!window.confirm('Batalkan pendaftaran ini? Riwayat tetap tersimpan.')) return;
    setCancelling(true); setError(null);
    try {
      const res = await studentRegistrationsApi.cancel(item.id);
      setItem(res.data);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal membatalkan')); }
    finally { setCancelling(false); }
  };

  if (loading) return <p className="admin-muted">Memuat...</p>;

  return (
    <div>
      <p><Link to="/student/registrations">&larr; Kembali</Link></p>
      <h2>Detail Pendaftaran</h2>
      {error && <div className="admin-error">{error}</div>}
      {!item ? <p className="admin-muted">Data tidak ditemukan.</p> : (
        <div className="admin-card">
          <p>ID: {item.id}</p>
          <p>Ekstrakurikuler: {item.extracurricular?.name || item.extracurricular_id}</p>
          <p>Tahun ajaran: {item.academic_year?.name || item.academic_year_id}</p>
          <p>Status: <span className="badge">{statusLabel(item.status)}</span></p>
          <p>Diajukan: {item.submitted_at || '-'}</p>
          <p>Disetujui: {item.approved_at || '-'}</p>
          <p>Ditolak: {item.rejected_at || '-'}</p>
          {item.rejection_reason && <p>Alasan penolakan: {item.rejection_reason}</p>}
          <p>Dibatalkan: {item.cancelled_at || '-'}</p>
          {['submitted', 'approved', 'active', 'draft'].includes(item.status) && (
            <button className="admin-btn danger" disabled={cancelling} onClick={cancel}>
              {cancelling ? 'Membatalkan...' : 'Batalkan Pendaftaran'}
            </button>
          )}
        </div>
      )}
    </div>
  );
}

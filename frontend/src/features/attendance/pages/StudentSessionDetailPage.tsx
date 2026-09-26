import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { studentSessionsApi } from '../api';
import { apiErrorMessage, sessionStatusLabel } from '../types';
import type { SessionItem } from '../types';

export function StudentSessionDetailPage() {
  const { id } = useParams();
  const [item, setItem] = useState<SessionItem | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!id) return;
    setLoading(true);
    studentSessionsApi.get(Number(id))
      .then((r) => setItem(r.data))
      .catch((e) => setError(apiErrorMessage(e, 'Gagal memuat sesi')))
      .finally(() => setLoading(false));
  }, [id]);

  if (loading) return <div><h2>Detail Sesi</h2><p className="admin-muted">Memuat...</p></div>;
  if (!item) return <div><h2>Detail Sesi</h2>{error && <div className="admin-error">{error}</div>}<Link to="/student/sessions">Kembali</Link></div>;

  return (
    <div>
      <h2>Sesi: {item.topic || `#${item.id}`}</h2>
      {error && <div className="admin-error">{error}</div>}
      <div className="admin-card">
        <p><b>Tanggal:</b> {item.session_date} {item.start_time || ''}–{item.end_time || ''}</p>
        <p><b>Ekskul:</b> {item.extracurricular?.name} | <b>Tahun:</b> {item.academic_year?.name}</p>
        <p><b>Venue:</b> {item.venue?.name || '-'} {item.venue?.address ? `— ${item.venue.address}` : ''}</p>
        <p><b>Status:</b> <span className="badge">{sessionStatusLabel(item.status)}</span></p>
        {item.notes && <p><b>Catatan:</b> {item.notes}</p>}
        <p className="admin-muted">Absensi dicatat pelatih setelah check-in. Kamu tidak bisa mengubah absensi sendiri.</p>
        <p><Link to="/student/sessions">Kembali ke daftar</Link> | <Link to="/student/attendance">Lihat absensiku</Link></p>
      </div>
    </div>
  );
}

import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { studentSessionsApi } from '../api';
import { apiErrorMessage, attendanceStatusLabel } from '../types';
import type { AttendanceItem } from '../types';

export function StudentAttendanceDetailPage() {
  const { id } = useParams();
  const [item, setItem] = useState<AttendanceItem | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!id) return;
    setLoading(true);
    studentSessionsApi.attendanceDetail(Number(id))
      .then((r) => setItem(r.data))
      .catch((e) => setError(apiErrorMessage(e, 'Gagal memuat absensi')))
      .finally(() => setLoading(false));
  }, [id]);

  if (loading) return <div><h2>Detail Absensi</h2><p className="admin-muted">Memuat...</p></div>;
  if (!item) return <div><h2>Detail Absensi</h2>{error && <div className="admin-error">{error}</div>}<Link to="/student/attendance">Kembali</Link></div>;

  return (
    <div>
      <h2>Detail Absensi</h2>
      {error && <div className="admin-error">{error}</div>}
      <div className="admin-card">
        <p><b>Sesi:</b> {item.session?.topic || `#${item.session_id}`} ({item.session?.session_date || '-'})</p>
        <p><b>Ekskul:</b> {item.session?.extracurricular?.name || '-'}</p>
        <p><b>Status:</b> <span className="badge">{attendanceStatusLabel(item.status)}</span></p>
        <p><b>Catatan:</b> {item.notes || '-'}</p>
        <p className="admin-muted">Dicatat: {item.recorded_at ? new Date(item.recorded_at).toLocaleString('id-ID') : '-'}</p>
        <p><Link to="/student/attendance">Kembali</Link></p>
      </div>
    </div>
  );
}

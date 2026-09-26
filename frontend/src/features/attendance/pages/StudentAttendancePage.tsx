import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { studentSessionsApi } from '../api';
import { apiErrorMessage, attendanceStatusLabel } from '../types';
import type { AttendanceItem } from '../types';

export function StudentAttendancePage() {
  const [items, setItems] = useState<AttendanceItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = async () => {
    setLoading(true); setError(null);
    try {
      const res = await studentSessionsApi.attendance();
      setItems(res.data);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal memuat absensi')); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(); }, []);

  return (
    <div>
      <h2>Absensiku</h2>
      {error && <div className="admin-error">{error}</div>}
      <div className="admin-card">
        {loading ? <p className="admin-muted">Memuat...</p> : items.length === 0 ? (
          <p className="admin-muted">Belum ada absensi.</p>
        ) : (
          <table className="admin-table">
            <thead><tr><th>Sesi</th><th>Ekskul</th><th>Status</th><th>Catatan</th><th>Aksi</th></tr></thead>
            <tbody>{items.map((a) => (
              <tr key={a.id}>
                <td>{a.session?.topic || `#${a.session_id}`} ({a.session?.session_date || '-'})</td>
                <td>{a.session?.extracurricular?.name || '-'}</td>
                <td><span className="badge">{attendanceStatusLabel(a.status)}</span></td>
                <td>{a.notes || '-'}</td>
                <td><Link to={`/student/attendance/${a.id}`}>Detail</Link></td>
              </tr>
            ))}</tbody>
          </table>
        )}
      </div>
    </div>
  );
}

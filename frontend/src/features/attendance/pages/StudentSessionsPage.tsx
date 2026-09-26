import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { studentSessionsApi } from '../api';
import { apiErrorMessage, sessionStatusLabel } from '../types';
import type { SessionItem } from '../types';

export function StudentSessionsPage() {
  const [items, setItems] = useState<SessionItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = async () => {
    setLoading(true); setError(null);
    try {
      const res = await studentSessionsApi.list();
      setItems(res.data);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal memuat sesi')); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(); }, []);

  return (
    <div>
      <h2>Sesi Latihan</h2>
      {error && <div className="admin-error">{error}</div>}
      <div className="admin-card">
        {loading ? <p className="admin-muted">Memuat...</p> : items.length === 0 ? (
          <p className="admin-muted">Belum ada sesi untuk keanggotaan aktifmu.</p>
        ) : (
          <table className="admin-table">
            <thead><tr><th>Tanggal</th><th>Ekskul</th><th>Venue</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>{items.map((s) => (
              <tr key={s.id}>
                <td>{s.session_date} {s.start_time || ''}</td>
                <td>{s.extracurricular?.name || s.extracurricular_id}</td>
                <td>{s.venue?.name || '-'}</td>
                <td><span className="badge">{sessionStatusLabel(s.status)}</span></td>
                <td><Link to={`/student/sessions/${s.id}`}>Detail</Link></td>
              </tr>
            ))}</tbody>
          </table>
        )}
      </div>
    </div>
  );
}

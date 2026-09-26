import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { coachSessionsApi } from '../api';
import { apiErrorMessage, sessionStatusLabel } from '../types';
import type { SessionItem } from '../types';

export function CoachSessionsPage() {
  const [items, setItems] = useState<SessionItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [status, setStatus] = useState('');

  const load = async () => {
    setLoading(true); setError(null);
    try {
      const res = await coachSessionsApi.list({ status: status || undefined });
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
        <div className="admin-row">
          <label className="admin-field">Status
            <select value={status} onChange={(e) => setStatus(e.target.value)}>
              <option value="">Semua</option>
              <option value="scheduled">Terjadwal</option>
              <option value="open">Dibuka</option>
              <option value="completed">Selesai</option>
              <option value="cancelled">Dibatalkan</option>
            </select>
          </label>
          <button className="admin-btn" type="button" onClick={load}>Cari</button>
        </div>
      </div>
      <div className="admin-card">
        {loading ? <p className="admin-muted">Memuat...</p> : items.length === 0 ? (
          <p className="admin-muted">Belum ada sesi untuk kamu.</p>
        ) : (
          <table className="admin-table">
            <thead><tr><th>Tanggal</th><th>Ekskul</th><th>Venue</th><th>Status</th><th>Check-in</th><th>Aksi</th></tr></thead>
            <tbody>{items.map((s) => (
              <tr key={s.id}>
                <td>{s.session_date} {s.start_time || ''}</td>
                <td>{s.extracurricular?.name || s.extracurricular_id}</td>
                <td>{s.venue?.name || '-'}</td>
                <td><span className="badge">{sessionStatusLabel(s.status)}</span></td>
                <td>{s.has_check_in ? 'Sudah' : 'Belum'}</td>
                <td><Link to={`/coach/sessions/${s.id}`}>Detail</Link></td>
              </tr>
            ))}</tbody>
          </table>
        )}
      </div>
    </div>
  );
}

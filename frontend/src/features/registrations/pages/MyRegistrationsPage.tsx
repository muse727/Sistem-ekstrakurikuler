import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { studentRegistrationsApi } from '../api';
import { apiErrorMessage, statusLabel } from '../types';
import type { Registration } from '../types';

export function MyRegistrationsPage() {
  const [items, setItems] = useState<Registration[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [cancelling, setCancelling] = useState<number | null>(null);

  const load = async () => {
    setLoading(true); setError(null);
    try {
      const res = await studentRegistrationsApi.list();
      setItems(res.data);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal memuat pendaftaran')); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(); }, []);

  const cancel = async (id: number) => {
    if (!window.confirm('Batalkan pendaftaran ini? Riwayat tetap tersimpan.')) return;
    setCancelling(id); setError(null);
    try {
      await studentRegistrationsApi.cancel(id);
      await load();
    } catch (e) { setError(apiErrorMessage(e, 'Gagal membatalkan')); }
    finally { setCancelling(null); }
  };

  return (
    <div>
      <h2>Pendaftaranku</h2>
      {error && <div className="admin-error">{error}</div>}
      <div className="admin-card">
        {loading ? <p className="admin-muted">Memuat...</p> : items.length === 0 ? (
          <p className="admin-muted">Belum ada pendaftaran.</p>
        ) : (
          <table className="admin-table">
            <thead><tr><th>ID</th><th>Ekstrakurikuler</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>{items.map((r) => (
              <tr key={r.id}>
                <td>{r.id}</td>
                <td>{r.extracurricular?.name || r.extracurricular_id}</td>
                <td><span className="badge">{statusLabel(r.status)}</span></td>
                <td>
                  <Link to={`/student/registrations/${r.id}`}>Detail</Link>
                  {['submitted', 'approved', 'active', 'draft'].includes(r.status) && (
                    <span> | <button className="admin-btn danger" disabled={cancelling === r.id} onClick={() => cancel(r.id)}>
                      {cancelling === r.id ? 'Membatalkan...' : 'Batalkan'}
                    </button></span>
                  )}
                </td>
              </tr>
            ))}</tbody>
          </table>
        )}
      </div>
    </div>
  );
}

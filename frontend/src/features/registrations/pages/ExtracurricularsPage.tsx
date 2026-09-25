import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { studentRegistrationsApi } from '../api';
import { apiErrorMessage } from '../types';
import type { Extracurricular } from '../types';

export function ExtracurricularsPage() {
  const [items, setItems] = useState<Extracurricular[]>([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState<number | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const load = async () => {
    setLoading(true); setError(null);
    try {
      const res = await studentRegistrationsApi.extracurriculars({ search: search || undefined });
      setItems(res.data);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal memuat ekstrakurikuler')); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(); }, []);

  const submit = async (id: number) => {
    if (!window.confirm('Daftar ke ekstrakurikuler ini?')) return;
    setSubmitting(id); setError(null); setNotice(null);
    try {
      await studentRegistrationsApi.submit(id);
      setNotice('Pendaftaran berhasil diajukan.');
    } catch (e) { setError(apiErrorMessage(e, 'Gagal mendaftar')); }
    finally { setSubmitting(null); }
  };

  return (
    <div>
      <h2>Ekstrakurikuler Tersedia</h2>
      {error && <div className="admin-error">{error}</div>}
      {notice && <div className="admin-card"><p className="admin-muted">{notice}</p></div>}
      <div className="admin-card">
        <div className="admin-row">
          <label className="admin-field">Cari<input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="nama / kode..." /></label>
          <button className="admin-btn" type="button" onClick={load}>Cari</button>
        </div>
      </div>
      <div className="admin-card">
        {loading ? <p className="admin-muted">Memuat...</p> : (
          <table className="admin-table">
            <thead><tr><th>Nama</th><th>Kode</th><th>Kuota</th><th>Aksi</th></tr></thead>
            <tbody>{items.map((r) => (
              <tr key={r.id}>
                <td><Link to={`/student/extracurriculars/${r.id}`}>{r.name}</Link></td>
                <td>{r.code}</td>
                <td>{r.quota ?? 'Tanpa batas'}</td>
                <td>
                  <button className="admin-btn primary" disabled={submitting === r.id} onClick={() => submit(r.id)}>
                    {submitting === r.id ? 'Mendaftar...' : 'Daftar'}
                  </button>
                </td>
              </tr>
            ))}</tbody>
          </table>
        )}
      </div>
    </div>
  );
}

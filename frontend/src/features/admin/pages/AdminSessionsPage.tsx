import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { adminSessionsApi } from '../../attendance/api';
import { apiErrorMessage, sessionStatusLabel } from '../../attendance/types';
import type { SessionItem } from '../../attendance/types';

export function AdminSessionsPage() {
  const [items, setItems] = useState<SessionItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [form, setForm] = useState({ extracurricular_id: '', venue_id: '', session_date: '', start_time: '15:30', end_time: '17:30', topic: '' });
  const [saving, setSaving] = useState(false);

  const load = async (p = page) => {
    setLoading(true); setError(null);
    try {
      const res = await adminSessionsApi.list({ page: p, search: search || undefined, status: status || undefined });
      setItems(res.data);
      setLastPage(res.meta.last_page);
      setPage(res.meta.current_page);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal memuat sesi')); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(1); }, []);

  const create = async () => {
    if (!form.extracurricular_id || !form.session_date || !form.start_time) { setError('Ekskul, tanggal, dan jam mulai wajib diisi.'); return; }
    setSaving(true); setError(null);
    try {
      await adminSessionsApi.create({
        extracurricular_id: Number(form.extracurricular_id),
        venue_id: form.venue_id ? Number(form.venue_id) : undefined,
        session_date: form.session_date,
        start_time: form.start_time,
        end_time: form.end_time || undefined,
        topic: form.topic || undefined,
      });
      setForm({ extracurricular_id: '', venue_id: '', session_date: '', start_time: '15:30', end_time: '17:30', topic: '' });
      await load(1);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal membuat sesi')); }
    finally { setSaving(false); }
  };

  return (
    <div>
      <h2>Sesi Latihan</h2>
      {error && <div className="admin-error">{error}</div>}
      <div className="admin-card">
        <h3>Buat Sesi</h3>
        <div className="admin-row">
          <label className="admin-field">ID Ekskul<input value={form.extracurricular_id} onChange={(e) => setForm({ ...form, extracurricular_id: e.target.value })} placeholder="contoh 1" /></label>
          <label className="admin-field">ID Venue (opsional)<input value={form.venue_id} onChange={(e) => setForm({ ...form, venue_id: e.target.value })} placeholder="contoh 1" /></label>
          <label className="admin-field">Tanggal<input type="date" value={form.session_date} onChange={(e) => setForm({ ...form, session_date: e.target.value })} /></label>
          <label className="admin-field">Mulai<input type="time" value={form.start_time} onChange={(e) => setForm({ ...form, start_time: e.target.value })} /></label>
          <label className="admin-field">Selesai<input type="time" value={form.end_time} onChange={(e) => setForm({ ...form, end_time: e.target.value })} /></label>
          <label className="admin-field">Topik<input value={form.topic} onChange={(e) => setForm({ ...form, topic: e.target.value })} placeholder="Latihan rutin" /></label>
          <button className="admin-btn primary" disabled={saving} onClick={create}>{saving ? 'Menyimpan...' : 'Buat'}</button>
        </div>
      </div>
      <div className="admin-card">
        <div className="admin-row">
          <label className="admin-field">Cari<input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="topik..." /></label>
          <label className="admin-field">Status
            <select value={status} onChange={(e) => setStatus(e.target.value)}>
              <option value="">Semua</option>
              <option value="scheduled">Terjadwal</option>
              <option value="open">Dibuka</option>
              <option value="completed">Selesai</option>
              <option value="cancelled">Dibatalkan</option>
            </select>
          </label>
          <button className="admin-btn" type="button" onClick={() => { setPage(1); load(1); }}>Cari</button>
        </div>
      </div>
      <div className="admin-card">
        {loading ? <p className="admin-muted">Memuat...</p> : items.length === 0 ? <p className="admin-muted">Belum ada sesi.</p> : (
          <>
            <table className="admin-table">
              <thead><tr><th>ID</th><th>Tanggal</th><th>Ekskul</th><th>Venue</th><th>Status</th><th>Aksi</th></tr></thead>
              <tbody>{items.map((s) => (
                <tr key={s.id}>
                  <td>{s.id}</td>
                  <td>{s.session_date} {s.start_time || ''}</td>
                  <td>{s.extracurricular?.name || s.extracurricular_id}</td>
                  <td>{s.venue?.name || '-'}</td>
                  <td><span className="badge">{sessionStatusLabel(s.status)}</span></td>
                  <td><Link to={`/admin/sessions/${s.id}`}>Detail</Link></td>
                </tr>
              ))}</tbody>
            </table>
            <div className="admin-row">
              <button className="admin-btn" disabled={page <= 1} onClick={() => load(page - 1)}>Prev</button>
              <span className="admin-muted">Hal {page} / {lastPage}</span>
              <button className="admin-btn" disabled={page >= lastPage} onClick={() => load(page + 1)}>Next</button>
            </div>
          </>
        )}
      </div>
    </div>
  );
}

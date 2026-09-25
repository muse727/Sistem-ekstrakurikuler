import { useEffect, useState } from 'react';
import { adminApi } from '../api';
import type { AcademicYear } from '../types';

export function AcademicYearsPage() {
  const [items, setItems] = useState<AcademicYear[]>([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState({ name: '', start_date: '', end_date: '', is_active: false });

  const load = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await adminApi.academicYears.list({ search: search || undefined });
      setItems(res.data);
    } catch (e: any) {
      setError(e.response?.data?.message || 'Gagal memuat tahun ajaran');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleCreate = async (ev: React.FormEvent) => {
    ev.preventDefault();
    setError(null);
    try {
      await adminApi.academicYears.create({
        name: form.name,
        start_date: form.start_date,
        end_date: form.end_date,
        is_active: form.is_active,
      });
      setForm({ name: '', start_date: '', end_date: '', is_active: false });
      await load();
    } catch (e: any) {
      setError(e.response?.data?.message || 'Gagal membuat tahun ajaran');
    }
  };

  const toggle = async (row: AcademicYear) => {
    try {
      if (row.is_active) {
        await adminApi.academicYears.deactivate(row.id);
      } else {
        await adminApi.academicYears.activate(row.id);
      }
      await load();
    } catch (e: any) {
      setError(e.response?.data?.message || 'Gagal mengubah status');
    }
  };

  return (
    <div>
      <h2>Tahun Ajaran</h2>
      {error && <div className="admin-error">{error}</div>}
      <div className="admin-card">
        <form onSubmit={handleCreate}>
          <div className="admin-row">
            <label className="admin-field">Nama<input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required placeholder="2026/2027" /></label>
            <label className="admin-field">Mulai<input type="date" value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })} required /></label>
            <label className="admin-field">Selesai<input type="date" value={form.end_date} onChange={(e) => setForm({ ...form, end_date: e.target.value })} required /></label>
            <label className="admin-field">Aktif<select value={form.is_active ? '1' : '0'} onChange={(e) => setForm({ ...form, is_active: e.target.value === '1' })}><option value="0">Tidak</option><option value="1">Ya</option></select></label>
            <button className="admin-btn primary" type="submit">Tambah</button>
          </div>
        </form>
      </div>
      <div className="admin-card">
        <div className="admin-row">
          <label className="admin-field">Cari<input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="nama..." /></label>
          <button className="admin-btn" type="button" onClick={load}>Cari</button>
        </div>
      </div>
      <div className="admin-card">
        {loading ? <p className="admin-muted">Memuat...</p> : (
          <table className="admin-table">
            <thead><tr><th>Nama</th><th>Periode</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
              {items.map((r) => (
                <tr key={r.id}>
                  <td>{r.name}</td>
                  <td>{r.start_date} – {r.end_date}</td>
                  <td><span className={`badge ${r.is_active ? 'active' : ''}`}>{r.is_active ? 'aktif' : 'nonaktif'}</span></td>
                  <td><button className="admin-btn" onClick={() => toggle(r)}>{r.is_active ? 'Nonaktifkan' : 'Aktifkan'}</button></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}

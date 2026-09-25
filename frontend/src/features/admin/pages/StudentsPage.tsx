import { useEffect, useState } from 'react';
import { adminApi } from '../api';
import type { Student } from '../types';

const empty: { student_number: string; nisn: string; name: string; gender: 'male' | 'female'; class_name: string; phone: string; email: string } = { student_number: '', nisn: '', name: '', gender: 'male', class_name: '', phone: '', email: '' };

export function StudentsPage() {
  const [items, setItems] = useState<Student[]>([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState(empty);
  const [editing, setEditing] = useState<number | null>(null);

  const load = async () => {
    setLoading(true); setError(null);
    try {
      const res = await adminApi.students.list({ search: search || undefined });
      setItems(res.data);
    } catch (e: any) { setError(e.response?.data?.message || 'Gagal memuat siswa'); }
    finally { setLoading(false); }
  };
  useEffect(() => { load(); }, []);

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault(); setError(null);
    try {
      const payload: any = { ...form, nisn: form.nisn || null };
      if (editing) await adminApi.students.update(editing, payload);
      else await adminApi.students.create(payload);
      setForm(empty); setEditing(null); await load();
    } catch (e: any) { setError(e.response?.data?.message || JSON.stringify(e.response?.data?.errors || 'Gagal menyimpan')); }
  };

  return (
    <div>
      <h2>Siswa</h2>
      {error && <div className="admin-error">{error}</div>}
      <div className="admin-card">
        <form onSubmit={submit}>
          <div className="admin-row">
            <label className="admin-field">NIS<input value={form.student_number} onChange={(e) => setForm({ ...form, student_number: e.target.value })} required /></label>
            <label className="admin-field">NISN<input value={form.nisn} onChange={(e) => setForm({ ...form, nisn: e.target.value })} /></label>
            <label className="admin-field">Nama<input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required /></label>
            <label className="admin-field">Gender<select value={form.gender} onChange={(e) => setForm({ ...form, gender: e.target.value as any })}><option value="male">male</option><option value="female">female</option></select></label>
            <label className="admin-field">Kelas<input value={form.class_name} onChange={(e) => setForm({ ...form, class_name: e.target.value })} /></label>
            <label className="admin-field">Phone<input value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} /></label>
            <label className="admin-field">Email<input value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></label>
            <button className="admin-btn primary" type="submit">{editing ? 'Update' : 'Tambah'}</button>
            {editing && <button className="admin-btn" type="button" onClick={() => { setEditing(null); setForm(empty); }}>Batal</button>}
          </div>
        </form>
      </div>
      <div className="admin-card">
        <div className="admin-row">
          <label className="admin-field">Cari<input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="nama / NIS / kelas..." /></label>
          <button className="admin-btn" type="button" onClick={load}>Cari</button>
        </div>
      </div>
      <div className="admin-card">
        {loading ? <p className="admin-muted">Memuat...</p> : (
          <table className="admin-table">
            <thead><tr><th>NIS</th><th>Nama</th><th>Kelas</th><th>Aksi</th></tr></thead>
            <tbody>{items.map((r) => (
              <tr key={r.id}><td>{r.student_number}</td><td>{r.name}</td><td>{r.class_name || '-'}</td>
                <td><button className="admin-btn" onClick={() => { setEditing(r.id); setForm({ student_number: r.student_number, nisn: r.nisn || '', name: r.name, gender: r.gender, class_name: r.class_name || '', phone: r.phone || '', email: r.email || '' }); }}>Edit</button></td></tr>
            ))}</tbody>
          </table>
        )}
      </div>
    </div>
  );
}

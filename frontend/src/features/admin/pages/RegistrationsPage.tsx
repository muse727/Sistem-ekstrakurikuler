import { useEffect, useState } from 'react';
import { adminRegistrationsApi } from '../../registrations/api';
import { apiErrorMessage, statusLabel } from '../../registrations/types';
import type { Registration } from '../../registrations/types';

const STATUSES = ['', 'submitted', 'approved', 'rejected', 'active', 'cancelled'];

export function AdminRegistrationsPage() {
  const [items, setItems] = useState<Registration[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [detail, setDetail] = useState<Registration | null>(null);
  const [rejectReason, setRejectReason] = useState('');
  const [busy, setBusy] = useState<number | null>(null);

  const load = async (p = page) => {
    setLoading(true); setError(null);
    try {
      const res = await adminRegistrationsApi.list({
        page: p,
        search: search || undefined,
        status: status || undefined,
      });
      setItems(res.data);
      setLastPage(res.meta.last_page);
      setPage(res.meta.current_page);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal memuat registrasi')); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(1); }, []);

  const refresh = async () => {
    await load(page);
    if (detail) {
      try {
        const r = await adminRegistrationsApi.get(detail.id);
        setDetail(r.data);
      } catch { /* keep old detail */ }
    }
  };

  const act = async (id: number, fn: () => Promise<unknown>, confirmText: string) => {
    if (!window.confirm(confirmText)) return;
    setBusy(id); setError(null);
    try {
      await fn();
      await refresh();
    } catch (e) { setError(apiErrorMessage(e, 'Aksi gagal')); }
    finally { setBusy(null); }
  };

  const reject = async (id: number) => {
    if (!rejectReason.trim() || rejectReason.trim().length < 3) {
      setError('Alasan penolakan wajib diisi (min 3 karakter).');
      return;
    }
    await act(id, () => adminRegistrationsApi.reject(id, rejectReason.trim()), `Tolak pendaftaran #${id}?`);
    setRejectReason('');
  };

  return (
    <div>
      <h2>Registrasi</h2>
      {error && <div className="admin-error">{error}</div>}
      <div className="admin-card">
        <div className="admin-row">
          <label className="admin-field">Cari<input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="nama / NIS / ekskul..." /></label>
          <label className="admin-field">Status
            <select value={status} onChange={(e) => setStatus(e.target.value)}>
              {STATUSES.map((s) => (
                <option key={s} value={s}>{s === '' ? 'Semua' : statusLabel(s as never)}</option>
              ))}
            </select>
          </label>
          <button className="admin-btn" type="button" onClick={() => { setPage(1); load(1); }}>Cari</button>
        </div>
      </div>
      <div className="admin-card">
        {loading ? <p className="admin-muted">Memuat...</p> : (
          <>
            <table className="admin-table">
              <thead><tr><th>ID</th><th>Siswa</th><th>Ekstrakurikuler</th><th>Status</th><th>Aksi</th></tr></thead>
              <tbody>{items.map((r) => (
                <tr key={r.id}>
                  <td>{r.id}</td>
                  <td>{r.student?.name || r.student_id}</td>
                  <td>{r.extracurricular?.name || r.extracurricular_id}</td>
                  <td><span className="badge">{statusLabel(r.status)}</span></td>
                  <td><button className="admin-btn" onClick={async () => {
                    try {
                      const d = await adminRegistrationsApi.get(r.id);
                      setDetail(d.data);
                    } catch (e) { setError(apiErrorMessage(e, 'Gagal memuat detail')); }
                  }}>Detail</button></td>
                </tr>
              ))}</tbody>
            </table>
            <div className="admin-row" style={{ marginTop: '.6rem' }}>
              <button className="admin-btn" disabled={page <= 1} onClick={() => load(page - 1)}>Prev</button>
              <span className="admin-muted">Hal {page} / {lastPage}</span>
              <button className="admin-btn" disabled={page >= lastPage} onClick={() => load(page + 1)}>Next</button>
            </div>
          </>
        )}
      </div>
      {detail && (
        <div className="admin-card">
          <h3>Detail #{detail.id} — <span className="badge">{statusLabel(detail.status)}</span></h3>
          <p>Siswa: {detail.student?.name || detail.student_id}</p>
          <p>Ekstrakurikuler: {detail.extracurricular?.name || detail.extracurricular_id}</p>
          <p>Tahun ajaran: {detail.academic_year?.name || detail.academic_year_id}</p>
          <p>Diajukan: {detail.submitted_at || '-'}</p>
          <p>Disetujui: {detail.approved_at || '-'} {detail.approved_by ? `(by ${detail.approved_by})` : ''}</p>
          <p>Ditolak: {detail.rejected_at || '-'} {detail.rejected_by ? `(by ${detail.rejected_by})` : ''}</p>
          {detail.rejection_reason && <p>Alasan: {detail.rejection_reason}</p>}
          <p>Dibatalkan: {detail.cancelled_at || '-'} {detail.cancelled_by ? `(by ${detail.cancelled_by})` : ''}</p>
          <div className="admin-row">
            <button className="admin-btn primary" disabled={busy === detail.id} onClick={() => act(detail.id, () => adminRegistrationsApi.approve(detail.id), `Setujui #${detail.id}?`)}>Approve</button>
            <button className="admin-btn primary" disabled={busy === detail.id} onClick={() => act(detail.id, () => adminRegistrationsApi.activate(detail.id), `Aktifkan #${detail.id}?`)}>Activate</button>
            <button className="admin-btn danger" disabled={busy === detail.id} onClick={() => act(detail.id, () => adminRegistrationsApi.cancel(detail.id), `Batalkan #${detail.id}? Riwayat tetap tersimpan.`)}>Cancel</button>
            <button className="admin-btn" onClick={() => setDetail(null)}>Tutup</button>
          </div>
          <div className="admin-row" style={{ marginTop: '.6rem' }}>
            <label className="admin-field">Alasan penolakan (wajib)<input value={rejectReason} onChange={(e) => setRejectReason(e.target.value)} placeholder="min 3 karakter" /></label>
            <button className="admin-btn danger" disabled={busy === detail.id} onClick={() => reject(detail.id)}>Reject</button>
          </div>
        </div>
      )}
    </div>
  );
}

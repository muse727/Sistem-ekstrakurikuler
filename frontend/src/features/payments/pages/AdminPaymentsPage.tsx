import { useEffect, useState } from 'react';
import { adminPaymentsApi } from '../api';
import { apiErrorMessage, formatRupiah, invoiceStatusLabel } from '../types';
import type { Invoice } from '../types';

const STATUSES = ['', 'unpaid', 'pending_verification', 'paid', 'rejected', 'cancelled'];

export function AdminPaymentsPage() {
  const [items, setItems] = useState<Invoice[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  const load = async (p = page) => {
    setLoading(true); setError(null);
    try {
      const res = await adminPaymentsApi.list({ page: p, search: search || undefined, status: status || undefined });
      setItems(res.data);
      setLastPage(res.meta.last_page);
      setPage(res.meta.current_page);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal memuat pembayaran')); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(1); }, []);

  return (
    <div>
      <h2>Pembayaran</h2>
      {error && <div className="admin-error">{error}</div>}
      <div className="admin-card">
        <div className="admin-row">
          <label className="admin-field">Cari<input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="invoice / nama siswa..." /></label>
          <label className="admin-field">Status
            <select value={status} onChange={(e) => setStatus(e.target.value)}>
              {STATUSES.map((s) => <option key={s} value={s}>{s === '' ? 'Semua' : invoiceStatusLabel(s as never)}</option>)}
            </select>
          </label>
          <button className="admin-btn" type="button" onClick={() => { setPage(1); load(1); }}>Cari</button>
        </div>
      </div>
      <div className="admin-card">
        {loading ? <p className="admin-muted">Memuat...</p> : items.length === 0 ? <p className="admin-muted">Belum ada tagihan.</p> : (
          <>
            <table className="admin-table">
              <thead><tr><th>Invoice</th><th>Siswa</th><th>Ekskul</th><th>Nominal</th><th>Status</th><th>Aksi</th></tr></thead>
              <tbody>{items.map((inv) => (
                <tr key={inv.id}>
                  <td>{inv.invoice_number}</td>
                  <td>{inv.registration?.student?.name || '-'}</td>
                  <td>{inv.registration?.extracurricular?.name || '-'}</td>
                  <td>{formatRupiah(inv.amount)}</td>
                  <td><span className="badge">{invoiceStatusLabel(inv.status)}</span></td>
                  <td><a href={`/admin/payments/${inv.id}`}>Detail</a></td>
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

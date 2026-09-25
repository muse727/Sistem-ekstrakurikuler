import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { studentPaymentsApi } from '../api';
import { apiErrorMessage, formatRupiah, invoiceStatusLabel } from '../types';
import type { Invoice } from '../types';

export function MyPaymentsPage() {
  const [items, setItems] = useState<Invoice[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = async () => {
    setLoading(true); setError(null);
    try {
      const res = await studentPaymentsApi.list();
      setItems(res.data);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal memuat tagihan')); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(); }, []);

  return (
    <div>
      <h2>Pembayaranku</h2>
      {error && <div className="admin-error">{error}</div>}
      <div className="admin-card">
        {loading ? <p className="admin-muted">Memuat...</p> : items.length === 0 ? (
          <p className="admin-muted">Belum ada tagihan. Tagihan muncul setelah pendaftaran disetujui admin.</p>
        ) : (
          <table className="admin-table">
            <thead><tr><th>Invoice</th><th>Ekskul</th><th>Nominal</th><th>Status</th><th>Jatuh Tempo</th><th>Aksi</th></tr></thead>
            <tbody>{items.map((inv) => (
              <tr key={inv.id}>
                <td>{inv.invoice_number}</td>
                <td>{inv.registration?.extracurricular?.name || '-'}</td>
                <td>{formatRupiah(inv.amount)}</td>
                <td><span className="badge">{invoiceStatusLabel(inv.status)}</span></td>
                <td>{inv.due_at ? new Date(inv.due_at).toLocaleDateString('id-ID') : '-'}</td>
                <td><Link to={`/student/payments/${inv.id}`}>Detail</Link></td>
              </tr>
            ))}</tbody>
          </table>
        )}
      </div>
    </div>
  );
}

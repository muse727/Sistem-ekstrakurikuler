import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { adminPaymentsApi, downloadProofFile } from '../api';
import { apiErrorMessage, formatRupiah, invoiceStatusLabel } from '../types';
import type { Invoice } from '../types';

export function AdminPaymentDetailPage() {
  const { id } = useParams();
  const [inv, setInv] = useState<Invoice | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);
  const [downloading, setDownloading] = useState<number | null>(null);

  const load = async () => {
    if (!id) return;
    setLoading(true); setError(null);
    try {
      const res = await adminPaymentsApi.get(Number(id));
      setInv(res.data);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal memuat tagihan')); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(); }, [id]);

  const verify = async () => {
    if (!inv || !window.confirm(`Setujui bukti pembayaran ${inv.invoice_number}? Registrasi akan aktif otomatis.`)) return;
    setBusy(true); setError(null); setSuccess(null);
    try {
      const res = await adminPaymentsApi.verify(inv.id);
      setInv(res.data);
      setSuccess('Pembayaran disetujui. Invoice LUNAS, registrasi aktif.');
    } catch (e) { setError(apiErrorMessage(e, 'Gagal menyetujui')); }
    finally { setBusy(false); }
  };

  const reject = async () => {
    if (!inv) return;
    if (reason.trim().length < 10) { setError('Alasan penolakan wajib diisi (min 10 karakter).'); return; }
    if (!window.confirm(`Tolak bukti pembayaran ${inv.invoice_number}?`)) return;
    setBusy(true); setError(null); setSuccess(null);
    try {
      const res = await adminPaymentsApi.reject(inv.id, reason.trim());
      setInv(res.data);
      setReason('');
      setSuccess('Bukti ditolak. Siswa bisa kirim ulang.');
    } catch (e) { setError(apiErrorMessage(e, 'Gagal menolak')); }
    finally { setBusy(false); }
  };

  const download = async (proofId: number, filename: string) => {
    if (!inv) return;
    setDownloading(proofId);
    try {
      await downloadProofFile(adminPaymentsApi.fileUrl(inv.id, proofId), filename);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal mengunduh')); }
    finally { setDownloading(null); }
  };

  if (loading) return <div><h2>Detail Pembayaran</h2><p className="admin-muted">Memuat...</p></div>;
  if (!inv) return <div><h2>Detail Pembayaran</h2>{error && <div className="admin-error">{error}</div>}<Link to="/admin/payments">Kembali</Link></div>;

  const proofs = inv.proofs && inv.proofs.length > 0 ? inv.proofs : (inv.latest_proof ? [inv.latest_proof] : []);
  const canAct = inv.status === 'pending_verification';

  return (
    <div>
      <h2>Invoice {inv.invoice_number}</h2>
      {error && <div className="admin-error">{error}</div>}
      {success && <div className="admin-success">{success}</div>}
      <div className="admin-card">
        <p>Siswa: {inv.registration?.student?.name || '-'} ({inv.registration?.student?.student_number || '-'})</p>
        <p>Ekskul: {inv.registration?.extracurricular?.name || '-'} ({inv.registration?.extracurricular?.code || '-'})</p>
        <p>Tahun ajaran: {inv.registration?.academic_year?.name || '-'}</p>
        <p>Status registrasi: {inv.registration?.status || '-'}</p>
        <p>Nominal: <strong>{formatRupiah(inv.amount)}</strong></p>
        <p>Status: <span className="badge">{invoiceStatusLabel(inv.status)}</span></p>
        <p>Diterbitkan: {inv.issued_at ? new Date(inv.issued_at).toLocaleString('id-ID') : '-'}</p>
        <p>Jatuh tempo: {inv.due_at ? new Date(inv.due_at).toLocaleDateString('id-ID') : '-'}</p>
        <p>Lunas: {inv.paid_at ? new Date(inv.paid_at).toLocaleString('id-ID') : '-'}</p>
      </div>

      <div className="admin-card">
        <h3>Verifikasi</h3>
        {!canAct ? (
          <p className="admin-muted">Tidak ada aksi: status {invoiceStatusLabel(inv.status)}.</p>
        ) : (
          <>
            <div className="admin-row">
              <button className="admin-btn primary" disabled={busy} onClick={verify}>{busy ? 'Memproses...' : 'Setujui (Approve)'}</button>
            </div>
            <div className="admin-row" style={{ marginTop: '.6rem' }}>
              <label className="admin-field">Alasan penolakan (wajib, min 10)<input value={reason} onChange={(e) => setReason(e.target.value)} placeholder="min 10 karakter" /></label>
              <button className="admin-btn danger" disabled={busy} onClick={reject}>Tolak (Reject)</button>
            </div>
          </>
        )}
      </div>

      <div className="admin-card">
        <h3>Riwayat Bukti ({proofs.length})</h3>
        {proofs.length === 0 ? <p className="admin-muted">Belum ada bukti.</p> : (
          <table className="admin-table">
            <thead><tr><th>ID</th><th>File</th><th>Tipe</th><th>Ukuran</th><th>Verifikasi</th><th>Alasan</th><th>Aksi</th></tr></thead>
            <tbody>{proofs.map((p) => (
              <tr key={p.id}>
                <td>{p.id}</td>
                <td>{p.original_filename}</td>
                <td>{p.mime_type}</td>
                <td>{Math.round(p.file_size / 1024)} KB</td>
                <td>{p.latest_verification?.status || 'menunggu'}</td>
                <td>{p.latest_verification?.rejection_reason || '-'}</td>
                <td><button className="admin-btn" disabled={downloading === p.id} onClick={() => download(p.id, p.original_filename)}>{downloading === p.id ? 'Mengunduh...' : 'Preview/Unduh'}</button></td>
              </tr>
            ))}</tbody>
          </table>
        )}
        <p><Link to="/admin/payments">Kembali ke daftar</Link></p>
      </div>
    </div>
  );
}

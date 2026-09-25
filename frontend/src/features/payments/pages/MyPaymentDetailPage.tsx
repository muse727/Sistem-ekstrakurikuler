import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { studentPaymentsApi, downloadProofFile } from '../api';
import { apiErrorMessage, formatRupiah, invoiceStatusLabel } from '../types';
import type { Invoice } from '../types';

const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];
const MAX_BYTES = 5 * 1024 * 1024;

export function MyPaymentDetailPage() {
  const { id } = useParams();
  const [inv, setInv] = useState<Invoice | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [file, setFile] = useState<File | null>(null);
  const [uploading, setUploading] = useState(false);
  const [downloading, setDownloading] = useState<number | null>(null);

  const load = async () => {
    if (!id) return;
    setLoading(true); setError(null);
    try {
      const res = await studentPaymentsApi.get(Number(id));
      setInv(res.data);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal memuat tagihan')); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(); }, [id]);

  const onFileChange = (f: File | null) => {
    setError(null); setSuccess(null);
    if (!f) { setFile(null); return; }
    if (!ALLOWED_TYPES.includes(f.type)) { setError('Tipe file tidak valid. Hanya JPG, PNG, atau PDF.'); return; }
    if (f.size > MAX_BYTES) { setError('File terlalu besar. Maksimal 5MB.'); return; }
    setFile(f);
  };

  const upload = async () => {
    if (!file || !inv) return;
    if (['paid', 'cancelled'].includes(inv.status)) { setError('Tagihan sudah lunas/dibatalkan, tidak bisa upload.'); return; }
    setUploading(true); setError(null); setSuccess(null);
    try {
      const res = await studentPaymentsApi.uploadProof(inv.id, file);
      setInv(res.data);
      setFile(null);
      setSuccess('Bukti pembayaran berhasil diupload. Status: menunggu verifikasi.');
    } catch (e) { setError(apiErrorMessage(e, 'Gagal upload bukti')); }
    finally { setUploading(false); }
  };

  const download = async (proofId: number, filename: string) => {
    if (!inv) return;
    setDownloading(proofId); setError(null);
    try {
      await downloadProofFile(studentPaymentsApi.fileUrl(inv.id, proofId), filename);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal mengunduh file')); }
    finally { setDownloading(null); }
  };

  if (loading) return <div><h2>Detail Pembayaran</h2><p className="admin-muted">Memuat...</p></div>;
  if (error && !inv) return <div><h2>Detail Pembayaran</h2><div className="admin-error">{error}</div><Link to="/student/payments">Kembali</Link></div>;
  if (!inv) return <div><h2>Detail Pembayaran</h2><p className="admin-muted">Tidak ditemukan.</p></div>;

  const canUpload = !['paid', 'cancelled'].includes(inv.status);
  const proofs = inv.proofs && inv.proofs.length > 0 ? inv.proofs : (inv.latest_proof ? [inv.latest_proof] : []);

  return (
    <div>
      <h2>Invoice {inv.invoice_number}</h2>
      {error && <div className="admin-error">{error}</div>}
      {success && <div className="admin-success">{success}</div>}
      <div className="admin-card">
        <p>Ekskul: {inv.registration?.extracurricular?.name || '-'} ({inv.registration?.extracurricular?.code || '-'})</p>
        <p>Nominal: <strong>{formatRupiah(inv.amount)}</strong></p>
        <p>Status: <span className="badge">{invoiceStatusLabel(inv.status)}</span></p>
        <p>Diterbitkan: {inv.issued_at ? new Date(inv.issued_at).toLocaleString('id-ID') : '-'}</p>
        <p>Jatuh tempo: {inv.due_at ? new Date(inv.due_at).toLocaleDateString('id-ID') : '-'}</p>
        <p>Lunas: {inv.paid_at ? new Date(inv.paid_at).toLocaleString('id-ID') : '-'}</p>
        <p>Status registrasi: {inv.registration?.status || '-'}</p>
        {inv.rejection_reason && <p>Alasan penolakan: {inv.rejection_reason}</p>}
        {inv.latest_verification?.rejection_reason && <p>Alasan penolakan terakhir: {inv.latest_verification.rejection_reason}</p>}
      </div>

      <div className="admin-card">
        <h3>Upload / Kirim Ulang Bukti</h3>
        {!canUpload ? (
          <p className="admin-muted">Upload diblokir: tagihan {invoiceStatusLabel(inv.status)}.</p>
        ) : (
          <div className="admin-row">
            <label className="admin-field">File (JPG/PNG/PDF, maks 5MB)
              <input type="file" accept=".jpg,.jpeg,.png,.pdf" onChange={(e) => onFileChange(e.target.files?.[0] || null)} />
            </label>
            <button className="admin-btn primary" disabled={!file || uploading} onClick={upload}>
              {uploading ? 'Mengupload...' : proofs.length > 0 ? 'Kirim Ulang' : 'Upload'}
            </button>
          </div>
        )}
      </div>

      <div className="admin-card">
        <h3>Riwayat Bukti ({proofs.length})</h3>
        {proofs.length === 0 ? <p className="admin-muted">Belum ada bukti.</p> : (
          <table className="admin-table">
            <thead><tr><th>ID</th><th>File</th><th>Status</th><th>Alasan</th><th>Aksi</th></tr></thead>
            <tbody>{proofs.map((p) => (
              <tr key={p.id}>
                <td>{p.id}</td>
                <td>{p.original_filename}</td>
                <td>{p.latest_verification?.status || 'menunggu verifikasi'}</td>
                <td>{p.latest_verification?.rejection_reason || '-'}</td>
                <td><button className="admin-btn" disabled={downloading === p.id} onClick={() => download(p.id, p.original_filename)}>
                  {downloading === p.id ? 'Mengunduh...' : 'Unduh'}
                </button></td>
              </tr>
            ))}</tbody>
          </table>
        )}
        <p><Link to="/student/payments">Kembali ke daftar</Link></p>
      </div>
    </div>
  );
}

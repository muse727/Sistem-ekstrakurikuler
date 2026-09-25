import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { studentRegistrationsApi } from '../api';
import { apiErrorMessage } from '../types';
import type { Extracurricular } from '../types';

export function ExtracurricularDetailPage() {
  const { id } = useParams<{ id: string }>();
  const [item, setItem] = useState<Extracurricular | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (!id) return;
    setLoading(true); setError(null);
    studentRegistrationsApi.extracurricularDetail(Number(id))
      .then((r) => setItem(r.data))
      .catch((e) => setError(apiErrorMessage(e, 'Gagal memuat detail')))
      .finally(() => setLoading(false));
  }, [id]);

  const submit = async () => {
    if (!item) return;
    if (!window.confirm(`Daftar ke ${item.name}?`)) return;
    setSubmitting(true); setError(null); setNotice(null);
    try {
      await studentRegistrationsApi.submit(item.id);
      setNotice('Pendaftaran berhasil diajukan. Cek di Pendaftaranku.');
    } catch (e) { setError(apiErrorMessage(e, 'Gagal mendaftar')); }
    finally { setSubmitting(false); }
  };

  if (loading) return <p className="admin-muted">Memuat...</p>;

  return (
    <div>
      <p><Link to="/student/extracurriculars">&larr; Kembali</Link></p>
      <h2>Detail Ekstrakurikuler</h2>
      {error && <div className="admin-error">{error}</div>}
      {notice && <div className="admin-card"><p className="admin-muted">{notice}</p></div>}
      {!item ? <p className="admin-muted">Data tidak ditemukan.</p> : (
        <div className="admin-card">
          <h3>{item.name} ({item.code})</h3>
          <p className="admin-muted">{item.description || '-'}</p>
          <p>Tahun ajaran: {item.academic_year?.name || item.academic_year_id}</p>
          <p>Kuota: {item.quota ?? 'Tanpa batas'}</p>
          <p>Biaya: {item.fee_amount}</p>
          <button className="admin-btn primary" disabled={submitting} onClick={submit}>
            {submitting ? 'Mendaftar...' : 'Daftar Sekarang'}
          </button>
        </div>
      )}
    </div>
  );
}

import { useEffect, useState } from 'react';
import { reportingApi, apiErrorMessage, formatRupiah, type ReportParams } from '../api';
import { adminApi } from '../../admin/api';
import type { AcademicYear, Extracurricular } from '../../admin/types';

interface AdminDash {
  academic_year?: { id?: number; name?: string; total_years?: number; active_years?: number };
  students?: { total?: number; active?: number };
  coaches?: { total?: number; active?: number };
  extracurriculars?: { total?: number; active?: number };
  membership?: { total?: number; submitted?: number; approved?: number; active?: number; rejected?: number; cancelled?: number };
  payments?: { billed?: number; paid?: number; outstanding?: number; unpaid?: number; pending_verification?: number; paid_count?: number; rejected?: number };
  attendance?: { attendance_percentage?: number; records?: { total?: number; hadir?: number; izin?: number; sakit?: number; alpa?: number }; sessions?: { scheduled?: number; open?: number; completed?: number; cancelled?: number } };
  evaluations?: { total?: number; draft?: number; published?: number; avg_final?: number | null; completion_percentage?: number };
}

export function AdminDashboardPage() {
  const [data, setData] = useState<AdminDash | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [years, setYears] = useState<AcademicYear[]>([]);
  const [ekskuls, setEkskuls] = useState<Extracurricular[]>([]);
  const [f, setF] = useState<ReportParams>({});

  const loadMeta = async () => {
    try {
      const y = await adminApi.academicYears.list({ per_page: 100 });
      setYears(y.data);
      const e = await adminApi.extracurriculars.list({ per_page: 100 });
      setEkskuls(e.data);
    } catch { /* ignore */ }
  };

  const load = async () => {
    setLoading(true); setError(null);
    try {
      const res = await reportingApi.adminDashboard(f);
      setData(res.data as AdminDash);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal memuat dashboard')); }
    finally { setLoading(false); }
  };

  useEffect(() => { loadMeta(); load(); }, []);

  return (
    <div>
      <h2>Dashboard Admin</h2>
      {error && <div className="admin-error">{error}</div>}
      <div className="admin-card">
        <div className="admin-row">
          <label className="admin-field">Tahun Ajaran
            <select value={f.academic_year_id || ''} onChange={(e) => setF({ ...f, academic_year_id: e.target.value ? Number(e.target.value) : undefined })}>
              <option value="">Aktif (default)</option>
              {years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
            </select>
          </label>
          <label className="admin-field">Ekstrakurikuler
            <select value={f.extracurricular_id || ''} onChange={(e) => setF({ ...f, extracurricular_id: e.target.value ? Number(e.target.value) : undefined })}>
              <option value="">Semua</option>
              {ekskuls.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}
            </select>
          </label>
          <label className="admin-field">Dari<input type="date" value={(f.date_from as string) || ''} onChange={(e) => setF({ ...f, date_from: e.target.value || undefined })} /></label>
          <label className="admin-field">Sampai<input type="date" value={(f.date_to as string) || ''} onChange={(e) => setF({ ...f, date_to: e.target.value || undefined })} /></label>
          <button className="admin-btn primary" type="button" onClick={load}>Terapkan</button>
        </div>
      </div>
      {loading ? <p className="admin-muted">Memuat...</p> : !data ? <p className="admin-muted">Belum ada data.</p> : (
        <>
          <div className="admin-card">
            <div className="admin-row">
              <div className="admin-field"><strong>Siswa Aktif</strong><div>{data.students?.active ?? 0} / {data.students?.total ?? 0}</div></div>
              <div className="admin-field"><strong>Ekskul Aktif</strong><div>{data.extracurriculars?.active ?? 0} / {data.extracurriculars?.total ?? 0}</div></div>
              <div className="admin-field"><strong>Anggota Aktif</strong><div>{data.membership?.active ?? 0}</div></div>
              <div className="admin-field"><strong>Belum Bayar</strong><div>{data.payments?.unpaid ?? 0} · {formatRupiah(data.payments?.outstanding ?? 0)}</div></div>
              <div className="admin-field"><strong>Lunas</strong><div>{formatRupiah(data.payments?.paid ?? 0)}</div></div>
              <div className="admin-field"><strong>Kehadiran</strong><div>{data.attendance?.attendance_percentage ?? 0}%</div></div>
              <div className="admin-field"><strong>Eval Published</strong><div>{data.evaluations?.published ?? 0}</div></div>
            </div>
          </div>
          <div className="admin-card">
            <h3>Keanggotaan</h3>
            <p className="admin-muted">Total {data.membership?.total ?? 0} · Submitted {data.membership?.submitted ?? 0} · Approved {data.membership?.approved ?? 0} · Aktif {data.membership?.active ?? 0} · Ditolak {data.membership?.rejected ?? 0} · Batal {data.membership?.cancelled ?? 0}</p>
          </div>
          <div className="admin-card">
            <h3>Pembayaran</h3>
            <p className="admin-muted">Ditagih {formatRupiah(data.payments?.billed ?? 0)} · Lunas {formatRupiah(data.payments?.paid ?? 0)} · Outstanding {formatRupiah(data.payments?.outstanding ?? 0)} · Pending {data.payments?.pending_verification ?? 0} · Ditolak {data.payments?.rejected ?? 0}</p>
          </div>
          <div className="admin-card">
            <h3>Kehadiran</h3>
            <p className="admin-muted">Hadir {data.attendance?.records?.hadir ?? 0} · Izin {data.attendance?.records?.izin ?? 0} · Sakit {data.attendance?.records?.sakit ?? 0} · Alpa {data.attendance?.records?.alpa ?? 0} · Sesi Sched {data.attendance?.sessions?.scheduled ?? 0} / Open {data.attendance?.sessions?.open ?? 0} / Done {data.attendance?.sessions?.completed ?? 0} / Cancel {data.attendance?.sessions?.cancelled ?? 0}</p>
          </div>
          <div className="admin-card">
            <h3>Evaluasi</h3>
            <p className="admin-muted">Total {data.evaluations?.total ?? 0} · Draft {data.evaluations?.draft ?? 0} · Published {data.evaluations?.published ?? 0} · Rata-rata {data.evaluations?.avg_final ?? '-'} · Completion {data.evaluations?.completion_percentage ?? 0}%</p>
          </div>
        </>
      )}
    </div>
  );
}

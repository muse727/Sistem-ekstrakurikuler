import { useEffect, useState } from 'react';
import { reportingApi, apiErrorMessage, formatRupiah, type ReportParams } from '../api';

interface CoachDash {
  assigned?: { id: number; name: string; code?: string }[];
  assigned_count?: number;
  membership?: { total?: number; active?: number; submitted?: number; approved?: number };
  sessions?: { counts?: Record<string, number>; upcoming?: Record<string, unknown>[]; recent?: Record<string, unknown>[] };
  attendance?: { attendance_percentage?: number; records?: Record<string, number> };
  evaluations?: { total?: number; published?: number; avg_final?: number | null; completion_percentage?: number };
}

export function CoachDashboardPage() {
  const [data, setData] = useState<CoachDash | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [f, setF] = useState<ReportParams>({});

  const load = async () => {
    setLoading(true); setError(null);
    try {
      const res = await reportingApi.coachDashboard(f);
      setData(res.data as CoachDash);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal memuat dashboard pelatih')); }
    finally { setLoading(false); }
  };
  useEffect(() => { load(); }, []);

  return (
    <div>
      <h2>Dashboard Pelatih</h2>
      {error && <div className="admin-error">{error}</div>}
      <div className="admin-card">
        <div className="admin-row">
          <label className="admin-field">Ekstrakurikuler (ID, hanya yang ditugaskan)
            <input type="number" value={f.extracurricular_id || ''} onChange={(e) => setF({ ...f, extracurricular_id: e.target.value ? Number(e.target.value) : undefined })} placeholder="opsional" />
          </label>
          <button className="admin-btn primary" type="button" onClick={load}>Terapkan</button>
        </div>
      </div>
      {loading ? <p className="admin-muted">Memuat...</p> : !data ? <p className="admin-muted">Belum ada data.</p> : (
        <>
          <div className="admin-card">
            <div className="admin-row">
              <div className="admin-field"><strong>Ekskul Ditugaskan</strong><div>{data.assigned_count ?? 0}</div></div>
              <div className="admin-field"><strong>Anggota Aktif</strong><div>{data.membership?.active ?? 0}</div></div>
              <div className="admin-field"><strong>Kehadiran</strong><div>{data.attendance?.attendance_percentage ?? 0}%</div></div>
              <div className="admin-field"><strong>Eval Published</strong><div>{data.evaluations?.published ?? 0}</div></div>
            </div>
          </div>
          <div className="admin-card">
            <h3>Ekskul Saya</h3>
            {(data.assigned || []).length === 0 ? <p className="admin-muted">Belum ada penugasan.</p> : (
              <ul>{(data.assigned || []).map((a) => <li key={a.id}>{a.name} ({a.code || '-'})</li>)}</ul>
            )}
          </div>
          <div className="admin-card">
            <h3>Sesi</h3>
            <p className="admin-muted">Total {data.sessions?.counts?.total ?? 0} · Selesai {data.sessions?.counts?.completed ?? 0} · Terjadwal {data.sessions?.counts?.scheduled ?? 0}</p>
          </div>
          <div className="admin-card">
            <h3>Evaluasi</h3>
            <p className="admin-muted">Total {data.evaluations?.total ?? 0} · Published {data.evaluations?.published ?? 0} · Completion {data.evaluations?.completion_percentage ?? 0}%</p>
          </div>
          <div className="admin-card">
            <h3>Pembayaran (ringkas)</h3>
            <p className="admin-muted">{formatRupiah(0)}</p>
          </div>
        </>
      )}
    </div>
  );
}

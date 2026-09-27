import { useEffect, useState } from 'react';
import { reportingApi, apiErrorMessage, formatRupiah } from '../api';

interface StudentDash {
  student_id?: number;
  membership?: { total?: number; active_count?: number; active?: { extracurricular_id?: number; extracurricular_name?: string; status?: string }[]; by_status?: Record<string, number> };
  payments?: { billed?: number; paid?: number; outstanding?: number; unpaid?: number; pending_verification?: number; paid_count?: number; rejected?: number };
  attendance?: { total?: number; hadir?: number; izin?: number; sakit?: number; alpa?: number; attendance_percentage?: number };
  evaluations?: { published_count?: number; avg_final?: number | null; latest?: { id?: number; extracurricular_name?: string; evaluation_period?: string; final_score?: number | null }[] };
}

export function StudentDashboardPage() {
  const [data, setData] = useState<StudentDash | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = async () => {
    setLoading(true); setError(null);
    try {
      const res = await reportingApi.studentDashboard({});
      setData(res.data as StudentDash);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal memuat dashboard siswa')); }
    finally { setLoading(false); }
  };
  useEffect(() => { load(); }, []);

  return (
    <div>
      <h2>Dashboard Saya</h2>
      {error && <div className="admin-error">{error}</div>}
      {loading ? <p className="admin-muted">Memuat...</p> : !data ? <p className="admin-muted">Belum ada data.</p> : (
        <>
          <div className="admin-card">
            <h3>Keanggotaan Aktif ({data.membership?.active_count ?? 0})</h3>
            {(data.membership?.active || []).length === 0 ? <p className="admin-muted">Belum ada ekskul aktif.</p> : (
              <ul>{(data.membership?.active || []).map((a) => <li key={String(a.extracurricular_id)}>{a.extracurricular_name} — {a.status}</li>)}</ul>
            )}
          </div>
          <div className="admin-card">
            <h3>Pembayaran</h3>
            <p className="admin-muted">Ditagih {formatRupiah(data.payments?.billed ?? 0)} · Lunas {formatRupiah(data.payments?.paid ?? 0)} · Outstanding {formatRupiah(data.payments?.outstanding ?? 0)} · Pending {data.payments?.pending_verification ?? 0}</p>
          </div>
          <div className="admin-card">
            <h3>Kehadiran ({data.attendance?.attendance_percentage ?? 0}%)</h3>
            <p className="admin-muted">Hadir {data.attendance?.hadir ?? 0} · Izin {data.attendance?.izin ?? 0} · Sakit {data.attendance?.sakit ?? 0} · Alpa {data.attendance?.alpa ?? 0}</p>
          </div>
          <div className="admin-card">
            <h3>Evaluasi Published ({data.evaluations?.published_count ?? 0})</h3>
            {(data.evaluations?.latest || []).length === 0 ? <p className="admin-muted">Belum ada evaluasi published.</p> : (
              <ul>{(data.evaluations?.latest || []).map((e) => <li key={String(e.id)}>{e.extracurricular_name} · {e.evaluation_period} · {e.final_score ?? '-'}</li>)}</ul>
            )}
            <p className="admin-muted">Rata-rata: {data.evaluations?.avg_final ?? '-'}</p>
          </div>
        </>
      )}
    </div>
  );
}

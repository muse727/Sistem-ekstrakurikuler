import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { studentEvaluationsApi } from '../api';
import { apiErrorMessage, evaluationPeriodLabel, evaluationStatusLabel } from '../types';
import type { AttendanceSummary, EvaluationItem } from '../types';

export function StudentEvaluationDetailPage() {
  const { id } = useParams();
  const [item, setItem] = useState<EvaluationItem | null>(null);
  const [summary, setSummary] = useState<AttendanceSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!id) return;
    setLoading(true); setError(null);
    studentEvaluationsApi.summary(Number(id))
      .then((r) => { setItem(r.data.evaluation); setSummary(r.data.attendance_summary); })
      .catch((e) => setError(apiErrorMessage(e, 'Gagal memuat evaluasi')))
      .finally(() => setLoading(false));
  }, [id]);

  if (loading) return <div><h2>Detail Evaluasi</h2><p className="admin-muted">Memuat...</p></div>;
  if (!item) return <div><h2>Detail Evaluasi</h2>{error && <div className="admin-error">{error}</div>}<Link to="/student/evaluations">Kembali</Link></div>;

  return (
    <div>
      <h2>Detail Evaluasi</h2>
      {error && <div className="admin-error">{error}</div>}
      <div className="admin-card">
        <p><b>Ekskul:</b> {item.extracurricular?.name || item.extracurricular_id}</p>
        <p><b>Periode:</b> {evaluationPeriodLabel(item.evaluation_period)}</p>
        <p><b>Penilai:</b> {item.evaluator?.name || '-'}</p>
        <p><b>Nilai kehadiran:</b> {item.attendance_score ?? '-'}</p>
        <p><b>Nilai keaktifan:</b> {item.activity_score ?? '-'}</p>
        <p><b>Nilai keterampilan:</b> {item.skill_score ?? '-'}</p>
        <p><b>Nilai kedisiplinan:</b> {item.discipline_score ?? '-'}</p>
        <p><b>Nilai akhir:</b> {item.final_score ?? '-'}</p>
        <p><b>Catatan:</b> {item.notes || '-'}</p>
        <p><b>Status:</b> <span className="badge">{evaluationStatusLabel(item.status)}</span></p>
        <p className="admin-muted">Dinilai: {item.evaluated_at ? new Date(item.evaluated_at).toLocaleString('id-ID') : '-'}</p>
        {summary && (
          <>
            <h3>Ringkasan Kehadiran (informasi)</h3>
            <p>Total sesi: {summary.total_sessions} · Hadir: {summary.hadir} · Izin: {summary.izin} · Sakit: {summary.sakit} · Alpa: {summary.alpa} · {summary.percentage}%</p>
          </>
        )}
        <p><Link to="/student/evaluations">Kembali</Link></p>
      </div>
    </div>
  );
}

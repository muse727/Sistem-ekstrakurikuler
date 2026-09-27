import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { adminEvaluationsApi } from '../api';
import { apiErrorMessage, evaluationPeriodLabel, evaluationStatusLabel } from '../types';
import type { AttendanceSummary, EvaluationItem } from '../types';

export function AdminEvaluationDetailPage() {
  const { id } = useParams();
  const [item, setItem] = useState<EvaluationItem | null>(null);
  const [summary, setSummary] = useState<AttendanceSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [attendance, setAttendance] = useState('');
  const [activity, setActivity] = useState('');
  const [skill, setSkill] = useState('');
  const [discipline, setDiscipline] = useState('');
  const [notes, setNotes] = useState('');
  const [busy, setBusy] = useState(false);

  const load = async () => {
    if (!id) return;
    setLoading(true); setError(null);
    try {
      const r = await adminEvaluationsApi.summary(Number(id));
      setItem(r.data.evaluation);
      setSummary(r.data.attendance_summary);
      setAttendance(r.data.evaluation.attendance_score?.toString() ?? '');
      setActivity(r.data.evaluation.activity_score?.toString() ?? '');
      setSkill(r.data.evaluation.skill_score?.toString() ?? '');
      setDiscipline(r.data.evaluation.discipline_score?.toString() ?? '');
      setNotes(r.data.evaluation.notes ?? '');
    } catch (e) { setError(apiErrorMessage(e, 'Gagal memuat evaluasi')); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(); }, [id]);

  const numOrNull = (v: string): number | null => (v === '' ? null : Number(v));

  const correct = async () => {
    if (!item) return;
    setBusy(true); setError(null); setSuccess(null);
    try {
      const res = await adminEvaluationsApi.update(item.id, {
        attendance_score: numOrNull(attendance),
        activity_score: numOrNull(activity),
        skill_score: numOrNull(skill),
        discipline_score: numOrNull(discipline),
        notes: notes || null,
      });
      setItem(res.data);
      setSuccess('Koreksi tersimpan.');
    } catch (e) { setError(apiErrorMessage(e, 'Gagal koreksi')); }
    finally { setBusy(false); }
  };

  const publish = async () => {
    if (!item) return;
    if (!confirm('Publikasikan evaluasi ini?')) return;
    setBusy(true); setError(null); setSuccess(null);
    try {
      const res = await adminEvaluationsApi.publish(item.id);
      setItem(res.data);
      setSuccess('Dipublikasikan.');
    } catch (e) { setError(apiErrorMessage(e, 'Gagal publish')); }
    finally { setBusy(false); }
  };

  const unpublish = async () => {
    if (!item) return;
    if (!confirm('Kembalikan ke draf? Siswa tidak akan melihatnya.')) return;
    setBusy(true); setError(null); setSuccess(null);
    try {
      const res = await adminEvaluationsApi.unpublish(item.id);
      setItem(res.data);
      setSuccess('Dikembalikan ke draf.');
    } catch (e) { setError(apiErrorMessage(e, 'Gagal unpublish')); }
    finally { setBusy(false); }
  };

  if (loading) return <div><h2>Detail Evaluasi</h2><p className="admin-muted">Memuat...</p></div>;
  if (!item) return <div><h2>Detail Evaluasi</h2>{error && <div className="admin-error">{error}</div>}<Link to="/admin/evaluations">Kembali</Link></div>;

  return (
    <div>
      <h2>Detail Evaluasi</h2>
      {error && <div className="admin-error">{error}</div>}
      {success && <div className="admin-success">{success}</div>}
      <div className="admin-card">
        <p><b>Siswa:</b> {item.student?.name || item.student_id} · <b>Ekskul:</b> {item.extracurricular?.name || '-'} · <b>Tahun:</b> {item.academic_year?.name || '-'}</p>
        <p><b>Periode:</b> {evaluationPeriodLabel(item.evaluation_period)} · <b>Status:</b> <span className="badge">{evaluationStatusLabel(item.status)}</span> · <b>Penilai:</b> {item.evaluator?.name || '-'}</p>
        <p><b>Nilai akhir (server):</b> {item.final_score ?? '-'}</p>
        {item.status === 'draft' ? (
          <>
            <div className="admin-row">
              <label className="admin-field">Kehadiran<input type="number" min={0} max={100} value={attendance} onChange={(e) => setAttendance(e.target.value)} /></label>
              <label className="admin-field">Keaktifan<input type="number" min={0} max={100} value={activity} onChange={(e) => setActivity(e.target.value)} /></label>
              <label className="admin-field">Keterampilan<input type="number" min={0} max={100} value={skill} onChange={(e) => setSkill(e.target.value)} /></label>
              <label className="admin-field">Kedisiplinan<input type="number" min={0} max={100} value={discipline} onChange={(e) => setDiscipline(e.target.value)} /></label>
            </div>
            <div className="admin-row">
              <label className="admin-field">Catatan<textarea value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={2000} rows={3} /></label>
            </div>
            <div className="admin-row">
              <button className="admin-btn primary" disabled={busy} onClick={correct}>Simpan Koreksi</button>
              <button className="admin-btn" disabled={busy} onClick={publish}>Publikasikan</button>
            </div>
          </>
        ) : (
          <>
            <p>Kehadiran: {item.attendance_score ?? '-'} · Keaktifan: {item.activity_score ?? '-'} · Keterampilan: {item.skill_score ?? '-'} · Kedisiplinan: {item.discipline_score ?? '-'}</p>
            <p><b>Catatan:</b> {item.notes || '-'}</p>
            <div className="admin-row">
              <button className="admin-btn" disabled={busy} onClick={unpublish}>Kembalikan ke Draf</button>
            </div>
          </>
        )}
        {summary && <p className="admin-muted">Kehadiran: total {summary.total_sessions}, hadir {summary.hadir}, izin {summary.izin}, sakit {summary.sakit}, alpa {summary.alpa} ({summary.percentage}%)</p>}
        <p className="admin-muted">Dinilai: {item.evaluated_at ? new Date(item.evaluated_at).toLocaleString('id-ID') : '-'}</p>
        <p><Link to="/admin/evaluations">Kembali</Link></p>
      </div>
    </div>
  );
}

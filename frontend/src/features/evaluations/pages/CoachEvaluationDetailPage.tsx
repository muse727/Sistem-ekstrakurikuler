import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { coachEvaluationsApi } from '../api';
import { apiErrorMessage, evaluationPeriodLabel, evaluationStatusLabel, previewFinal } from '../types';
import type { AttendanceSummary, EvaluationItem } from '../types';

export function CoachEvaluationDetailPage() {
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
      const r = await coachEvaluationsApi.summary(Number(id));
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

  const numOrUndef = (v: string): number | null => (v === '' ? null : Number(v));

  const save = async () => {
    if (!item) return;
    setBusy(true); setError(null); setSuccess(null);
    try {
      const res = await coachEvaluationsApi.update(item.id, {
        attendance_score: numOrUndef(attendance),
        activity_score: numOrUndef(activity),
        skill_score: numOrUndef(skill),
        discipline_score: numOrUndef(discipline),
        notes: notes || null,
      });
      setItem(res.data);
      setSuccess('Draf berhasil disimpan. Nilai akhir dihitung server.');
    } catch (e) { setError(apiErrorMessage(e, 'Gagal menyimpan')); }
    finally { setBusy(false); }
  };

  const publish = async () => {
    if (!item) return;
    if (!confirm('Publikasikan evaluasi ini? Siswa akan bisa melihatnya.')) return;
    setBusy(true); setError(null); setSuccess(null);
    try {
      const res = await coachEvaluationsApi.publish(item.id);
      setItem(res.data);
      setSuccess('Evaluasi dipublikasikan.');
    } catch (e) { setError(apiErrorMessage(e, 'Gagal mempublikasi')); }
    finally { setBusy(false); }
  };

  if (loading) return <div><h2>Detail Evaluasi</h2><p className="admin-muted">Memuat...</p></div>;
  if (!item) return <div><h2>Detail Evaluasi</h2>{error && <div className="admin-error">{error}</div>}<Link to="/coach/evaluations">Kembali</Link></div>;

  const isDraft = item.status === 'draft';
  const preview = previewFinal(attendance, activity, skill, discipline);

  return (
    <div>
      <h2>Detail Evaluasi</h2>
      {error && <div className="admin-error">{error}</div>}
      {success && <div className="admin-success">{success}</div>}
      <div className="admin-card">
        <p><b>Siswa:</b> {item.student?.name || item.student_id} · <b>Ekskul:</b> {item.extracurricular?.name || item.extracurricular_id}</p>
        <p><b>Periode:</b> {evaluationPeriodLabel(item.evaluation_period)} · <b>Status:</b> <span className="badge">{evaluationStatusLabel(item.status)}</span></p>
        <p><b>Nilai akhir (server):</b> {item.final_score ?? '-'}</p>
        {isDraft ? (
          <>
            <div className="admin-row">
              <label className="admin-field">Kehadiran (0-100)<input type="number" min={0} max={100} value={attendance} onChange={(e) => setAttendance(e.target.value)} /></label>
              <label className="admin-field">Keaktifan (0-100)<input type="number" min={0} max={100} value={activity} onChange={(e) => setActivity(e.target.value)} /></label>
              <label className="admin-field">Keterampilan (0-100)<input type="number" min={0} max={100} value={skill} onChange={(e) => setSkill(e.target.value)} /></label>
              <label className="admin-field">Kedisiplinan (0-100)<input type="number" min={0} max={100} value={discipline} onChange={(e) => setDiscipline(e.target.value)} /></label>
            </div>
            <div className="admin-row">
              <label className="admin-field">Catatan<textarea value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={2000} rows={3} /></label>
            </div>
            <p className="admin-muted">Pratinjau: {preview ?? '-'} (final dihitung server, bukan input manual)</p>
            <div className="admin-row">
              <button className="admin-btn primary" disabled={busy} onClick={save}>Simpan Draf</button>
              <button className="admin-btn" disabled={busy} onClick={publish}>Publikasikan</button>
            </div>
          </>
        ) : (
          <>
            <p><b>Nilai kehadiran:</b> {item.attendance_score ?? '-'}</p>
            <p><b>Nilai keaktifan:</b> {item.activity_score ?? '-'}</p>
            <p><b>Nilai keterampilan:</b> {item.skill_score ?? '-'}</p>
            <p><b>Nilai kedisiplinan:</b> {item.discipline_score ?? '-'}</p>
            <p><b>Catatan:</b> {item.notes || '-'}</p>
            <p className="admin-muted">Sudah dipublikasikan, tidak bisa diubah coach.</p>
          </>
        )}
        {summary && <p className="admin-muted">Kehadiran: total {summary.total_sessions}, hadir {summary.hadir}, izin {summary.izin}, sakit {summary.sakit}, alpa {summary.alpa} ({summary.percentage}%)</p>}
        <p><Link to="/coach/evaluations">Kembali</Link></p>
      </div>
    </div>
  );
}

import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { coachEvaluationsApi } from '../api';
import { apiErrorMessage, previewFinal } from '../types';
import type { EligibleRegistration } from '../types';

export function CoachEvaluationNewPage() {
  const navigate = useNavigate();
  const [regs, setRegs] = useState<EligibleRegistration[]>([]);
  const [loadingRegs, setLoadingRegs] = useState(true);
  const [registrationId, setRegistrationId] = useState('');
  const [period, setPeriod] = useState('midterm');
  const [attendance, setAttendance] = useState('');
  const [activity, setActivity] = useState('');
  const [skill, setSkill] = useState('');
  const [discipline, setDiscipline] = useState('');
  const [notes, setNotes] = useState('');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    coachEvaluationsApi.eligible({ per_page: 100 })
      .then((r) => {
        setRegs(r.data);
        if (r.data.length > 0) setRegistrationId(String(r.data[0].id));
      })
      .catch((e) => setError(apiErrorMessage(e, 'Gagal memuat siswa eligible')))
      .finally(() => setLoadingRegs(false));
  }, []);

  const preview = previewFinal(attendance, activity, skill, discipline);

  const numOrNull = (v: string): number | null => (v === '' ? null : Number(v));

  const save = async () => {
    if (!registrationId) { setError('Pilih siswa eligible terlebih dahulu.'); return; }
    setSaving(true); setError(null);
    try {
      const res = await coachEvaluationsApi.create({
        registration_id: Number(registrationId),
        evaluation_period: period,
        attendance_score: numOrNull(attendance),
        activity_score: numOrNull(activity),
        skill_score: numOrNull(skill),
        discipline_score: numOrNull(discipline),
        notes: notes || null,
      });
      navigate(`/coach/evaluations/${res.data.id}`, { replace: true });
    } catch (e) { setError(apiErrorMessage(e, 'Gagal menyimpan draf')); }
    finally { setSaving(false); }
  };

  return (
    <div>
      <h2>Buat Evaluasi</h2>
      {error && <div className="admin-error">{error}</div>}
      <div className="admin-card">
        {loadingRegs ? <p className="admin-muted">Memuat siswa...</p> : regs.length === 0 ? (
          <p className="admin-muted">Tidak ada registrasi eligible (aktif/disetejui) di ekskul kamu.</p>
        ) : (
          <>
            <div className="admin-row">
              <label className="admin-field">Siswa (eligible)
                <select value={registrationId} onChange={(e) => setRegistrationId(e.target.value)}>
                  {regs.map((r) => <option key={r.id} value={r.id}>#{r.id} · {r.student?.name || r.student_id} · {r.extracurricular?.name || ''}</option>)}
                </select>
              </label>
              <label className="admin-field">Periode
                <select value={period} onChange={(e) => setPeriod(e.target.value)}>
                  <option value="midterm">Tengah Semester</option>
                  <option value="final">Akhir Semester</option>
                </select>
              </label>
            </div>
            <div className="admin-row">
              <label className="admin-field">Kehadiran (0-100)<input type="number" min={0} max={100} value={attendance} onChange={(e) => setAttendance(e.target.value)} placeholder="opsional untuk draf" /></label>
              <label className="admin-field">Keaktifan (0-100)<input type="number" min={0} max={100} value={activity} onChange={(e) => setActivity(e.target.value)} placeholder="opsional untuk draf" /></label>
              <label className="admin-field">Keterampilan (0-100)<input type="number" min={0} max={100} value={skill} onChange={(e) => setSkill(e.target.value)} placeholder="opsional untuk draf" /></label>
              <label className="admin-field">Kedisiplinan (0-100)<input type="number" min={0} max={100} value={discipline} onChange={(e) => setDiscipline(e.target.value)} placeholder="opsional untuk draf" /></label>
            </div>
            <div className="admin-row">
              <label className="admin-field">Catatan<textarea value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={2000} rows={3} /></label>
            </div>
            <p className="admin-muted">Pratinjau nilai akhir (dihitung server saat simpan): {preview ?? '-'}</p>
            <button className="admin-btn primary" disabled={saving} onClick={save}>{saving ? 'Menyimpan...' : 'Simpan Draf'}</button>
          </>
        )}
        <p><Link to="/coach/evaluations">Kembali</Link></p>
      </div>
    </div>
  );
}

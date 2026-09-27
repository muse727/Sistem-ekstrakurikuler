import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { coachEvaluationsApi } from '../api';
import { apiErrorMessage, evaluationPeriodLabel, evaluationStatusLabel } from '../types';
import type { EvaluationItem } from '../types';

export function CoachEvaluationsPage() {
  const [items, setItems] = useState<EvaluationItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [period, setPeriod] = useState('');
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  const load = async (p = page) => {
    setLoading(true); setError(null);
    try {
      const res = await coachEvaluationsApi.list({ page: p, evaluation_period: period || undefined, status: status || undefined });
      setItems(res.data);
      setLastPage(res.meta.last_page);
      setPage(res.meta.current_page);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal memuat evaluasi')); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(1); }, []);

  return (
    <div>
      <h2>Evaluasi Siswa</h2>
      <p><Link to="/coach/evaluations/new">+ Buat evaluasi baru</Link></p>
      {error && <div className="admin-error">{error}</div>}
      <div className="admin-card">
        <div className="admin-row">
          <label className="admin-field">Periode
            <select value={period} onChange={(e) => setPeriod(e.target.value)}>
              <option value="">Semua</option>
              <option value="midterm">Tengah Semester</option>
              <option value="final">Akhir Semester</option>
            </select>
          </label>
          <label className="admin-field">Status
            <select value={status} onChange={(e) => setStatus(e.target.value)}>
              <option value="">Semua</option>
              <option value="draft">Draf</option>
              <option value="published">Dipublikasi</option>
            </select>
          </label>
          <button className="admin-btn" type="button" onClick={() => { setPage(1); load(1); }}>Cari</button>
        </div>
      </div>
      <div className="admin-card">
        {loading ? <p className="admin-muted">Memuat...</p> : items.length === 0 ? (
          <p className="admin-muted">Belum ada evaluasi.</p>
        ) : (
          <>
            <table className="admin-table">
              <thead><tr><th>Siswa</th><th>Ekskul</th><th>Periode</th><th>Akhir</th><th>Status</th><th>Aksi</th></tr></thead>
              <tbody>{items.map((ev) => (
                <tr key={ev.id}>
                  <td>{ev.student?.name || ev.student_id}</td>
                  <td>{ev.extracurricular?.name || ev.extracurricular_id}</td>
                  <td>{evaluationPeriodLabel(ev.evaluation_period)}</td>
                  <td>{ev.final_score ?? '-'}</td>
                  <td><span className="badge">{evaluationStatusLabel(ev.status)}</span></td>
                  <td><Link to={`/coach/evaluations/${ev.id}`}>Detail</Link></td>
                </tr>
              ))}</tbody>
            </table>
            <div className="admin-row" style={{ marginTop: '.6rem' }}>
              <button className="admin-btn" disabled={page <= 1} onClick={() => load(page - 1)}>Prev</button>
              <span className="admin-muted">Hal {page} / {lastPage}</span>
              <button className="admin-btn" disabled={page >= lastPage} onClick={() => load(page + 1)}>Next</button>
            </div>
          </>
        )}
      </div>
    </div>
  );
}

import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { studentEvaluationsApi } from '../api';
import { apiErrorMessage, evaluationPeriodLabel, evaluationStatusLabel } from '../types';
import type { EvaluationItem } from '../types';

export function StudentEvaluationsPage() {
  const [items, setItems] = useState<EvaluationItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = async () => {
    setLoading(true); setError(null);
    try {
      const res = await studentEvaluationsApi.list();
      setItems(res.data);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal memuat evaluasi')); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(); }, []);

  return (
    <div>
      <h2>Evaluasiku</h2>
      {error && <div className="admin-error">{error}</div>}
      <div className="admin-card">
        {loading ? <p className="admin-muted">Memuat...</p> : items.length === 0 ? (
          <p className="admin-muted">Belum ada evaluasi yang dipublikasi.</p>
        ) : (
          <table className="admin-table">
            <thead><tr><th>Ekskul</th><th>Periode</th><th>Nilai Akhir</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>{items.map((ev) => (
              <tr key={ev.id}>
                <td>{ev.extracurricular?.name || ev.extracurricular_id}</td>
                <td>{evaluationPeriodLabel(ev.evaluation_period)}</td>
                <td>{ev.final_score ?? '-'}</td>
                <td><span className="badge">{evaluationStatusLabel(ev.status)}</span></td>
                <td><Link to={`/student/evaluations/${ev.id}`}>Detail</Link></td>
              </tr>
            ))}</tbody>
          </table>
        )}
      </div>
    </div>
  );
}

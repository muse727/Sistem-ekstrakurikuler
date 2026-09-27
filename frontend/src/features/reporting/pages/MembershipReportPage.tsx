import { useEffect, useState } from 'react';
import { reportingApi, apiErrorMessage, type ReportParams } from '../api';
import { adminApi } from '../../admin/api';
import type { AcademicYear, Extracurricular } from '../../admin/types';

export function MembershipReportPage() {
  const [rows, setRows] = useState<unknown[]>([]);
  const [summary, setSummary] = useState<Record<string, number>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [years, setYears] = useState<AcademicYear[]>([]);
  const [ekskuls, setEkskuls] = useState<Extracurricular[]>([]);
  const [f, setF] = useState<ReportParams>({ per_page: 15 });
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);

  useEffect(() => {
    adminApi.academicYears.list({ per_page: 100 }).then((r) => setYears(r.data)).catch(() => {});
    adminApi.extracurriculars.list({ per_page: 100 }).then((r) => setEkskuls(r.data)).catch(() => {});
  }, []);

  const load = async (p = page) => {
    setLoading(true); setError(null);
    try {
      const res = await reportingApi.membership({ ...f, page: p });
      setRows(res.data.items as unknown[]);
      setSummary((res.data.summary as Record<string, number>) || {});
      setPage(res.meta.current_page); setLastPage(res.meta.last_page); setTotal(res.meta.total);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal memuat laporan keanggotaan')); }
    finally { setLoading(false); }
  };
  useEffect(() => { load(1); }, []);

  return (
    <div>
      <h2>Laporan Keanggotaan</h2>
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
          <label className="admin-field">Status
            <select value={f.status || ''} onChange={(e) => setF({ ...f, status: e.target.value || undefined })}>
              <option value="">Semua</option>
              {['draft', 'submitted', 'approved', 'active', 'rejected', 'cancelled'].map((s) => <option key={s} value={s}>{s}</option>)}
            </select>
          </label>
          <button className="admin-btn primary" type="button" onClick={() => load(1)}>Terapkan</button>
        </div>
      </div>
      <div className="admin-card">
        <p className="admin-muted">Total {summary.total ?? 0} · Aktif {summary.active ?? 0} · Submitted {summary.submitted ?? 0} · Approved {summary.approved ?? 0} · Ditolak {summary.rejected ?? 0} · Batal {summary.cancelled ?? 0}</p>
      </div>
      <div className="admin-card">
        {loading ? <p className="admin-muted">Memuat...</p> : rows.length === 0 ? <p className="admin-muted">Belum ada data.</p> : (
          <>
            <table className="admin-table">
              <thead><tr><th>ID</th><th>Siswa</th><th>Ekskul</th><th>Status</th></tr></thead>
              <tbody>{(rows as Record<string, unknown>[]).map((r, i) => (
                <tr key={String((r as { id?: number }).id ?? i)}>
                  <td>{String((r as { id?: number }).id ?? '-')}</td>
                  <td>{String(((r as { student?: { name?: string } }).student?.name) ?? ((r as { student_name?: string }).student_name ?? '-'))}</td>
                  <td>{String(((r as { extracurricular?: { name?: string } }).extracurricular?.name) ?? '-')}</td>
                  <td><span className="badge">{String((r as { status?: string }).status ?? '-')}</span></td>
                </tr>
              ))}</tbody>
            </table>
            <div className="admin-row">
              <button className="admin-btn" disabled={page <= 1} onClick={() => load(page - 1)}>Prev</button>
              <span className="admin-muted">Hal {page} / {lastPage} · {total} data</span>
              <button className="admin-btn" disabled={page >= lastPage} onClick={() => load(page + 1)}>Next</button>
            </div>
          </>
        )}
      </div>
    </div>
  );
}

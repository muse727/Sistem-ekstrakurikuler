import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { adminSessionsApi, downloadCheckInPhoto } from '../../attendance/api';
import { apiErrorMessage, attendanceStatusLabel, sessionStatusLabel } from '../../attendance/types';
import type { AttendanceItem, AttendanceStatus, CheckIn, SessionItem } from '../../attendance/types';

export function AdminSessionDetailPage() {
  const { id } = useParams();
  const sessionId = Number(id);
  const [session, setSession] = useState<SessionItem | null>(null);
  const [checkIns, setCheckIns] = useState<CheckIn[]>([]);
  const [attendance, setAttendance] = useState<AttendanceItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [correcting, setCorrecting] = useState<{ id: number; status: AttendanceStatus; notes: string } | null>(null);

  const load = async () => {
    if (!sessionId) return;
    setLoading(true); setError(null);
    try {
      const s = await adminSessionsApi.get(sessionId);
      setSession(s.data);
      try {
        const c = await adminSessionsApi.checkIns(sessionId);
        setCheckIns(c.data);
      } catch { setCheckIns([]); }
      try {
        const a = await adminSessionsApi.attendance(sessionId);
        setAttendance(a.data);
      } catch { setAttendance([]); }
    } catch (e) { setError(apiErrorMessage(e, 'Gagal memuat sesi')); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(); }, [id]);

  const act = async (fn: () => Promise<unknown>, confirmText: string) => {
    if (!window.confirm(confirmText)) return;
    setBusy(true); setError(null); setNotice(null);
    try {
      await fn();
      await load();
      setNotice('Berhasil.');
    } catch (e) { setError(apiErrorMessage(e, 'Aksi gagal')); }
    finally { setBusy(false); }
  };

  const downloadPhoto = async (c: CheckIn) => {
    setError(null);
    try {
      await downloadCheckInPhoto(adminSessionsApi.photoUrl(c.id), `checkin-${c.id}.jpg`);
    } catch (e) { setError(apiErrorMessage(e, 'Gagal mengunduh foto')); }
  };

  const submitCorrect = async () => {
    if (!correcting) return;
    setBusy(true); setError(null);
    try {
      await adminSessionsApi.correct(sessionId, correcting.id, correcting.status, correcting.notes || undefined);
      setCorrecting(null);
      await load();
      setNotice('Koreksi tersimpan.');
    } catch (e) { setError(apiErrorMessage(e, 'Gagal koreksi')); }
    finally { setBusy(false); }
  };

  if (loading) return <div><h2>Detail Sesi</h2><p className="admin-muted">Memuat...</p></div>;
  if (!session) return <div><h2>Detail Sesi</h2>{error && <div className="admin-error">{error}</div>}<Link to="/admin/sessions">Kembali</Link></div>;

  return (
    <div>
      <h2>Sesi #{session.id}: {session.topic || '-'}</h2>
      {error && <div className="admin-error">{error}</div>}
      {notice && <div className="admin-card"><p className="admin-muted">{notice}</p></div>}
      <div className="admin-card">
        <p><b>Tanggal:</b> {session.session_date} {session.start_time || ''}–{session.end_time || ''}</p>
        <p><b>Ekskul:</b> {session.extracurricular?.name} | <b>Status:</b> <span className="badge">{sessionStatusLabel(session.status)}</span></p>
        <p><b>Venue:</b> {session.venue?.name || '-'}</p>
        <div className="admin-row" style={{ marginTop: '.6rem' }}>
          <button className="admin-btn primary" disabled={busy} onClick={() => act(() => adminSessionsApi.open(session.id), 'Buka sesi ini?')}>Open</button>
          <button className="admin-btn primary" disabled={busy} onClick={() => act(() => adminSessionsApi.complete(session.id), 'Selesaikan sesi ini?')}>Complete</button>
          <button className="admin-btn danger" disabled={busy} onClick={() => act(() => adminSessionsApi.cancel(session.id), 'Batalkan sesi ini?')}>Cancel</button>
          <Link to="/admin/sessions">Kembali</Link>
        </div>
      </div>

      <div className="admin-card">
        <h3>Check-in Pelatih ({checkIns.length})</h3>
        {checkIns.length === 0 ? <p className="admin-muted">Belum ada check-in.</p> : (
          <table className="admin-table">
            <thead><tr><th>ID</th><th>Pelatih</th><th>Jarak</th><th>Akurasi</th><th>Server</th><th>Aksi</th></tr></thead>
            <tbody>{checkIns.map((c) => (
              <tr key={c.id}>
                <td>{c.id}</td>
                <td>{c.coach?.name || c.coach_id}</td>
                <td>{c.distance_from_venue_meters ?? '-'}m</td>
                <td>±{c.accuracy_meters ?? '-'}m</td>
                <td>{c.server_received_at ? new Date(c.server_received_at).toLocaleString('id-ID') : '-'}</td>
                <td><button className="admin-btn" onClick={() => downloadPhoto(c)}>Unduh foto</button></td>
              </tr>
            ))}</tbody>
          </table>
        )}
      </div>

      <div className="admin-card">
        <h3>Absensi Siswa ({attendance.length})</h3>
        {attendance.length === 0 ? <p className="admin-muted">Belum ada absensi.</p> : (
          <table className="admin-table">
            <thead><tr><th>Siswa</th><th>Status</th><th>Catatan</th><th>Aksi</th></tr></thead>
            <tbody>{attendance.map((a) => (
              <tr key={a.id}>
                <td>{a.student?.name || a.student_id}</td>
                <td>{attendanceStatusLabel(a.status)}</td>
                <td>{a.notes || '-'}</td>
                <td><button className="admin-btn" onClick={() => setCorrecting({ id: a.id, status: a.status, notes: a.notes || '' })}>Koreksi</button></td>
              </tr>
            ))}</tbody>
          </table>
        )}
        {correcting && (
          <div className="admin-row" style={{ marginTop: '.6rem' }}>
            <label className="admin-field">Status
              <select value={correcting.status} onChange={(e) => setCorrecting({ ...correcting, status: e.target.value as AttendanceStatus })}>
                <option value="hadir">Hadir</option>
                <option value="izin">Izin</option>
                <option value="sakit">Sakit</option>
                <option value="alpa">Alpa</option>
              </select>
            </label>
            <label className="admin-field">Catatan<input value={correcting.notes} onChange={(e) => setCorrecting({ ...correcting, notes: e.target.value })} /></label>
            <button className="admin-btn primary" disabled={busy} onClick={submitCorrect}>Simpan</button>
            <button className="admin-btn" onClick={() => setCorrecting(null)}>Batal</button>
          </div>
        )}
      </div>
    </div>
  );
}

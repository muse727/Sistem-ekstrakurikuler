import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { coachSessionsApi } from '../api';
import { apiErrorMessage, attendanceStatusLabel, sessionStatusLabel } from '../types';
import type { AttendanceItem, AttendanceStatus, CheckIn, SessionItem } from '../types';

const STATUSES: AttendanceStatus[] = ['hadir', 'izin', 'sakit', 'alpa'];

export function CoachSessionDetailPage() {
  const { id } = useParams();
  const sessionId = Number(id);
  const [session, setSession] = useState<SessionItem | null>(null);
  const [attendance, setAttendance] = useState<AttendanceItem[]>([]);
  const [checkIn, setCheckIn] = useState<CheckIn | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [photo, setPhoto] = useState<File | null>(null);
  const [checkingIn, setCheckingIn] = useState(false);
  const [geo, setGeo] = useState<{ lat: number; lng: number; acc: number } | null>(null);
  const [geoError, setGeoError] = useState<string | null>(null);
  const [rows, setRows] = useState<Record<number, { status: AttendanceStatus; notes: string }>>({});
  const [saving, setSaving] = useState(false);

  const load = async () => {
    if (!sessionId) return;
    setLoading(true); setError(null);
    try {
      const s = await coachSessionsApi.get(sessionId);
      setSession(s.data);
      try {
        const a = await coachSessionsApi.attendance(sessionId);
        setAttendance(a.data);
        const map: Record<number, { status: AttendanceStatus; notes: string }> = {};
        a.data.forEach((r) => { map[r.student_id] = { status: r.status, notes: r.notes || '' }; });
        setRows(map);
      } catch { setAttendance([]); }
    } catch (e) { setError(apiErrorMessage(e, 'Gagal memuat sesi')); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(); }, [id]);

  const requestLocation = () => {
    setGeoError(null);
    if (!navigator.geolocation) { setGeoError('Geolocation tidak didukung browser.'); return; }
    navigator.geolocation.getCurrentPosition(
      (pos) => setGeo({ lat: pos.coords.latitude, lng: pos.coords.longitude, acc: Math.round(pos.coords.accuracy || 0) }),
      () => setGeoError('Izin lokasi ditolak / tidak tersedia. Nyalakan GPS lalu coba lagi.'),
      { enableHighAccuracy: true, timeout: 15000 },
    );
  };

  const doCheckIn = async () => {
    if (!session || !photo) { setError('Pilih foto selfie dulu.'); return; }
    if (!geo) { setError('Ambil lokasi GPS dulu.'); return; }
    setCheckingIn(true); setError(null); setNotice(null);
    try {
      const res = await coachSessionsApi.checkIn(session.id, {
        latitude: geo.lat,
        longitude: geo.lng,
        accuracy_meters: geo.acc,
        device_captured_at: new Date().toISOString(),
        photo,
      });
      setCheckIn(res.data);
      setNotice(`Check-in berhasil. Jarak ${res.data.distance_from_venue_meters ?? '-'} meter dari venue.`);
      await load();
    } catch (e) { setError(apiErrorMessage(e, 'Check-in gagal')); }
    finally { setCheckingIn(false); }
  };

  const saveBulk = async () => {
    const items = Object.entries(rows).map(([student_id, v]) => ({
      student_id: Number(student_id),
      status: v.status,
      notes: v.notes || undefined,
    }));
    if (items.length === 0) { setError('Belum ada data absensi.'); return; }
    setSaving(true); setError(null); setNotice(null);
    try {
      await coachSessionsApi.recordBulk(sessionId, items);
      setNotice('Absensi tersimpan.');
      await load();
    } catch (e) { setError(apiErrorMessage(e, 'Gagal menyimpan absensi')); }
    finally { setSaving(false); }
  };

  if (loading) return <div><h2>Detail Sesi</h2><p className="admin-muted">Memuat...</p></div>;
  if (!session) return <div><h2>Detail Sesi</h2>{error && <div className="admin-error">{error}</div>}<Link to="/coach/sessions">Kembali</Link></div>;

  return (
    <div>
      <h2>Sesi: {session.topic || `#${session.id}`}</h2>
      {error && <div className="admin-error">{error}</div>}
      {notice && <div className="admin-card"><p className="admin-muted">{notice}</p></div>}
      <div className="admin-card">
        <p><b>Tanggal:</b> {session.session_date} {session.start_time || ''}–{session.end_time || ''}</p>
        <p><b>Ekskul:</b> {session.extracurricular?.name} | <b>Status:</b> <span className="badge">{sessionStatusLabel(session.status)}</span></p>
        <p><b>Venue:</b> {session.venue?.name || '-'} {session.venue?.radius_meters ? `(radius ${session.venue.radius_meters}m)` : ''}</p>
        <p><b>Check-in:</b> {session.has_check_in ? 'Sudah' : 'Belum'} {checkIn?.distance_from_venue_meters != null ? `— ${checkIn.distance_from_venue_meters}m dari venue` : ''}</p>
      </div>

      <div className="admin-card">
        <h3>Check-in Pelatih</h3>
        {session.status !== 'open' ? (
          <p className="admin-muted">Check-in hanya saat sesi OPEN. Minta admin membuka sesi.</p>
        ) : (
          <>
            <div className="admin-row">
              <button className="admin-btn" type="button" onClick={requestLocation}>Ambil Lokasi GPS</button>
              <span className="admin-muted">{geo ? `${geo.lat.toFixed(6)}, ${geo.lng.toFixed(6)} (±${geo.acc}m)` : 'Belum ada lokasi'}</span>
            </div>
            {geoError && <p className="admin-muted">{geoError}</p>}
            <div className="admin-row" style={{ marginTop: '.6rem' }}>
              <label className="admin-field">Foto selfie (JPG/PNG max 5MB)
                <input type="file" accept="image/jpeg,image/png" onChange={(e) => setPhoto(e.target.files?.[0] || null)} />
              </label>
              <button className="admin-btn primary" disabled={checkingIn} onClick={doCheckIn}>{checkingIn ? 'Mengirim...' : 'Kirim Check-in'}</button>
            </div>
            <p className="admin-muted">Backend yang menentukan jarak & waktu server. Jangan edit koordinat manual.</p>
          </>
        )}
      </div>

      <div className="admin-card">
        <h3>Absensi Siswa</h3>
        {attendance.length === 0 ? <p className="admin-muted">Belum ada absensi. Isi manual per siswa lalu simpan.</p> : (
          <table className="admin-table">
            <thead><tr><th>Siswa</th><th>Status</th><th>Catatan</th></tr></thead>
            <tbody>{attendance.map((a) => (
              <tr key={a.id}>
                <td>{a.student?.name || a.student_id}</td>
                <td>{attendanceStatusLabel(a.status)}</td>
                <td>{a.notes || '-'}</td>
              </tr>
            ))}</tbody>
          </table>
        )}
        <div className="admin-row" style={{ marginTop: '.6rem' }}>
          <label className="admin-field">NIS/ID siswa<input id="t007-student-id" placeholder="contoh 3" /></label>
          <label className="admin-field">Status
            <select id="t007-student-status">{STATUSES.map((s) => <option key={s} value={s}>{attendanceStatusLabel(s)}</option>)}</select>
          </label>
          <button className="admin-btn" type="button" onClick={() => {
            const idEl = document.getElementById('t007-student-id') as HTMLInputElement | null;
            const stEl = document.getElementById('t007-student-status') as HTMLSelectElement | null;
            const sid = Number(idEl?.value);
            if (!sid) { setError('Isi ID siswa dulu.'); return; }
            setRows((prev) => ({ ...prev, [sid]: { status: (stEl?.value as AttendanceStatus) || 'hadir', notes: prev[sid]?.notes || '' } }));
          }}>Tambah ke daftar</button>
        </div>
        {Object.keys(rows).length > 0 && (
          <>
            <table className="admin-table" style={{ marginTop: '.6rem' }}>
              <thead><tr><th>ID Siswa</th><th>Status</th><th>Catatan</th><th>Aksi</th></tr></thead>
              <tbody>{Object.entries(rows).map(([sid, v]) => (
                <tr key={sid}>
                  <td>{sid}</td>
                  <td>
                    <select value={v.status} onChange={(e) => setRows((p) => ({ ...p, [sid]: { ...v, status: e.target.value as AttendanceStatus } }))}>
                      {STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
                    </select>
                  </td>
                  <td><input value={v.notes} onChange={(e) => setRows((p) => ({ ...p, [sid]: { ...v, notes: e.target.value } }))} placeholder="opsional" /></td>
                  <td><button className="admin-btn danger" onClick={() => setRows((p) => { const n = { ...p }; delete n[Number(sid)]; return n; })}>Hapus</button></td>
                </tr>
              ))}</tbody>
            </table>
            <button className="admin-btn primary" disabled={saving} onClick={saveBulk} style={{ marginTop: '.6rem' }}>{saving ? 'Menyimpan...' : 'Simpan Absensi'}</button>
          </>
        )}
        <p><Link to="/coach/sessions">Kembali</Link></p>
      </div>
    </div>
  );
}

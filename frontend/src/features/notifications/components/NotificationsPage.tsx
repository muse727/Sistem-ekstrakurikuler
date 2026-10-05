import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { notificationsApi } from '../api';
import { notificationTypeLabel, type AppNotification } from '../types';
import { targetPath } from './NotificationBell';

export function NotificationsPage({ role }: { role: string | undefined }) {
  const [items, setItems] = useState<AppNotification[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [onlyUnread, setOnlyUnread] = useState(false);
  const [total, setTotal] = useState(0);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await notificationsApi.list({ per_page: 15, unread: onlyUnread ? 1 : 0 });
      setItems(res.items);
      setTotal(res.meta?.total ?? res.items.length);
    } catch {
      setError('Gagal memuat notifikasi');
    } finally {
      setLoading(false);
    }
  }, [onlyUnread]);

  useEffect(() => {
    load();
    const t = window.setInterval(load, 45000);
    return () => window.clearInterval(t);
  }, [load]);

  const handleRead = async (n: AppNotification) => {
    if (n.read_at) return;
    try {
      await notificationsApi.markRead(n.id);
      setItems((prev) => prev.map((x) => (x.id === n.id ? { ...x, read_at: new Date().toISOString() } : x)));
    } catch {
      // abaikan
    }
  };

  const handleReadAll = async () => {
    try {
      await notificationsApi.markAllRead();
      setItems((prev) => prev.map((x) => ({ ...x, read_at: x.read_at ?? new Date().toISOString() })));
    } catch {
      // abaikan
    }
  };

  return (
    <div>
      <h2>Notifikasi</h2>
      {error && <div className="admin-error">{error}</div>}
      <div className="admin-card">
        <div className="admin-row" style={{ marginBottom: '.8rem' }}>
          <label style={{ display: 'flex', gap: '.4rem', alignItems: 'center' }}>
            <input type="checkbox" checked={onlyUnread} onChange={(e) => setOnlyUnread(e.target.checked)} />
            Belum dibaca saja
          </label>
          <button type="button" className="admin-btn" onClick={handleReadAll}>Tandai semua dibaca</button>
          <span className="admin-muted">Total: {total}</span>
        </div>
        {loading ? (
          <p className="admin-muted">Memuat...</p>
        ) : items.length === 0 ? (
          <p className="admin-muted">Belum ada notifikasi.</p>
        ) : (
          <table className="admin-table">
            <thead>
              <tr><th>Tipe</th><th>Judul</th><th>Pesan</th><th>Waktu</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
              {items.map((n) => {
                const to = targetPath(n, role);
                return (
                  <tr key={n.id} style={{ background: n.read_at ? undefined : '#eef4ff' }}>
                    <td>{notificationTypeLabel(n.type)}</td>
                    <td>{n.title}</td>
                    <td>{n.message}</td>
                    <td>{n.created_at ? new Date(n.created_at).toLocaleString('id-ID') : '-'}</td>
                    <td>{n.read_at ? 'Dibaca' : 'Baru'}</td>
                    <td>
                      {!n.read_at && <button type="button" className="admin-btn" onClick={() => handleRead(n)}>Tandai dibaca</button>}{' '}
                      {to && <Link to={to}>Lihat</Link>}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}

import { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { notificationsApi } from '../api';
import { notificationTypeLabel, type AppNotification } from '../types';

function timeAgo(iso: string | null): string {
  if (!iso) return '-';
  const t = new Date(iso).getTime();
  const diff = Math.max(0, Date.now() - t);
  const m = Math.floor(diff / 60000);
  if (m < 1) return 'baru saja';
  if (m < 60) return `${m} mnt lalu`;
  const h = Math.floor(m / 60);
  if (h < 24) return `${h} jam lalu`;
  return new Date(iso).toLocaleDateString('id-ID');
}

export function targetPath(n: AppNotification, role: string | undefined): string | null {
  if (n.reference_type === 'registration') {
    if (role === 'admin' || role === 'super_admin') return '/admin/registrations';
    if (role === 'student') return '/student/registrations';
    return null;
  }
  if (n.reference_type === 'invoice') {
    if (role === 'admin' || role === 'super_admin') return '/admin/payments';
    if (role === 'student') return '/student/payments';
    return null;
  }
  if (n.reference_type === 'evaluation') {
    if (role === 'admin' || role === 'super_admin') return '/admin/evaluations';
    if (role === 'coach') return '/coach/evaluations';
    if (role === 'student') return '/student/evaluations';
    return null;
  }
  return null;
}

export function NotificationBell({ role, basePath }: { role: string | undefined; basePath: string }) {
  const [count, setCount] = useState(0);
  const [items, setItems] = useState<AppNotification[]>([]);
  const [open, setOpen] = useState(false);
  const timer = useRef<number | null>(null);

  const load = useCallback(async () => {
    try {
      const c = await notificationsApi.unreadCount();
      setCount(c);
      const res = await notificationsApi.list({ per_page: 8 });
      setItems(res.items);
    } catch {
      // diamkan, bell tidak boleh merusak layout
    }
  }, []);

  useEffect(() => {
    load();
    timer.current = window.setInterval(load, 45000);
    return () => {
      if (timer.current) window.clearInterval(timer.current);
    };
  }, [load]);

  const handleRead = async (n: AppNotification) => {
    if (n.read_at) return;
    try {
      await notificationsApi.markRead(n.id);
      setItems((prev) => prev.map((x) => (x.id === n.id ? { ...x, read_at: new Date().toISOString() } : x)));
      setCount((c) => Math.max(0, c - 1));
    } catch {
      // abaikan
    }
  };

  const handleReadAll = async () => {
    try {
      await notificationsApi.markAllRead();
      setItems((prev) => prev.map((x) => ({ ...x, read_at: x.read_at ?? new Date().toISOString() })));
      setCount(0);
    } catch {
      // abaikan
    }
  };

  return (
    <div style={{ position: 'relative' }}>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        title="Notifikasi"
        style={{ background: '#1f2937', color: '#fff', border: 'none', borderRadius: 8, padding: '.45rem .7rem', cursor: 'pointer' }}
      >
        🔔{count > 0 && <span style={{ marginLeft: 6, background: '#dc2626', borderRadius: 999, padding: '0 .45rem', fontSize: '.75rem' }}>{count}</span>}
      </button>
      {open && (
        <div style={{ position: 'absolute', right: 0, top: '2.4rem', width: 340, maxHeight: 420, overflowY: 'auto', background: '#fff', color: '#111', border: '1px solid #e5e7eb', borderRadius: 10, boxShadow: '0 10px 30px rgba(0,0,0,.15)', zIndex: 50 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '.6rem .8rem', borderBottom: '1px solid #eee' }}>
            <strong>Notifikasi</strong>
            <button type="button" onClick={handleReadAll} style={{ background: 'none', border: 'none', color: '#0d6efd', cursor: 'pointer' }}>Tandai semua dibaca</button>
          </div>
          {items.length === 0 ? (
            <p className="admin-muted" style={{ padding: '.8rem' }}>Belum ada notifikasi.</p>
          ) : (
            items.map((n) => {
              const to = targetPath(n, role);
              return (
                <div key={n.id} onClick={() => handleRead(n)} style={{ padding: '.6rem .8rem', borderBottom: '1px solid #f1f1f1', background: n.read_at ? '#fff' : '#eef4ff', cursor: 'pointer' }}>
                  <div style={{ fontSize: '.72rem', color: '#6b7280' }}>{notificationTypeLabel(n.type)} · {timeAgo(n.created_at)}</div>
                  <div style={{ fontWeight: 600 }}>{n.title}</div>
                  <div style={{ fontSize: '.85rem', color: '#374151' }}>{n.message}</div>
                  {to && (
                    <Link to={to} onClick={(e) => e.stopPropagation()} style={{ fontSize: '.8rem' }}>Lihat</Link>
                  )}
                </div>
              );
            })
          )}
          <div style={{ padding: '.6rem .8rem', textAlign: 'center' }}>
            <Link to={basePath}>Lihat semua</Link>
          </div>
        </div>
      )}
    </div>
  );
}

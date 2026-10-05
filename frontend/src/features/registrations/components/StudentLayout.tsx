import type { ReactNode } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useAuth } from '../../auth/hooks/useAuth';
import { NotificationBell } from '../../notifications/components/NotificationBell';
import '../../admin/components/admin.css';

export function StudentLayout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout();
    navigate('/login', { replace: true });
  };

  return (
    <div className="admin-shell">
      <aside className="admin-sidebar">
        <div className="admin-brand" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}><span>Ekstrakurikuler · Siswa</span><NotificationBell role={user?.role} basePath="/student/notifications" /></div>
        <div className="admin-user">
          <div className="admin-user-name">{user?.name}</div>
          <div className="admin-user-role">{user?.role}</div>
        </div>
        <nav className="admin-nav">
          <NavLink to="/student/extracurriculars" className={({ isActive }) => (isActive ? 'active' : '')}>
            Ekstrakurikuler
          </NavLink>
          <NavLink to="/student/registrations" className={({ isActive }) => (isActive ? 'active' : '')}>
            Pendaftaranku
          </NavLink>
          <NavLink to="/student/payments" className={({ isActive }) => (isActive ? 'active' : '')}>
            Pembayaranku
          </NavLink>
          <NavLink to="/student/sessions" className={({ isActive }) => (isActive ? 'active' : '')}>
            Sesi Latihan
          </NavLink>
          <NavLink to="/student/attendance" className={({ isActive }) => (isActive ? 'active' : '')}>
            Absensiku
          </NavLink>
          <NavLink to="/student/evaluations" className={({ isActive }) => (isActive ? 'active' : '')}>
            Evaluasiku
          </NavLink>
          <NavLink to="/student/dashboard" className={({ isActive }) => (isActive ? 'active' : '')}>
            Dashboard
          </NavLink>
          <NavLink to="/student/notifications" className={({ isActive }) => (isActive ? 'active' : '')}>
            Notifikasi
          </NavLink>
        </nav>
        <button className="admin-logout" onClick={handleLogout}>
          Logout
        </button>
      </aside>
      <main className="admin-main">
        <Outlet />
      </main>
    </div>
  );
}

export function RequireStudent({ children }: { children: ReactNode }) {
  const { user, isLoading } = useAuth();
  if (isLoading) return <div style={{ padding: '2rem', textAlign: 'center' }}>Memuat...</div>;
  if (!user || user.role !== 'student') {
    return (
      <div style={{ maxWidth: '600px', margin: '3rem auto', padding: '1.5rem', textAlign: 'center' }}>
        <h2>403 — Akses ditolak</h2>
        <p>Halaman ini hanya untuk siswa.</p>
        <a href="/login">Kembali ke login</a>
      </div>
    );
  }
  return <>{children}</>;
}

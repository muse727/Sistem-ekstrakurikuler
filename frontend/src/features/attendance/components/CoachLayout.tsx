import type { ReactNode } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useAuth } from '../../auth/hooks/useAuth';
import '../../admin/components/admin.css';

export function CoachLayout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout();
    navigate('/login', { replace: true });
  };

  return (
    <div className="admin-shell">
      <aside className="admin-sidebar">
        <div className="admin-brand">Ekstrakurikuler · Pelatih</div>
        <div className="admin-user">
          <div className="admin-user-name">{user?.name}</div>
          <div className="admin-user-role">{user?.role}</div>
        </div>
        <nav className="admin-nav">
          <NavLink to="/coach/sessions" className={({ isActive }) => (isActive ? 'active' : '')}>
            Sesi Latihan
          </NavLink>
          <NavLink to="/coach/attendance" className={({ isActive }) => (isActive ? 'active' : '')}>
            Absensi
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

export function RequireCoach({ children }: { children: ReactNode }) {
  const { user, isLoading } = useAuth();
  if (isLoading) return <div style={{ padding: '2rem', textAlign: 'center' }}>Memuat...</div>;
  if (!user || user.role !== 'coach') {
    return (
      <div style={{ maxWidth: '600px', margin: '3rem auto', padding: '1.5rem', textAlign: 'center' }}>
        <h2>403 — Akses ditolak</h2>
        <p>Halaman ini hanya untuk pelatih.</p>
        <a href="/login">Kembali ke login</a>
      </div>
    );
  }
  return <>{children}</>;
}

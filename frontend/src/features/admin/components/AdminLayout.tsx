import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useAuth } from '../../auth/hooks/useAuth';
import { NotificationBell } from '../../notifications/components/NotificationBell';
import './admin.css';

export function AdminLayout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout();
    navigate('/login', { replace: true });
  };

  return (
    <div className="admin-shell">
      <aside className="admin-sidebar">
        <div className="admin-brand" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}><span>Ekstrakurikuler · Admin</span><NotificationBell role={user?.role} basePath="/admin/notifications" /></div>
        <div className="admin-user">
          <div className="admin-user-name">{user?.name}</div>
          <div className="admin-user-role">{user?.role}</div>
        </div>
        <nav className="admin-nav">
          <NavLink to="/admin/academic-years" className={({ isActive }) => (isActive ? 'active' : '')}>
            Tahun Ajaran
          </NavLink>
          <NavLink to="/admin/students" className={({ isActive }) => (isActive ? 'active' : '')}>
            Siswa
          </NavLink>
          <NavLink to="/admin/coaches" className={({ isActive }) => (isActive ? 'active' : '')}>
            Pelatih
          </NavLink>
          <NavLink to="/admin/venues" className={({ isActive }) => (isActive ? 'active' : '')}>
            Venue
          </NavLink>
          <NavLink to="/admin/extracurriculars" className={({ isActive }) => (isActive ? 'active' : '')}>
            Ekstrakurikuler
          </NavLink>
          <NavLink to="/admin/registrations" className={({ isActive }) => (isActive ? 'active' : '')}>
            Registrasi
          </NavLink>
          <NavLink to="/admin/payments" className={({ isActive }) => (isActive ? 'active' : '')}>
            Pembayaran
          </NavLink>
          <NavLink to="/admin/sessions" className={({ isActive }) => (isActive ? 'active' : '')}>
            Sesi & Absensi
          </NavLink>
          <NavLink to="/admin/evaluations" className={({ isActive }) => (isActive ? 'active' : '')}>
            Evaluasi
          </NavLink>
          <NavLink to="/admin/dashboard" className={({ isActive }) => (isActive ? 'active' : '')}>
            Dashboard
          </NavLink>
          <NavLink to="/admin/reports/membership" className={({ isActive }) => (isActive ? 'active' : '')}>
            Lap. Keanggotaan
          </NavLink>
          <NavLink to="/admin/reports/payments" className={({ isActive }) => (isActive ? 'active' : '')}>
            Lap. Pembayaran
          </NavLink>
          <NavLink to="/admin/reports/attendance" className={({ isActive }) => (isActive ? 'active' : '')}>
            Lap. Kehadiran
          </NavLink>
          <NavLink to="/admin/reports/evaluations" className={({ isActive }) => (isActive ? 'active' : '')}>
            Lap. Evaluasi
          </NavLink>
          <NavLink to="/admin/notifications" className={({ isActive }) => (isActive ? 'active' : '')}>
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

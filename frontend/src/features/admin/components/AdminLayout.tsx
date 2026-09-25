import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useAuth } from '../../auth/hooks/useAuth';
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
        <div className="admin-brand">Ekstrakurikuler · Admin</div>
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

import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../../auth/hooks/useAuth';

export function RequireAuth({ children }: { children: ReactNode }) {
  const { isAuthenticated, isLoading } = useAuth();
  if (isLoading) return <div style={{ padding: '2rem', textAlign: 'center' }}>Memuat...</div>;
  if (!isAuthenticated) return <Navigate to="/login" replace />;
  return <>{children}</>;
}

export function RequireAdmin({ children }: { children: ReactNode }) {
  const { user, isLoading } = useAuth();
  if (isLoading) return <div style={{ padding: '2rem', textAlign: 'center' }}>Memuat...</div>;
  if (!user || (user.role !== 'admin' && user.role !== 'super_admin')) {
    return (
      <div style={{ maxWidth: '600px', margin: '3rem auto', padding: '1.5rem', textAlign: 'center' }}>
        <h2>403 — Akses ditolak</h2>
        <p>Halaman ini hanya untuk admin.</p>
        <a href="/login">Kembali ke login</a>
      </div>
    );
  }
  return <>{children}</>;
}

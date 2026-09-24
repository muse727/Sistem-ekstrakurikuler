import React, { useState } from 'react';
import { useAuth } from '../hooks/useAuth';

export const LoginPage: React.FC = () => {
  const { login, user, logout, isAuthenticated, isLoading } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      await login({ email, password });
    } catch (err: any) {
      if (err.response?.data?.message) {
        setError(err.response.data.message);
      } else {
        setError('Terjadi kesalahan saat login.');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  if (isLoading) {
    return <div style={{ padding: '2rem', textAlign: 'center' }}>Memuat status autentikasi...</div>;
  }

  if (isAuthenticated && user) {
    return (
      <div style={{ maxWidth: '400px', margin: '3rem auto', padding: '1.5rem', border: '1px solid #ccc', borderRadius: '8px' }}>
        <h2>Status Autentikasi</h2>
        <p><strong>Nama:</strong> {user.name}</p>
        <p><strong>Email:</strong> {user.email}</p>
        <p><strong>Role:</strong> {user.role}</p>
        {user.student && <p><strong>NIS:</strong> {user.student.student_number}</p>}
        {user.coach && <p><strong>NIP/Emp No:</strong> {user.coach.employee_number ?? '-'}</p>}
        <button
          onClick={logout}
          style={{ width: '100%', padding: '0.5rem', backgroundColor: '#dc3545', color: '#fff', border: 'none', borderRadius: '4px', cursor: 'pointer' }}
        >
          Logout
        </button>
      </div>
    );
  }

  return (
    <div style={{ maxWidth: '400px', margin: '3rem auto', padding: '1.5rem', border: '1px solid #ccc', borderRadius: '8px' }}>
      <h2>Login Sistem Ekstrakurikuler</h2>
      {error && <div style={{ color: 'red', marginBottom: '1rem' }}>{error}</div>}
      <form onSubmit={handleSubmit}>
        <div style={{ marginBottom: '1rem' }}>
          <label style={{ display: 'block', marginBottom: '0.5rem' }}>Email</label>
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            style={{ width: '100%', padding: '0.5rem', boxSizing: 'border-box' }}
          />
        </div>
        <div style={{ marginBottom: '1rem' }}>
          <label style={{ display: 'block', marginBottom: '0.5rem' }}>Password</label>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            style={{ width: '100%', padding: '0.5rem', boxSizing: 'border-box' }}
          />
        </div>
        <button
          type="submit"
          disabled={isSubmitting}
          style={{ width: '100%', padding: '0.75rem', backgroundColor: '#0d6efd', color: '#fff', border: 'none', borderRadius: '4px', cursor: 'pointer' }}
        >
          {isSubmitting ? 'Memproses...' : 'Masuk'}
        </button>
      </form>
    </div>
  );
};

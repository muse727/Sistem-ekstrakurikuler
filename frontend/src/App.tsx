import React, { useState, useEffect } from 'react';
import api from './lib/api';
import type { ApiResponse } from './types/api';

export const App: React.FC = () => {
  const [status, setStatus] = useState<'checking' | 'connected' | 'disconnected'>('checking');
  const [serverMessage, setServerMessage] = useState<string>('');

  useEffect(() => {
    const checkHealth = async () => {
      try {
        const response = await api.get<ApiResponse>('/health');
        if (response.data && response.data.success) {
          setStatus('connected');
          setServerMessage(response.data.message || 'API is healthy');
        } else {
          setStatus('disconnected');
          setServerMessage('Received invalid response');
        }
      } catch {
        setStatus('disconnected');
        setServerMessage('Unable to reach backend API');
      }
    };

    checkHealth();
  }, []);

  return (
    <div style={{ fontFamily: 'system-ui, -apple-system, sans-serif', padding: '2rem', maxWidth: '600px', margin: '0 auto' }}>
      <h1>Extracurricular Management System</h1>
      <p>Frontend is running.</p>
      
      <div style={{ marginTop: '1.5rem', padding: '1rem', border: '1px solid #e2e8f0', borderRadius: '8px', background: '#f8fafc' }}>
        <strong>Backend Status: </strong>
        {status === 'checking' && <span>Checking...</span>}
        {status === 'connected' && <span style={{ color: '#16a34a', fontWeight: 'bold' }}>Connected ({serverMessage})</span>}
        {status === 'disconnected' && <span style={{ color: '#dc2626', fontWeight: 'bold' }}>Disconnected ({serverMessage})</span>}
      </div>
    </div>
  );
};

export default App;

import type { AuthResponse, LoginCredentials, ProfileResponse } from './types';
import axios from 'axios';

const api = axios.create({
  baseURL: '/api/v1',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export const authApi = {
  login: async (credentials: LoginCredentials): Promise<AuthResponse> => {
    const res = await api.post<AuthResponse>('/auth/login', credentials);
    return res.data;
  },

  logout: async (): Promise<{ success: boolean; message: string }> => {
    const res = await api.post<{ success: boolean; message: string }>('/auth/logout');
    return res.data;
  },

  getProfile: async (): Promise<ProfileResponse> => {
    const res = await api.get<ProfileResponse>('/auth/me');
    return res.data;
  },
};

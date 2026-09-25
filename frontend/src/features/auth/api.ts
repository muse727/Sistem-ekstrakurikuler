import api from '../../lib/api';
import type { AuthResponse, LoginCredentials, ProfileResponse } from './types';

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

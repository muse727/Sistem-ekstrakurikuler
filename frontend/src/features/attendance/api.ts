import api from '../../lib/api';
import type {
  AttendanceItem,
  CheckIn,
  PaginatedResponse,
  SessionItem,
  SingleResponse,
  AttendanceStatus,
} from './types';

export interface SessionListParams {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
  academic_year_id?: number;
  extracurricular_id?: number;
  from_date?: string;
  to_date?: string;
  session_id?: number;
}

function toParams(p?: SessionListParams) {
  const out: Record<string, string | number> = {};
  if (!p) return out;
  for (const [k, v] of Object.entries(p)) {
    if (v === undefined || v === null || v === '') continue;
    out[k] = v as string | number;
  }
  return out;
}

function authDownloadUrl(path: string): string {
  const base = (import.meta.env.VITE_API_URL as string | undefined) || 'http://localhost:8000/api/v1';
  const sep = path.includes('?') ? '&' : '?';
  return `${base}${path}${sep}`;
}

export async function downloadCheckInPhoto(url: string, filename: string): Promise<void> {
  const token = localStorage.getItem('auth_token') || '';
  const res = await fetch(url, { headers: token ? { Authorization: `Bearer ${token}` } : {} });
  if (!res.ok) throw new Error(`Gagal mengunduh (${res.status})`);
  const blob = await res.blob();
  const obj = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = obj;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(obj);
}

export const coachSessionsApi = {
  list: (p?: SessionListParams) =>
    api.get<PaginatedResponse<SessionItem>>('/coach/sessions', { params: toParams(p) }).then((r) => r.data),
  get: (id: number) =>
    api.get<SingleResponse<SessionItem>>(`/coach/sessions/${id}`).then((r) => r.data),
  checkIn: (id: number, payload: { latitude: number; longitude: number; accuracy_meters: number; device_captured_at?: string; photo: File }) => {
    const form = new FormData();
    form.append('latitude', String(payload.latitude));
    form.append('longitude', String(payload.longitude));
    form.append('accuracy_meters', String(payload.accuracy_meters));
    if (payload.device_captured_at) form.append('device_captured_at', payload.device_captured_at);
    form.append('photo', payload.photo);
    return api.post<SingleResponse<CheckIn>>(`/coach/sessions/${id}/check-in`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }).then((r) => r.data);
  },
  attendance: (id: number) =>
    api.get<{ success: boolean; message: string; data: AttendanceItem[] }>(`/coach/sessions/${id}/attendance`).then((r) => r.data),
  recordBulk: (id: number, attendance: { student_id: number; status: AttendanceStatus; notes?: string }[]) =>
    api.post<{ success: boolean; message: string; data: AttendanceItem[] }>(`/coach/sessions/${id}/attendance`, { attendance }).then((r) => r.data),
  recordOne: (id: number, studentId: number, status: AttendanceStatus, notes?: string) =>
    api.post<SingleResponse<AttendanceItem>>(`/coach/sessions/${id}/attendance/${studentId}`, { status, notes }).then((r) => r.data),
  myAttendance: (p?: SessionListParams) =>
    api.get<PaginatedResponse<AttendanceItem>>('/coach/attendance', { params: toParams(p) }).then((r) => r.data),
};

export const adminSessionsApi = {
  list: (p?: SessionListParams) =>
    api.get<PaginatedResponse<SessionItem>>('/admin/sessions', { params: toParams(p) }).then((r) => r.data),
  get: (id: number) =>
    api.get<SingleResponse<SessionItem>>(`/admin/sessions/${id}`).then((r) => r.data),
  create: (payload: { extracurricular_id: number; academic_year_id?: number; venue_id?: number; session_date: string; start_time: string; end_time?: string; topic?: string; notes?: string }) =>
    api.post<SingleResponse<SessionItem>>('/admin/sessions', payload).then((r) => r.data),
  open: (id: number) =>
    api.post<SingleResponse<SessionItem>>(`/admin/sessions/${id}/open`).then((r) => r.data),
  complete: (id: number) =>
    api.post<SingleResponse<SessionItem>>(`/admin/sessions/${id}/complete`).then((r) => r.data),
  cancel: (id: number) =>
    api.post<SingleResponse<SessionItem>>(`/admin/sessions/${id}/cancel`).then((r) => r.data),
  checkIns: (id: number) =>
    api.get<{ success: boolean; message: string; data: CheckIn[] }>(`/admin/sessions/${id}/check-ins`).then((r) => r.data),
  attendance: (id: number, p?: SessionListParams) =>
    api.get<PaginatedResponse<AttendanceItem>>(`/admin/sessions/${id}/attendance`, { params: toParams(p) }).then((r) => r.data),
  recordBulk: (id: number, attendance: { student_id: number; status: AttendanceStatus; notes?: string }[]) =>
    api.post<{ success: boolean; message: string; data: AttendanceItem[] }>(`/admin/sessions/${id}/attendance`, { attendance }).then((r) => r.data),
  correct: (sessionId: number, attendanceId: number, status: AttendanceStatus, notes?: string) =>
    api.post<SingleResponse<AttendanceItem>>(`/admin/sessions/${sessionId}/attendance/${attendanceId}`, { status, notes }).then((r) => r.data),
  photoUrl: (checkInId: number) => authDownloadUrl(`/check-ins/${checkInId}/photo`),
};

export const studentSessionsApi = {
  list: (p?: SessionListParams) =>
    api.get<PaginatedResponse<SessionItem>>('/student/sessions', { params: toParams(p) }).then((r) => r.data),
  get: (id: number) =>
    api.get<SingleResponse<SessionItem>>(`/student/sessions/${id}`).then((r) => r.data),
  attendance: (p?: SessionListParams) =>
    api.get<PaginatedResponse<AttendanceItem>>('/student/attendance', { params: toParams(p) }).then((r) => r.data),
  attendanceDetail: (id: number) =>
    api.get<SingleResponse<AttendanceItem>>(`/student/attendance/${id}`).then((r) => r.data),
};

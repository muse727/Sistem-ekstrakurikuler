import api from '../../lib/api';
import type {
  AcademicYear,
  Coach,
  Extracurricular,
  PaginatedResponse,
  Schedule,
  SingleResponse,
  Student,
  Venue,
} from './types';

export interface ListParams {
  page?: number;
  per_page?: number;
  search?: string;
  [key: string]: unknown;
}

function toParams(p?: ListParams) {
  const out: Record<string, string | number> = {};
  if (!p) return out;
  for (const [k, v] of Object.entries(p)) {
    if (v === undefined || v === null || v === '') continue;
    out[k] = v as string | number;
  }
  return out;
}

export const adminApi = {
  academicYears: {
    list: (p?: ListParams) =>
      api.get<PaginatedResponse<AcademicYear>>('/admin/academic-years', { params: toParams(p) }).then((r) => r.data),
    get: (id: number) =>
      api.get<SingleResponse<AcademicYear>>(`/admin/academic-years/${id}`).then((r) => r.data),
    create: (payload: Partial<AcademicYear>) =>
      api.post<SingleResponse<AcademicYear>>('/admin/academic-years', payload).then((r) => r.data),
    update: (id: number, payload: Partial<AcademicYear>) =>
      api.put<SingleResponse<AcademicYear>>(`/admin/academic-years/${id}`, payload).then((r) => r.data),
    activate: (id: number) =>
      api.post<SingleResponse<AcademicYear>>(`/admin/academic-years/${id}/activate`).then((r) => r.data),
    deactivate: (id: number) =>
      api.post<SingleResponse<AcademicYear>>(`/admin/academic-years/${id}/deactivate`).then((r) => r.data),
  },
  students: {
    list: (p?: ListParams) =>
      api.get<PaginatedResponse<Student>>('/admin/students', { params: toParams(p) }).then((r) => r.data),
    create: (payload: Partial<Student>) =>
      api.post<SingleResponse<Student>>('/admin/students', payload).then((r) => r.data),
    update: (id: number, payload: Partial<Student>) =>
      api.put<SingleResponse<Student>>(`/admin/students/${id}`, payload).then((r) => r.data),
  },
  coaches: {
    list: (p?: ListParams) =>
      api.get<PaginatedResponse<Coach>>('/admin/coaches', { params: toParams(p) }).then((r) => r.data),
    create: (payload: Partial<Coach>) =>
      api.post<SingleResponse<Coach>>('/admin/coaches', payload).then((r) => r.data),
    update: (id: number, payload: Partial<Coach>) =>
      api.put<SingleResponse<Coach>>(`/admin/coaches/${id}`, payload).then((r) => r.data),
  },
  venues: {
    list: (p?: ListParams) =>
      api.get<PaginatedResponse<Venue>>('/admin/venues', { params: toParams(p) }).then((r) => r.data),
    create: (payload: Partial<Venue>) =>
      api.post<SingleResponse<Venue>>('/admin/venues', payload).then((r) => r.data),
    update: (id: number, payload: Partial<Venue>) =>
      api.put<SingleResponse<Venue>>(`/admin/venues/${id}`, payload).then((r) => r.data),
  },
  extracurriculars: {
    list: (p?: ListParams & { academic_year_id?: number }) =>
      api.get<PaginatedResponse<Extracurricular>>('/admin/extracurriculars', { params: toParams(p) }).then((r) => r.data),
    get: (id: number) =>
      api.get<SingleResponse<Extracurricular>>(`/admin/extracurriculars/${id}`).then((r) => r.data),
    create: (payload: Partial<Extracurricular>) =>
      api.post<SingleResponse<Extracurricular>>('/admin/extracurriculars', payload).then((r) => r.data),
    update: (id: number, payload: Partial<Extracurricular>) =>
      api.put<SingleResponse<Extracurricular>>(`/admin/extracurriculars/${id}`, payload).then((r) => r.data),
    assignCoach: (id: number, coach_id: number, role: 'primary' | 'assistant' = 'primary') =>
      api.post<SingleResponse<Extracurricular>>(`/admin/extracurriculars/${id}/coaches`, { coach_id, role }).then((r) => r.data),
    detachCoach: (id: number, coachId: number) =>
      api.delete<SingleResponse<Extracurricular>>(`/admin/extracurriculars/${id}/coaches/${coachId}`).then((r) => r.data),
    addSchedule: (id: number, payload: Partial<Schedule>) =>
      api.post<SingleResponse<Schedule>>(`/admin/extracurriculars/${id}/schedules`, payload).then((r) => r.data),
    removeSchedule: (id: number, scheduleId: number) =>
      api.delete<{ success: boolean; message: string }>(`/admin/extracurriculars/${id}/schedules/${scheduleId}`).then((r) => r.data),
  },
};

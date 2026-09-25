import api from '../../lib/api';
import type {
  PaginatedResponse,
  Registration,
  SingleResponse,
  Extracurricular,
} from './types';

export interface RegistrationListParams {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
  academic_year_id?: number;
  extracurricular_id?: number;
  student_id?: number;
}

function toParams(p?: RegistrationListParams) {
  const out: Record<string, string | number> = {};
  if (!p) return out;
  for (const [k, v] of Object.entries(p)) {
    if (v === undefined || v === null || v === '') continue;
    out[k] = v as string | number;
  }
  return out;
}

export const studentRegistrationsApi = {
  extracurriculars: (p?: RegistrationListParams) =>
    api.get<PaginatedResponse<Extracurricular>>('/student/extracurriculars', { params: toParams(p) }).then((r) => r.data),
  extracurricularDetail: (id: number) =>
    api.get<SingleResponse<Extracurricular>>(`/student/extracurriculars/${id}`).then((r) => r.data),
  list: (p?: RegistrationListParams) =>
    api.get<PaginatedResponse<Registration>>('/student/registrations', { params: toParams(p) }).then((r) => r.data),
  get: (id: number) =>
    api.get<SingleResponse<Registration>>(`/student/registrations/${id}`).then((r) => r.data),
  submit: (extracurricular_id: number) =>
    api.post<SingleResponse<Registration>>('/student/registrations', { extracurricular_id }).then((r) => r.data),
  cancel: (id: number) =>
    api.post<SingleResponse<Registration>>(`/student/registrations/${id}/cancel`).then((r) => r.data),
};

export const adminRegistrationsApi = {
  list: (p?: RegistrationListParams) =>
    api.get<PaginatedResponse<Registration>>('/admin/registrations', { params: toParams(p) }).then((r) => r.data),
  get: (id: number) =>
    api.get<SingleResponse<Registration>>(`/admin/registrations/${id}`).then((r) => r.data),
  approve: (id: number) =>
    api.post<SingleResponse<Registration>>(`/admin/registrations/${id}/approve`).then((r) => r.data),
  reject: (id: number, rejection_reason: string) =>
    api.post<SingleResponse<Registration>>(`/admin/registrations/${id}/reject`, { rejection_reason }).then((r) => r.data),
  activate: (id: number) =>
    api.post<SingleResponse<Registration>>(`/admin/registrations/${id}/activate`).then((r) => r.data),
  cancel: (id: number) =>
    api.post<SingleResponse<Registration>>(`/admin/registrations/${id}/cancel`).then((r) => r.data),
};

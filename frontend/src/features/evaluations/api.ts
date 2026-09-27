import api from '../../lib/api';
import type { AttendanceSummary, EligibleRegistration, EvaluationItem, PaginatedResponse, SingleResponse } from './types';

export interface EvaluationListParams {
  page?: number;
  per_page?: number;
  search?: string;
  academic_year_id?: number;
  extracurricular_id?: number;
  student_id?: number;
  evaluator_id?: number;
  evaluation_period?: string;
  status?: string;
  registration_id?: number;
}

function toParams(p?: EvaluationListParams) {
  const out: Record<string, string | number> = {};
  if (!p) return out;
  for (const [k, v] of Object.entries(p)) {
    if (v === undefined || v === null || v === '') continue;
    out[k] = v as string | number;
  }
  return out;
}

export interface ScorePayload {
  attendance_score?: number | null;
  activity_score?: number | null;
  skill_score?: number | null;
  discipline_score?: number | null;
  notes?: string | null;
}

export const studentEvaluationsApi = {
  list: (p?: EvaluationListParams) =>
    api.get<PaginatedResponse<EvaluationItem>>('/student/evaluations', { params: toParams(p) }).then((r) => r.data),
  get: (id: number) =>
    api.get<SingleResponse<EvaluationItem>>(`/student/evaluations/${id}`).then((r) => r.data),
  summary: (id: number) =>
    api.get<{ success: boolean; message: string; data: { evaluation: EvaluationItem; attendance_summary: AttendanceSummary } }>(`/student/evaluations/${id}/summary`).then((r) => r.data),
};

export const coachEvaluationsApi = {
  list: (p?: EvaluationListParams) =>
    api.get<PaginatedResponse<EvaluationItem>>('/coach/evaluations', { params: toParams(p) }).then((r) => r.data),
  eligible: (p?: EvaluationListParams) =>
    api.get<PaginatedResponse<EligibleRegistration>>('/coach/evaluations/eligible', { params: toParams(p) }).then((r) => r.data),
  get: (id: number) =>
    api.get<SingleResponse<EvaluationItem>>(`/coach/evaluations/${id}`).then((r) => r.data),
  create: (payload: { registration_id: number; evaluation_period: string } & ScorePayload) =>
    api.post<SingleResponse<EvaluationItem>>('/coach/evaluations', payload).then((r) => r.data),
  update: (id: number, payload: ScorePayload) =>
    api.patch<SingleResponse<EvaluationItem>>(`/coach/evaluations/${id}`, payload).then((r) => r.data),
  publish: (id: number) =>
    api.post<SingleResponse<EvaluationItem>>(`/coach/evaluations/${id}/publish`).then((r) => r.data),
  summary: (id: number) =>
    api.get<{ success: boolean; message: string; data: { evaluation: EvaluationItem; attendance_summary: AttendanceSummary } }>(`/coach/evaluations/${id}/summary`).then((r) => r.data),
};

export const adminEvaluationsApi = {
  list: (p?: EvaluationListParams) =>
    api.get<PaginatedResponse<EvaluationItem>>('/admin/evaluations', { params: toParams(p) }).then((r) => r.data),
  get: (id: number) =>
    api.get<SingleResponse<EvaluationItem>>(`/admin/evaluations/${id}`).then((r) => r.data),
  update: (id: number, payload: ScorePayload) =>
    api.patch<SingleResponse<EvaluationItem>>(`/admin/evaluations/${id}`, payload).then((r) => r.data),
  publish: (id: number) =>
    api.post<SingleResponse<EvaluationItem>>(`/admin/evaluations/${id}/publish`).then((r) => r.data),
  unpublish: (id: number) =>
    api.post<SingleResponse<EvaluationItem>>(`/admin/evaluations/${id}/unpublish`).then((r) => r.data),
  summary: (id: number) =>
    api.get<{ success: boolean; message: string; data: { evaluation: EvaluationItem; attendance_summary: AttendanceSummary } }>(`/admin/evaluations/${id}/summary`).then((r) => r.data),
};

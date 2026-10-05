import api from '../../lib/api';
import type { AppNotification, PaginatedResponse, SingleResponse } from './types';

interface RawPaginated {
  success: boolean;
  message: string;
  data: AppNotification[] | { data: AppNotification[] };
  meta?: PaginatedResponse<AppNotification>['meta'];
  links?: PaginatedResponse<AppNotification>['links'];
}

export interface NotificationListParams {
  page?: number;
  per_page?: number;
  unread?: number | boolean;
}

function toParams(p?: NotificationListParams) {
  const out: Record<string, string | number> = {};
  if (!p) return out;
  if (p.page !== undefined) out.page = p.page;
  if (p.per_page !== undefined) out.per_page = p.per_page;
  if (p.unread !== undefined) out.unread = p.unread ? 1 : 0;
  return out;
}

export function extractItems(raw: RawPaginated): AppNotification[] {
  if (Array.isArray(raw.data)) return raw.data;
  if (raw.data && Array.isArray((raw.data as { data: AppNotification[] }).data)) {
    return (raw.data as { data: AppNotification[] }).data;
  }
  return [];
}

export const notificationsApi = {
  list: (p?: NotificationListParams) =>
    api.get<RawPaginated>('/notifications', { params: toParams(p) }).then((r) => ({
      raw: r.data,
      items: extractItems(r.data),
      meta: r.data.meta,
    })),
  unreadCount: () =>
    api.get<{ success: boolean; message: string; data: { unread_count: number } }>('/notifications/unread-count').then((r) => r.data.data.unread_count),
  markRead: (id: number) =>
    api.post<SingleResponse<AppNotification>>(`/notifications/${id}/read`).then((r) => r.data),
  markAllRead: () =>
    api.post<{ success: boolean; message: string; data: { updated: number } }>('/notifications/read-all').then((r) => r.data),
};

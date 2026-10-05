<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->listForUser((int) $request->user()->id, $request->all());

        return ApiResponse::paginate(
            $paginator,
            NotificationResource::collection($paginator->items()),
            'Notifications retrieved successfully'
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success(
            ['unread_count' => $this->service->unreadCount((int) $request->user()->id)],
            'Unread count retrieved successfully'
        );
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        if ((int) $notification->user_id !== (int) $request->user()->id) {
            return ApiResponse::error('Notification not found.', null, 404);
        }

        return ApiResponse::success(
            new NotificationResource($this->service->markAsRead($notification)),
            'Notification marked as read'
        );
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = $this->service->markAllAsRead((int) $request->user()->id);

        return ApiResponse::success(
            ['updated' => $updated],
            'All notifications marked as read'
        );
    }
}

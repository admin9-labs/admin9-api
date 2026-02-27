<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manage current user's database notifications.
 */
#[Group('Notifications')]
class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * List current user's notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->paginate();

        return $this->success($notifications);
    }

    /**
     * Mark a single notification as read.
     */
    public function read(Request $request, string $notification): JsonResponse
    {
        $this->notificationService->markAsRead($request->user(), $notification);

        return $this->success(message: 'Notification marked as read');
    }

    /**
     * Mark all notifications as read.
     */
    public function readAll(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($request->user());

        return $this->success(['updated' => $count]);
    }

    /**
     * Delete a single notification.
     */
    public function destroy(Request $request, string $notification): JsonResponse
    {
        $this->notificationService->delete($request->user(), $notification);

        return $this->success(message: 'Notification deleted');
    }

    /**
     * Delete all notifications.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $count = $this->notificationService->deleteAll($request->user());

        return $this->success(['deleted' => $count]);
    }

    /**
     * Get unread notification count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->notificationService->unreadCount($request->user());

        return $this->success(['count' => $count]);
    }
}

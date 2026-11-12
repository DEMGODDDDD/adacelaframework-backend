<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Notification::where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $request->user()->id);

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->latest()->paginate($request->get('per_page', 15));

        return NotificationResource::collection($notifications)->response();
    }

    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->notifiable_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        $notification->markAsRead();

        return (new NotificationResource($notification))->response();
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        Notification::where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\TaskNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaskNotificationController extends Controller
{
    public function page(Request $request)
    {
        $user = $request->user();
        $perPage = $request->get('per_page', 5);

        $notifications = TaskNotification::query()
            ->with(['task:id,title,status,priority,due_date,assignee_id,assigned_by,created_at,updated_at', 'task.assignee:id,name', 'task.assignor:id,name'])
            ->where('user_id', $user->id)
            ->orderBy('is_read')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Task/Notifications', [
            'notifications' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'from' => $notifications->firstItem(),
                'to' => $notifications->lastItem(),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = TaskNotification::query()
            ->with(['task:id,title,status'])
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->latest()
            ->limit(20)
            ->get(['id', 'task_id', 'type', 'title', 'message', 'created_at']);

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $notifications->count(),
        ]);
    }

    public function markAsRead(Request $request, TaskNotification $notification)
    {
        if ((int) $notification->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function markTaskNotificationsRead(Request $request)
    {
        $validated = $request->validate([
            'task_id' => 'required|exists:tasks,id',
        ]);

        TaskNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('task_id', $validated['task_id'])
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request)
    {
        TaskNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return redirect()->back()->with('success', 'All notifications marked as complete.');
    }
}

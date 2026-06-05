<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaskController extends Controller
{
    public function index()
    {
        $tasks = Task::with(['assignee', 'assignor', 'creator', 'updater'])->get();
        $users = User::all(['id', 'name', 'role', 'profile_photo']);

        return Inertia::render('Task/Index', [
            'tasks' => $tasks,
            'users' => $users,
        ]);
    }

    public function calendar()
    {
        $tasks = Task::with(['assignee', 'assignor', 'creator', 'updater'])->get();
        $users = User::all(['id', 'name', 'role']);

        return Inertia::render('Task/Calendar', [
            'tasks' => $tasks,
            'users' => $users,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|string',
            'priority' => 'required|string',
            'category' => 'nullable|string',
            'due_date' => 'nullable|date',
            'assignee_id' => 'nullable|exists:users,id',
        ]);

        $task = new Task($validated);
        $task->created_by = auth()->id();
        
        if ($request->has('assignee_id') && $request->assignee_id) {
            $task->assigned_by = auth()->id();
        }
        
        $task->save();

        if (!empty($task->assignee_id)) {
            TaskNotification::create([
                'user_id' => $task->assignee_id,
                'task_id' => $task->id,
                'type' => 'task_assigned',
                'title' => $task->title,
                'message' => $task->description ?: 'No description provided.',
                'is_read' => false,
                'created_by' => auth()->id(),
            ]);
        }

        return redirect()->back()->with('success', 'Task created successfully');
    }

    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|string',
            'priority' => 'required|string',
            'category' => 'nullable|string',
            'due_date' => 'nullable|date',
            'assignee_id' => 'nullable|exists:users,id',
        ]);

        $oldAssigneeId = $task->assignee_id;
        
        $task->fill($validated);
        $task->updated_by = auth()->id();
        
        // Update assigned_by if assignee changed
        if ($task->assignee_id != $oldAssigneeId && $task->assignee_id) {
            $task->assigned_by = auth()->id();

            TaskNotification::create([
                'user_id' => $task->assignee_id,
                'task_id' => $task->id,
                'type' => 'task_assigned',
                'title' => $task->title,
                'message' => $task->description ?: 'No description provided.',
                'is_read' => false,
                'created_by' => auth()->id(),
            ]);
        }

        if (($oldAssigneeId !== null || $task->assignee_id !== null) && $task->status === 'completed') {
            TaskNotification::where('task_id', $task->id)
                ->where('user_id', $task->assignee_id ?: $oldAssigneeId)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);
        }

        $task->save();

        return redirect()->back()->with('success', 'Task updated successfully');
    }

    public function destroy(Task $task)
    {
        $task->delete();
        return redirect()->back()->with('success', 'Task deleted successfully');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:tasks,id',
        ]);

        Task::whereIn('id', $validated['ids'])->delete();

        return redirect()->back()->with('success', 'Selected tasks deleted successfully');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Todo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TodoController extends Controller
{
    public function index(Request $request)
    {
        // User is authenticated (middleware ensures this)
        $query = Todo::where('user_id', $request->user()->id);

        // Sort by priority (high, medium, low) then by created_at
        $query->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END")
              ->orderBy('created_at', 'desc');

        return $query->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high',
            'file' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $data = [
            'title' => $request->title,
            'description' => $request->description,
            'priority' => $request->priority ?? 'medium',
            'user_id' => $request->user()->id,
        ];

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('todos', $fileName, 'public');
            $data['file_path'] = $filePath;
        }

        $todo = Todo::create($data);

        return response()->json($todo, 201);
    }

    public function show(Request $request, string $id)
    {
        $todo = Todo::where('user_id', $request->user()->id)->findOrFail($id);
        return response()->json($todo);
    }

    public function update(Request $request, string $id)
    {
        $todo = Todo::where('user_id', $request->user()->id)->findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high',
            'file' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $data = [
            'title' => $request->title,
            'description' => $request->description,
        ];

        if ($request->filled('priority')) {
            $data['priority'] = $request->priority;
        }

        if ($request->hasFile('file')) {
            if ($todo->file_path && Storage::disk('public')->exists($todo->file_path)) {
                Storage::disk('public')->delete($todo->file_path);
            }

            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('todos', $fileName, 'public');
            $data['file_path'] = $filePath;
        }

        $todo->update($data);

        return response()->json($todo);
    }

    public function destroy(Request $request, string $id)
    {
        $todo = Todo::where('user_id', $request->user()->id)->findOrFail($id);

        if ($todo->file_path && Storage::disk('public')->exists($todo->file_path)) {
            Storage::disk('public')->delete($todo->file_path);
        }

        $todo->delete();

        return response()->json(null, 204);
    }
}

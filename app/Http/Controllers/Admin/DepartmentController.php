<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index()
    {
        return response()->json(
            Department::orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                'unique:departments,code',
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'is_active' => [
                'boolean',
            ],
        ]);

        $department = Department::create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Department created successfully.',
            'department' => $department,
        ], 201);
    }

    public function update(
        Request $request,
        Department $department
    ) {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique(
                    'departments',
                    'code'
                )->ignore($department->id),
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'is_active' => [
                'boolean',
            ],
        ]);

        $department->update([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Department updated successfully.',
            'department' => $department->fresh(),
        ]);
    }

    public function destroy(
        Department $department
    ) {
        if ($department->expenses()->exists()) {
            return response()->json([
                'message' =>
                    'This department has expenses and cannot be deleted. Deactivate it instead.'
            ], 422);
        }

        $department->delete();

        return response()->json([
            'message' => 'Department deleted successfully.',
        ]);
    }

    public function toggle(
        Department $department
    ) {
        $department->update([
            'is_active' => !$department->is_active,
        ]);

        return response()->json([
            'message' => 'Department status updated.',
            'department' => $department->fresh(),
        ]);
    }
}

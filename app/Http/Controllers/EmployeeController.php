<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\AuditService;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q', ''));

        return view('accounting.employees.index', [
            'employees' => Employee::query()
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('name_en', 'like', "%{$search}%")
                        ->orWhere('name_ar', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%");
                }))
                ->orderBy('name_en')
                ->get(),
            'search' => $search,
        ]);
    }

    public function create()
    {
        return view('accounting.employees.form', ['employee' => new Employee(['is_active' => true])]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $employee = Employee::create($this->validated($request) + ['created_by' => $request->user()->id]);
        $audit->record('employee_created', $employee, null, $employee->toArray());

        return redirect()->route('accounting.employees.index')->with('status', __('Employee created.'));
    }

    public function edit(Employee $employee)
    {
        return view('accounting.employees.form', compact('employee'));
    }

    public function update(Request $request, Employee $employee, AuditService $audit)
    {
        $before = $employee->toArray();
        $employee->update($this->validated($request) + ['updated_by' => $request->user()->id]);
        $audit->record('employee_updated', $employee, $before, $employee->fresh()->toArray());

        return redirect()->route('accounting.employees.index')->with('status', __('Employee updated.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}

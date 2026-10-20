<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', [
            'users' => User::orderBy('name')->paginate(25),
        ]);
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['role' => 'viewer', 'is_active' => true])]);
    }

    public function store(Request $request, AuditService $audit)
    {
        DB::transaction(function () use ($request, $audit) {
            $user = User::create($this->validated($request));
            $audit->record('user_created', $user, null, $user->toArray());
        });

        return redirect()->route('users.index')->with('status', 'User created.');
    }

    public function edit(User $user)
    {
        return view('users.form', compact('user'));
    }

    public function update(Request $request, User $user, AuditService $audit)
    {
        $data = $this->validated($request, $user);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $before = $user->toArray();
        DB::transaction(function () use ($user, $data, $audit, $before) {
            $user->update($data);
            $audit->record('user_updated', $user, $before, $user->fresh()->toArray());
        });

        return redirect()->route('users.index')->with('status', 'User updated.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'is_active' => ['sometimes', 'boolean'],
        ]) + ['is_active' => false];
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    /**
     * All registered users with their attendance totals.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search'));

        $employees = User::query()
            ->withCount('attendances')
            ->withMax('attendances', 'recorded_at')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.employees', [
            'employees' => $employees,
            'search' => $search,
        ]);
    }

    /**
     * Promote or demote a user.
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_EMPLOYEE])],
        ]);

        if ($user->id === $request->user()->id && $data['role'] !== User::ROLE_ADMIN) {
            return back()->withErrors(['role' => 'You cannot remove your own administrator access.']);
        }

        $user->update(['role' => $data['role']]);

        return back()->with('status', $user->name.' is now '.($data['role'] === User::ROLE_ADMIN ? 'an administrator' : 'an employee').'.');
    }
}

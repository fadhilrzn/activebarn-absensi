<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\ValidationMessages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Kelola akun admin dan resepsionis. Hanya admin. */
class UserController
{
    public function index(): View
    {
        return view('settings.users', [
            'users' => User::orderBy('role')->orderBy('name')->get(),
            'roles' => UserRole::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ], ValidationMessages::messages(), ValidationMessages::attributes());

        User::create($data + ['is_active' => true]);

        return redirect()->route('users.index')->with('status', "Akun {$data['name']} dibuat. Minta pemiliknya mengganti kata sandi di menu Akun saya.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ], ValidationMessages::messages(), ValidationMessages::attributes());
        $active = $request->boolean('is_active');

        // Mencegah admin terkunci dari sistem karena mengubah akunnya sendiri.
        if ($user->is($request->user()) && ($data['role'] !== $user->role?->value || ! $active)) {
            return back()->withErrors(['user' => 'Anda tidak dapat mengubah peran atau menonaktifkan akun Anda sendiri.']);
        }

        $user->update(['role' => $data['role'], 'is_active' => $active]);

        // Sesi yang sedang berjalan ikut dihentikan, supaya akun nonaktif tidak bisa terus dipakai.
        if (! $active) {
            $this->endSessions($user);
        }

        return redirect()->route('users.index')->with('status', "Akun {$user->name} diperbarui.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'Ubah kata sandi Anda sendiri di menu Akun saya.']);
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ], ValidationMessages::messages(), ValidationMessages::attributes());

        $user->update(['password' => $data['password']]);
        $this->endSessions($user);

        return redirect()->route('users.index')->with('status', "Kata sandi {$user->name} diganti. Sampaikan kata sandi baru kepadanya.");
    }

    private function endSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}

<?php

namespace App\Http\Controllers;

use App\Support\ValidationMessages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Profil dan kata sandi milik pengguna yang sedang masuk (admin maupun resepsionis). */
class AccountController
{
    public function edit(Request $request): View
    {
        return view('account.edit', ['user' => $request->user()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ], ValidationMessages::messages(), ValidationMessages::attributes());

        $user->update($data);

        return redirect()->route('account.edit')->with('status', 'Profil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed', 'different:current_password'],
        ], ValidationMessages::messages(), ValidationMessages::attributes());

        $request->user()->update(['password' => $data['password']]);

        return redirect()->route('account.edit')->with('status', 'Kata sandi diganti.');
    }
}

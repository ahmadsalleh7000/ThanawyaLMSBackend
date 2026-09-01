<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function store(Request $request, LoginEventService $events): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials + ['role' => User::ROLE_ADMIN, 'is_active' => true], $request->boolean('remember'))) {
            $events->record($request, User::where('email', $credentials['email'])->first(), $credentials['email'], 'admin_web', false, 'invalid_credentials_or_inactive');
            throw ValidationException::withMessages(['email' => 'بيانات دخول الإدارة غير صحيحة.']);
        }
        $request->session()->regenerate();
        $events->record($request, $request->user(), $credentials['email'], 'admin_web', true);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

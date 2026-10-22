<?php

namespace App\Http\Controllers;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StopImpersonationController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless($request->session()->has('impersonator_id'), 403);
        $admin = User::query()->whereKey($request->session()->get('impersonator_id'))->first();
        abort_unless($admin?->canAccessPanel(Filament::getPanel('admin')), 403);

        $request->session()->forget('impersonator_id');
        Auth::login($admin);
        $request->session()->forget('password_hash_web');
        $request->session()->regenerate();

        return redirect('/admin/users');
    }
}

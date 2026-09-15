<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderByDesc('created_at')->paginate(50);

        return view('admin.users.index', compact('users'));
    }

    public function toggleBan(User $user)
    {
        if ($user->is_admin) {
            return back()->with('error', '管理者はBAN対象外です。');
        }

        $user->update(['is_banned' => ! $user->is_banned]);
        $label = $user->is_banned ? 'BANしました' : 'BAN解除しました';

        return back()->with('success', "{$user->name}を{$label}。");
    }
}

<?php
// app/Http/Controllers/DashboardController.php
namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard', [
            'users' => User::select('id', 'name', 'email', 'is_admin', 'last_login_at')->get(),
            'totalUsers' => User::count(),
            'totalMessages' => \App\Models\Message::count(),
        ]);
    }
}

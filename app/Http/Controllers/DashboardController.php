<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Services\Dashboard\AdminDashboard;
use App\Services\Dashboard\TrainerDashboard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, AdminDashboard $admin, TrainerDashboard $trainer): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard/Index', $user->role === UserRole::Trainer ? $trainer->build($user) : $admin->build());
    }
}

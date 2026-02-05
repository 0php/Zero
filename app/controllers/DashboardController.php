<?php

namespace App\Controllers;

use Zero\Lib\Http\Request;
use Zero\Lib\Auth;

/**
 * Display the authenticated user's dashboard.
 */
class DashboardController
{
    /**
     * Render the dashboard view with the current user context.
     */
    public function index(?string $lang = null)
    {
        $request = Request::instance();
        $user = Auth::user();

        return view('pages/dashboard', [
            'user' => $user,
        ]);
    }
}

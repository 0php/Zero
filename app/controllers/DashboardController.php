<?php

namespace App\Controllers;

use Zero\Lib\Http\Request;
use Zero\Lib\Auth;

class DashboardController
{
    public function index()
    {
        $request = Request::instance();
        $user = Auth::user();

        return view('pages/dashboard', [
            'user' => $user,
        ]);
    }
}

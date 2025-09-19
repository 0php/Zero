<?php

namespace App\Controllers;

use App\Services\Dashboard\QueryExamples;
use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;

class DashboardController
{
    public function index()
    {
        $examples = QueryExamples::build();
        $request = Request::instance();

        if ($request->expectsJson()) {
            return Response::json([
                'examples' => $examples,
            ]);
        }

        return view('pages/dashboard', [
            'examples' => $examples,
            'title' => 'Dashboard',
            'subtitle' => 'Quick look at sample DBML queries and your app data.',
        ]);
    }
}

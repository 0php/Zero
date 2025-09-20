<?php

namespace App\Controllers;

use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;

class HomeController
{
    public function index()
    {
        $request = Request::instance();
        return view('pages/home');
    }
}

<?php
namespace App\Middlewares;

use Zero\Lib\Auth\Auth as AuthManager;
use Zero\Lib\Http\Response;
use Zero\Lib\Session;

class Auth
{
    public function handle(): ?Response
    {
        if (AuthManager::user()) {
            return null;
        }

        $current = $_SERVER['REQUEST_URI'] ?? '/';
        Session::set('auth_redirect', $current);

        AuthManager::logout();
        return Response::redirect('/login');
    }
}

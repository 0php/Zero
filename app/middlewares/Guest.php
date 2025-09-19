<?php

namespace App\Middlewares;

use Zero\Lib\Auth\Auth as AuthManager;
use Zero\Lib\Http\Response;
use Zero\Lib\Session;

class Guest
{
    public function handle(): ?Response
    {
        if (! AuthManager::user()) {
            return null;
        }

        $target = Session::get('auth_redirect') ?: '/';

        return Response::redirect($target);
    }
}

<?php

namespace App\Middlewares;

use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;
use Zero\Lib\Auth\Auth;
use App\Models\RoleUser;

class Role
{
    public function handle(Request $request, ...$arguments): ?Response
    {
        
        if(!Auth::user()->hasManyRoles($arguments[0] ?? $arguments)) {
            return Response::redirect('/');
        }

        return null;
    }
}

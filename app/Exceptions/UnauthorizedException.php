<?php

namespace App\Exceptions;

use Exception;

class UnauthorizedException extends Exception
{
    public function register()
    {
        $this->renderable(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) {
            return response()->json([
                'responseMessage' => 'You do not have the required authorization.',
                'responseStatus'  => 403,
            ]);
        });
    }
}

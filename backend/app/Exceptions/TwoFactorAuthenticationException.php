<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Support\Responsable;

class TwoFactorAuthenticationException extends Exception
{
    protected $response;

    /**
     * Report or log an exception.
     */
    public function report()
    {
        // 2FA要求は正常な動作なのでログを出力しない
        return false;
    }

    public function __construct(Responsable $response)
    {
        $this->response = $response;
        parent::__construct('Two Factor Authentication Required');
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render($request)
    {
        return $this->response->toResponse($request);
    }
}
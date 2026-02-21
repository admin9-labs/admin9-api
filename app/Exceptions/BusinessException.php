<?php

namespace App\Exceptions;

use Mitoop\Http\Exceptions\ClientSafeException;

/**
 * @scaffold
 */
class BusinessException extends ClientSafeException
{
    public function __construct(string $message, int $errorCode = 400)
    {
        parent::__construct($message, null, $errorCode);
    }
}

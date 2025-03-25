<?php

declare(strict_types=1);

namespace App\Exceptions;

class CartException extends \Exception
{
    public function __construct($message = "Cart operation failed", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
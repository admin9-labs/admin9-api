<?php

namespace App\Services\Traits;

use Illuminate\Database\QueryException;

trait DetectsUniqueViolation
{
    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $code = (string) $e->errorInfo[0];

        return in_array($code, ['23000', '23505']) || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }
}

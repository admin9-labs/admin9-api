<?php

namespace App\Filters;

use Mitoop\LaravelQueryBuilder\AbstractFilter;
use Mitoop\LaravelQueryBuilder\ValueResolvers\Like;

/**
 * @scaffold
 */
class AuditLogFilter extends AbstractFilter
{
    protected array $allowedSorts = ['id', 'created_at'];

    protected function rules(): array
    {
        return [
            'id',
            'log_name',
            'event',
            'causer_id',
            'description|like' => new Like,
            'date_from:created_at|gte',
            'date_to:created_at|lte',
        ];
    }
}

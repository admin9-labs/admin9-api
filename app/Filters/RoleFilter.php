<?php

namespace App\Filters;

use Mitoop\LaravelQueryBuilder\AbstractFilter;
use Mitoop\LaravelQueryBuilder\ValueResolvers\Like;

/**
 * @scaffold
 */
class RoleFilter extends AbstractFilter
{
    protected array $allowedSorts = ['id', 'created_at'];

    protected function rules(): array
    {
        return [
            'id',
            'name|like' => new Like,
        ];
    }
}

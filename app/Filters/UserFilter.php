<?php

namespace App\Filters;

use Mitoop\LaravelQueryBuilder\AbstractFilter;
use Mitoop\LaravelQueryBuilder\ValueResolvers\LikeAny;

/**
 * @scaffold
 */
class UserFilter extends AbstractFilter
{
    protected array $allowedSorts = ['id', 'created_at'];

    protected function rules(): array
    {
        return [
            'id',
            'is_active',
            'name',
            'email',
            'keyword|like_any' => new LikeAny(['name', 'email']),
        ];
    }
}

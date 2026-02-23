<?php

namespace App\Filters;

use Mitoop\LaravelQueryBuilder\AbstractFilter;
use Mitoop\LaravelQueryBuilder\ValueResolvers\Like;

/**
 * @scaffold
 */
class DictionaryTypeFilter extends AbstractFilter
{
    protected array $allowedSorts = ['id', 'sort', 'created_at'];

    protected function rules(): array
    {
        return [
            'id',
            'name|like' => new Like,
            'code|like' => new Like,
            'is_active',
        ];
    }
}

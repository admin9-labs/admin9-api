<?php

namespace App\Filters;

use Mitoop\LaravelQueryBuilder\AbstractFilter;
use Mitoop\LaravelQueryBuilder\ValueResolvers\Like;

/**
 * @scaffold
 */
class DictionaryItemFilter extends AbstractFilter
{
    protected array $allowedSorts = ['id', 'sort', 'created_at'];

    protected function rules(): array
    {
        return [
            'id',
            'dictionary_type_id',
            'label|like' => new Like,
            'value|like' => new Like,
            'is_active',
        ];
    }
}

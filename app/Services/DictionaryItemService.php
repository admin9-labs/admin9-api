<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\DictionaryItem;
use App\Services\Traits\DetectsUniqueViolation;
use Illuminate\Database\QueryException;

/**
 * @scaffold
 */
class DictionaryItemService
{
    use DetectsUniqueViolation;

    /**
     * @throws BusinessException
     */
    public function createItem(array $data): DictionaryItem
    {
        try {
            return DictionaryItem::create($data);
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                throw new BusinessException('Dictionary item value already exists in this type');
            }
            throw $e;
        }
    }

    /**
     * @throws BusinessException
     */
    public function updateItem(DictionaryItem $item, array $data): DictionaryItem
    {
        try {
            $item->update($data);

            return $item;
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                throw new BusinessException('Dictionary item value already exists in this type');
            }
            throw $e;
        }
    }

    public function deleteItem(DictionaryItem $item): void
    {
        $itemId = $item->id;
        $itemValue = $item->value;
        $typeId = $item->dictionary_type_id;

        $item->delete();

        activity('dict_item')
            ->causedBy(auth()->user())
            ->event('deleted')
            ->withProperties([
                'old' => ['item_id' => $itemId, 'item_value' => $itemValue, 'dictionary_type_id' => $typeId],
            ])
            ->log('Dictionary item deleted');
    }
}

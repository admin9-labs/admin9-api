<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\DictionaryType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Context;

/**
 * @scaffold
 */
class DictionaryTypeService
{
    /**
     * @throws BusinessException
     */
    public function createType(array $data): DictionaryType
    {
        try {
            return DictionaryType::create($data);
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                throw new BusinessException('Dictionary type name or code already exists', 422);
            }
            throw $e;
        }
    }

    /**
     * @throws BusinessException
     */
    public function updateType(DictionaryType $type, array $data): DictionaryType
    {
        try {
            $type->update($data);

            return $type;
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                throw new BusinessException('Dictionary type name or code already exists', 422);
            }
            throw $e;
        }
    }

    /**
     * @throws BusinessException
     */
    public function deleteType(DictionaryType $type): void
    {
        if ($type->items()->exists()) {
            throw new BusinessException('Cannot delete dictionary type that has items. Remove items first', 403);
        }

        $typeId = $type->id;
        $typeCode = $type->code;

        $type->delete();

        activity('dict_type')
            ->causedBy(auth()->user())
            ->event('deleted')
            ->withProperties(array_filter([
                'type_id' => $typeId,
                'type_code' => $typeCode,
                'ip' => Context::get('ip'),
            ], fn ($v) => $v !== null))
            ->log('deleted');
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $code = (string) $e->errorInfo[0];

        return in_array($code, ['23000', '23505']) || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }
}

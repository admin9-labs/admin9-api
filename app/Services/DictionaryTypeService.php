<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\DictionaryType;
use App\Services\Traits\DetectsUniqueViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * @scaffold
 */
class DictionaryTypeService
{
    use DetectsUniqueViolation;

    // Caching pattern example: use a constant for the cache key prefix so it's easy to find and manage.
    private const CACHE_KEY_LIST = 'dict_types:list';

    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get paginated dictionary types with caching.
     *
     * Cache::remember() example — caches the full query result per page/filter combination.
     * Downstream projects can adopt this pattern for any read-heavy, rarely-changing data.
     */
    public function list(string $filterClass): mixed
    {
        $cacheKey = self::CACHE_KEY_LIST.':'.md5(request()->fullUrl());

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filterClass) {
            return DictionaryType::query()
                ->filter($filterClass)
                ->paginate();
        });
    }

    /**
     * @throws BusinessException
     */
    public function createType(array $data): DictionaryType
    {
        try {
            $type = DictionaryType::create($data);
            $this->clearListCache();

            return $type;
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                throw new BusinessException('Dictionary type name or code already exists');
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
            $this->clearListCache();

            return $type;
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                throw new BusinessException('Dictionary type name or code already exists');
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
            throw new BusinessException('Cannot delete dictionary type that has items. Remove items first');
        }

        $typeId = $type->id;
        $typeCode = $type->code;

        $type->delete();

        $this->clearListCache();

        activity('dict_type')
            ->causedBy(auth()->user())
            ->event('deleted')
            ->withProperties([
                'old' => ['type_id' => $typeId, 'type_code' => $typeCode],
            ])
            ->log('Dictionary type deleted');
    }

    /**
     * Flush all cached list pages. Uses Cache::forget() with a tag-like prefix approach.
     * For production with many cache variations, consider using Cache::tags() (requires Redis/Memcached).
     */
    private function clearListCache(): void
    {
        Cache::forget(self::CACHE_KEY_LIST);
    }
}

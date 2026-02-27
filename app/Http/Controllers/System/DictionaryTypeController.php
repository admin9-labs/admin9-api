<?php

namespace App\Http\Controllers\System;

use App\Exceptions\BusinessException;
use App\Filters\DictionaryTypeFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\System\DictionaryTypeRequest;
use App\Models\DictionaryType;
use App\Services\DictionaryTypeService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

/**
 * @scaffold — Dictionary type CRUD + item query.
 */
#[Group('Dictionary Types', weight: 6)]
class DictionaryTypeController extends Controller
{
    public function __construct(
        private readonly DictionaryTypeService $dictionaryTypeService,
    ) {}

    /**
     * List dictionary types.
     */
    public function index(): JsonResponse
    {
        $types = $this->dictionaryTypeService->list(DictionaryTypeFilter::class);

        return $this->success($types);
    }

    /**
     * Create dictionary type.
     */
    public function store(DictionaryTypeRequest $request): JsonResponse
    {
        $type = $this->dictionaryTypeService->createType($request->validated());

        return $this->success($type);
    }

    /**
     * Get dictionary type detail.
     */
    public function show(DictionaryType $dictType): JsonResponse
    {
        $dictType->load('items');

        return $this->success($dictType);
    }

    /**
     * Update dictionary type.
     *
     * @throws BusinessException
     */
    public function update(DictionaryTypeRequest $request, DictionaryType $dictType): JsonResponse
    {
        $type = $this->dictionaryTypeService->updateType($dictType, $request->validated());

        return $this->success($type);
    }

    /**
     * Delete dictionary type.
     *
     * @throws BusinessException
     */
    public function destroy(DictionaryType $dictType): JsonResponse
    {
        $this->dictionaryTypeService->deleteType($dictType);

        return $this->success();
    }

    /**
     * Get active dictionary items by type code.
     *
     * @response array<int, array{id: int, dictionary_type_id: int, label: string, value: string, sort: int, is_active: bool}>
     */
    public function items(string $code): JsonResponse
    {
        $type = DictionaryType::where('code', $code)->where('is_active', true)->first();

        if (! $type) {
            return $this->error('Dictionary type not found', 404);
        }

        $items = $type->items()->where('is_active', true)->orderBy('sort')->get();

        return $this->success($items);
    }
}

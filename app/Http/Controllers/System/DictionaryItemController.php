<?php

namespace App\Http\Controllers\System;

use App\Exceptions\BusinessException;
use App\Filters\DictionaryItemFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\System\DictionaryItemRequest;
use App\Models\DictionaryItem;
use App\Services\DictionaryItemService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

/**
 * @scaffold — Dictionary item CRUD.
 */
#[Group('Dictionary Items', weight: 7)]
class DictionaryItemController extends Controller
{
    public function __construct(
        private readonly DictionaryItemService $dictionaryItemService,
    ) {}

    /**
     * List dictionary items.
     */
    public function index(): JsonResponse
    {
        $items = DictionaryItem::query()
            ->filter(DictionaryItemFilter::class)
            ->with('dictionaryType:id,name,code')
            ->paginate();

        return $this->success($items);
    }

    /**
     * Create dictionary item.
     *
     * @throws BusinessException
     */
    public function store(DictionaryItemRequest $request): JsonResponse
    {
        $item = $this->dictionaryItemService->createItem($request->validated());

        return $this->success($item);
    }

    /**
     * Get dictionary item detail.
     */
    public function show(DictionaryItem $dictItem): JsonResponse
    {
        $dictItem->load('dictionaryType:id,name,code');

        return $this->success($dictItem);
    }

    /**
     * Update dictionary item.
     *
     * @throws BusinessException
     */
    public function update(DictionaryItemRequest $request, DictionaryItem $dictItem): JsonResponse
    {
        $item = $this->dictionaryItemService->updateItem($dictItem, $request->validated());

        return $this->success($item);
    }

    /**
     * Delete dictionary item.
     */
    public function destroy(DictionaryItem $dictItem): JsonResponse
    {
        $this->dictionaryItemService->deleteItem($dictItem);

        return $this->success();
    }
}

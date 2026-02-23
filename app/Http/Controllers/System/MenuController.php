<?php

namespace App\Http\Controllers\System;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\System\MenuRequest;
use App\Models\Menu;
use App\Services\MenuService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

/**
 * Menu CRUD for admin management.
 */
#[Group('Menus', weight: 4)]
class MenuController extends Controller
{
    public function __construct(
        private readonly MenuService $menuService,
    ) {}

    /**
     * List menu tree.
     *
     * @response array<int, array{id: int, parent_id: int, type: int, name: string, path: ?string, component: ?string, permission: ?string, locale: ?string, icon: ?string, sort: int, is_hidden: bool, is_active: bool, children: array<int, mixed>}>
     */
    public function index(): JsonResponse
    {
        return $this->success($this->menuService->getTree());
    }

    /**
     * Create menu.
     *
     * @throws BusinessException
     */
    public function store(MenuRequest $request): JsonResponse
    {
        $menu = $this->menuService->createMenu($request->validated());

        return $this->success($menu);
    }

    /**
     * Get menu detail.
     */
    public function show(Menu $menu): JsonResponse
    {
        $menu->load('children');

        return $this->success($menu);
    }

    /**
     * Update menu.
     *
     * @throws BusinessException
     */
    public function update(MenuRequest $request, Menu $menu): JsonResponse
    {
        $menu = $this->menuService->updateMenu($menu, $request->validated());

        return $this->success($menu);
    }

    /**
     * Delete menu.
     *
     * @throws BusinessException
     */
    public function destroy(Menu $menu): JsonResponse
    {
        $this->menuService->deleteMenu($menu);

        return $this->success();
    }
}

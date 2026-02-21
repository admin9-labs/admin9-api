<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

/**
 * @scaffold — Permission list.
 */
#[Group('Permissions', weight: 3)]
class PermissionController extends Controller
{
    /**
     * List permissions.
     */
    public function index(): JsonResponse
    {
        return $this->success(Permission::where('guard_name', 'api')->get(['id', 'name']));
    }
}

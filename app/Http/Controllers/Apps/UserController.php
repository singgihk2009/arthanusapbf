<?php

namespace App\Http\Controllers\Apps;

use App\Models\User;
use App\Http\Requests\UserRequest;
use App\Models\Inventory\Warehouse;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class UserController extends Controller implements HasMiddleware
{
    /**
     * middleware
     */
    public static function middleware()
    {
        return [
            new Middleware('permission:users-data', only: ['index']),
            new Middleware('permission:users-create', only: ['create']),
            new Middleware('permission:users-update', only: ['update']),
            new Middleware('permission:users-destroy', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // get all users data
        $users = User::query()
            ->with('roles')
            ->when(request()->search, fn($query) => $query->where('name', 'like', '%'. request()->search .'%'))
            ->select('id', 'name', 'avatar', 'email')
            ->latest()
            ->paginate(7)
            ->withQueryString();

        // render view
        return inertia('Apps/Users/Index', [
            'users' => $users
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // get all role data
        $roles = Role::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // render view
        return inertia('Apps/Users/Create', [
            'roles' => $roles,
            'warehouses' => Warehouse::query()->select('id','code','name')->orderBy('name')->get()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UserRequest $request)
    {
        // create new user data
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        // assign role to user
        $user->assignRole($request->selectedRoles);
        $this->syncWarehouses($user, $request->input('warehouse_ids', []), $request->input('default_warehouse_id'));

        // render view
        return to_route('apps.users.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        // get all role data
        $roles = Role::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // load relationship
        $user->load(['roles' => fn($query) => $query->select('id', 'name'), 'roles.permissions' => fn($query) => $query->select('id', 'name')]);

        // render view
        return inertia('Apps/Users/Edit', [
            'roles' => $roles,
            'warehouses' => Warehouse::query()->select('id','code','name')->orderBy('name')->get(),
            'user' => $user,
            'warehouses' => Warehouse::query()->select('id','code','name')->orderBy('name')->get(),
            'assignedWarehouseIds' => $user->warehouses()->pluck('warehouses.id')->map(fn($id)=>(string)$id)->values(),
            'defaultWarehouseId' => optional($user->defaultWarehouse())->id ? (string) optional($user->defaultWarehouse())->id : null
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UserRequest $request, User $user)
    {
        // check if user send request password
        if($request->password)
            // update user data password
            $user->update([
                'password' => bcrypt($request->password),
            ]);

        // update user data name
        $user->update([
            'name' => $request->name,
        ]);

        // assign role to user
        $user->syncRoles($request->selectedRoles);
        $this->syncWarehouses($user, $request->input('warehouse_ids', []), $request->input('default_warehouse_id'));

        // render view
        return to_route('apps.users.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $ids = explode(',', $id);

        if(count($ids) > 0)
            User::whereIn('id', $ids)->delete();
        else
            User::findOrFail($id)->delete();

        // render view
        return back();
    }

    private function syncWarehouses(User $user, array $warehouseIds, mixed $defaultWarehouseId): void
    {
        $warehouseIds = collect($warehouseIds)->map(fn ($id) => (int) $id)->unique()->values();
        $isStockkeeper = collect($user->getRoleNames())->contains(fn ($name) => strtolower((string) $name) === 'stockkeeper');
        if ($isStockkeeper && $warehouseIds->isEmpty()) { abort(422, 'Role Stockkeeper wajib memiliki minimal satu warehouse.'); }

        $defaultWarehouseId = $defaultWarehouseId ? (int) $defaultWarehouseId : ($warehouseIds->first() ?: null);
        if ($defaultWarehouseId && ! $warehouseIds->contains($defaultWarehouseId)) { abort(422, 'Default warehouse harus termasuk assigned warehouse.'); }

        $syncData = $warehouseIds->mapWithKeys(fn ($id) => [$id => ['is_default' => $defaultWarehouseId === $id]])->all();
        $user->warehouses()->sync($syncData);
    }
}


<?php

namespace App\Http\Controllers\Apps\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\CategoryRequest;
use App\Models\Inventory\Category;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CategoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:master-category-data', only: ['index']),
            new Middleware('permission:master-category-create', only: ['create', 'store']),
            new Middleware('permission:master-category-update', only: ['edit', 'update']),
            new Middleware('permission:master-category-delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        $categories = Category::query()
            ->with('parent:id,name')
            ->when(request()->search, fn ($query) => $query->where('name', 'like', '%'.request()->search.'%'))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return inertia('Apps/MasterData/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create()
    {
        return inertia('Apps/MasterData/Categories/Create', [
            'parents' => Category::query()->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function store(CategoryRequest $request)
    {
        Category::query()->create($request->validated());

        return to_route('apps.master-data.categories.index');
    }

    public function edit(Category $category)
    {
        return inertia('Apps/MasterData/Categories/Edit', [
            'category' => $category,
            'parents' => Category::query()->where('id', '!=', $category->id)->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function update(CategoryRequest $request, Category $category)
    {
        $category->update($request->validated());

        return to_route('apps.master-data.categories.index');
    }

    public function destroy(string $id)
    {
        $ids = explode(',', $id);

        Category::query()->whereIn('id', $ids)->delete();

        return back();
    }
}

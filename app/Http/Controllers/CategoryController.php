<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $q = trim($request->get('q',''));
        $categories = Category::query()
            ->when($q, fn($qry)=>$qry->where('name','like',"%{$q}%"))
            ->orderBy('name')
            ->paginate(20)
            ->appends($request->query());

        return view('categories.index', compact('categories','q'));
        //return response()->json($categories);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name'=>['required','string','max:100','unique:categories,name']]);
        $cat = Category::create($data);
        return response()->json($cat, 201);
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate(['name'=>['required','string','max:100',"unique:categories,name,{$category->id}"]]);
        $category->update($data);
        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        $category->update(['is_active' => false]);
        return response()->json(['ok'=>true]);
    }
}

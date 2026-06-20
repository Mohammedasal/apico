<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function index()
    {
        return view('materials.index', ['materials' => Material::orderBy('name')->paginate(25)]);
    }

    public function create()
    {
        return view('materials.form', ['material' => new Material(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        Material::create($this->validated($request));

        return redirect()->route('materials.index')->with('status', 'Material created.');
    }

    public function edit(Material $material)
    {
        return view('materials.form', compact('material'));
    }

    public function update(Request $request, Material $material)
    {
        $material->update($this->validated($request));

        return redirect()->route('materials.index')->with('status', 'Material updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'in:recycle,stock,both'],
            'default_processing_cost_per_kg' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]) + ['is_active' => false];
    }
}

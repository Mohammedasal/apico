@extends('layouts.app')

@section('content')
<h1>{{ $material->exists ? __('Edit Material') : __('New Material') }}</h1>
<form method="post" action="{{ $material->exists ? route('materials.update', $material) : route('materials.store') }}">
    @csrf
    @if ($material->exists) @method('put') @endif
    <div class="form-grid">
        <div><label>{{ __('Name') }}</label><input name="name" value="{{ old('name', $material->name) }}">@error('name')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Type') }}</label><select class="searchable-select" name="type"><option value="" @selected(blank(old('type', $material->type)))>{{ __('Optional') }}</option><option value="both" @selected(old('type', $material->type) === 'both')>{{ __('Both') }}</option><option value="recycle" @selected(old('type', $material->type) === 'recycle')>{{ __('Recycle') }}</option><option value="stock" @selected(old('type', $material->type) === 'stock')>{{ __('Stock') }}</option></select></div>
        <div><label>{{ __('Processing Cost/Kg') }}</label><input type="number" step="0.001" name="default_processing_cost_per_kg" value="{{ old('default_processing_cost_per_kg', $material->default_processing_cost_per_kg ?? 0) }}"></div>
        <div><label>{{ __('Active') }}</label><select name="is_active"><option value="1" @selected(old('is_active', $material->is_active) == 1)>{{ __('Yes') }}</option><option value="0" @selected(old('is_active', $material->is_active) == 0)>{{ __('No') }}</option></select></div>
    </div>
    <p><button>{{ __('Save') }}</button></p>
</form>
@endsection

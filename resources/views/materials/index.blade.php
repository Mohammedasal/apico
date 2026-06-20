@extends('layouts.app')

@section('content')
<div class="toolbar">
    <h1>{{ __('Materials') }}</h1>
    @if (auth()->user()?->canWriteOperationalData())
        <a class="button" href="{{ route('materials.create') }}">{{ __('New Material') }}</a>
    @endif
</div>
<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Processing Cost/Kg') }}</th><th>{{ __('Active') }}</th><th></th></tr></thead>
    <tbody>
    @foreach ($materials as $material)
        <tr>
            <td>{{ $material->name }}</td>
            <td>{{ __(ucfirst($material->type ?: 'Optional')) }}</td>
            <td>{{ number_format($material->default_processing_cost_per_kg, 3) }}</td>
            <td>{{ $material->is_active ? __('Yes') : __('No') }}</td>
            <td>@if (auth()->user()?->canWriteOperationalData())<a href="{{ route('materials.edit', $material) }}">{{ __('Edit') }}</a>@endif</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
{{ $materials->links() }}
@endsection

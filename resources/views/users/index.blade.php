@extends('layouts.app')

@section('content')
<div class="toolbar">
    <h1>{{ __('Users') }}</h1>
    <a class="button" href="{{ route('users.create') }}">{{ __('New User') }}</a>
</div>
<div class="table-wrap">
    <table>
        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Email') }}</th><th>{{ __('Role') }}</th><th>{{ __('Status') }}</th><th>{{ __('Created') }}</th><th></th></tr></thead>
        <tbody>
        @foreach ($users as $user)
            <tr>
                <td>{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
                <td>{{ __(ucwords(str_replace('_', ' ', $user->role))) }}</td>
                <td>{{ $user->is_active ? __('Active') : __('Inactive') }}</td>
                <td>{{ $user->created_at?->format('Y-m-d H:i') }}</td>
                <td><a href="{{ route('users.edit', $user) }}">{{ __('Edit') }}</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
{{ $users->links() }}
@endsection

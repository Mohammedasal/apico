@extends('layouts.app')

@section('content')
@php $isEdit = $user->exists; @endphp
<h1>{{ $isEdit ? __('Edit User') : __('New User') }}</h1>
<form method="post" action="{{ $isEdit ? route('users.update', $user) : route('users.store') }}">
    @csrf
    @if ($isEdit) @method('put') @endif
    <div class="form-grid">
        <div><label>{{ __('Name') }}</label><input name="name" value="{{ old('name', $user->name) }}">@error('name')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Email') }}</label><input type="email" name="email" value="{{ old('email', $user->email) }}">@error('email')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Password') }}</label><input type="password" name="password" placeholder="{{ $isEdit ? __('Leave blank to keep current password') : '' }}">@error('password')<div class="error">{{ $message }}</div>@enderror</div>
        <div>
            <label>{{ __('Role') }}</label>
            <select name="role">
                @foreach (\App\Models\User::ROLES as $value => $label)
                    <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ __(ucwords(str_replace('_', ' ', $value))) }}</option>
                @endforeach
            </select>
            @error('role')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div><label>{{ __('Status') }}</label><select name="is_active"><option value="1" @selected(old('is_active', $user->is_active) == 1)>{{ __('Active') }}</option><option value="0" @selected(old('is_active', $user->is_active) == 0)>{{ __('Inactive') }}</option></select></div>
    </div>
    <p><button>{{ __('Save User') }}</button></p>
</form>
@endsection

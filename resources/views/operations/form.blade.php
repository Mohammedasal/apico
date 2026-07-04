@extends('layouts.app')

@php $isEdit = filled($record); @endphp

@section('content')
<h1>{{ $isEdit ? __('Edit :item', ['item' => __($config['title'])]) : __('New :item', ['item' => __($config['title'])]) }}</h1>
@if (! $isEdit && filled($recentRecord))
    <div class="card" style="margin-bottom:16px">
        <strong>{{ __('Last added') }}</strong>
        <span style="margin-inline-start:12px">{{ $recentRecord->date?->format('Y-m-d') }}</span>
        @if ($module === 'stock-purchases')
            <span> | {{ $recentRecord->supplier?->name ?? $recentRecord->supplier_name }}</span>
        @else
            <span> | {{ $recentRecord->customer?->name }}</span>
        @endif
        @if ($module === 'payments')
            <span> | {{ number_format((float) $recentRecord->amount, 3) }} JOD</span>
        @elseif ($module === 'recycle-out')
            <span> | {{ number_format((float) $recentRecord->weight_kg, 3) }} kg | {{ number_format((float) $recentRecord->total_amount, 3) }} JOD</span>
        @elseif ($module === 'stock-purchases')
            <span> | {{ number_format((float) $recentRecord->weight_kg, 3) }} kg | {{ number_format((float) $recentRecord->total_cost, 3) }} JOD</span>
        @elseif ($module === 'stock-sales')
            <span> | {{ number_format((float) $recentRecord->weight_kg, 3) }} kg | {{ number_format((float) $recentRecord->sales_value, 3) }} JOD</span>
        @else
            <span> | {{ number_format((float) $recentRecord->weight_kg, 3) }} kg</span>
        @endif
    </div>
@endif
<form method="post" action="{{ $isEdit ? route('operations.update', [$module, $record->id]) : route('operations.store', $module) }}">
    @csrf
    @if ($isEdit) @method('put') @endif
    <div class="form-grid">
        <div><label>{{ __('Date') }}</label><input type="date" name="date" value="{{ old('date', $record?->date?->toDateString() ?? now()->toDateString()) }}">@error('date')<div class="error">{{ $message }}</div>@enderror</div>
        @if ($module !== 'stock-purchases')
            <div><label>{{ __('Customer') }}</label><select class="searchable-select" name="customer_id"><option value="">{{ __('Select') }}</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id', $record?->customer_id) == $customer->id)>{{ $customer->name }}</option>@endforeach</select>@error('customer_id')<div class="error">{{ $message }}</div>@enderror</div>
        @endif
        @if ($module === 'stock-purchases')
            <div><label>{{ __('Supplier') }}</label><select class="searchable-select" name="supplier_id"><option value="">{{ __('Select') }}</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id', $record?->supplier_id) == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select>@error('supplier_id')<div class="error">{{ $message }}</div>@enderror</div>
        @endif
        @if ($module !== 'payments')
            <div><label>{{ __('Material') }}</label><select class="searchable-select" name="material_id"><option value="">{{ __('Optional') }}</option>@foreach ($materials as $material)<option value="{{ $material->id }}" @selected(old('material_id', $record?->material_id) == $material->id)>{{ $material->name }}</option>@endforeach</select>@error('material_id')<div class="error">{{ $message }}</div>@enderror</div>
            @if ($module !== 'recycle-out')
                <div><label>{{ __('Weight Kg') }}</label><input type="number" step="0.001" name="weight_kg" value="{{ old('weight_kg', $record?->weight_kg) }}">@error('weight_kg')<div class="error">{{ $message }}</div>@enderror</div>
            @endif
        @endif
        @if ($module === 'recycle-out')
            <div><label>{{ __('Recycled Out Kg') }}</label><input type="number" step="0.001" name="recycled_out_kg" value="{{ old('recycled_out_kg', $record?->recycled_out_kg ?? 0) }}">@error('recycled_out_kg')<div class="error">{{ $message }}</div>@enderror</div>
            <div><label>{{ __('Waste Kg') }}</label><input type="number" step="0.001" name="waste_kg" value="{{ old('waste_kg', $record?->waste_kg ?? 0) }}">@error('waste_kg')<div class="error">{{ $message }}</div>@enderror</div>
            <div><label>{{ __('Non-Recycled Kg') }}</label><input type="number" step="0.001" name="non_recycled_kg" value="{{ old('non_recycled_kg', $record?->non_recycled_kg ?? 0) }}">@error('non_recycled_kg')<div class="error">{{ $message }}</div>@enderror</div>
            <div><label>{{ __('Rate/Kg') }}</label><input type="text" inputmode="decimal" name="rate_per_kg" value="{{ old('rate_per_kg', $record?->rate_per_kg) }}">@error('rate_per_kg')<div class="error">{{ $message }}</div>@enderror</div>
        @elseif ($module === 'payments')
            <div><label>{{ __('Amount') }}</label><input type="number" step="0.001" name="amount" value="{{ old('amount', $record?->amount) }}">@error('amount')<div class="error">{{ $message }}</div>@enderror</div>
            <div><label>{{ __('Type') }}</label><select name="payment_type"><option value="cash" @selected(old('payment_type', $record?->payment_type ?? 'cash') === 'cash')>{{ __('Cash') }}</option><option value="cheque" @selected(old('payment_type', $record?->payment_type) === 'cheque')>{{ __('Cheque') }}</option><option value="bank_transfer" @selected(old('payment_type', $record?->payment_type) === 'bank_transfer')>{{ __('Bank Transfer') }}</option><option value="exchange_of_goods" @selected(old('payment_type', $record?->payment_type) === 'exchange_of_goods')>{{ __('Exchange of Goods') }}</option></select></div>
            <div><label>{{ __('Method') }}</label><input name="payment_method" value="{{ old('payment_method', $record?->payment_method) }}"></div>
            <div><label>{{ __('Reference') }}</label><input name="reference_no" value="{{ old('reference_no', $record?->reference_no) }}"></div>
            <div><label>{{ __('Bank') }}</label><input name="bank_name" value="{{ old('bank_name', $record?->bank_name) }}"></div>
            <div><label>{{ __('Cheque Due Date') }}</label><input type="date" name="cheque_due_date" value="{{ old('cheque_due_date', $record?->cheque_due_date?->toDateString()) }}"></div>
            <div><label>{{ __('Cheque Status') }}</label><select name="cheque_status"><option value="pending" @selected(old('cheque_status', $record?->cheque_status ?? 'pending') === 'pending')>{{ __('Pending') }}</option><option value="collected" @selected(old('cheque_status', $record?->cheque_status) === 'collected')>{{ __('Collected') }}</option><option value="bounced" @selected(old('cheque_status', $record?->cheque_status) === 'bounced')>{{ __('Bounced') }}</option><option value="cancelled" @selected(old('cheque_status', $record?->cheque_status) === 'cancelled')>{{ __('Cancelled') }}</option></select></div>
        @elseif ($module === 'stock-purchases')
            <div><label>{{ __('Cost/Kg') }}</label><input type="number" step="0.001" name="cost_per_kg" value="{{ old('cost_per_kg', $record?->cost_per_kg) }}">@error('cost_per_kg')<div class="error">{{ $message }}</div>@enderror</div>
        @elseif ($module === 'stock-sales')
            <input type="hidden" name="price_input_mode" id="price_input_mode" value="{{ old('price_input_mode', 'rate') }}">
            <div><label>{{ __('Selling Price/Kg') }}</label><input id="stock_sale_rate" type="number" step="0.000001" name="selling_price_per_kg" value="{{ old('selling_price_per_kg', $record?->selling_price_per_kg) }}">@error('selling_price_per_kg')<div class="error">{{ $message }}</div>@enderror</div>
            <div><label>{{ __('Total Price') }}</label><input id="stock_sale_total" type="number" step="0.001" name="sales_value" value="{{ old('sales_value', $record?->sales_value) }}">@error('sales_value')<div class="error">{{ $message }}</div>@enderror</div>
            <div><label>{{ __('Admin Override') }}</label><select name="admin_override"><option value="0">{{ __('No') }}</option><option value="1">{{ __('Yes') }}</option></select></div>
        @endif
        <div style="grid-column:1/-1"><label>{{ __('Notes') }}</label><textarea name="notes">{{ old('notes', $record?->notes) }}</textarea>@error('notes')<div class="error">{{ $message }}</div>@enderror</div>
    </div>
    <p><button>{{ __('Save') }}</button></p>
</form>
@if ($module === 'stock-sales')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const weight = document.querySelector('input[name="weight_kg"]');
    const rate = document.getElementById('stock_sale_rate');
    const total = document.getElementById('stock_sale_total');
    const mode = document.getElementById('price_input_mode');

    function calculateFromRate() {
        const weightValue = Number.parseFloat(weight.value);
        const rateValue = Number.parseFloat(rate.value);
        total.value = Number.isFinite(weightValue) && Number.isFinite(rateValue)
            ? (weightValue * rateValue).toFixed(3)
            : '';
    }

    function calculateFromTotal() {
        const weightValue = Number.parseFloat(weight.value);
        const totalValue = Number.parseFloat(total.value);
        rate.value = Number.isFinite(weightValue) && weightValue > 0 && Number.isFinite(totalValue)
            ? (totalValue / weightValue).toFixed(6)
            : '';
    }

    rate.addEventListener('input', function () {
        mode.value = 'rate';
        calculateFromRate();
    });
    total.addEventListener('input', function () {
        mode.value = 'total';
        calculateFromTotal();
    });
    weight.addEventListener('input', function () {
        mode.value === 'total' ? calculateFromTotal() : calculateFromRate();
    });
});
</script>
@endif
@endsection

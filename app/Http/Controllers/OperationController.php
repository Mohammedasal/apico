<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Material;
use App\Models\Payment;
use App\Models\RecycleIn;
use App\Models\RecycleOut;
use App\Models\Setting;
use App\Models\StockPurchase;
use App\Models\StockSale;
use App\Models\Supplier;
use App\Services\ApicoCalculator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OperationController extends Controller
{
    private array $modules = [
        'recycle-in' => ['title' => 'Recycle In', 'model' => RecycleIn::class],
        'recycle-out' => ['title' => 'Recycle Out', 'model' => RecycleOut::class],
        'payments' => ['title' => 'Payments', 'model' => Payment::class],
        'stock-purchases' => ['title' => 'Stock Purchases', 'model' => StockPurchase::class],
        'stock-sales' => ['title' => 'Stock Sales', 'model' => StockSale::class],
    ];

    public function index(string $module)
    {
        $config = $this->module($module);
        $records = $config['model']::query()
            ->with($this->relations($module))
            ->latest('date')
            ->latest('id')
            ->paginate(25);

        return view('operations.index', compact('module', 'config', 'records'));
    }

    public function create(string $module)
    {
        return view('operations.form', [
            'module' => $module,
            'config' => $this->module($module),
            'record' => null,
            'customers' => Customer::orderBy('name')->get(),
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(),
            'materials' => Material::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(string $module, Request $request, ApicoCalculator $calculator)
    {
        $data = $this->validated($module, $request, $calculator);
        $data['created_by'] = $request->user()->id;
        $this->module($module)['model']::create($data);

        return redirect()->route('operations.index', $module)->with('status', __(':item saved.', ['item' => __($this->module($module)['title'])]));
    }

    public function edit(string $module, int $id)
    {
        return view('operations.form', [
            'module' => $module,
            'config' => $this->module($module),
            'record' => $this->module($module)['model']::findOrFail($id),
            'customers' => Customer::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'materials' => Material::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(string $module, int $id, Request $request, ApicoCalculator $calculator)
    {
        $record = $this->module($module)['model']::findOrFail($id);
        $data = $this->validated($module, $request, $calculator, $id);
        $data['updated_by'] = $request->user()->id;
        $record->update($data);

        return redirect()->route('operations.index', $module)->with('status', __(':item updated.', ['item' => __($this->module($module)['title'])]));
    }

    private function module(string $module): array
    {
        abort_unless(isset($this->modules[$module]), 404);

        return $this->modules[$module];
    }

    private function relations(string $module): array
    {
        return match ($module) {
            'payments' => ['customer', 'creator', 'editor'],
            'stock-purchases' => ['supplier', 'material', 'creator', 'editor'],
            default => ['customer', 'material', 'creator', 'editor'],
        };
    }

    private function validated(string $module, Request $request, ApicoCalculator $calculator, ?int $ignoreId = null): array
    {
        $data = match ($module) {
            'payments' => $request->validate([
                'date' => ['required', 'date'],
                'customer_id' => ['required', 'exists:customers,id'],
                'amount' => ['required', 'numeric'],
                'payment_type' => ['required', 'in:cash,cheque,bank_transfer,exchange_of_goods'],
                'payment_method' => ['nullable', 'string', 'max:255'],
                'reference_no' => ['nullable', 'string', 'max:255'],
                'bank_name' => ['nullable', 'string', 'max:255'],
                'cheque_due_date' => ['nullable', 'date'],
                'cheque_status' => ['nullable', 'in:pending,collected,bounced,cancelled'],
                'notes' => ['nullable', 'string'],
            ]),
            'stock-purchases' => $request->validate([
                'date' => ['required', 'date'],
                'supplier_id' => ['required', 'exists:suppliers,id'],
                'material_id' => ['nullable', 'exists:materials,id'],
                'weight_kg' => ['required', 'numeric', 'gt:0'],
                'cost_per_kg' => ['required', 'numeric', 'min:0'],
                'notes' => ['nullable', 'string'],
            ]),
            'stock-sales' => $request->validate([
                'date' => ['required', 'date'],
                'customer_id' => ['required', 'exists:customers,id'],
                'material_id' => ['nullable', 'exists:materials,id'],
                'weight_kg' => ['required', 'numeric', 'gt:0'],
                'selling_price_per_kg' => ['required', 'numeric', 'min:0'],
                'purchase_cost_per_kg' => ['required', 'numeric', 'min:0'],
                'granulation_cost_per_kg' => ['nullable', 'numeric', 'min:0'],
                'notes' => ['nullable', 'string'],
                'admin_override' => ['sometimes', 'boolean'],
            ]),
            'recycle-in' => $request->validate([
                'date' => ['required', 'date'],
                'customer_id' => ['required', 'exists:customers,id'],
                'material_id' => ['nullable', 'exists:materials,id'],
                'weight_kg' => ['required', 'numeric', 'gt:0'],
                'notes' => ['nullable', 'string'],
            ]),
            'recycle-out' => $request->validate([
                'date' => ['required', 'date'],
                'customer_id' => ['required', 'exists:customers,id'],
                'material_id' => ['nullable', 'exists:materials,id'],
                'recycled_out_kg' => ['nullable', 'numeric', 'min:0'],
                'waste_kg' => ['nullable', 'numeric', 'min:0'],
                'non_recycled_kg' => ['nullable', 'numeric', 'min:0'],
                'rate_per_kg' => ['required', 'numeric', 'min:0'],
                'notes' => ['nullable', 'string'],
            ]),
            default => $request->validate([
                'date' => ['required', 'date'],
                'customer_id' => ['required', 'exists:customers,id'],
                'material_id' => ['required', 'exists:materials,id'],
                'weight_kg' => ['required', 'numeric', 'gt:0'],
                'rate_per_kg' => ['required', 'numeric', 'min:0'],
                'notes' => ['nullable', 'string'],
            ]),
        };

        if ($module === 'recycle-in') {
            $data['rate_per_kg'] = 0;
            $data['total_amount'] = 0;
        }

        if ($module === 'recycle-out') {
            $data['recycled_out_kg'] = (float) ($data['recycled_out_kg'] ?? 0);
            $data['waste_kg'] = (float) ($data['waste_kg'] ?? 0);
            $data['non_recycled_kg'] = (float) ($data['non_recycled_kg'] ?? 0);
            $data['weight_kg'] = round($data['recycled_out_kg'] + $data['waste_kg'] + $data['non_recycled_kg'], 3);

            if ($data['weight_kg'] <= 0) {
                throw ValidationException::withMessages(['recycled_out_kg' => 'Enter recycled out, waste, or non-recycled weight.']);
            }

            if ($data['recycled_out_kg'] > 0 && (float) $data['rate_per_kg'] === 0.0 && blank($data['notes'] ?? null)) {
                throw ValidationException::withMessages(['notes' => 'A note is required when price is zero.']);
            }

            $data['total_amount'] = $calculator->recycleTotal((float) $data['recycled_out_kg'], (float) $data['rate_per_kg']);
        }

        if ($module === 'payments' && (float) $data['amount'] < 0 && blank($data['notes'] ?? null)) {
            throw ValidationException::withMessages(['notes' => 'A note is required for negative payments or adjustments.']);
        }

        if ($module === 'payments') {
            $data['cheque_status'] = $data['payment_type'] === 'cheque'
                ? ($data['cheque_status'] ?? 'pending')
                : 'pending';
            $data['cheque_due_date'] = $data['payment_type'] === 'cheque' ? ($data['cheque_due_date'] ?? null) : null;
        }

        if ($module === 'stock-purchases') {
            $data['supplier_name'] = Supplier::findOrFail($data['supplier_id'])->name;

            if ((float) $data['cost_per_kg'] === 0.0 && blank($data['notes'] ?? null)) {
                throw ValidationException::withMessages(['notes' => 'A note is required when cost is zero.']);
            }

            $data['total_cost'] = $calculator->recycleTotal((float) $data['weight_kg'], (float) $data['cost_per_kg']);
        }

        if ($module === 'stock-sales') {
            if ((float) $data['selling_price_per_kg'] === 0.0 && blank($data['notes'] ?? null)) {
                throw ValidationException::withMessages(['notes' => 'A note is required when selling price is zero.']);
            }

            $available = $calculator->remainingStockWeight(filled($data['material_id'] ?? null) ? (int) $data['material_id'] : null);
            $currentWeight = $ignoreId ? (float) StockSale::findOrFail($ignoreId)->weight_kg : 0.0;
            $allowOverride = Setting::where('key', 'allow_stock_override')->value('value') === '1' || (bool) ($data['admin_override'] ?? false);

            if (((float) $data['weight_kg'] - $currentWeight) > $available && ! $allowOverride) {
                throw ValidationException::withMessages(['weight_kg' => 'Stock sale exceeds available stock. Enable admin override in settings to allow it.']);
            }

            $values = $calculator->stockSaleValues(
                (float) $data['weight_kg'],
                (float) $data['selling_price_per_kg'],
                (float) $data['purchase_cost_per_kg'],
                (float) ($data['granulation_cost_per_kg'] ?? 0)
            );

            $data['sales_value'] = $values['sales_value'];
            $data['net_profit'] = $values['net_profit'];
            unset($data['admin_override']);
        }

        return $data;
    }
}

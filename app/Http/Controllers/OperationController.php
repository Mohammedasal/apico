<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Material;
use App\Models\Payment;
use App\Models\RecycleIn;
use App\Models\RecycleOut;
use App\Models\Setting;
use App\Models\StockPurchase;
use App\Models\StockSale;
use App\Models\Supplier;
use App\Services\AccountingPostingService;
use App\Services\ApicoCalculator;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function index(string $module, Request $request)
    {
        $config = $this->module($module);
        $filters = $this->filters($module, $request);
        $query = $config['model']::query()
            ->with($this->relations($module));

        $this->applyFilters($module, $query, $filters);
        $this->applySorting($module, $query, $filters);

        $records = $query
            ->paginate(25)
            ->withQueryString();

        return view('operations.index', [
            'module' => $module,
            'config' => $config,
            'records' => $records,
            'filters' => $filters,
            'customers' => in_array($module, ['recycle-in', 'recycle-out', 'payments', 'stock-sales'], true)
                ? Customer::orderBy('name')->get()
                : collect(),
            'suppliers' => $module === 'stock-purchases'
                ? Supplier::orderBy('name')->get()
                : collect(),
            'materials' => $module !== 'payments'
                ? Material::orderBy('name')->get()
                : collect(),
        ]);
    }

    public function create(string $module)
    {
        $recentRecord = null;
        $recentId = session("recent_operation.{$module}");

        if ($recentId) {
            $recentRecord = $this->module($module)['model']::with($this->relations($module))->find($recentId);
        }

        return view('operations.form', [
            'module' => $module,
            'config' => $this->module($module),
            'record' => null,
            'customers' => Customer::orderBy('name')->get(),
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(),
            'materials' => Material::where('is_active', true)->orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderByDesc('is_default')->orderBy('name_en')->get(),
            'cashAccounts' => CashAccount::where('is_active', true)->orderByDesc('is_default')->orderBy('name_en')->get(),
            'recentRecord' => $recentRecord,
        ]);
    }

    public function store(string $module, Request $request, ApicoCalculator $calculator, AccountingPostingService $posting)
    {
        $data = $this->validated($module, $request, $calculator);
        $data['created_by'] = $request->user()->id;
        $record = DB::transaction(function () use ($module, $data, $request, $posting) {
            $record = $this->module($module)['model']::create($data);
            $posting->postOperational($record, $request->user());

            return $record;
        });

        return redirect()
            ->route('operations.create', $module)
            ->with("recent_operation.{$module}", $record->id)
            ->with('status', __(':item saved.', ['item' => __($this->module($module)['title'])]));
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
            'bankAccounts' => BankAccount::where('is_active', true)->orderByDesc('is_default')->orderBy('name_en')->get(),
            'cashAccounts' => CashAccount::where('is_active', true)->orderByDesc('is_default')->orderBy('name_en')->get(),
            'recentRecord' => null,
        ]);
    }

    public function update(string $module, int $id, Request $request, ApicoCalculator $calculator, AccountingPostingService $posting)
    {
        $record = $this->module($module)['model']::findOrFail($id);
        if ($record instanceof Payment && $record->payment_type === 'cheque' && $record->cheque_status !== 'pending') {
            throw ValidationException::withMessages(['cheque_status' => __('Settled cheques must be corrected from the cheque settlement page.')]);
        }
        $data = $this->validated($module, $request, $calculator, $id);
        $data['updated_by'] = $request->user()->id;
        DB::transaction(function () use ($record, $data, $request, $posting) {
            $record->update($data);
            $posting->repostOperational($record->fresh(), $request->user());
        });

        return redirect()->route('operations.index', $module)->with('status', __(':item updated.', ['item' => __($this->module($module)['title'])]));
    }

    public function destroy(
        string $module,
        int $id,
        Request $request,
        AccountingPostingService $posting,
        AuditService $audit
    ) {
        abort_unless($request->user()?->role === 'admin', 403);
        $record = $this->module($module)['model']::findOrFail($id);

        DB::transaction(function () use ($record, $request, $posting, $audit) {
            $before = $record->toArray();
            $posting->reverseOperational($record, $request->user());
            $audit->record('operation_deleted', $record, $before, null);
            $record->delete();
        });

        return redirect()
            ->route('operations.index', $module)
            ->with('status', __(':item deleted.', ['item' => __($this->module($module)['title'])]));
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

    private function filters(string $module, Request $request): array
    {
        $sortable = array_keys($this->sortableColumns($module));
        $sort = $request->input('sort', 'date');

        return [
            'customer_id' => $request->integer('customer_id') ?: null,
            'supplier_id' => $request->integer('supplier_id') ?: null,
            'material_id' => $request->integer('material_id') ?: null,
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'min_weight' => $request->filled('min_weight') ? (float) $request->input('min_weight') : null,
            'max_weight' => $request->filled('max_weight') ? (float) $request->input('max_weight') : null,
            'min_amount' => $request->filled('min_amount') ? (float) $request->input('min_amount') : null,
            'max_amount' => $request->filled('max_amount') ? (float) $request->input('max_amount') : null,
            'payment_type' => in_array($request->input('payment_type'), ['cash', 'cheque', 'bank_transfer', 'exchange_of_goods'], true)
                ? $request->input('payment_type')
                : null,
            'cheque_status' => in_array($request->input('cheque_status'), ['pending', 'collected', 'bounced', 'cancelled'], true)
                ? $request->input('cheque_status')
                : null,
            'search' => trim((string) $request->input('search')) ?: null,
            'sort' => in_array($sort, $sortable, true) ? $sort : 'date',
            'direction' => $request->input('direction') === 'asc' ? 'asc' : 'desc',
        ];
    }

    private function applyFilters(string $module, Builder $query, array $filters): void
    {
        $amountColumn = $this->amountColumn($module);

        $query
            ->when(
                in_array($module, ['recycle-in', 'recycle-out', 'payments', 'stock-sales'], true) ? ($filters['customer_id'] ?? null) : null,
                fn (Builder $query, $customerId) => $query->where('customer_id', $customerId)
            )
            ->when($module === 'stock-purchases' ? ($filters['supplier_id'] ?? null) : null, fn (Builder $query, $supplierId) => $query->where('supplier_id', $supplierId))
            ->when($module !== 'payments' ? ($filters['material_id'] ?? null) : null, fn (Builder $query, $materialId) => $query->where('material_id', $materialId))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('date', '<=', $to))
            ->when($module !== 'payments' && ! is_null($filters['min_weight'] ?? null), fn (Builder $query) => $query->where('weight_kg', '>=', $filters['min_weight']))
            ->when($module !== 'payments' && ! is_null($filters['max_weight'] ?? null), fn (Builder $query) => $query->where('weight_kg', '<=', $filters['max_weight']))
            ->when($amountColumn && ! is_null($filters['min_amount'] ?? null), fn (Builder $query) => $query->where($amountColumn, '>=', $filters['min_amount']))
            ->when($amountColumn && ! is_null($filters['max_amount'] ?? null), fn (Builder $query) => $query->where($amountColumn, '<=', $filters['max_amount']))
            ->when($module === 'payments' ? ($filters['payment_type'] ?? null) : null, fn (Builder $query, $type) => $query->where('payment_type', $type))
            ->when($module === 'payments' ? ($filters['cheque_status'] ?? null) : null, fn (Builder $query, $status) => $query->where('payment_type', 'cheque')->where('cheque_status', $status))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) use ($module) {
                $query->where(function (Builder $query) use ($module, $search) {
                    $query->where('notes', 'like', "%{$search}%");

                    if ($module === 'payments') {
                        $query->orWhere('reference_no', 'like', "%{$search}%")
                            ->orWhere('payment_method', 'like', "%{$search}%")
                            ->orWhere('bank_name', 'like', "%{$search}%");
                    }

                    if ($module === 'stock-purchases') {
                        $query->orWhere('supplier_name', 'like', "%{$search}%");
                    }
                });
            });
    }

    private function applySorting(string $module, Builder $query, array $filters): void
    {
        $sort = $filters['sort'];
        $direction = $filters['direction'];
        $column = $this->sortableColumns($module)[$sort];
        $table = $query->getModel()->getTable();

        if ($column === 'customer') {
            $query->orderBy(
                Customer::select('name')->whereColumn('customers.id', "{$table}.customer_id"),
                $direction
            );
        } elseif ($column === 'supplier') {
            $query->orderBy(
                Supplier::select('name')->whereColumn('suppliers.id', "{$table}.supplier_id"),
                $direction
            );
        } elseif ($column === 'material') {
            $query->orderBy(
                Material::select('name')->whereColumn('materials.id', "{$table}.material_id"),
                $direction
            );
        } else {
            $query->orderBy($column, $direction);
        }

        $query->orderBy('id', $direction);
    }

    private function sortableColumns(string $module): array
    {
        $columns = [
            'date' => 'date',
            'amount' => $this->amountColumn($module),
            'notes' => 'notes',
            'audit' => 'created_at',
        ];

        if ($module === 'stock-purchases') {
            $columns['supplier'] = 'supplier';
        } else {
            $columns['customer'] = 'customer';
        }

        if ($module !== 'payments') {
            $columns['material'] = 'material';
            $columns['weight'] = 'weight_kg';
        } else {
            $columns['type'] = 'payment_type';
            $columns['cheque'] = 'cheque_due_date';
        }

        return array_filter($columns);
    }

    private function amountColumn(string $module): ?string
    {
        return match ($module) {
            'recycle-in', 'recycle-out' => 'total_amount',
            'payments' => 'amount',
            'stock-purchases' => 'total_cost',
            'stock-sales' => 'sales_value',
            default => null,
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
                'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
                'cash_account_id' => ['nullable', 'exists:cash_accounts,id'],
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
                'selling_price_per_kg' => ['nullable', 'required_without:sales_value', 'numeric', 'min:0'],
                'sales_value' => ['nullable', 'required_without:selling_price_per_kg', 'numeric', 'min:0'],
                'price_input_mode' => ['nullable', 'in:rate,total'],
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
            $data['cheque_status'] = 'pending';
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
            $weightKg = (float) $data['weight_kg'];
            $inputMode = $data['price_input_mode'] ?? 'rate';

            if ($inputMode === 'total' && filled($data['sales_value'] ?? null)) {
                $data['sales_value'] = round((float) $data['sales_value'], 3);
                $data['selling_price_per_kg'] = round($data['sales_value'] / $weightKg, 6);
            } else {
                $data['selling_price_per_kg'] = round((float) $data['selling_price_per_kg'], 6);
                $data['sales_value'] = $calculator->recycleTotal($weightKg, $data['selling_price_per_kg']);
            }

            if ((float) $data['sales_value'] === 0.0 && blank($data['notes'] ?? null)) {
                throw ValidationException::withMessages(['notes' => 'A note is required when selling price is zero.']);
            }

            $available = $calculator->remainingStockWeight(filled($data['material_id'] ?? null) ? (int) $data['material_id'] : null);
            $currentWeight = $ignoreId ? (float) StockSale::findOrFail($ignoreId)->weight_kg : 0.0;
            $allowOverride = Setting::where('key', 'allow_stock_override')->value('value') === '1' || (bool) ($data['admin_override'] ?? false);

            if (((float) $data['weight_kg'] - $currentWeight) > $available && ! $allowOverride) {
                throw ValidationException::withMessages(['weight_kg' => 'Stock sale exceeds available stock. Enable admin override in settings to allow it.']);
            }

            $purchaseCost = $calculator->weightedAverageStockCost(
                filled($data['material_id'] ?? null) ? (int) $data['material_id'] : null,
                $data['date'],
                $ignoreId
            );
            $data['purchase_cost_per_kg'] = $purchaseCost;
            $data['granulation_cost_per_kg'] = 0;
            $data['net_profit'] = round($data['sales_value'] - ($weightKg * $purchaseCost), 3);
            unset($data['admin_override'], $data['price_input_mode']);
        }

        return $data;
    }
}

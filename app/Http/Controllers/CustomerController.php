<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\ApicoCalculator;
use App\Services\SimpleXlsxExporter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
            'sort' => ['nullable', 'in:name,balance_kg,balance_jod,status'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
        ]);
        $search = trim((string) ($validated['q'] ?? ''));
        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';
        $sortColumn = [
            'name' => 'name',
            'balance_kg' => 'remaining_balance_kg',
            'balance_jod' => 'remaining_balance_jod',
            'status' => 'status',
        ][$sort];
        $customers = Customer::query()
            ->select('customers.*')
            ->selectRaw($this->customerJodBalanceSql().' AS remaining_balance_jod')
            ->selectRaw($this->customerKgBalanceSql().' AS remaining_balance_kg')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy($sortColumn, $direction)
            ->orderBy('id')
            ->paginate((int) ($validated['per_page'] ?? 25))
            ->withQueryString();

        return view('customers.index', compact('customers', 'search', 'sort', 'direction') + [
            'status' => $validated['status'] ?? '',
            'perPage' => (int) ($validated['per_page'] ?? 25),
        ]);
    }

    public function create()
    {
        return view('customers.form', ['customer' => new Customer]);
    }

    public function store(Request $request)
    {
        Customer::create($this->validated($request));

        return redirect()->route('customers.index')->with('status', 'Customer created.');
    }

    public function show(Customer $customer, Request $request, ApicoCalculator $calculator)
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $search = trim((string) $request->input('q', ''));
        $statement = $calculator->customerStatement($customer, $from, $to);
        $statement['transactions'] = $this->filterStatementRows($statement['transactions'], $search);

        return view('customers.show', [
            'customer' => $customer,
            'statement' => $statement,
            'search' => $search,
            'exportColumns' => $this->statementColumns(),
            'statementTitle' => __('Customer Statement'),
            'statementGeneratedAt' => Carbon::today()->translatedFormat('F j, Y'),
            'title' => $this->statementTitle($customer->name),
        ]);
    }

    public function export(Customer $customer, Request $request, ApicoCalculator $calculator, SimpleXlsxExporter $exporter)
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $search = trim((string) $request->input('q', ''));
        $columns = collect($this->statementColumns())->only($request->input('columns', array_keys($this->statementColumns())))->all() ?: $this->statementColumns();
        $statement = $calculator->customerStatement($customer, $from, $to);
        $rows = $this->filterStatementRows($statement['transactions'], $search)->map(fn (array $row) => [
            'date' => $row['date'],
            'type' => __($row['type']),
            'description' => $row['description'],
            'weight_delta' => number_format((float) ($row['display_weight'] ?? $row['weight_delta']), 3, '.', ''),
            'amount' => number_format((float) $row['amount'], 3, '.', ''),
            'running_balance' => number_format((float) $row['running_balance'], 3, '.', ''),
            'running_weight' => number_format((float) $row['running_weight'], 3, '.', ''),
            'notes' => $row['notes'],
        ]);

        return $exporter->download(
            $this->statementFilename($customer->name),
            array_values($columns),
            $rows->map(fn (array $row) => collect(array_keys($columns))->map(fn (string $column) => $row[$column] ?? '')->all())->all(),
            [
                [__('Customer'), $customer->name],
                [__('Generated Date'), Carbon::today()->translatedFormat('F j, Y')],
                [__('Statement Period'), $from.' '.__('to').' '.$to],
            ]
        );
    }

    public function edit(Customer $customer)
    {
        return view('customers.form', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request));

        return redirect()->route('customers.show', $customer)->with('status', 'Customer updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'opening_balance' => ['nullable', 'numeric'],
            'opening_weight_balance_kg' => ['nullable', 'numeric'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function customerJodBalanceSql(): string
    {
        return 'ROUND(COALESCE(customers.opening_balance, 0)
            + COALESCE((SELECT SUM(total_amount) FROM recycle_outs WHERE recycle_outs.customer_id = customers.id), 0)
            + COALESCE((SELECT SUM(sales_value) FROM stock_sales WHERE stock_sales.customer_id = customers.id), 0)
            - COALESCE((SELECT SUM(amount) FROM payments WHERE payments.customer_id = customers.id), 0), 3)';
    }

    private function customerKgBalanceSql(): string
    {
        return 'ROUND(COALESCE(customers.opening_weight_balance_kg, 0)
            + COALESCE((SELECT SUM(weight_kg) FROM recycle_ins WHERE recycle_ins.customer_id = customers.id), 0)
            - COALESCE((SELECT SUM(weight_kg) FROM recycle_outs WHERE recycle_outs.customer_id = customers.id), 0), 3)';
    }

    private function filterStatementRows($rows, string $search)
    {
        if ($search === '') {
            return $rows;
        }

        $needle = strtolower($search);

        return $rows->filter(fn (array $row) => str_contains(strtolower(implode(' ', [
            $row['date'],
            $row['type'],
            $row['description'],
            $row['notes'],
        ])), $needle))->values();
    }

    private function statementColumns(): array
    {
        return [
            'date' => __('Date'),
            'type' => __('Type'),
            'description' => __('Description'),
            'weight_delta' => __('Kg +/-'),
            'amount' => __('JOD +/-'),
            'running_balance' => __('Running JOD Balance'),
            'running_weight' => __('Running Kg Balance'),
            'notes' => __('Notes'),
        ];
    }

    private function statementTitle(string $name): string
    {
        return __('Customer Statement').' - '.$name.' - '.Carbon::today()->translatedFormat('F j, Y');
    }

    private function statementFilename(string $name): string
    {
        $slug = trim((string) preg_replace('/[^\pL\pN]+/u', '-', $name), '-') ?: 'customer-statement';

        return $slug.'-'.Str::slug(Carbon::today()->format('F j')).'.xlsx';
    }
}

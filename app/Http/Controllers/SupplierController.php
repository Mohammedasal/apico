<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\ApicoCalculator;
use App\Services\SimpleXlsxExporter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $suppliers = Supplier::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('suppliers.index', compact('suppliers', 'search'));
    }

    public function create()
    {
        return view('suppliers.form', ['supplier' => new Supplier(['status' => 'active'])]);
    }

    public function store(Request $request)
    {
        Supplier::create($this->validated($request));

        return redirect()->route('suppliers.index')->with('status', 'Supplier created.');
    }

    public function show(Supplier $supplier, Request $request, ApicoCalculator $calculator)
    {
        $search = trim((string) $request->input('q', ''));
        $statement = $calculator->supplierStatement(
            $supplier,
            $request->input('from', now()->startOfMonth()->toDateString()),
            $request->input('to', now()->toDateString())
        );
        $statement['transactions'] = $this->filterStatementRows($statement['transactions'], $search);

        return view('suppliers.show', [
            'supplier' => $supplier,
            'statement' => $statement,
            'search' => $search,
            'exportColumns' => $this->statementColumns(),
            'statementTitle' => __('Supplier Statement'),
            'statementGeneratedAt' => Carbon::today()->translatedFormat('F j, Y'),
            'title' => $this->statementTitle($supplier->name),
        ]);
    }

    public function export(Supplier $supplier, Request $request, ApicoCalculator $calculator, SimpleXlsxExporter $exporter)
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $search = trim((string) $request->input('q', ''));
        $columns = collect($this->statementColumns())->only($request->input('columns', array_keys($this->statementColumns())))->all() ?: $this->statementColumns();
        $statement = $calculator->supplierStatement($supplier, $from, $to);
        $rows = $this->filterStatementRows($statement['transactions'], $search)->map(fn (array $row) => [
            'date' => $row['date'],
            'type' => __($row['type']),
            'description' => $row['description'],
            'weight_kg' => number_format((float) $row['weight_kg'], 3, '.', ''),
            'amount' => number_format((float) $row['amount'], 3, '.', ''),
            'running_balance' => number_format((float) $row['running_balance'], 3, '.', ''),
            'notes' => $row['notes'],
        ]);

        return $exporter->download(
            $this->statementFilename($supplier->name),
            array_values($columns),
            $rows->map(fn (array $row) => collect(array_keys($columns))->map(fn (string $column) => $row[$column] ?? '')->all())->all(),
            [
                [__('Supplier'), $supplier->name],
                [__('Generated Date'), Carbon::today()->translatedFormat('F j, Y')],
                [__('Statement Period'), $from.' '.__('to').' '.$to],
            ]
        );
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validated($request));

        return redirect()->route('suppliers.show', $supplier)->with('status', 'Supplier updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'opening_balance' => ['nullable', 'numeric'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string'],
        ]);
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
            'weight_kg' => __('Kg'),
            'amount' => __('JOD +/-'),
            'running_balance' => __('Running Balance'),
            'notes' => __('Notes'),
        ];
    }

    private function statementTitle(string $name): string
    {
        return __('Supplier Statement').' - '.$name.' - '.Carbon::today()->translatedFormat('F j, Y');
    }

    private function statementFilename(string $name): string
    {
        $slug = trim((string) preg_replace('/[^\pL\pN]+/u', '-', $name), '-') ?: 'supplier-statement';

        return $slug.'-'.Str::slug(Carbon::today()->format('F j')).'.xlsx';
    }
}

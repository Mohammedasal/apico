<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;

class SupplierPaymentController extends Controller
{
    public function index()
    {
        return view('supplier-payments.index', [
            'payments' => SupplierPayment::with(['supplier', 'creator', 'editor'])->latest('date')->latest('id')->paginate(25),
        ]);
    }

    public function create()
    {
        return view('supplier-payments.form', [
            'payment' => new SupplierPayment(['date' => now(), 'payment_type' => 'cash', 'cheque_status' => 'pending']),
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        SupplierPayment::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('supplier-payments.index')->with('status', 'Supplier payment saved.');
    }

    public function edit(SupplierPayment $supplierPayment)
    {
        return view('supplier-payments.form', [
            'payment' => $supplierPayment,
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, SupplierPayment $supplierPayment)
    {
        $supplierPayment->update($this->validated($request) + ['updated_by' => $request->user()->id]);

        return redirect()->route('supplier-payments.index')->with('status', 'Supplier payment updated.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'amount' => ['required', 'numeric'],
            'payment_type' => ['required', 'in:cash,cheque,bank_transfer,exchange_of_goods'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'cheque_due_date' => ['nullable', 'date'],
            'cheque_status' => ['nullable', 'in:pending,collected,bounced,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['cheque_status'] = $data['payment_type'] === 'cheque' ? ($data['cheque_status'] ?? 'pending') : 'pending';
        $data['cheque_due_date'] = $data['payment_type'] === 'cheque' ? ($data['cheque_due_date'] ?? null) : null;

        return $data;
    }
}

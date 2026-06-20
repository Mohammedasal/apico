<?php

namespace App\Http\Controllers;

use App\Models\ChequeOut;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;

class ChequeOutController extends Controller
{
    public function index()
    {
        return view('cheques.out', [
            'cheques' => ChequeOut::with(['creator', 'editor'])->orderBy('due_date')->orderBy('id')->paginate(50),
            'supplierCheques' => $this->supplierCheques(),
            'record' => null,
        ]);
    }

    public function store(Request $request)
    {
        ChequeOut::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('cheques-out.index')->with('status', 'Outgoing cheque saved.');
    }

    public function edit(ChequeOut $chequeOut)
    {
        return view('cheques.out', [
            'cheques' => ChequeOut::with(['creator', 'editor'])->orderBy('due_date')->orderBy('id')->paginate(50),
            'supplierCheques' => $this->supplierCheques(),
            'record' => $chequeOut,
        ]);
    }

    public function update(Request $request, ChequeOut $chequeOut)
    {
        $chequeOut->update($this->validated($request) + ['updated_by' => $request->user()->id]);

        return redirect()->route('cheques-out.index')->with('status', 'Outgoing cheque updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'payee' => ['required', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'cheque_number' => ['nullable', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:pending,cleared,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function supplierCheques()
    {
        return SupplierPayment::with(['supplier', 'creator', 'editor'])
            ->where('payment_type', 'cheque')
            ->orderBy('cheque_due_date')
            ->orderBy('date')
            ->get();
    }
}

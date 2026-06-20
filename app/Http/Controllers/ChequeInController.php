<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

class ChequeInController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status');

        return view('cheques.in', [
            'status' => $status,
            'cheques' => Payment::with('customer')
                ->where('payment_type', 'cheque')
                ->with(['creator', 'editor'])
                ->when($status, fn ($query) => $query->where('cheque_status', $status))
                ->orderBy('cheque_due_date')
                ->orderBy('date')
                ->paginate(50),
        ]);
    }

    public function update(Request $request, Payment $payment)
    {
        abort_unless($payment->payment_type === 'cheque', 404);

        $data = $request->validate([
            'cheque_status' => ['required', 'in:pending,collected,bounced,cancelled'],
        ]);

        $payment->update($data + ['updated_by' => $request->user()->id]);

        return redirect()->route('cheques-in.index')->with('status', 'Incoming cheque status updated.');
    }
}

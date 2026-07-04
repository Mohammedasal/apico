<?php

namespace App\Http\Controllers;

use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountMappingController extends Controller
{
    public function index()
    {
        return view('accounting.mappings.index', [
            'mappings' => AccountMapping::with('account')
                ->whereNull('entity_type')
                ->orderBy('mapping_key')
                ->get(),
            'accounts' => ChartOfAccount::where('is_active', true)
                ->where('is_posting', true)
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function update(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'mappings' => ['required', 'array'],
            'mappings.*' => ['required', 'exists:chart_of_accounts,id'],
        ]);

        DB::transaction(function () use ($data, $request, $audit) {
            foreach ($data['mappings'] as $mappingId => $accountId) {
                $mapping = AccountMapping::findOrFail($mappingId);
                $before = $mapping->toArray();
                $mapping->update(['account_id' => $accountId, 'updated_by' => $request->user()->id]);
                $audit->record('account_mapping_updated', $mapping, $before, $mapping->fresh()->toArray());
            }
        });

        return redirect()->route('accounting.mappings.index')->with('status', __('Account mappings updated.'));
    }
}

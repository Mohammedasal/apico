<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __($title ?? 'APICO Factory') }}</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --ink:#18201d; --muted:#66706b; --line:#dde5e1; --panel:#ffffff; --bg:#f4f6f5; --soft:#eef3f1; --accent:#176b55; --accent-strong:#0f513f; --blue:#2563eb; --amber:#b45309; --red:#b91c1c; --green:#166534; }
        * { box-sizing: border-box; }
        body { margin:0; font-family:Arial, sans-serif; background:var(--bg); color:var(--ink); font-size:14px; }
        html[dir="rtl"] body { font-family:Tahoma, Arial, sans-serif; }
        a { color:var(--accent); }
        .app-shell { display:grid; grid-template-columns:230px minmax(0, 1fr); min-height:100vh; }
        header { background:#fbfcfb; border-right:1px solid var(--line); padding:18px 14px; position:sticky; top:0; height:100vh; overflow:auto; }
        html[dir="rtl"] header { border-right:0; border-left:1px solid var(--line); }
        .brand { display:flex; align-items:center; gap:10px; margin-bottom:18px; padding:0 6px; }
        .brand-mark { display:grid; place-items:center; width:34px; height:34px; border-radius:8px; background:var(--accent); color:#fff; font-weight:bold; }
        .brand strong { display:block; font-size:16px; }
        .brand span { display:block; color:var(--muted); font-size:12px; }
        nav { display:grid; gap:3px; }
        nav a, .button, .link-button { color:var(--ink); text-decoration:none; border:1px solid transparent; background:transparent; padding:8px 10px; border-radius:6px; display:inline-flex; align-items:center; gap:8px; min-height:34px; font-weight:600; }
        nav a:hover, nav a.active, .button:hover, .link-button:hover { background:var(--soft); border-color:var(--line); color:var(--accent-strong); }
        .nav-section { color:var(--muted); font-size:11px; font-weight:bold; letter-spacing:.04em; text-transform:uppercase; margin:14px 10px 5px; }
        .main-area { min-width:0; }
        .topbar { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:14px 24px; background:rgba(244,246,245,.92); border-bottom:1px solid var(--line); position:sticky; top:0; z-index:2; backdrop-filter:blur(10px); }
        .topbar-title { font-weight:bold; }
        main { max-width:1360px; margin:0 auto; padding:22px 24px 32px; }
        h1 { font-size:24px; line-height:1.2; margin:0; }
        .toolbar { display:flex; justify-content:space-between; gap:14px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:10px; }
        .card, main > form:not(.filters) { background:var(--panel); border:1px solid var(--line); border-radius:8px; padding:14px; box-shadow:0 1px 2px #18201d0a; }
        .kpi { font-size:22px; font-weight:bold; margin-top:5px; line-height:1.15; }
        .kpi-card { min-height:92px; display:flex; flex-direction:column; justify-content:space-between; }
        .kpi-card .muted { font-size:12px; }
        .muted { color:var(--muted); }
        .section-title { display:flex; justify-content:space-between; align-items:center; gap:12px; margin:18px 0 9px; }
        .section-title h2 { margin:0; }
        .quick-actions { display:grid; grid-template-columns:repeat(auto-fit, minmax(155px, 1fr)); gap:10px; margin:12px 0 16px; }
        .quick-action { display:flex; align-items:center; gap:10px; min-height:64px; padding:12px; background:#fff; border:1px solid var(--line); border-radius:8px; text-decoration:none; color:var(--ink); box-shadow:0 1px 2px #18201d0a; }
        .quick-action:hover { border-color:var(--accent); transform:translateY(-1px); }
        .action-mark { display:grid; place-items:center; flex:0 0 34px; width:34px; height:34px; border-radius:8px; color:#fff; font-size:12px; font-weight:bold; }
        .mark-in { background:var(--green); }
        .mark-out { background:var(--accent); }
        .mark-pay { background:var(--amber); }
        .mark-buy { background:var(--blue); }
        .mark-sale { background:#7c3aed; }
        .quick-action strong { display:block; font-size:14px; }
        .quick-action strong + span { display:block; margin-top:2px; }
        .quick-action span { color:var(--muted); font-size:12px; }
        .quick-action .action-mark { color:#fff; font-size:12px; }
        .table-wrap { overflow:auto; border:1px solid var(--line); border-radius:8px; background:#fff; }
        table { width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--line); }
        .table-wrap table { border:0; }
        th, td { padding:9px 10px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; }
        html[dir="rtl"] th, html[dir="rtl"] td { text-align:right; }
        th { background:#f0f4f2; color:#44504a; font-size:12px; font-weight:bold; white-space:nowrap; }
        tr:hover td { background:#fbfdfc; }
        .statement-table { table-layout:fixed; }
        .statement-table .description-cell, .statement-table .notes-cell { font-size:12px; line-height:1.35; overflow-wrap:anywhere; word-break:break-word; white-space:normal; }
        .ledger-table th:nth-child(3), .ledger-table td:nth-child(3) { width:28%; }
        .ledger-table th:nth-child(8), .ledger-table td:nth-child(8) { width:16%; }
        .recycle-out-table th:nth-child(2), .recycle-out-table td:nth-child(2) { width:16%; }
        .recycle-out-table th:nth-child(9), .recycle-out-table td:nth-child(9) { width:14%; }
        h2 { margin:22px 0 10px; font-size:17px; }
        input, select, textarea { width:100%; padding:9px 10px; border:1px solid #cfd9d4; border-radius:6px; background:#fff; color:var(--ink); }
        input:focus, select:focus, textarea:focus { outline:2px solid #b8ded1; border-color:var(--accent); }
        textarea { min-height:86px; resize:vertical; }
        select.searchable-select { display:none; }
        .combo { position:relative; }
        .combo-input { padding-right:32px; }
        html[dir="rtl"] .combo-input { padding-right:10px; padding-left:32px; }
        .combo::after { content:""; position:absolute; right:11px; top:15px; border-left:5px solid transparent; border-right:5px solid transparent; border-top:6px solid var(--muted); pointer-events:none; }
        html[dir="rtl"] .combo::after { right:auto; left:11px; }
        .combo-list { display:none; position:absolute; z-index:20; left:0; right:0; top:calc(100% + 4px); max-height:220px; overflow:auto; background:#fff; border:1px solid var(--line); border-radius:6px; box-shadow:0 8px 20px #0000001a; }
        .combo.open .combo-list { display:block; }
        .combo-option { padding:9px 10px; cursor:pointer; }
        .combo-option:hover, .combo-option.active { background:#edf7f1; color:var(--accent); }
        .combo-option.empty { color:var(--muted); cursor:default; }
        label { display:block; font-size:13px; font-weight:bold; margin-bottom:6px; }
        .check { display:flex; align-items:center; gap:7px; margin:0; font-weight:600; }
        .check input { width:auto; }
        form.filters, .form-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:10px; align-items:end; }
        .filters { padding:10px; background:#fff; border:1px solid var(--line); border-radius:8px; }
        .toolbar .filters { grid-template-columns:repeat(3, minmax(118px, 1fr)); min-width:390px; }
        button { background:var(--accent); color:#fff; border:0; border-radius:6px; padding:10px 14px; cursor:pointer; font-weight:bold; }
        button:hover { background:var(--accent-strong); }
        .error { color:#b91c1c; font-size:13px; margin-top:4px; }
        .status { border:1px solid #a7d7bd; background:#edf9f1; padding:10px; border-radius:6px; margin-bottom:14px; }
        .amount-negative { color:#b91c1c; }
        .amount-positive { color:#166534; }
        .pagination { display:flex; gap:6px; flex-wrap:wrap; margin-top:12px; }
        .pagination a, .pagination span { border:1px solid var(--line); background:#fff; padding:6px 9px; border-radius:6px; text-decoration:none; color:var(--ink); }
        .pagination .active span, .pagination span[aria-current] { background:var(--accent); color:#fff; border-color:var(--accent); }
        @page { size:A4 landscape; margin:8mm; }
        @media print {
            header, .topbar, form, .no-print { display:none !important; }
            .app-shell { display:block; }
            main { max-width:none; padding:0; }
            body { background:#fff; font-size:10px; }
            body.print-ledger-only main > :not(.ledger-print-area) { display:none !important; }
            body.print-ledger-only .ledger-print-area { display:block !important; }
            h1 { font-size:18px; margin:0 0 4px; }
            h2 { font-size:12px; margin:10px 0 5px; }
            .grid { grid-template-columns:repeat(4, 1fr); gap:6px; margin:8px 0 !important; }
            .card { padding:6px; border-radius:4px; }
            .kpi { font-size:14px; margin-top:3px; }
            table { page-break-inside:auto; }
            tr { break-inside:avoid; page-break-inside:avoid; }
            th, td { font-size:9px; padding:4px; }
            .statement-table .description-cell, .statement-table .notes-cell { font-size:8.5px; line-height:1.25; }
        }
        @media (max-width: 880px) {
            .app-shell { display:block; }
            header { position:static; height:auto; border-right:0; border-bottom:1px solid var(--line); }
            html[dir="rtl"] header { border-left:0; }
            .brand { margin-bottom:10px; }
            nav { display:flex; flex-wrap:wrap; }
            .nav-section { width:100%; margin-top:10px; }
            .topbar { position:static; padding:12px 16px; }
            main { padding:16px; }
            .toolbar .filters { min-width:0; width:100%; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); }
        }
    </style>
</head>
<body>
@php $currentUser = auth()->user(); @endphp
<div class="app-shell">
<header>
    <div class="brand">
        <div class="brand-mark">A</div>
        <div><strong>APICO</strong><span>{{ __('Factory ledger') }}</span></div>
    </div>
    <nav>
        <a @class(['active' => request()->routeIs('dashboard')]) href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
        <div class="nav-section">{{ __('Transactions') }}</div>
        <a @class(['active' => request()->is('recycle-in*')]) href="{{ route('operations.index', 'recycle-in') }}">{{ __('Recycle In') }}</a>
        <a @class(['active' => request()->is('recycle-out*')]) href="{{ route('operations.index', 'recycle-out') }}">{{ __('Recycle Out') }}</a>
        <a @class(['active' => request()->is('payments*')]) href="{{ route('operations.index', 'payments') }}">{{ __('Payments') }}</a>
        <a @class(['active' => request()->is('stock-purchases*')]) href="{{ route('operations.index', 'stock-purchases') }}">{{ __('Purchases') }}</a>
        <a @class(['active' => request()->is('stock-sales*')]) href="{{ route('operations.index', 'stock-sales') }}">{{ __('Sales') }}</a>
        <div class="nav-section">{{ __('Master Data') }}</div>
        <a @class(['active' => request()->routeIs('customers.*')]) href="{{ route('customers.index') }}">{{ __('Customers') }}</a>
        <a @class(['active' => request()->routeIs('suppliers.*')]) href="{{ route('suppliers.index') }}">{{ __('Suppliers') }}</a>
        <a @class(['active' => request()->routeIs('materials.*')]) href="{{ route('materials.index') }}">{{ __('Materials') }}</a>
        @if ($currentUser?->canViewFinancialReports())
            <div class="nav-section">{{ __('Finance') }}</div>
            <a @class(['active' => request()->routeIs('production.*')]) href="{{ route('production.index') }}">{{ __('Production / P&L') }}</a>
        @endif
        <a @class(['active' => request()->routeIs('cheques-in.*')]) href="{{ route('cheques-in.index') }}">{{ __('Cheques In') }}</a>
        <a @class(['active' => request()->routeIs('cheques-out.*')]) href="{{ route('cheques-out.index') }}">{{ __('Cheques Out') }}</a>
        <a @class(['active' => request()->routeIs('supplier-payments.*')]) href="{{ route('supplier-payments.index') }}">{{ __('Supplier Payments') }}</a>
        @if ($currentUser?->canViewFinancialReports())
            <div class="nav-section">{{ __('Review') }}</div>
            <a @class(['active' => request()->routeIs('reports.*')]) href="{{ route('reports.monthly') }}">{{ __('Reports') }}</a>
        @endif
        @if ($currentUser?->canManageSystem())
            <div class="nav-section">{{ __('System') }}</div>
            <a @class(['active' => request()->routeIs('settings.*')]) href="{{ route('settings.index') }}">{{ __('Settings') }}</a>
            <a @class(['active' => request()->routeIs('users.*')]) href="{{ route('users.index') }}">{{ __('Users') }}</a>
        @endif
    </nav>
</header>
<div class="main-area">
<div class="topbar">
    <div class="topbar-title">{{ __($title ?? 'APICO Factory') }}</div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <div>
            <a class="button" href="{{ route('language.switch', 'en') }}">English</a>
            <a class="button" href="{{ route('language.switch', 'ar') }}">العربية</a>
        </div>
        <div class="muted">{{ $currentUser?->name }} | {{ __(ucwords(str_replace('_', ' ', $currentUser?->role ?? ''))) }} | {{ now()->format('d M Y') }}</div>
        <form method="post" action="{{ route('logout') }}" style="margin:0">
            @csrf
            <button class="link-button" style="padding:7px 10px">{{ __('Logout') }}</button>
        </form>
    </div>
</div>
<main>
    @if (session('status'))
        <div class="status">{{ __(session('status')) }}</div>
    @endif
    @yield('content')
</main>
</div>
</div>
<script>
document.querySelectorAll('select.searchable-select').forEach((select) => {
    const options = Array.from(select.options).map((option) => ({ value: option.value, text: option.text }));
    const selected = options.find((option) => option.value === select.value);
    const combo = document.createElement('div');
    const input = document.createElement('input');
    const list = document.createElement('div');

    combo.className = 'combo';
    input.type = 'text';
    input.className = 'combo-input';
    input.autocomplete = 'off';
    input.value = selected && selected.value !== '' ? selected.text : '';
    input.placeholder = select.options[0]?.text || @json(__('Select'));
    list.className = 'combo-list';

    const render = () => {
        const term = input.value.toLowerCase();
        const matches = options.filter((option) => option.value === '' || option.text.toLowerCase().includes(term));
        list.innerHTML = '';

        if (matches.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'combo-option empty';
            empty.textContent = @json(__('No matches'));
            list.appendChild(empty);
            return;
        }

        matches.forEach((option) => {
            const row = document.createElement('div');
            row.className = 'combo-option' + (option.value === select.value ? ' active' : '');
            row.textContent = option.text;
            row.dataset.value = option.value;
            row.addEventListener('mousedown', (event) => {
                event.preventDefault();
                select.value = option.value;
                input.value = option.value === '' ? '' : option.text;
                combo.classList.remove('open');
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
            list.appendChild(row);
        });
    };

    input.addEventListener('focus', () => {
        combo.classList.add('open');
        render();
    });
    input.addEventListener('input', () => {
        select.value = '';
        combo.classList.add('open');
        render();
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') combo.classList.remove('open');
    });
    input.addEventListener('blur', () => {
        setTimeout(() => {
            const selectedOption = options.find((option) => option.value === select.value);
            input.value = selectedOption && selectedOption.value !== '' ? selectedOption.text : '';
            combo.classList.remove('open');
        }, 120);
    });

    combo.appendChild(input);
    combo.appendChild(list);
    select.parentNode.insertBefore(combo, select);
});

document.querySelectorAll('input[type="number"]').forEach((input) => {
    input.addEventListener('wheel', () => input.blur());
});
</script>
</body>
</html>

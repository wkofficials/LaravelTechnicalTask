<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #1f2937; background: #f3f4f6; margin: 0; }
        .sheet { max-width: 780px; margin: 24px auto; background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .toolbar { max-width: 780px; margin: 16px auto 0; display: flex; justify-content: space-between; padding: 0 4px; }
        .toolbar a, .toolbar button { font: inherit; font-size: 14px; color: #2563eb; background: none; border: 0; cursor: pointer; text-decoration: none; }
        h1 { margin: 0; font-size: 28px; }
        .muted { color: #6b7280; }
        .row { display: flex; justify-content: space-between; gap: 24px; margin-top: 28px; }
        table { width: 100%; border-collapse: collapse; margin-top: 32px; }
        th, td { text-align: left; padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
        th:last-child, td:last-child { text-align: right; }
        .total td { font-weight: 700; font-size: 18px; border-bottom: 0; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 13px; font-weight: 600; }
        .paid { background: #dcfce7; color: #166534; } .open { background: #fef3c7; color: #92400e; } .void { background: #e5e7eb; color: #374151; }
        .note { margin-top: 28px; padding: 14px; background: #f9fafb; border-radius: 6px; font-size: 14px; }
        @media print { body { background: #fff; } .toolbar { display: none; } .sheet { box-shadow: none; margin: 0; max-width: none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ $backUrl }}">&larr; Back</a>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <div class="sheet">
        <div class="row" style="margin-top:0">
            <div>
                <h1>Invoice</h1>
                <div class="muted">{{ $invoice->number }}</div>
            </div>
            <div style="text-align:right">
                <strong>{{ config('billing.seller.name') }}</strong><br>
                @if (config('billing.seller.address'))<span class="muted">{!! nl2br(e(config('billing.seller.address'))) !!}</span><br>@endif
                <span class="muted">{{ config('billing.seller.email') }}</span>
            </div>
        </div>

        <div class="row">
            <div>
                <div class="muted">Billed to</div>
                <strong>{{ $invoice->company->name }}</strong>
            </div>
            <div style="text-align:right">
                <div><span class="muted">Issued:</span> {{ $invoice->created_at->toFormattedDateString() }}</div>
                <div><span class="muted">Due:</span> {{ $invoice->due_at->toFormattedDateString() }}</div>
                <div style="margin-top:6px">
                    <span class="badge {{ $invoice->status }}">{{ $invoice->statusLabel() }}</span>
                    @if ($invoice->paid_at) <span class="muted">on {{ $invoice->paid_at->toFormattedDateString() }}</span> @endif
                </div>
            </div>
        </div>

        <table>
            <thead><tr><th>Description</th><th>Amount</th></tr></thead>
            <tbody>
                <tr><td>{{ $invoice->description }}</td><td>{{ $invoice->amount() }}</td></tr>
                <tr class="total"><td>Total</td><td>{{ $invoice->amount() }}</td></tr>
            </tbody>
        </table>

        @if ($invoice->status === \App\Models\Invoice::STATUS_OPEN)
            <div class="note">
                <strong>How to pay:</strong> {{ config('billing.seller.payment_instructions') }}
                Please use <strong>{{ $invoice->number }}</strong> as the payment reference.
            </div>
        @elseif ($invoice->status === \App\Models\Invoice::STATUS_VOID)
            <div class="note">This invoice was cancelled and does not need to be paid.</div>
        @endif
    </div>
</body>
</html>

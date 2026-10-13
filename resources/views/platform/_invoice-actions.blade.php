{{-- Mark paid / void for an open invoice. Expects $invoice. --}}
@if ($invoice->status === \App\Models\Invoice::STATUS_OPEN)
    <form method="POST" action="{{ route('platform.invoices.paid', $invoice) }}" class="d-inline-flex gap-1">
        @csrf
        <input type="text" name="payment_reference" class="form-control form-control-sm" style="width: 9rem"
               placeholder="Bank reference" aria-label="Payment reference for {{ $invoice->number }}">
        <button type="submit" class="btn btn-sm btn-success"
                data-confirm="Record payment of {{ $invoice->amount() }} for {{ $invoice->number }}? This activates the plan."
                onclick="return confirm(this.dataset.confirm);">Mark paid</button>
    </form>
    <form method="POST" action="{{ route('platform.invoices.void', $invoice) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-secondary"
                data-confirm="Void {{ $invoice->number }}? The company won't need to pay it."
                onclick="return confirm(this.dataset.confirm);">Void</button>
    </form>
@endif

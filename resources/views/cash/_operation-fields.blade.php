@php
    $sameOperation = old('cash_action') === $action
        && (int) old('cash_register_id') === (int) $register->id;
    $operationKey = $sameOperation ? old('idempotency_key', $key) : $key;
@endphp
<input type="hidden" name="cash_action" value="{{ $action }}">
<input type="hidden" name="cash_register_id" value="{{ $register->id }}">
<input type="hidden" name="idempotency_key" value="{{ $operationKey }}">

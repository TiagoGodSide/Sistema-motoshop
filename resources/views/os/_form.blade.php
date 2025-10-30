@csrf
<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label">Cliente</label>
    <input name="customer_name" class="form-control" value="{{ old('customer_name', $workorder->customer_name ?? '') }}" required>
  </div>
  <div class="col-md-3">
    <label class="form-label">Telefone</label>
    <input name="customer_phone" class="form-control" value="{{ old('customer_phone', $workorder->customer_phone ?? '') }}">
  </div>
  <div class="col-md-3">
    <label class="form-label">Documento</label>
    <input name="customer_doc" class="form-control" value="{{ old('customer_doc', $workorder->customer_doc ?? '') }}">
  </div>

  <div class="col-md-3">
    <label class="form-label">Placa</label>
    <input name="vehicle_plate" class="form-control" value="{{ old('vehicle_plate', $workorder->vehicle_plate ?? '') }}">
  </div>
  <div class="col-md-5">
    <label class="form-label">Modelo</label>
    <input name="vehicle_model" class="form-control" value="{{ old('vehicle_model', $workorder->vehicle_model ?? '') }}">
  </div>
  <div class="col-md-4">
    <label class="form-label">KM</label>
    <input name="vehicle_km" class="form-control" value="{{ old('vehicle_km', $workorder->vehicle_km ?? '') }}">
  </div>

  <div class="col-md-4">
    <label class="form-label">Status</label>
    <select name="status" class="form-select">
      @foreach(['aberta','em_andamento','aguardando_aprovacao','finalizada','cancelada'] as $st)
        <option value="{{ $st }}" @selected(old('status', $workorder->status ?? 'aberta') === $st)>
          {{ str_replace('_',' ', ucfirst($st)) }}
        </option>
      @endforeach
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label">Previsão (opcional)</label>
    <input type="datetime-local" name="expected_at" class="form-control"
           value="{{ old('expected_at', optional($workorder->expected_at ?? null)->format('Y-m-d\TH:i')) }}">
  </div>
  <div class="col-md-4">
    <label class="form-label">Total (opcional)</label>
    <input type="number" step="0.01" min="0" name="total" class="form-control"
           value="{{ old('total', $workorder->total ?? 0) }}">
  </div>

  <div class="col-12">
    <label class="form-label">Descrição / Serviços</label>
    <textarea name="notes" rows="4" class="form-control">{{ old('notes', $workorder->notes ?? '') }}</textarea>
  </div>
</div>

@if ($errors->any())
  <div class="alert alert-danger mt-3">
    <ul class="mb-0">
      @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
    </ul>
  </div>
@endif
<button class="btn btn-dark mt-3">Salvar</button>
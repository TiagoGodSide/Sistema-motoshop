{{-- Modal: Ajustar estoque (fora do form) --}}
<div class="modal fade" id="adjustStockModal{{ $product->id }}" tabindex="-1" aria-labelledby="adjustStockLabel{{ $product->id }}" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('products.adjust', $product) }}" enctype="multipart/form-data" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title" id="adjustStockLabel{{ $product->id }}">Ajustar estoque</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2 text-muted">
          Estoque atual: <span class="fw-bold">{{ (int)$product->stock }}</span>
          @if(!is_null($product->min_stock))
            <span class="ms-2">| Mínimo: {{ (int)$product->min_stock }}</span>
          @endif
        </div>

        <div class="row g-3">
        <div class="col-6">
          <label class="form-label">Tipo</label>
          <select name="type" class="form-select" required>
            <option value="in">Entrada (+)</option>
            <option value="out">Saída (−)</option>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label">Quantidade</label>
          <input type="number" name="qty" class="form-control" min="1" step="1" required>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Motivo</label>
          <select name="reason_code" id="reason_code{{ $product->id }}" class="form-select">
            <option value="">— selecione —</option>
            <option value="inventory">Inventário/Acerto</option>
            <option value="loss">Perda/Quebra</option>
            <option value="exchange">Troca/Devolução</option>
            <option value="gift">Brinde</option>
            <option value="other">Outro</option>
          </select>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Complemento</label>
          <input type="text" name="reason" id="reason{{ $product->id }}" class="form-control" maxlength="180" placeholder="ex.: Ajuste de inventário">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Preço unit. (opcional)</label>
          <input type="number" name="unit_price" class="form-control" min="0" step="0.01" placeholder="ex.: 12,50">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Comprovante (jpg/png/pdf, até 4MB)</label>
          <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
        </div>
      </div>

      @push('scripts')
      <script>
      document.addEventListener('DOMContentLoaded', () => {
        const sel = document.getElementById('reason_code{{ $product->id }}');
        const txt = document.getElementById('reason{{ $product->id }}');
        if(!sel || !txt) return;
        function updatePlaceholder(){
          const map = {
            'inventory':'Ex.: Inventário 2025/10',
            'loss':'Ex.: Quebra na bancada',
            'exchange':'Ex.: Devolução OS #123',
            'gift':'Ex.: Brinde ao cliente',
            'other':'Descreva o motivo'
          };
          txt.placeholder = map[sel.value] || 'ex.: Ajuste de inventário';
        }
        sel.addEventListener('change', updatePlaceholder);
        updatePlaceholder();
      });
      </script>
      @endpush

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary">Salvar ajuste</button>
      </div>
    </form>
  </div>
</div>

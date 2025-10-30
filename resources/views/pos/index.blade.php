  @extends('layouts.app')
  @section('title','PDV')

  @section('content')
  <!-- Casca externa do PDV (borda/sombra) -->
  <div class="mt-3">
      <i class="bi bi-upc-scan"></i> <strong>PDV</strong>
        </div>
        <div class="border rounded-3 p-2 mb-3">
                <div class="d-flex align-items-center gap-2">
                  <strong class="mb-0">Cliente</strong>
                  <input id="customerInput" class="form-control form-control-sm" placeholder="Nome, telefone ou doc." style="max-width:280px">
                  <button id="customerAdd" class="btn btn-sm btn-outline-secondary">Novo</button>
                  <span id="customerSelected" class="text-muted small ms-auto">—</span>
                </div>
            <div id="customerHints" class="list-group list-group-flush"></div>
          </div>
      <div class="border rounded-4 p-3 mb-3 bg-white shadow-sm">
        <div class="row g-3">
          {{-- ESQUERDA: Busca e resultados --}}
          <div class="col-lg-8">
            <div class="row g-2 mb-2">
              <div class="col-md-12">
                <label class="form-label mb-1">Escaneie ou digite o código</label>
                <input id="barcodeInput" class="form-control form-control-lg" placeholder="EAN, interno ou SKU">
              </div><br>
              <div class="col-md-12">
                <label class="form-label mb-1">Buscar por nome/SKU</label>
                <input id="searchInput" class="form-control form-control-lg" placeholder="Ex.: Óleo 10W40">
       </div>
   </div>

        <div class="mb-2 fw-semibold">Produtos encontrados</div>
              <div id="results" class="row row-cols-1 row-cols-md-2 g-3">
                    {{-- itens renderizados via JS --}}
          </div>
                    </div>

          {{-- DIREITA: Carrinho --}}
          <div class="col-lg-4">
            <div class="border rounded-3 p-3 bg-white shadow-sm position-sticky" style="top: 1rem;">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-basket"></i>
                  <strong class="mb-0">Carrinho</strong>
                </div>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="baixarEstoque" checked>
                  <label class="form-check-label small" for="baixarEstoque">Dar baixa</label>
                </div>
              </div>

              <div class="table-responsive">
                <table class="table table-sm align-middle" id="cartTable">
                  <thead class="table-light">
                    <tr>
                      <th>Produto</th>
                      <th class="text-center">Qtd</th>
                      {{-- Se quiser desconto por item, descomente a linha abaixo e o JS já trata --}}
                      {{-- <th class="text-end" data-disc-col>Desc</th> --}}
                      <th class="text-end">Preço</th>
                      <th class="text-end">Subtotal</th>
                      <th style="width:1%"></th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>

              <div class="d-flex justify-content-between">
                <span class="text-muted">Subtotal</span>
                <strong id="subtotal">R$ 0,00</strong>
              </div>

              <div class="d-flex justify-content-between align-items-center my-2">
                <span class="text-muted">Desconto total</span>
                <input id="discountTotal" class="form-control form-control-sm text-end" style="width:120px" value="0,00">
              </div>

              <div class="d-flex justify-content-between">
                <span class="text-muted">Descontos</span>
                <span>R$ 0,00</span>
              </div>

              <hr class="my-2">
              <div class="d-flex justify-content-between align-items-center">
                <strong>Total</strong>
                <div class="fs-5" id="total">R$ 0,00</div>
              </div>

              <button id="finalizar" class="btn btn-dark w-100 mt-2">
                <i class="bi bi-receipt"></i> Finalizar
              </button>

              <div class="d-flex align-items-center gap-2 mt-2">
                <label class="text-muted small mb-0">Pagamento</label>
                <select id="paymentMethod" class="form-select form-select-sm w-auto">
                  @foreach(config('payment.methods', ['dinheiro'=>'Dinheiro','pix'=>'PIX','cartao'=>'Cartão','outro'=>'Outro']) as $val=>$label)
                    <option value="{{ $val }}">{{ $label }}</option>
                  @endforeach
                </select>
              </div>
          <button class="btn btn-outline-primary w-100 mt-2" data-bs-toggle="modal" data-bs-target="#paySplitModal">
      <i class="bi bi-columns-gap"></i> Dividir pagamento
    </button>

          <div class="d-flex gap-2 mt-2">
            <button id="orcamento" class="btn btn-outline-secondary flex-fill">
              <i class="bi bi-file-earmark"></i> Orçamento
            </button>
            <button id="imprimir" class="btn btn-outline-secondary flex-fill">
              <i class="bi bi-printer"></i> Imprimir
            </button>
          </div>

          <div class="d-flex justify-content-end mt-2">
            <a href="{{ route('cash.index') }}" class="btn btn-outline-dark">
              <i class="bi bi-cash-coin"></i> Caixa
            </a>
          </div>
          <div class="btn-group ms-2">
            <a href="{{ route('os.create') }}" class="btn btn-outline-secondary">
              <i class="bi bi-file-plus"></i> Nova O.S.
            </a>
            <a href="{{ route('os.index') }}" class="btn btn-outline-secondary">
              <i class="bi bi-wrench"></i> O.S. abertas
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Split -->
      <div class="modal fade" id="paySplitModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Dividir pagamento</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
              <div class="small text-muted mb-2">Informe os valores por método. A soma deve bater com o Total.</div>
              <div class="row g-2">
                @foreach(config('payment.methods') as $val=>$label)
                <div class="col-6">
                  <label class="form-label small">{{ $label }}</label>
                  <input class="form-control pay-split" data-method="{{ $val }}" placeholder="0,00">
                </div>
                @endforeach
              </div>
              <div class="mt-2 d-flex justify-content-between">
                <span class="text-muted">Soma</span>
                <strong id="splitSum">R$ 0,00</strong>
              </div>
              <div class="d-flex justify-content-between">
                <span class="text-muted">Total da venda</span>
                <strong id="splitTarget">R$ 0,00</strong>
              </div>
              <div class="form-text">Deixe vazio para métodos não usados.</div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
              <button id="applySplit" class="btn btn-dark">Aplicar</button>
            </div>
          </div>
        </div>
      </div>

  @endsection

 @push('scripts')
<script>
      (() => {
        // ===== utils =====
        const $id = (s) => document.getElementById(s);
        const money = (v) => Number(v || 0).toLocaleString('pt-BR', { style:'currency', currency:'BRL' });
        const parseBRL = (v) => (v==null) ? 0 : (Number(String(v).replace(/\./g,'').replace(',','.')) || 0);
        const parseMoneyText = (s) => parseBRL(String(s||'').replace(/[^\d,,-.]/g,'').replace(/\s+/g,''));
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

        // ===== elementos =====
        const results     = $id('results');
        const cartBody    = document.querySelector('#cartTable tbody');
        const subtotalEl  = $id('subtotal');
        const totalEl     = $id('total');
        const searchInput = $id('searchInput');
        const barcodeInput= $id('barcodeInput');
        const btnFinalizar= $id('finalizar');
        const btnOrcamento= $id('orcamento');
        const btnImprimir = $id('imprimir');
        const baixarEstoqueEl = $id('baixarEstoque');
        const discountTotalEl = $id('discountTotal');
        const pmSelect    = $id('paymentMethod');
        const hasItemDiscountCol = !!document.querySelector('#cartTable thead [data-disc-col]');
        const csrfToken   = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        // Cliente (rápido)
        const customerInput = $id('customerInput');
        const customerHints = $id('customerHints');
        const customerAdd   = $id('customerAdd');
        const customerSelected = $id('customerSelected');
        let customer = null; // {id,name}

        // Split (pagamento dividido)
        const splitModal = $id('paySplitModal');
        const splitSumEl = $id('splitSum');
        const splitTargetEl = $id('splitTarget');
        let splitPayments = null; // [{method, amount}, ...]

        // ===== estado inicial =====
        if (!results || !cartBody || !subtotalEl || !totalEl) {
          console.warn('PDV: IDs essenciais não encontrados (results, cartTable, subtotal, total).');
          return;
        }

        // restaura última forma de pagamento
        if (pmSelect) {
          const last = localStorage.getItem('pdv_pm');
          if (last && [...pmSelect.options].some(o=>o.value===last)) pmSelect.value = last;
          pmSelect.addEventListener('change', ()=> localStorage.setItem('pdv_pm', pmSelect.value));
        }

        // ===== render da lista de resultados =====
        function renderResults(list) {
          results.innerHTML = '';
          if (!list?.length) {
            results.innerHTML = '<div class="text-muted">Nenhum produto encontrado…</div>';
            return;
          }
          list.forEach(p => {
            const col = document.createElement('div');
            col.className = 'col';

            const card = document.createElement('div');
            card.className = 'border rounded p-2 shadow-sm d-flex justify-content-between align-items-center';

            const left = document.createElement('div');
            const name = document.createElement('div');
            name.className = 'fw-medium';
            name.textContent = p.name;
            const meta = document.createElement('small');
            meta.className = 'text-muted';
            meta.textContent = `SKU ${p.sku}${p.ean ? ` · EAN ${p.ean}` : ''}`;
            left.appendChild(name); left.appendChild(meta);

            const right = document.createElement('div');
            right.className = 'text-end';
            const price = document.createElement('div');
            price.className = 'fw-bold';
            price.textContent = money(p.price);
            const btn = document.createElement('button');
            btn.className = 'btn btn-sm btn-dark mt-1';
            btn.textContent = '+ Adicionar';
            btn.addEventListener('click', () => addToCart({
              id: p.id, sku: p.sku, price: p.price, name: p.name
            }));

            right.appendChild(price); right.appendChild(btn);
            card.appendChild(left); card.appendChild(right);
            col.appendChild(card);
            results.appendChild(col);
          });
        }

        // ===== cálculo =====
        function recalc() {
          let subtotal = 0;

          cartBody.querySelectorAll('tr').forEach(tr => {
            if (!tr.dataset.id) return;

            const price = parseFloat(tr.dataset.price || 0);
            const qtyEl = tr.querySelector('[data-qty]') || tr.querySelector('input');
            const qty   = Number(qtyEl?.value || 0);

            const discEl = tr.querySelector('[data-disc]');
            const disc   = discEl ? parseBRL(discEl.value) : 0;

            const line  = Math.max(0, price * qty - disc);
            const subTd = tr.querySelector('[data-sub]');
            if (subTd) subTd.textContent = money(line);

            subtotal += line;
          });

          subtotalEl.textContent = money(subtotal);
          const dTot = discountTotalEl ? parseBRL(discountTotalEl.value) : 0;
          totalEl.textContent = money(Math.max(0, subtotal - dTot));
        }

        // ===== carrinho =====
        function addToCart(p) {
          const existing = cartBody.querySelector(`tr[data-id="${p.id}"]`);
          if (existing) {
            const qtyInput = existing.querySelector('[data-qty]') || existing.querySelector('input');
            qtyInput.value = Number(qtyInput.value || 0) + 1;
            return recalc();
          }

          const tr = document.createElement('tr');
          tr.dataset.id = p.id;
          tr.dataset.price = p.price;

          const tdName = document.createElement('td');
          tdName.innerHTML = `${esc(p.name)}<br><small class="text-muted">SKU ${esc(p.sku)}</small>`;

          const tdQty = document.createElement('td');
          tdQty.className = 'text-center';
          tdQty.style.width = '110px';
          tdQty.innerHTML = `
            <div class="input-group input-group-sm">
              <button class="btn btn-outline-secondary" data-dec>-</button>
              <input class="form-control text-center" data-qty type="number" min="1" value="1">
              <button class="btn btn-outline-secondary" data-inc>+</button>
            </div>
          `;

          let tdDisc = null;
          if (hasItemDiscountCol) {
            tdDisc = document.createElement('td');
            tdDisc.className = 'text-end';
            tdDisc.style.width = '110px';
            tdDisc.innerHTML = `<input class="form-control form-control-sm text-end" data-disc value="0">`;
          }

          const tdPrice = document.createElement('td');
          tdPrice.className = 'text-end';
          tdPrice.textContent = money(p.price);

          const tdSub = document.createElement('td');
          tdSub.className = 'text-end';
          tdSub.setAttribute('data-sub','');
          tdSub.textContent = money(p.price);

          const tdRm = document.createElement('td');
          tdRm.className = 'text-end';
          tdRm.innerHTML = `<button class="btn btn-sm btn-link text-danger" data-remove>&times;</button>`;

          tr.appendChild(tdName);
          tr.appendChild(tdQty);
          if (tdDisc) tr.appendChild(tdDisc);
          tr.appendChild(tdPrice);
          tr.appendChild(tdSub);
          tr.appendChild(tdRm);

          cartBody.appendChild(tr);
          recalc();
        }

        function getCartItems() {
          const items = [];
          cartBody.querySelectorAll('tr').forEach(tr => {
            if (!tr.dataset.id) return;
            const qtyEl  = tr.querySelector('[data-qty]') || tr.querySelector('input');
            const discEl = tr.querySelector('[data-disc]');
            items.push({
              product_id: Number(tr.dataset.id),
              qty:        Number(qtyEl?.value || 0),
              price:      parseFloat(tr.dataset.price || 0),
              discount:   discEl ? parseBRL(discEl.value) : 0
            });
          });
          return items;
        }

        // ===== busca produtos =====
        async function fetchFind(q='') {
          try {
            const url = new URL(`{{ route('pos.find') }}`, window.location.origin);
            if (q !== '') url.searchParams.set('q', q);
            const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!r.ok) throw new Error('Falha ao buscar produtos');
            const list = await r.json();
            renderResults(list);
          } catch (e) {
            console.error(e);
            results.innerHTML = `<div class="text-muted">Erro ao carregar produtos.</div>`;
          }
        }

        // ===== eventos carrinho =====
        cartBody.addEventListener('click', (e) => {
          const tr = e.target.closest('tr');
          if (!tr) return;

          // destaque linha selecionada
          cartBody.querySelectorAll('tr').forEach(r => r.classList.remove('table-active'));
          tr.classList.add('table-active');

          if (e.target.matches('[data-remove]')) {
            tr.remove(); return recalc();
          }

          const qtyInput = tr.querySelector('[data-qty]') || tr.querySelector('input');
          if (!qtyInput) return;

          if (e.target.matches('[data-inc]')) {
            qtyInput.value = Number(qtyInput.value || 0) + 1; return recalc();
          }
          if (e.target.matches('[data-dec]')) {
            qtyInput.value = Math.max(1, Number(qtyInput.value || 0) - 1); return recalc();
          }
        });
        cartBody.addEventListener('input', recalc);

        // ===== busca por nome =====
        if (searchInput) {
          searchInput.addEventListener('input', (e) => {
            const q = e.target.value.trim();
            if (q.length < 2) { fetchFind(''); return; }
            fetchFind(q);
          });
        }

        // ===== leitura por código =====
        if (barcodeInput) {
          barcodeInput.addEventListener('keydown', async (e) => {
            if (e.key !== 'Enter') return;
            const code = e.target.value.trim();
            if (!code) return;
            const url = new URL(`{{ route('pos.find') }}`, window.location.origin);
            url.searchParams.set('q', code);
            const r = await fetch(url, { headers: { 'Accept':'application/json' } });
            const list = await r.json();
            if (list[0]) addToCart(list[0]);
            e.target.select();
          });
        }

        // ===== cliente rápido =====
        customerInput?.addEventListener('input', async (e)=>{
          const q = e.target.value.trim();
          if (q.length < 2){ customerHints.innerHTML=''; return; }
          const url = new URL(`{{ route('customers.find') }}`, window.location.origin);
          url.searchParams.set('q', q);
          const r = await fetch(url, { headers:{'Accept':'application/json'} });
          const list = await r.json();
          customerHints.innerHTML = '';
          list.forEach(c=>{
            const a = document.createElement('button');
            a.type = 'button';
            a.className = 'list-group-item list-group-item-action';
            a.textContent = `${c.name}${c.phone ? ' · '+c.phone : ''}`;
            a.addEventListener('click', ()=>{
              customer = { id:c.id, name:c.name };
              customerSelected && (customerSelected.textContent = c.name);
              customerHints.innerHTML = '';
              customerInput.value = '';
            });
            customerHints.appendChild(a);
          });
        });

        customerAdd?.addEventListener('click', async ()=>{
          const name = prompt('Nome do cliente:');
          if (!name) return;
          const r = await fetch(`{{ route('customers.quick') }}`, {
            method:'POST',
            headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN': csrfToken},
            body: JSON.stringify({ name })
          });
          if (!r.ok){ return alert('Não foi possível criar o cliente.'); }
          const c = await r.json();
          customer = { id:c.id, name:c.name };
          customerSelected && (customerSelected.textContent = c.name);
        });

        // ===== split de pagamento =====
        function sumSplit(){
          let sum = 0;
          document.querySelectorAll('#paySplitModal .pay-split').forEach(inp=>{
            const v = parseBRL(inp.value);
            if (v > 0) sum += v;
          });
          splitSumEl && (splitSumEl.textContent = money(sum));
          splitTargetEl && (splitTargetEl.textContent = totalEl.textContent);
          return {sum, total: parseMoneyText(totalEl.textContent) };
        }

        splitModal?.addEventListener('show.bs.modal', ()=>{
          document.querySelectorAll('#paySplitModal .pay-split').forEach(inp=> inp.value = '');
          splitPayments = null;
          sumSplit();
        });
        document.querySelectorAll('#paySplitModal .pay-split').forEach(inp=> inp.addEventListener('input', sumSplit));

        $id('applySplit')?.addEventListener('click', ()=>{
          const {sum, total} = sumSplit();
          if (Math.abs(sum - total) > 0.009) {
            return alert('A soma dos pagamentos deve ser igual ao Total.');
          }
          const list = [];
          document.querySelectorAll('#paySplitModal .pay-split').forEach(inp=>{
            const v = parseBRL(inp.value);
            const m = inp.dataset.method;
            if (v > 0) list.push({ method:m, amount: v });
          });
          splitPayments = list;
          pmSelect?.classList.add('is-valid');
          const modal = window.bootstrap?.Modal?.getInstance(splitModal);
          if (modal) modal.hide();
        });

        // ===== atalhos =====
        document.addEventListener('keydown', (e) => {
          // F2 = Finalizar
          if (e.key === 'F2') { e.preventDefault(); btnFinalizar?.click(); }
          // F3 = Orçamento
          if (e.key === 'F3') { e.preventDefault(); btnOrcamento?.click(); }
          // Delete = remove linha selecionada (fora de inputs)
          if (e.key === 'Delete') {
            const tag = document.activeElement?.tagName;
            if (!/^(INPUT|TEXTAREA|SELECT)$/.test(tag||'')) {
              const sel = cartBody.querySelector('tr.table-active');
              if (sel) { sel.remove(); recalc(); }
            }
          }
          // Ctrl+F = focar busca
          if (e.ctrlKey && e.key.toLowerCase() === 'f') {
            e.preventDefault();
            searchInput?.focus();
          }
        });

        // ===== finalizar (pago) =====
        if (btnFinalizar) btnFinalizar.addEventListener('click', async () => {
          const items = getCartItems();
          if (!items.length) return alert('Carrinho vazio.');

          const pm = pmSelect?.value || 'dinheiro';
          const lowered = baixarEstoqueEl?.checked ?? true;
          const dTot = discountTotalEl ? parseBRL(discountTotalEl.value) : 0;

          const payload = {
            items,
            discount: dTot,
            lowered_stock: lowered,
            draft: false,
            customer_name: customer?.name || undefined,
            customer_id:   customer?.id   || undefined,
            ...(Array.isArray(splitPayments) && splitPayments.length
                ? { payments: splitPayments }
                : { payment_method: pm })
          };

          try {
            const r = await fetch(`{{ route('pos.checkout') }}`, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
              },
              body: JSON.stringify(payload)
            });
            if (!r.ok) throw new Error(await r.text());
            const order = await r.json();
            window.location = `/orders/${order.id}/receipt`;
          } catch (err) {
            alert('Erro ao finalizar:\n' + err.message);
          }
        });

        // ===== orçamento (draft) =====
        if (btnOrcamento) btnOrcamento.addEventListener('click', async () => {
          const items = getCartItems();
          if (!items.length) return alert('Carrinho vazio.');

          const dTot = discountTotalEl ? parseBRL(discountTotalEl.value) : 0;

          const payload = {
            items,
            discount: dTot,
            draft: true,
            customer_name: customer?.name || undefined,
            customer_id:   customer?.id   || undefined
          };

          try {
            const r = await fetch(`{{ route('pos.checkout') }}`, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
              },
              body: JSON.stringify(payload)
            });
            if (!r.ok) throw new Error(await r.text());
            const order = await r.json();
            window.location = `/orders/${order.id}`;
          } catch (err) {
            alert('Erro ao salvar orçamento:\n' + err.message);
          }
        });

        // ===== imprimir (no PDV, lembrinho) =====
        if (btnImprimir) btnImprimir.addEventListener('click', () => {
          alert('Finalize a venda para imprimir o cupom.');
        });

        // primeira carga
        fetchFind('');
  })();
</script>
@endpush

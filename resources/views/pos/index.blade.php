@extends('layouts.app')
@section('title','PDV')
@section('content')

<div class="row g-3">
  <div class="col-lg-7">
    <div class="soft-card bg-white p-3">
      <div class="row g-2 align-items-center">
        <div class="col-md-7">
          <label class="form-label mb-1">Escaneie ou digite o código</label>
          <input id="barcodeInput" class="form-control form-control-lg" placeholder="EAN, interno ou SKU" autofocus>
        </div>
        <div class="col-md-5">
          <label class="form-label mb-1">Buscar por nome/SKU</label>
          <input id="searchInput" class="form-control form-control-lg" placeholder="Ex.: Óleo 10W40">
        </div>
      </div>

      <div class="mt-3">
        <h6 class="mb-2">Produtos encontrados</h6>
        <div id="results" class="row row-cols-1 row-cols-md-2 g-2"></div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="soft-card bg-white p-3 h-100 d-flex flex-column">
      <div class="d-flex justify-content-between align-items-center">
        <h6><i class="bi bi-basket"></i> Carrinho</h6>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="baixarEstoque" checked>
          <label for="baixarEstoque" class="form-check-label">Dar baixa</label>
        </div>
      </div>

      <div class="table-responsive mt-2 flex-grow-1">
        <table class="table table-sm align-middle" id="cartTable">
          <thead class="table-light">
            <tr><th>Produto</th><th class="text-center">Qtd</th><th class="text-end">Desc</th><th class="text-end">Preço</th><th class="text-end">Subtotal</th><th></th></tr>
            <td class="text-end" style="width:110px">
              <input class="form-control form-control-sm text-end" data-disc value="0">
            </td>

          </thead>
          <tbody></tbody>
        </table>
      </div>

      <div class="mt-auto">
        <div class="d-flex justify-content-between"><span class="text-muted">Subtotal</span><strong id="subtotal">R$ 0,00</strong></div>
        <div class="d-flex justify-content-between">
              <span class="text-muted">Desconto total</span>
              <input id="discountTotal" class="form-control form-control-sm text-end" style="width:120px" value="0,00">
        </div>
        <div class="d-flex justify-content-between"><span class="text-muted">Descontos</span><strong id="discount">R$ 0,00</strong></div>
        <div class="d-flex justify-content-between fs-5 border-top pt-2"><span>Total</span><strong id="total">R$ 0,00</strong></div>
        <div class="mt-3 d-grid gap-2">
          <button class="btn btn-dark btn-lg" id="finalizar"><i class="bi bi-cash-coin"></i> Finalizar</button>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary w-50" id="orcamento"><i class="bi bi-receipt"></i> Orçamento</button>
            <button class="btn btn-outline-secondary w-50" id="imprimir"><i class="bi bi-printer"></i> Imprimir</button>
          </div>
              <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 class="mb-0"><i class="bi bi-upc-scan"></i> PDV</h4>
            <a href="{{ route('cash.index') }}" class="btn btn-outline-dark">
              <i class="bi bi-cash-coin"></i> Caixa
            </a>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

@push('scripts')
<script>
(() => {
  // ===== util =====
  const $id = (s) => document.getElementById(s);
  const money = (v) => Number(v || 0).toLocaleString('pt-BR', { style:'currency', currency:'BRL' });
  const parseBRL = (v) => {
    if (v == null) return 0;
    return Number(String(v).replace(/\./g,'').replace(',','.')) || 0;
  };
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
  const discountTotalEl = $id('discountTotal'); // pode não existir
  const hasItemDiscountCol = !!document.querySelector('#cartTable thead [data-disc-col]');
  const csrfToken   = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  if (!results || !cartBody || !subtotalEl || !totalEl) {
    console.warn('PDV: estrutura HTML não encontrada. Verifique IDs (results, cartTable, subtotal, total).');
    return;
  }

  // ===== render da lista de resultados =====
  function renderResults(list) {
    results.innerHTML = '';
    list.forEach(p => {
      const col = document.createElement('div');
      col.className = 'col';

      const card = document.createElement('div');
      card.className = 'border rounded p-2 d-flex justify-content-between align-items-center';

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
      // não usar JSON no atributo → usa dataset + closure
      btn.addEventListener('click', () => addToCart({
        id: p.id, sku: p.sku, price: p.price, name: p.name
      }));

      right.appendChild(price); right.appendChild(btn);
      card.appendChild(left); card.appendChild(right);
      col.appendChild(card);
      results.appendChild(col);
    });
  }

  // ===== recálculo =====
  function recalc() {
    let subtotal = 0;

    cartBody.querySelectorAll('tr').forEach(tr => {
      if (!tr.dataset.id) return; // ignora linhas que não são itens

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

  // ===== adicionar item =====
  function addToCart(p) {
    // já existe?
    const existing = cartBody.querySelector(`tr[data-id="${p.id}"]`);
    if (existing) {
      const qtyInput = existing.querySelector('[data-qty]') || existing.querySelector('input');
      qtyInput.value = Number(qtyInput.value || 0) + 1;
      return recalc();
    }

    const tr = document.createElement('tr');
    tr.dataset.id = p.id;
    tr.dataset.price = p.price;

    // célula produto
    const tdName = document.createElement('td');
    tdName.innerHTML = `${esc(p.name)}<br><small class="text-muted">SKU ${esc(p.sku)}</small>`;

    // célula qtd
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

    // célula desconto por item (opcional)
    let tdDisc = null;
    if (hasItemDiscountCol) {
      tdDisc = document.createElement('td');
      tdDisc.className = 'text-end';
      tdDisc.style.width = '110px';
      tdDisc.innerHTML = `<input class="form-control form-control-sm text-end" data-disc value="0">`;
    }

    // célula preço
    const tdPrice = document.createElement('td');
    tdPrice.className = 'text-end';
    tdPrice.textContent = money(p.price);

    // célula subtotal
    const tdSub = document.createElement('td');
    tdSub.className = 'text-end';
    tdSub.setAttribute('data-sub','');
    tdSub.textContent = money(p.price);

    // célula remover
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

  // ===== coletar itens =====
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

  // ===== busca no backend =====
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

  // ===== eventos: resultados / carrinho =====
  cartBody.addEventListener('click', (e) => {
    const tr = e.target.closest('tr');
    if (!tr) return;

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
  if (discountTotalEl) discountTotalEl.addEventListener('input', recalc);

  // busca por nome
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      const q = e.target.value.trim();
      if (q.length < 2) { fetchFind(''); return; }
      fetchFind(q);
    });
  }

  // leitura por código
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

  // finalizar (pago)
  if (btnFinalizar) btnFinalizar.addEventListener('click', async () => {
    const items = getCartItems();
    if (!items.length) return alert('Carrinho vazio.');

    const pm = prompt('Forma de pagamento? (dinheiro, pix, cartao, outro)', 'dinheiro') || 'dinheiro';
    const lowered = baixarEstoqueEl?.checked ?? true;
    const dTot = discountTotalEl ? parseBRL(discountTotalEl.value) : 0;

    try {
      const r = await fetch(`{{ route('pos.checkout') }}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
          items, discount: dTot, payment_method: pm, lowered_stock: lowered, draft: false
        })
      });
      if (!r.ok) throw new Error(await r.text());
      const order = await r.json();
      window.location = `/orders/${order.id}/receipt`;
    } catch (err) {
      alert('Erro ao finalizar:\n' + err.message);
    }
  });

  // orçamento (draft)
  if (btnOrcamento) btnOrcamento.addEventListener('click', async () => {
    const items = getCartItems();
    if (!items.length) return alert('Carrinho vazio.');
    const dTot = discountTotalEl ? parseBRL(discountTotalEl.value) : 0;

    try {
      const r = await fetch(`{{ route('pos.checkout') }}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ items, discount: dTot, draft: true })
      });
      if (!r.ok) throw new Error(await r.text());
      const order = await r.json();
      window.location = `/orders/${order.id}`;
    } catch (err) {
      alert('Erro ao salvar orçamento:\n' + err.message);
    }
  });

  // imprimir (no PDV avisa para finalizar primeiro)
  if (btnImprimir) btnImprimir.addEventListener('click', () => {
    alert('Finalize a venda para imprimir o cupom.');
  });

  // primeira carga
  fetchFind('');
})();
</script>
@endpush


@endsection

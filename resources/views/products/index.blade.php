@extends('layouts.app')
@section('title','Produtos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-box-seam"></i> Produtos</h4>
  <div class="btn-group">
    <a href="{{ route('products.import.form') }}" class="btn btn-outline-secondary">
      <i class="bi bi-upload"></i> Importar CSV
    </a>
    <a href="{{ route('products.export.csv') }}" class="btn btn-outline-secondary">
      <i class="bi bi-download"></i> Exportar CSV
    </a>
   @if($tab === 'baixo-minimo')
    <a href="{{ route('products.low.csv') }}" class="btn btn-outline-secondary">
      <i class="bi bi-download"></i> Exportar “Abaixo do mínimo”
    </a>
  @endif
    <button id="btnBatchLabels" class="btn btn-outline-primary" disabled
            data-bs-toggle="modal" data-bs-target="#labelsBatchModal">
      <i class="bi bi-upc-scan"></i> Etiquetas (lote)
    </button>
    <a href="{{ route('products.create') }}" class="btn btn-dark">
      <i class="bi bi-plus-lg"></i> Novo
    </a>

  </div>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link {{ ($tab ?? 'ativos')==='ativos' ? 'active' : '' }}"
       href="{{ route('products.index',['tab'=>'ativos']) }}">Ativos</a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ ($tab ?? '')==='desativados' ? 'active' : '' }}"
       href="{{ route('products.index',['tab'=>'desativados']) }}">Desativados</a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ ($tab ?? '')==='sem-estoque' ? 'active' : '' }}"
       href="{{ route('products.index',['tab'=>'sem-estoque']) }}">
       Sem Estoque
       @isset($noStockCount)<span class="badge text-bg-secondary">{{ $noStockCount }}</span>@endisset
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ ($tab ?? '')==='baixo-minimo' ? 'active' : '' }}"
       href="{{ route('products.index',['tab'=>'baixo-minimo']) }}">
       Abaixo do mínimo
       @isset($lowCount)<span class="badge text-bg-warning">{{ $lowCount }}</span>@endisset
    </a>
  </li>
</ul>

<form method="get" class="input-group mb-3" style="max-width:400px">
  <input type="hidden" name="tab" value="{{ $tab }}">
  <input name="q" class="form-control" value="{{ $q ?? '' }}" placeholder="Buscar por nome, SKU, código">
  <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
</form>

<div class="table-responsive">
  <table class="table table-hover table-sm align-middle">
  <thead class="table-light">
    <tr>
      <th style="width:36px;">
        <input type="checkbox" id="chkAll"> {{-- seleciona todos --}}
      </th>
      <th>Produto</th>
      <th>SKU</th>
      <th>Preço</th>
      <th>Estoque</th>
      <th>Cód. Interno</th>
      <th class="text-end">Ações</th>
    </tr>
  </thead>

  <tbody>
    @foreach($products as $p)
      <tr>
        <td>
          <input type="checkbox"
                 class="row-chk"
                 value="{{ $p->id }}"
                 data-name="{{ $p->name }}">
        </td>
        <td>{{ $p->name }}</td>
        <td>{{ $p->sku }}</td>
        <td>R$ {{ number_format($p->price,2,',','.') }}</td>
        <td>{{ $p->stock }}</td>
        <td><span class="kbd">{{ $p->internal_barcode }}</span></td>
        <td class="text-end">
          <a href="{{ route('products.edit',$p) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
          <a href="{{ route('products.history',$p) }}" class="btn btn-sm btn-outline-secondary">Histórico</a>
          <a href="{{ route('products.label',$p) }}" target="_blank" class="btn btn-sm btn-outline-primary">Etiqueta</a>
          <form action="{{ route('products.toggle',$p) }}" method="POST" class="d-inline">
            @csrf
            <button class="btn btn-sm btn-outline-{{ $p->is_active ? 'danger' : 'success' }}">
              {{ $p->is_active ? 'Inativar' : 'Ativar' }}
            </button>
          </form>
        </td>
      </tr>
    @endforeach
  </tbody>
</table>

</div>

{{ $products->links() }}

    <div class="modal fade" id="labelsBatchModal" tabindex="-1" aria-labelledby="labelsBatchLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('products.labels.batch') }}" class="modal-content">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title" id="labelsBatchLabel">Imprimir etiquetas (lote)</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3 mb-2">
              <div class="col-md-4">
                <label class="form-label">Código a usar</label>
                <select name="code" class="form-select">
                  <option value="internal">Interno (preferencial)</option>
                  <option value="ean">EAN (fallback para interno/SKU)</option>
                  <option value="sku">SKU (fallback para interno/EAN)</option>
                </select>
              </div>
            </div>
            <div class="input-group input-group-sm mb-2">
                <span class="input-group-text">Qtd p/ todos</span>
                <input type="number" id="qtyAll" min="1" step="1" class="form-control" value="1">
                <button type="button" class="btn btn-outline-secondary" id="btnApplyQty">Aplicar</button>
             </div>
            <div id="labelsList"></div>
            <div class="text-muted small mt-2">Dica: deixe a quantidade = 1 para itens sem repetição.</div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-primary">Gerar etiquetas</button>
          </div>
        </form>
      </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', () => {
      const chkAll = document.getElementById('chkAll');
      const btn = document.getElementById('btnBatchLabels');
      const list = document.getElementById('labelsList');

      function selected() {
        return Array.from(document.querySelectorAll('.row-chk:checked'));
      }
      function refreshBtn() {
        btn.disabled = selected().length === 0;
      }

      // selecionar todos
      if (chkAll) {
        chkAll.addEventListener('change', () => {
          document.querySelectorAll('.row-chk').forEach(c => c.checked = chkAll.checked);
          refreshBtn();
        });
      }
      // selecionar individuais
      document.querySelectorAll('.row-chk').forEach(c => c.addEventListener('change', refreshBtn));

      // ao abrir o modal, montar a lista
      const modal = document.getElementById('labelsBatchModal');
      modal.addEventListener('show.bs.modal', () => {
        const rows = selected();
        list.innerHTML = '';
        if (rows.length === 0) return;

        const wrap = document.createElement('div');
        wrap.className = 'table-responsive';
        const table = document.createElement('table');
        table.className = 'table table-sm align-middle';
        table.innerHTML = `
          <thead class="table-light">
            <tr><th>Produto</th><th style="width:120px">Quantidade</th></tr>
          </thead>
          <tbody></tbody>
        `;
        const tbody = table.querySelector('tbody');

        rows.forEach((chk) => {
          const id = chk.value;
          const name = chk.dataset.name;
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${name}<input type="hidden" name="ids[]" value="${id}"></td>
            <td><input type="number" min="1" step="1" name="qty[${id}]" value="1" class="form-control form-control-sm"></td>
          `;
          tbody.appendChild(tr);
        });

        wrap.appendChild(table);
        list.appendChild(wrap);
      });
    });
    </script>
    <script>
        (() => {
          const KEY = 'labels:selected'; // { id: name }
          const btn = document.getElementById('btnBatchLabels');
          const chkAll = document.getElementById('chkAll');

          const load = () => JSON.parse(localStorage.getItem(KEY) || '{}');
          const save = (m) => localStorage.setItem(KEY, JSON.stringify(m));
          const count = () => Object.keys(load()).length;

          function refreshFromStorageOnPage() {
            const map = load();
            document.querySelectorAll('.row-chk').forEach(chk => {
              chk.checked = !!map[chk.value];
            });
            if (chkAll) {
              const rows = Array.from(document.querySelectorAll('.row-chk'));
              chkAll.checked = rows.length && rows.every(c => c.checked);
              chkAll.indeterminate = rows.some(c => c.checked) && !chkAll.checked;
            }
            if (btn) {
              btn.disabled = count() === 0;
              btn.innerHTML = `<i class="bi bi-upc-scan"></i> Etiquetas (lote) ${count() ? '· '+count() : ''}`;
            }
          }

          function hookCheckboxes() {
            document.querySelectorAll('.row-chk').forEach(chk => {
              chk.addEventListener('change', () => {
                const map = load();
                if (chk.checked) map[chk.value] = chk.dataset.name || chk.value;
                else delete map[chk.value];
                save(map);
                refreshFromStorageOnPage();
              });
            });
            if (chkAll) {
              chkAll.addEventListener('change', () => {
                const map = load();
                document.querySelectorAll('.row-chk').forEach(chk => {
                  chk.checked = chkAll.checked;
                  if (chk.checked) map[chk.value] = chk.dataset.name || chk.value;
                  else delete map[chk.value];
                });
                save(map);
                refreshFromStorageOnPage();
              });
            }
          }

          // monta a lista do modal a partir do storage (todas as páginas)
          const modal = document.getElementById('labelsBatchModal');
          modal?.addEventListener('show.bs.modal', () => {
            const list = document.getElementById('labelsList');
            const map = load();
            list.innerHTML = '';
            if (!Object.keys(map).length) return;

            const wrap = document.createElement('div');
            wrap.className = 'table-responsive';
            wrap.innerHTML = `
              <div class="d-flex justify-content-between mb-2">
                <div class="small text-muted">Total selecionados: ${Object.keys(map).length}</div>
                <button type="button" class="btn btn-sm btn-outline-danger" id="btnClearSel">Limpar seleção</button>
              </div>
              <table class="table table-sm align-middle">
                <thead class="table-light"><tr><th>Produto</th><th style="width:120px">Quantidade</th></tr></thead>
                <tbody></tbody>
              </table>`;
            const tbody = wrap.querySelector('tbody');
            Object.entries(map).forEach(([id, name]) => {
              const tr = document.createElement('tr');
              tr.innerHTML = `
                <td>${name}<input type="hidden" name="ids[]" value="${id}"></td>
                <td><input type="number" min="1" step="1" name="qty[${id}]" value="1" class="form-control form-control-sm"></td>`;
              tbody.appendChild(tr);
            });
            list.appendChild(wrap);

            wrap.querySelector('#btnClearSel')?.addEventListener('click', () => {
              localStorage.removeItem(KEY);
              refreshFromStorageOnPage();
              list.innerHTML = '<div class="text-muted">Seleção limpa.</div>';
            });
          });

          // inicializa
          hookCheckboxes();
          refreshFromStorageOnPage();
        })();
        </script>
        <script>
          document.getElementById('btnApplyQty')?.addEventListener('click', () => {
            const v = parseInt(document.getElementById('qtyAll').value || '1', 10);
            document.querySelectorAll('#labelsList input[name^="qty["]').forEach(inp => inp.value = v);
          });
        </script>
    @endpush
@endsection

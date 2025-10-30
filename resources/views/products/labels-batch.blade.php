<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Etiquetas (lote)</title>
<style>
  :root { --cols: 3; --label-w: auto; }
  body { font-family: Arial, sans-serif; margin:0 }
  .toolbar { padding:8px; display:flex; gap:8px; align-items:center; border-bottom:1px solid #ddd }
  .grid { display:grid; grid-template-columns: repeat(var(--cols), var(--label-w)); gap: 8px; padding: 8px; }
  .label { border:1px dashed #aaa; padding:6px; text-align:center; }
  .name { font-size: 12px; white-space: nowrap; overflow:hidden; text-overflow: ellipsis; }
  .code { font-size: 11px; margin-top: 2px; }
  .price { font-size: 12px; font-weight:bold; margin-top:2px; display:none; }
  body.show-price .price { display:block; }
  @media print {
    .toolbar { display:none }
    body { margin:0 }
  }
</style>
</head>
<body>
<div class="toolbar no-print">
  <button onclick="window.print()">Imprimir</button>
  <span style="margin-left:8px">Formato:</span>
  <button type="button" onclick="setPaper('58')"  >58 mm</button>
  <button type="button" onclick="setPaper('80')"  >80 mm</button>
  <button type="button" onclick="setPaper('a4')"  >A4</button>
  <span style="margin-left:8px">Exibir:</span>
  <label><input type="checkbox" id="chkPrice"> Preço</label>
</div>

<div class="grid" id="grid">
  @foreach($items as $it)
    @for($i=0; $i<$it['qty']; $i++)
      <div class="label">
        <div class="name">{{ $it['product']->name }}</div>
        <div>{!! $it['svg'] !!}</div>
        <div class="code">{{ $it['code'] }}</div>
        <div class="price">R$ {{ number_format((float)$it['product']->price,2,',','.') }}</div>
      </div>
    @endfor
  @endforeach
</div>

<script>
function setPaper(kind){
  if (kind==='58') { document.documentElement.style.setProperty('--cols', 1); document.documentElement.style.setProperty('--label-w', '58mm'); }
  else if (kind==='80') { document.documentElement.style.setProperty('--cols', 1); document.documentElement.style.setProperty('--label-w', '80mm'); }
  else { document.documentElement.style.setProperty('--cols', 3); document.documentElement.style.setProperty('--label-w', 'auto'); }
}
document.getElementById('chkPrice').addEventListener('change', e => {
  document.body.classList.toggle('show-price', e.target.checked);
});
// default
setPaper('a4');
</script>
</body>
</html>

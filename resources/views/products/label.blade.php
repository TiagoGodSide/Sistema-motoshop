<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Etiqueta — {{ $product->name }}</title>
<style>
  @media print {
    .no-print { display:none; }
    body { margin:0; }
  }
  .label {
    width: 58mm; /* ajuste para a sua impressora térmica */
    padding: 6px;
    font-family: Arial, sans-serif;
    border: 1px dashed #aaa;
  }
  .name { font-size: 12px; font-weight: bold; }
  .sku  { font-size: 10px; color:#555; }
</style>
</head>
<body>
<div class="label">
  <div class="name">{{ $product->name }}</div>
  <div class="sku">SKU: {{ $product->sku }}</div>
  <div>{!! $barcodeSvg !!}</div>
  <div style="font-size:10px">{{ $product->internal_barcode }}</div>
</div>
<button class="no-print" onclick="window.print()">Imprimir</button>
</body>
</html>

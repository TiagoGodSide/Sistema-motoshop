<!doctype html><html lang="pt-br"><head>
<meta charset="utf-8"><title>Etiqueta — {{ $product->name }}</title>
<style> @media print{ .no-print{display:none} body{margin:0} }
  body{font-family:Arial,sans-serif}
  .tag{width:60mm;padding:6px}
  .center{text-align:center}.small{font-size:11px}.bold{font-weight:bold}
</style></head><body>
<div class="tag">
  <div class="bold small">{{ $product->name }}</div>
  <div class="center" style="margin:4px 0">{!! $barcodeSvg !!}</div>
  <div class="center small">{{ $code }}</div>
  <div class="center small">R$ {{ number_format($product->price,2,',','.') }}</div>
</div>
<div class="no-print" style="padding:8px;text-align:center">
  <a href="javascript:print()">Imprimir</a>
</div>
</body></html>

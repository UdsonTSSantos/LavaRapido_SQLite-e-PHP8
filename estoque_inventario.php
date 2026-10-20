<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_estoque();

// Busca os itens ativos em ordem alfabética
$stmt = db()->query("SELECT * FROM estoque_itens WHERE ativo = 1 ORDER BY nome COLLATE NOCASE");
$itens = $stmt->fetchAll();

$total_itens = 0;
$total_valor_custo = 0;
$total_valor_venda = 0;

foreach ($itens as $i) {
    $total_itens += $i['quantidade'];
    $total_valor_custo += ($i['quantidade'] * $i['preco_custo_centavos']);
    $total_valor_venda += ($i['quantidade'] * $i['preco_venda_centavos']);
}

$titulo = 'Inventário de Estoque';
require __DIR__ . '/header.php';
?>

<div class="max-w-5xl mx-auto">
  <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Inventário de Estoque</h1>
      <p class="text-sm text-slate-500">Posição atual do estoque e valores investidos.</p>
    </div>

    <div class="text-xs text-slate-500 font-semibold">
          Emitido em: <?= date('d/m/Y H:i') ?>
    </div>

    <div class="flex items-center gap-2">
      <button onclick="window.print()" class="rounded-lg bg-brand-500 hover:bg-brand-700 text-white px-4 py-2 text-sm font-bold shadow-sm no-print">Imprimir</button>
      <a href="estoque.php" class="rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 text-sm font-semibold no-print">← Voltar</a>
    </div>
  </div>

  <div class="bg-white rounded-2xl shadow-card border border-slate-200 overflow-hidden print:shadow-none print:border-0">
    
    <!-- Cabeçalho do Relatório -->
    <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex flex-wrap gap-4 justify-between items-center print:bg-white print:border-b-2 print:border-slate-800">
      <h2 class="font-bold text-slate-700 uppercase tracking-wider text-sm">Posição de Estoque</h2>
      
      <div class="flex gap-6 text-sm">
        <div>QTD.Itens: <span class="font-bold text-slate-900"><?= number_format($total_itens, 2, ',', '.') ?></span></div>
        <div>Custo: <span class="font-bold text-rose-600"><?= e(centavos_para_moeda_brl($total_valor_custo)) ?></span></div>
        <div>Venda: <span class="font-bold text-emerald-700"><?= e(centavos_para_moeda_brl($total_valor_venda)) ?></span></div>
      </div>
    </div>

    <table class="min-w-full text-sm">
      <thead class="bg-slate-100 text-slate-600 print:bg-white">
        <tr>
          <th class="text-left px-4 py-3 font-bold">Código</th>
          <th class="text-left px-4 py-3 font-bold">Descrição do Item</th>
          <th class="text-right px-4 py-3 font-bold">Qtd em Estoque</th>
          <th class="text-right px-4 py-3 font-bold">Preço Custo</th>
          <th class="text-right px-4 py-3 font-bold">Total Custo</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php if(!$itens): ?>
          <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Nenhum item cadastrado no estoque.</td></tr>
        <?php else: foreach($itens as $i): 
          $subtotal_custo = $i['quantidade'] * $i['preco_custo_centavos'];
          $isBaixo = $i['quantidade'] <= $i['estoque_minimo'];
        ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 font-mono text-slate-600"><?= e($i['codigo']) ?></td>
            <td class="px-4 py-3 font-semibold text-slate-800">
              <?= e($i['nome']) ?>
              <?php if ($isBaixo): ?>
                <span class="ml-2 text-[10px] uppercase font-bold text-rose-600 bg-rose-100 px-2 py-0.5 rounded-full print:hidden">Baixo</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-right font-bold <?= $isBaixo ? 'text-rose-600' : 'text-slate-900' ?>">
              <?= number_format($i['quantidade'], 2, ',', '.') ?> <span class="text-xs font-normal text-slate-500"><?= e($i['unidade']) ?></span>
            </td>
            <td class="px-4 py-3 text-right text-slate-600"><?= e(centavos_para_moeda_brl((int)$i['preco_custo_centavos'])) ?></td>
            <td class="px-4 py-3 text-right font-bold text-slate-700"><?= e(centavos_para_moeda_brl((int)$subtotal_custo)) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
  @media print {
    body { background: #fff; }
    nav, .no-print { display: none !important; }
    main { padding: 0 !important; max-width: 100% !important; }
    table { font-size: 12px !important; }
    th, td { padding: 8px 12px !important; }
  }
</style>

<?php require __DIR__ . '/footer.php'; ?>
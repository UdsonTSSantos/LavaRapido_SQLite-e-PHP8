<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_estoque();
ensure_lavagem_pecas();

$mesRef = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mesRef)) $mesRef = date('Y-m');
$inicio = $mesRef . '-01';
$fim    = date('Y-m-t', strtotime($inicio));

// Agrupa por item e soma as quantidades e os valores
$sql = "
    SELECT 
        p.estoque_item_id,
        p.nome,
        SUM(p.quantidade) AS qtd_total,
        SUM(p.total_centavos) AS total_geral
    FROM lavagem_pecas p
    INNER JOIN lavagem_entradas e ON e.id = p.entrada_id
    WHERE e.data_entrada BETWEEN :ini AND :fim
    GROUP BY p.estoque_item_id, p.nome
    ORDER BY p.nome ASC
";
$st = db()->prepare($sql);
$st->execute([':ini' => $inicio, ':fim' => $fim]);
$vendas = $st->fetchAll();

$totalGeral = 0;
foreach ($vendas as $v) $totalGeral += (int)$v['total_geral'];

$titulo = 'Relatório de Peças Utilizadas';
require __DIR__ . '/header.php';
?>

<div class="max-w-5xl mx-auto">
  <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Peças Utilizadas no Mês</h1>
      <p class="text-sm text-slate-500">Relatório de saídas do estoque agrupado por peça.</p>
    </div>
    <div class="flex items-center gap-2">
      <form method="get" class="flex items-center gap-2">
        <label class="text-xs text-slate-500 font-bold">Mês:</label>
        <input type="month" name="mes" value="<?= e($mesRef) ?>" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-400">
      </form>
      <button onclick="window.print()" class="rounded-lg bg-brand-500 hover:bg-brand-700 text-white px-4 py-2 text-sm font-bold shadow-sm no-print">Imprimir</button>
      <a href="estoque.php" class="rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 text-sm font-semibold no-print">← Voltar</a>
    </div>
  </div>

  <div class="bg-white rounded-2xl shadow-card border border-slate-200 overflow-hidden print:shadow-none print:border-0">
    <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center print:bg-white print:border-b-2 print:border-slate-800">
      <h2 class="font-bold text-slate-700 uppercase tracking-wider text-sm">Relação de Peças</h2>
      <div class="text-sm">Total no Mês: <span class="font-bold text-emerald-700 text-lg"><?= e(centavos_para_moeda_brl($totalGeral)) ?></span></div>
    </div>
    <table class="min-w-full text-sm">
      <thead class="bg-slate-100 text-slate-600 print:bg-white">
        <tr>
          <th class="text-left px-6 py-3 font-bold">Peça</th>
          <th class="text-right px-6 py-3 font-bold">Qtd Total</th>
          <th class="text-right px-6 py-3 font-bold">Total Gerado</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php if(!$vendas): ?>
          <tr><td colspan="3" class="px-6 py-8 text-center text-slate-400">Nenhuma peça utilizada neste mês.</td></tr>
        <?php else: foreach($vendas as $v): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-6 py-3 font-semibold text-slate-800"><?= e($v['nome']) ?></td>
            <td class="px-6 py-3 text-right font-bold text-slate-700"><?= number_format($v['qtd_total'], 2, ',', '.') ?></td>
            <td class="px-6 py-3 text-right font-bold text-emerald-700"><?= e(centavos_para_moeda_brl((int)$v['total_geral'])) ?></td>
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
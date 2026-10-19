<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_estoque();
ensure_lavagem_pecas();

$mesRef = $_GET['mes'] ?? date('Y-m');
$inicio = $mesRef . '-01';
$fim    = date('Y-m-t', strtotime($inicio));

$sql = "
    SELECT p.*, e.data_entrada, e.hora_entrada, e.id as entrada_id,
           COALESCE(c.nome, e.cliente_nome_avulso, '—') AS cliente_nome
    FROM lavagem_pecas p
    INNER JOIN lavagem_entradas e ON e.id = p.entrada_id
    LEFT JOIN clientes c ON c.id = e.cliente_id
    WHERE e.data_entrada BETWEEN :ini AND :fim
    ORDER BY e.data_entrada DESC, p.id DESC
";
$st = db()->prepare($sql);
$st->execute([':ini' => $inicio, ':fim' => $fim]);
$vendas = $st->fetchAll();

$totalGeral = 0;
foreach ($vendas as $v) $totalGeral += (int)$v['total_centavos'];

$titulo = 'Relatório de Peças Vendidas';
require __DIR__ . '/header.php';
?>

<div class="max-w-5xl mx-auto">
  <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Peças Utilizadas no Mês</h1>
      <p class="text-sm text-slate-500">Relatório de saídas do estoque em comandas.</p>
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
    <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
      <h2 class="font-bold text-slate-700 uppercase tracking-wider text-sm">Relação de Peças</h2>
      <div class="text-sm">Total no Mês: <span class="font-bold text-emerald-700 text-lg"><?= e(centavos_para_moeda_brl($totalGeral)) ?></span></div>
    </div>
    <table class="min-w-full text-sm">
      <thead class="bg-slate-100 text-slate-600 print:bg-white">
        <tr>
          <th class="text-left px-4 py-2.5 font-bold">Data</th>
          <th class="text-left px-4 py-2.5 font-bold">Peça</th>
          <th class="text-left px-4 py-2.5 font-bold">Cliente / Comanda</th>
          <th class="text-right px-4 py-2.5 font-bold">Qtd</th>
          <th class="text-right px-4 py-2.5 font-bold">Unitário</th>
          <th class="text-right px-4 py-2.5 font-bold">Total</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php if(!$vendas): ?>
          <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Nenhuma peça utilizada neste mês.</td></tr>
        <?php else: foreach($vendas as $v): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-2.5 whitespace-nowrap"><?= date('d/m/Y', strtotime($v['data_entrada'])) ?></td>
            <td class="px-4 py-2.5 font-semibold text-slate-800"><?= e($v['nome']) ?></td>
            <td class="px-4 py-2.5"><span class="font-medium"><?= e($v['cliente_nome']) ?></span> <span class="text-xs text-slate-400 ml-1">(#<?= $v['entrada_id'] ?>)</span></td>
            <td class="px-4 py-2.5 text-right font-bold text-slate-800"><?= number_format($v['quantidade'], 2, ',', '.') ?></td>
            <td class="px-4 py-2.5 text-right text-slate-600"><?= e(centavos_para_moeda_brl((int)$v['preco_unitario_centavos'])) ?></td>
            <td class="px-4 py-2.5 text-right font-bold text-emerald-700"><?= e(centavos_para_moeda_brl((int)$v['total_centavos'])) ?></td>
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
    main { padding: 0 !important; }
  }
</style>
<?php require __DIR__ . '/footer.php'; ?>
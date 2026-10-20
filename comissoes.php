<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_comissoes();

$mesRef = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mesRef)) $mesRef = date('Y-m');
$inicio = $mesRef . '-01';
$fim    = date('Y-m-t', strtotime($inicio));

// Busca todas as comissões do mês agrupadas por usuário
$sql = "
    SELECT u.nome AS usuario_nome, COUNT(c.id) as qtd_servicos, SUM(c.valor_centavos) AS total_centavos
    FROM comissoes_lancadas c
    INNER JOIN usuarios u ON u.id = c.usuario_id
    WHERE c.data_entrada BETWEEN :ini AND :fim
    GROUP BY c.usuario_id
    ORDER BY total_centavos DESC
";
$st = db()->prepare($sql);
$st->execute([':ini' => $inicio, ':fim' => $fim]);
$resumoUsuarios = $st->fetchAll();

$totalGeral = 0;
foreach ($resumoUsuarios as $ru) $totalGeral += (int)$ru['total_centavos'];

// Busca os detalhes para exibir na tabela
$sqlDetalhes = "
    SELECT c.*, e.placa, u.nome AS usuario_nome
    FROM comissoes_lancadas c
    INNER JOIN lavagem_entradas e ON e.id = c.entrada_id
    INNER JOIN usuarios u ON u.id = c.usuario_id
    WHERE c.data_entrada BETWEEN :ini AND :fim
    ORDER BY c.data_entrada DESC, c.id DESC
";
$stD = db()->prepare($sqlDetalhes);
$stD->execute([':ini' => $inicio, ':fim' => $fim]);
$detalhes = $stD->fetchAll();

$titulo = 'Comissões a Pagar';
require __DIR__ . '/header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-bold text-slate-900">Módulo de Comissões</h1>
    <p class="text-sm text-slate-500">Relação de valores a serem pagos aos funcionários.</p>
  </div>
  <div class="flex items-center gap-2">
    <form method="get" class="flex items-center gap-2">
      <label class="text-xs text-slate-500 font-bold">Mês:</label>
      <input type="month" name="mes" value="<?= e($mesRef) ?>" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-400">
    </form>
    <button onclick="window.print()" class="rounded-lg bg-brand-500 hover:bg-brand-700 text-white px-4 py-2 text-sm font-bold shadow-sm no-print">Imprimir</button>
  </div>

    <div class="flex items-center gap-2">
    <form method="get" class="flex items-center gap-2">
      <label class="text-xs text-slate-500 font-bold">Mês:</label>
      <input type="month" name="mes" value="<?= e($mesRef) ?>" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-400">
    </form>
    
    <!-- NOVO BOTÃO -->
    <a href="comissoes_recibo.php" class="rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 text-sm font-bold shadow-sm no-print">
      Gerar Recibo
    </a>

    <button onclick="window.print()" class="rounded-lg bg-brand-500 hover:bg-brand-700 text-white px-4 py-2 text-sm font-bold shadow-sm no-print">Imprimir Lista</button>
  </div>  
















</div>

<!-- Resumo por Funcionário -->
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
  <?php if (!$resumoUsuarios): ?>
    <div class="col-span-full rounded-xl border border-slate-200 bg-white p-6 text-center text-slate-400">Nenhuma comissão gerada neste mês.</div>
  <?php else: foreach ($resumoUsuarios as $ru): ?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
      <div class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1"><?= e($ru['usuario_nome']) ?></div>
      <div class="text-xl font-extrabold text-emerald-700"><?= e(centavos_para_moeda_brl((int)$ru['total_centavos'])) ?></div>
      <div class="text-xs text-slate-400 mt-1"><?= (int)$ru['qtd_servicos'] ?> serviço(s) realizado(s)</div>
    </div>
  <?php endforeach; endif; ?>
</div>

<!-- Detalhamento -->
<div class="bg-white rounded-2xl shadow-card border border-slate-200 overflow-hidden">
  <div class="px-5 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
    <h2 class="font-bold text-slate-800">Detalhamento das Comissões</h2>
    <div class="text-sm">Total do Mês: <span class="font-bold text-emerald-700 text-lg"><?= e(centavos_para_moeda_brl($totalGeral)) ?></span></div>
  </div>
  <table class="min-w-full text-sm">
    <thead class="bg-white text-slate-600 border-b border-slate-200">
      <tr>
        <th class="text-left px-4 py-3 font-bold">Data</th>
        <th class="text-left px-4 py-3 font-bold">Cód. Lavagem</th>
        <th class="text-left px-4 py-3 font-bold">Funcionário</th>
        <th class="text-left px-4 py-3 font-bold">Descrição</th>
        <th class="text-left px-4 py-3 font-bold">Placa</th>
        <th class="text-right px-4 py-3 font-bold">Valor</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      <?php if(!$detalhes): ?>
        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Nenhum registro.</td></tr>
      <?php else: foreach($detalhes as $d): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-3 whitespace-nowrap text-slate-600"><?= date('d/m/Y', strtotime($d['data_entrada'])) ?></td>
          <td class="px-4 py-3 font-mono font-bold text-brand-700">#<?= str_pad((int)$d['entrada_id'], 6, '0', STR_PAD_LEFT) ?></td>
          <td class="px-4 py-3 font-semibold text-slate-800"><?= e($d['usuario_nome']) ?></td>
          <td class="px-4 py-3 text-slate-700"><?= e($d['nome_lavagem']) ?></td>
          <td class="px-4 py-3 font-mono font-semibold"><?= e(formatar_placa($d['placa'])) ?></td>
          <td class="px-4 py-3 text-right font-bold text-emerald-700"><?= e(centavos_para_moeda_brl((int)$d['valor_centavos'])) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<style>
  @media print {
    body { background: #fff; }
    nav, .no-print { display: none !important; }
    main { padding: 0 !important; max-width: 100% !important; }
    .shadow-card { box-shadow: none !important; border: 1px solid #ccc !important; }
  }
</style>

<?php require __DIR__ . '/footer.php'; ?>
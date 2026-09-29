<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_pagamentos();

$m = metricas_pagamentos();
$anoMes = date('Y-m');

/* ---------- Dados para gráficos ---------- */

/* Por categoria (ano atual) */
$porCategoria = db()->prepare("
    SELECT c.nome, c.cor, COALESCE(SUM(p.valor_centavos),0) AS total
    FROM pagamentos_despesas p
    INNER JOIN categorias_pagamento c ON c.id = p.categoria_id
    WHERE p.data_vencimento LIKE :ano
    GROUP BY c.id
    ORDER BY total DESC
");
$porCategoria->execute([':ano' => date('Y') . '%']);
$porCategoria = $porCategoria->fetchAll();

/* Por mês (últimos 6 meses) */
$porMes = [];
for ($i = 5; $i >= 0; $i--) {
    $ref = date('Y-m', strtotime("-$i months"));
    $st = db()->prepare("
        SELECT
          COALESCE(SUM(CASE WHEN data_pagamento LIKE :m THEN valor_centavos ELSE 0 END), 0) AS pago,
          COALESCE(SUM(CASE WHEN data_pagamento = '' AND data_vencimento LIKE :m THEN valor_centavos ELSE 0 END), 0) AS aberto
        FROM pagamentos_despesas
        WHERE data_vencimento LIKE :m OR data_pagamento LIKE :m
    ");
    $st->execute([':m' => $ref . '%']);
    $row = $st->fetch();
    $porMes[] = [
        'mes'    => date('m/Y', strtotime($ref . '-01')),
        'pago'   => (int)$row['pago'],
        'aberto' => (int)$row['aberto'],
    ];
}

/* Top favorecidos (ano atual) */
$topPessoas = db()->prepare("
    SELECT pessoa_nome, tipo_pessoa, COALESCE(SUM(valor_centavos),0) AS total
    FROM pagamentos_despesas
    WHERE data_vencimento LIKE :ano
    GROUP BY pessoa_nome, tipo_pessoa
    ORDER BY total DESC
    LIMIT 8
");
$topPessoas->execute([':ano' => date('Y') . '%']);
$topPessoas = $topPessoas->fetchAll();

/* Status atual (contagem) */
$statusAtual = db()->query("
    SELECT
      SUM(CASE WHEN data_pagamento <> '' THEN 1 ELSE 0 END) AS pagos,
      SUM(CASE WHEN data_pagamento = '' AND data_vencimento >= date('now','localtime') THEN 1 ELSE 0 END) AS pendentes,
      SUM(CASE WHEN data_pagamento = '' AND data_vencimento <  date('now','localtime') THEN 1 ELSE 0 END) AS atrasados
    FROM pagamentos_despesas
")->fetch();

/* Vencendo nos próximos 7 dias */
$proximos = db()->query("
    SELECT p.*, c.nome AS categoria_nome, c.cor AS categoria_cor, c.icone AS categoria_icone
    FROM pagamentos_despesas p
    LEFT JOIN categorias_pagamento c ON c.id = p.categoria_id
    WHERE p.data_pagamento = ''
      AND p.data_vencimento BETWEEN date('now','localtime') AND date('now','localtime','+7 days')
    ORDER BY p.data_vencimento
")->fetchAll();

$titulo = 'Dashboard de pagamentos';
require __DIR__ . '/header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
  <div>
    <h1 class="text-2xl font-semibold">Dashboard de pagamentos</h1>
    <p class="text-sm text-slate-500">Visão geral de <?= e(date('m/Y')) ?></p>
  </div>
  <div class="flex gap-2">
    <a href="pagamentos.php" class="rounded-lg bg-slate-700 hover:bg-slate-600 text-white px-4 py-2 text-sm">← Lista</a>
    <a href="pagamento_form.php" class="rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 text-sm font-medium">+ Novo</a>
  </div>
</div>

<!-- Cards -->
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-6">
  <div class="bg-white rounded-xl shadow p-5">
    <div class="text-xs text-slate-500">Pago no mês</div>
    <div class="text-2xl font-bold text-emerald-700 mt-1"><?= e(centavos_para_moeda_brl($m['pago_mes'])) ?></div>
  </div>
  <div class="bg-white rounded-xl shadow p-5">
    <div class="text-xs text-slate-500">Pendente no mês</div>
    <div class="text-2xl font-bold text-amber-700 mt-1"><?= e(centavos_para_moeda_brl($m['pendente_mes'])) ?></div>
  </div>
  <div class="bg-white rounded-xl shadow p-5">
    <div class="text-xs text-slate-500">Em atraso (<?= (int)$m['atraso_qtd'] ?>)</div>
    <div class="text-2xl font-bold text-rose-700 mt-1"><?= e(centavos_para_moeda_brl($m['atraso_total'])) ?></div>
  </div>
  <div class="bg-white rounded-xl shadow p-5">
    <div class="text-xs text-slate-500">Vence hoje (<?= (int)$m['vence_hoje_qtd'] ?>)</div>
    <div class="text-2xl font-bold text-sky-700 mt-1"><?= e(centavos_para_moeda_brl($m['vence_hoje_total'])) ?></div>
    <?php if ($m['vence_amanha_qtd'] > 0): ?>
      <div class="text-xs text-slate-500 mt-1">Amanhã: <?= e(centavos_para_moeda_brl($m['vence_amanha_total'])) ?></div>
    <?php endif; ?>
  </div>
</div>

<!-- Gráficos linha 1 -->
<div class="grid gap-4 lg:grid-cols-3 mb-6">

  <div class="lg:col-span-2 bg-white rounded-xl shadow p-5">
    <h2 class="font-semibold mb-3">Pagos x Em aberto (últimos 6 meses)</h2>
    <canvas id="graficoMes" height="120"></canvas>
  </div>

  <div class="bg-white rounded-xl shadow p-5">
    <h2 class="font-semibold mb-3">Status atual</h2>
    <canvas id="graficoStatus" height="180"></canvas>
  </div>

</div>

<!-- Gráficos linha 2 -->
<div class="grid gap-4 lg:grid-cols-2 mb-6">

  <div class="bg-white rounded-xl shadow p-5">
    <h2 class="font-semibold mb-3">Por categoria (<?= e(date('Y')) ?>)</h2>
    <?php if (!$porCategoria): ?>
      <p class="text-sm text-slate-400">Sem dados.</p>
    <?php else: ?>
      <canvas id="graficoCategoria" height="200"></canvas>
    <?php endif; ?>
  </div>

  <div class="bg-white rounded-xl shadow p-5">
    <h2 class="font-semibold mb-3">Top favorecidos (<?= e(date('Y')) ?>)</h2>
    <?php if (!$topPessoas): ?>
      <p class="text-sm text-slate-400">Sem dados.</p>
    <?php else: ?>
      <canvas id="graficoTop" height="200"></canvas>
    <?php endif; ?>
  </div>

</div>

<!-- Próximos vencimentos -->
<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
  <div class="px-5 py-4 border-b border-slate-200">
    <h2 class="font-semibold">Vencendo nos próximos 7 dias</h2>
  </div>
  <?php if (!$proximos): ?>
    <div class="px-5 py-8 text-center text-slate-400 text-sm">Nada nos próximos 7 dias. 🎉</div>
  <?php else: ?>
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-slate-600">
        <tr>
          <th class="text-left px-4 py-2">Vencimento</th>
          <th class="text-left px-4 py-2">Categoria</th>
          <th class="text-left px-4 py-2">Descrição</th>
          <th class="text-left px-4 py-2">Favorecido</th>
          <th class="text-right px-4 py-2">Valor</th>
          <th class="px-4 py-2"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($proximos as $p):
          $dias = (int)((strtotime($p['data_vencimento']) - strtotime(date('Y-m-d'))) / 86400);
          $urg  = $dias === 0 ? 'bg-rose-50' : ($dias === 1 ? 'bg-amber-50' : '');
        ?>
        <tr class="<?= $urg ?> hover:bg-slate-50">
          <td class="px-4 py-2.5 font-medium whitespace-nowrap">
            <?= e(date('d/m/Y', strtotime($p['data_vencimento']))) ?>
            <div class="text-xs text-slate-500">
              <?= $dias === 0 ? 'hoje' : ($dias === 1 ? 'amanhã' : "em {$dias} dias") ?>
            </div>
          </td>
          <td class="px-4 py-2.5">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs"
                  style="background: <?= e($p['categoria_cor'] . '20') ?>; color: <?= e($p['categoria_cor']) ?>">
              <?= e($p['categoria_icone']) ?> <?= e($p['categoria_nome']) ?>
            </span>
          </td>
          <td class="px-4 py-2.5"><?= e($p['descricao']) ?></td>
          <td class="px-4 py-2.5"><?= e($p['pessoa_nome']) ?></td>
          <td class="px-4 py-2.5 text-right font-medium">
            <?= e(centavos_para_moeda_brl((int)$p['valor_centavos'])) ?>
          </td>
          <td class="px-4 py-2.5 text-right">
            <a href="pagamento_form.php?id=<?= (int)$p['id'] ?>"
               class="text-sky-600 hover:underline text-xs">Editar</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const brl = c => 'R$ ' + (c / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');

/* --- Gráfico: Pagos x Em aberto (barra) --- */
new Chart(document.getElementById('graficoMes'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($porMes, 'mes')) ?>,
    datasets: [
      {
        label: 'Pago',
        data: <?= json_encode(array_map(fn($x) => $x['pago'] / 100, $porMes)) ?>,
        backgroundColor: '#10b981'
      },
      {
        label: 'Em aberto',
        data: <?= json_encode(array_map(fn($x) => $x['aberto'] / 100, $porMes)) ?>,
        backgroundColor: '#f59e0b'
      }
    ]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'bottom' },
      tooltip: { callbacks: { label: c => c.dataset.label + ': ' + brl(c.raw * 100) } }
    },
    scales: {
      y: { beginAtZero: true, ticks: { callback: v => 'R$ ' + v.toFixed(0) } }
    }
  }
});

/* --- Gráfico: Status (doughnut) --- */
new Chart(document.getElementById('graficoStatus'), {
  type: 'doughnut',
  data: {
    labels: ['Pagos', 'Pendentes', 'Atrasados'],
    datasets: [{
      data: [
        <?= (int)$statusAtual['pagos'] ?>,
        <?= (int)$statusAtual['pendentes'] ?>,
        <?= (int)$statusAtual['atrasados'] ?>
      ],
      backgroundColor: ['#10b981', '#f59e0b', '#ef4444']
    }]
  },
  options: { plugins: { legend: { position: 'bottom' } } }
});

<?php if ($porCategoria): ?>
/* --- Gráfico: Por categoria (pizza) --- */
new Chart(document.getElementById('graficoCategoria'), {
  type: 'pie',
  data: {
    labels: <?= json_encode(array_column($porCategoria, 'nome')) ?>,
    datasets: [{
      data: <?= json_encode(array_map(fn($x) => $x['total'] / 100, $porCategoria)) ?>,
      backgroundColor: <?= json_encode(array_column($porCategoria, 'cor')) ?>
    }]
  },
  options: {
    plugins: {
      legend: { position: 'right' },
      tooltip: { callbacks: { label: c => c.label + ': ' + brl(c.raw * 100) } }
    }
  }
});
<?php endif; ?>

<?php if ($topPessoas): ?>
/* --- Gráfico: Top favorecidos (barra horizontal) --- */
new Chart(document.getElementById('graficoTop'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_map(fn($x) => $x['pessoa_nome'], $topPessoas)) ?>,
    datasets: [{
      label: 'Total',
      data: <?= json_encode(array_map(fn($x) => $x['total'] / 100, $topPessoas)) ?>,
      backgroundColor: '#3b82f6'
    }]
  },
  options: {
    indexAxis: 'y',
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: c => brl(c.raw * 100) } }
    },
    scales: { x: { ticks: { callback: v => 'R$ ' + v.toFixed(0) } } }
  }
});
<?php endif; ?>
</script>

<?php require __DIR__ . '/footer.php'; ?>
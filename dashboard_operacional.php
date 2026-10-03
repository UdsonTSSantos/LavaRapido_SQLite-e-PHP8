<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_entradas();

/* =========================================================
 *  PERÍODO (mês de referência)
 * ========================================================= */
$mesRef = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mesRef)) $mesRef = date('Y-m');

$inicio  = $mesRef . '-01';
$fim     = date('Y-m-t', strtotime($inicio));
$rotulo  = date('m/Y', strtotime($inicio));

$inicioAnt = date('Y-m-01', strtotime($inicio . ' -1 month'));
$fimAnt    = date('Y-m-t',  strtotime($inicio . ' -1 month'));
$rotAnt    = date('m/Y', strtotime($inicioAnt));

/* =========================================================
 *  KPIs — MÊS ATUAL
 * ========================================================= */
$st = db()->prepare("
    SELECT
        COUNT(*)                                    AS qtd,
        COALESCE(SUM(total_centavos), 0)            AS faturamento,
        COALESCE(SUM(desconto_centavos), 0)         AS desconto,
        COALESCE(SUM(subtotal_centavos), 0)         AS subtotal,
        COUNT(DISTINCT cliente_id)                  AS clientes,
        COUNT(DISTINCT placa)                       AS veiculos
    FROM lavagem_entradas
    WHERE data_entrada BETWEEN :ini AND :fim
      AND status IN ('concluida', 'entregue')
");
$st->execute([':ini' => $inicio, ':fim' => $fim]);
$k = $st->fetch();

$k['faturamento'] = (int)$k['faturamento'];
$k['desconto']    = (int)$k['desconto'];
$k['subtotal']    = (int)$k['subtotal'];
$k['ticket']      = $k['qtd'] > 0 ? (int)round($k['faturamento'] / $k['qtd']) : 0;

/* =========================================================
 *  KPIs — MÊS ANTERIOR (comparativo)
 * ========================================================= */
$st = db()->prepare("
    SELECT
        COUNT(*)                            AS qtd,
        COALESCE(SUM(total_centavos), 0)    AS faturamento
    FROM lavagem_entradas
    WHERE data_entrada BETWEEN :ini AND :fim
      AND status IN ('concluida', 'entregue')
");
$st->execute([':ini' => $inicioAnt, ':fim' => $fimAnt]);
$ant = $st->fetch();
$ant['faturamento'] = (int)$ant['faturamento'];
$ant['ticket']      = $ant['qtd'] > 0 ? (int)round($ant['faturamento'] / $ant['qtd']) : 0;

/* Variação percentual */
function variacao(float $atual, float $anterior): array {
    if ($anterior <= 0) {
        return ['pct' => $atual > 0 ? 100 : 0, 'dir' => $atual > 0 ? 'up' : 'flat'];
    }
    $pct = (($atual - $anterior) / $anterior) * 100;
    return ['pct' => round($pct, 1), 'dir' => $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat')];
}
$varFat = variacao($k['faturamento'], $ant['faturamento']);
$varQtd = variacao($k['qtd'],         $ant['qtd']);
$varTkt = variacao($k['ticket'],      $ant['ticket']);

/* =========================================================
 *  LAVAGENS POR DIA (mês atual)
 * ========================================================= */
$st = db()->prepare("
    SELECT
        data_entrada                                AS dia,
        COUNT(*)                                    AS qtd,
        COALESCE(SUM(total_centavos), 0)            AS total
    FROM lavagem_entradas
    WHERE data_entrada BETWEEN :ini AND :fim
      AND status IN ('concluida', 'entregue')
    GROUP BY data_entrada
    ORDER BY data_entrada
");
$st->execute([':ini' => $inicio, ':fim' => $fim]);
$porDia = $st->fetchAll();

/* Preenche TODOS os dias do mês (com zero onde não houve movimento) */
$dias = [];
$totalDias = (int)date('t', strtotime($inicio));
$mapaDia = [];
foreach ($porDia as $d) $mapaDia[$d['dia']] = $d;

for ($i = 1; $i <= $totalDias; $i++) {
    $dia = sprintf('%s-%02d', $mesRef, $i);
    $dias[] = [
        'dia'   => str_pad((string)$i, 2, '0', STR_PAD_LEFT),
        'qtd'   => (int)($mapaDia[$dia]['qtd']   ?? 0),
        'total' => (int)($mapaDia[$dia]['total'] ?? 0),
    ];
}

/* =========================================================
 *  FORMAS DE PAGAMENTO (mês atual)
 * ========================================================= */
$st = db()->prepare("
    SELECT
        p.forma_pagamento                       AS forma,
        COUNT(*)                                AS qtd,
        COALESCE(SUM(p.valor_centavos), 0)      AS total
    FROM pagamentos p
    WHERE p.data_pagamento BETWEEN :ini AND :fim
    GROUP BY p.forma_pagamento
    ORDER BY total DESC
");
$st->execute([':ini' => $inicio, ':fim' => $fim]);
$formas = $st->fetchAll();

$formasLabels = formas_pagamento();
foreach ($formas as &$f) {
    $f['label'] = $formasLabels[$f['forma']] ?? $f['forma'];
}
unset($f);

/* =========================================================
 *  TOP SERVIÇOS (mês atual)
 * ========================================================= */
$st = db()->prepare("
    SELECT
        i.nome                                  AS servico,
        COUNT(*)                                AS qtd,
        COALESCE(SUM(i.preco_centavos), 0)      AS total
    FROM lavagem_itens i
    INNER JOIN lavagem_entradas e ON e.id = i.entrada_id
    WHERE e.data_entrada BETWEEN :ini AND :fim
      AND e.status IN ('concluida', 'entregue')
    GROUP BY i.lavagem_id, i.nome
    ORDER BY qtd DESC, total DESC
    LIMIT 8
");
$st->execute([':ini' => $inicio, ':fim' => $fim]);
$topServicos = $st->fetchAll();

/* =========================================================
 *  TOP CLIENTES (mês atual)
 * ========================================================= */
$st = db()->prepare("
    SELECT
        COALESCE(c.nome, e.cliente_nome_avulso, '—') AS cliente,
        COUNT(*)                                     AS qtd,
        COALESCE(SUM(e.total_centavos), 0)           AS total
    FROM lavagem_entradas e
    LEFT JOIN clientes c ON c.id = e.cliente_id
    WHERE e.data_entrada BETWEEN :ini AND :fim
      AND e.status IN ('concluida', 'entregue')
    GROUP BY e.cliente_id, COALESCE(c.nome, e.cliente_nome_avulso, '—')
    ORDER BY total DESC
    LIMIT 8
");
$st->execute([':ini' => $inicio, ':fim' => $fim]);
$topClientes = $st->fetchAll();

/* =========================================================
 *  LAVAGENS POR PERÍODO DO DIA (manhã/tarde/noite)
 * ========================================================= */
$st = db()->prepare("
    SELECT
        CASE
            WHEN CAST(substr(hora_entrada, 1, 2) AS INTEGER) < 12 THEN 'Manhã'
            WHEN CAST(substr(hora_entrada, 1, 2) AS INTEGER) < 18 THEN 'Tarde'
            ELSE 'Noite'
        END AS periodo,
        COUNT(*) AS qtd
    FROM lavagem_entradas
    WHERE data_entrada BETWEEN :ini AND :fim
      AND status IN ('concluida', 'entregue')
    GROUP BY periodo
");
$st->execute([':ini' => $inicio, ':fim' => $fim]);
$periodoRows = $st->fetchAll();
$periodoMap = ['Manhã' => 0, 'Tarde' => 0, 'Noite' => 0];
foreach ($periodoRows as $p) $periodoMap[$p['periodo']] = (int)$p['qtd'];

$titulo = 'Dashboard Operacional';
require __DIR__ . '/header.php';
?>

<!-- ============ CABEÇALHO ============ -->
<div class="flex flex-wrap items-end justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Dashboard Operacional</h1>
    <p class="text-sm text-slate-500 mt-1">
      Indicadores de desempenho — <strong><?= e($rotulo) ?></strong>
    </p>
  </div>

  <form method="get" class="flex items-center gap-2">
    <label class="text-xs text-slate-500 font-medium">Mês</label>
    <input type="month" name="mes" value="<?= e($mesRef) ?>"
           onchange="this.form.submit()"
           class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-400">
    <a href="dashboard_operacional.php" class="text-xs text-brand-600 hover:underline font-medium">Mês atual</a>
  </form>
</div>

<!-- ============ KPIs PRINCIPAIS ============ -->
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">

  <!-- Faturamento -->
  <div class="fade-up rounded-2xl bg-white p-5 border border-slate-200 shadow-card">
    <div class="flex items-start justify-between">
      <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Faturamento</div>
      <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg" style="background:#f0fbff">💰</div>
    </div>
    <div class="text-2xl font-extrabold text-slate-900 mt-3">
      <?= e(centavos_para_moeda_brl($k['faturamento'])) ?>
    </div>
    <?php if ($ant['faturamento'] > 0): ?>
      <div class="mt-2 text-xs font-semibold flex items-center gap-1
        <?= $varFat['dir'] === 'up' ? 'text-emerald-600' : ($varFat['dir'] === 'down' ? 'text-rose-600' : 'text-slate-500') ?>">
        <?= $varFat['dir'] === 'up' ? '▲' : ($varFat['dir'] === 'down' ? '▼' : '■') ?>
        <?= number_format(abs($varFat['pct']), 1, ',', '.') ?>%
        <span class="text-slate-400 font-normal">vs <?= e($rotAnt) ?></span>
      </div>
    <?php else: ?>
      <div class="mt-2 text-xs text-slate-400">Sem base no mês anterior</div>
    <?php endif; ?>
  </div>

  <!-- Ticket médio -->
  <div class="fade-up rounded-2xl bg-white p-5 border border-slate-200 shadow-card">
    <div class="flex items-start justify-between">
      <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Ticket médio</div>
      <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg" style="background:#f0fbff">🎯</div>
    </div>
    <div class="text-2xl font-extrabold text-slate-900 mt-3">
      <?= e(centavos_para_moeda_brl($k['ticket'])) ?>
    </div>
    <?php if ($ant['ticket'] > 0): ?>
      <div class="mt-2 text-xs font-semibold flex items-center gap-1
        <?= $varTkt['dir'] === 'up' ? 'text-emerald-600' : ($varTkt['dir'] === 'down' ? 'text-rose-600' : 'text-slate-500') ?>">
        <?= $varTkt['dir'] === 'up' ? '▲' : ($varTkt['dir'] === 'down' ? '▼' : '■') ?>
        <?= number_format(abs($varTkt['pct']), 1, ',', '.') ?>%
        <span class="text-slate-400 font-normal">vs <?= e($rotAnt) ?></span>
      </div>
    <?php else: ?>
      <div class="mt-2 text-xs text-slate-400">Sem base no mês anterior</div>
    <?php endif; ?>
  </div>

  <!-- Lavagens -->
  <div class="fade-up rounded-2xl bg-white p-5 border border-slate-200 shadow-card">
    <div class="flex items-start justify-between">
      <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Lavagens</div>
      <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg" style="background:#f0fbff">🚗</div>
    </div>
    <div class="text-2xl font-extrabold text-slate-900 mt-3">
      <?= (int)$k['qtd'] ?>
    </div>
    <?php if ((int)$ant['qtd'] > 0): ?>
      <div class="mt-2 text-xs font-semibold flex items-center gap-1
        <?= $varQtd['dir'] === 'up' ? 'text-emerald-600' : ($varQtd['dir'] === 'down' ? 'text-rose-600' : 'text-slate-500') ?>">
        <?= $varQtd['dir'] === 'up' ? '▲' : ($varQtd['dir'] === 'down' ? '▼' : '■') ?>
        <?= number_format(abs($varQtd['pct']), 1, ',', '.') ?>%
        <span class="text-slate-400 font-normal">vs <?= e($rotAnt) ?></span>
      </div>
    <?php else: ?>
      <div class="mt-2 text-xs text-slate-400">Sem base no mês anterior</div>
    <?php endif; ?>
  </div>

  <!-- Clientes únicos -->
  <div class="fade-up rounded-2xl bg-white p-5 border border-slate-200 shadow-card">
    <div class="flex items-start justify-between">
      <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Clientes atendidos</div>
      <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg" style="background:#f0fbff">👥</div>
    </div>
    <div class="text-2xl font-extrabold text-slate-900 mt-3">
      <?= (int)$k['clientes'] ?>
    </div>
    <div class="mt-2 text-xs text-slate-400">
      <?= (int)$k['veiculos'] ?> veículo(s) distinto(s)
    </div>
  </div>

</div>

<!-- ============ KPIs SECUNDÁRIOS ============ -->
<div class="grid gap-4 sm:grid-cols-3 mb-6">

  <div class="rounded-xl bg-white p-4 border border-slate-200 shadow-soft">
    <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Subtotal bruto</div>
    <div class="text-lg font-bold text-slate-900 mt-1"><?= e(centavos_para_moeda_brl($k['subtotal'])) ?></div>
  </div>

  <div class="rounded-xl bg-white p-4 border border-slate-200 shadow-soft">
    <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Descontos concedidos</div>
    <div class="text-lg font-bold text-rose-600 mt-1">
      - <?= e(centavos_para_moeda_brl($k['desconto'])) ?>
    </div>
    <?php if ($k['subtotal'] > 0): ?>
      <div class="text-xs text-slate-400 mt-0.5">
        <?= number_format($k['desconto'] / $k['subtotal'] * 100, 1, ',', '.') ?>% do subtotal
      </div>
    <?php endif; ?>
  </div>

  <div class="rounded-xl bg-white p-4 border border-slate-200 shadow-soft">
    <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Média por dia</div>
    <div class="text-lg font-bold text-slate-900 mt-1">
      <?php
        $diasComMov = count(array_filter($dias, fn($d) => $d['qtd'] > 0));
        $media = $diasComMov > 0 ? (int)round($k['faturamento'] / $diasComMov) : 0;
        echo e(centavos_para_moeda_brl($media));
      ?>
    </div>
    <div class="text-xs text-slate-400 mt-0.5">
      <?= $diasComMov ?> dia(s) com movimento
    </div>
  </div>

</div>

<!-- ============ GRÁFICO: LAVAGENS POR DIA ============ -->
<div class="bg-white rounded-2xl shadow-card border border-slate-200 p-5 mb-6">
  <div class="flex items-center justify-between mb-4">
    <h2 class="font-bold text-slate-800">Lavagens por dia — <?= e($rotulo) ?></h2>
    <div class="text-xs text-slate-500 font-medium">Barras: quantidade · Linha: faturamento</div>
  </div>
  <div class="relative w-full" style="height: 320px;">
    <canvas id="graficoDias"></canvas>
</div>
</div>

<!-- ============ GRÁFICOS LADO A LADO ============ -->
<div class="grid gap-4 lg:grid-cols-2 mb-6">

  <!-- Formas de pagamento -->
  <div class="bg-white rounded-2xl shadow-card border border-slate-200 p-5">
    <h2 class="font-bold text-slate-800 mb-4">Formas de pagamento</h2>
    <?php if (!$formas): ?>
      <p class="text-sm text-slate-400 py-6 text-center">Sem pagamentos no período.</p>
    <?php else: ?>
    <div class="relative w-full" style="height: 280px;">
        <canvas id="graficoFormas"></canvas>
    </div>
    <?php endif; ?>
  </div>

  <!-- Período do dia -->
  <div class="bg-white rounded-2xl shadow-card border border-slate-200 p-5">
    <h2 class="font-bold text-slate-800 mb-4">Movimento por período do dia</h2>
    <div class="relative w-full" style="height: 280px;">
        <canvas id="graficoPeriodo"></canvas>
    </div>
  </div>

</div>

<!-- ============ TOP SERVIÇOS + TOP CLIENTES ============ -->
<div class="grid gap-4 lg:grid-cols-2 mb-6">

  <!-- Top serviços -->
  <div class="bg-white rounded-2xl shadow-card border border-slate-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200">
      <h2 class="font-bold text-slate-800">Serviços mais realizados</h2>
      <p class="text-xs text-slate-500 mt-0.5">No período de <?= e($rotulo) ?></p>
    </div>
    <?php if (!$topServicos): ?>
      <div class="px-5 py-10 text-center text-slate-400 text-sm">Sem serviços no período.</div>
    <?php else: ?>
      <table class="min-w-full text-sm">
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($topServicos as $s): ?>
            <tr class="hover:bg-slate-50">
              <td class="px-5 py-3 font-semibold text-slate-800"><?= e($s['servico']) ?></td>
              <td class="px-5 py-3 text-right whitespace-nowrap">
                <span class="inline-flex items-center justify-center min-w-[2.25rem] px-2 py-0.5 rounded-full
                             text-xs font-bold bg-brand-50 text-brand-700">
                  <?= (int)$s['qtd'] ?>x
                </span>
              </td>
              <td class="px-5 py-3 text-right font-bold text-slate-700 whitespace-nowrap">
                <?= e(centavos_para_moeda_brl((int)$s['total'])) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- Top clientes -->
  <div class="bg-white rounded-2xl shadow-card border border-slate-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200">
      <h2 class="font-bold text-slate-800">Top clientes</h2>
      <p class="text-xs text-slate-500 mt-0.5">Por faturamento no período</p>
    </div>
    <?php if (!$topClientes): ?>
      <div class="px-5 py-10 text-center text-slate-400 text-sm">Sem clientes no período.</div>
    <?php else: ?>
      <table class="min-w-full text-sm">
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($topClientes as $i => $c): ?>
            <tr class="hover:bg-slate-50">
              <td class="px-5 py-3">
                <div class="flex items-center gap-3">
                  <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold
                               <?= $i === 0 ? 'bg-brand-400 text-white' : 'bg-slate-100 text-slate-500' ?>">
                    <?= $i + 1 ?>
                  </span>
                  <span class="font-semibold text-slate-800 truncate"><?= e($c['cliente']) ?></span>
                </div>
              </td>
              <td class="px-5 py-3 text-right text-xs text-slate-500 whitespace-nowrap">
                <?= (int)$c['qtd'] ?> lavagem(ns)
              </td>
              <td class="px-5 py-3 text-right font-bold text-slate-700 whitespace-nowrap">
                <?= e(centavos_para_moeda_brl((int)$c['total'])) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>

<!-- ============ GRÁFICO: TOP SERVIÇOS ============ -->
<?php if ($topServicos): ?>
<div class="bg-white rounded-2xl shadow-card border border-slate-200 p-5 mb-6">
  <h2 class="font-bold text-slate-800 mb-4">Serviços mais realizados — quantidade</h2>
  <div class="relative w-full" style="height: 320px;">
    <canvas id="graficoTopServicos"></canvas>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
  const BRAND = {
    dark:   '#004173',
    mid:    '#0979b0',
    light:  '#0cb7f2',
    soft:   '#7cdaf9',
    pale:   '#b6ffff',
  };

  Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
  Chart.defaults.color = '#475569';

  const brl = c => 'R$ ' + (c / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');

  /* ============ LAVAGENS POR DIA ============ */
  const diasLabels   = <?= json_encode(array_column($dias, 'dia')) ?>;
  const diasQtd      = <?= json_encode(array_column($dias, 'qtd')) ?>;
  const diasTotal    = <?= json_encode(array_map(fn($d) => $d['total'] / 100, $dias)) ?>;

  new Chart(document.getElementById('graficoDias'), {
    type: 'bar',
    data: {
      labels: diasLabels,
      datasets: [
        {
          label: 'Lavagens',
          data: diasQtd,
          backgroundColor: BRAND.light,
          borderRadius: 6,
          yAxisID: 'y',
          order: 2,
        },
        {
          label: 'Faturamento (R$)',
          data: diasTotal,
          type: 'line',
          borderColor: BRAND.dark,
          backgroundColor: BRAND.dark,
          borderWidth: 2,
          tension: 0.35,
          pointRadius: 3,
          pointHoverRadius: 5,
          yAxisID: 'y1',
          order: 1,
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16, boxWidth: 8 } },
        tooltip: {
          callbacks: {
            label: c => c.dataset.label + ': ' +
              (c.dataset.yAxisID === 'y1' ? brl(c.parsed.y * 100) : c.parsed.y)
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          position: 'left',
          title: { display: true, text: 'Lavagens', color: '#64748b', font: { size: 11, weight: '600' } },
          ticks: { stepSize: 1, color: '#64748b' },
          grid: { color: '#f1f5f9' }
        },
        y1: {
          beginAtZero: true,
          position: 'right',
          title: { display: true, text: 'Faturamento (R$)', color: '#64748b', font: { size: 11, weight: '600' } },
          ticks: { color: '#64748b', callback: v => 'R$ ' + v.toFixed(0) },
          grid: { drawOnChartArea: false }
        },
        x: {
          grid: { display: false },
          ticks: { color: '#64748b', font: { size: 10 } }
        }
      }
    }
  });

  /* ============ FORMAS DE PAGAMENTO ============ */
  <?php if ($formas): ?>
  new Chart(document.getElementById('graficoFormas'), {
    type: 'doughnut',
    data: {
      labels: <?= json_encode(array_column($formas, 'label')) ?>,
      datasets: [{
        data: <?= json_encode(array_map(fn($f) => $f['total'] / 100, $formas)) ?>,
        backgroundColor: [BRAND.dark, BRAND.mid, BRAND.light, BRAND.soft, BRAND.pale, '#cbd5e1', '#94a3b8'],
        borderWidth: 2,
        borderColor: '#fff',
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '60%',
      plugins: {
        legend: { position: 'right', labels: { usePointStyle: true, padding: 12, boxWidth: 8 } },
        tooltip: {
          callbacks: {
            label: c => {
              const tot = c.dataset.data.reduce((a, b) => a + b, 0);
              const pct = tot > 0 ? (c.parsed / tot * 100).toFixed(1) : '0';
              return c.label + ': ' + brl(c.parsed * 100) + ' (' + pct + '%)';
            }
          }
        }
      }
    }
  });
  <?php endif; ?>

  /* ============ PERÍODO DO DIA ============ */
  new Chart(document.getElementById('graficoPeriodo'), {
    type: 'bar',
    data: {
      labels: ['Manhã', 'Tarde', 'Noite'],
      datasets: [{
        label: 'Lavagens',
        data: [<?= (int)$periodoMap['Manhã'] ?>, <?= (int)$periodoMap['Tarde'] ?>, <?= (int)$periodoMap['Noite'] ?>],
        backgroundColor: [BRAND.soft, BRAND.light, BRAND.dark],
        borderRadius: 8,
        barThickness: 60,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1, color: '#64748b' }, grid: { color: '#f1f5f9' } },
        x: { grid: { display: false }, ticks: { color: '#64748b', font: { weight: '600' } } }
      }
    }
  });

  /* ============ TOP SERVIÇOS ============ */
  <?php if ($topServicos): ?>
  new Chart(document.getElementById('graficoTopServicos'), {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_column($topServicos, 'servico')) ?>,
      datasets: [{
        label: 'Quantidade',
        data: <?= json_encode(array_map(fn($s) => (int)$s['qtd'], $topServicos)) ?>,
        backgroundColor: BRAND.mid,
        borderRadius: 6,
      }]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { beginAtZero: true, ticks: { stepSize: 1, color: '#64748b' }, grid: { color: '#f1f5f9' } },
        y: { grid: { display: false }, ticks: { color: '#334155', font: { weight: '600' } } }
      }
    }
  });
  <?php endif; ?>
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
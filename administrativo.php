<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_entradas();
ensure_pagamentos();

/* =========================================================
 *  MÊS DE REFERÊNCIA
 * ========================================================= */
$mesRef = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mesRef)) $mesRef = date('Y-m');

$inicio = $mesRef . '-01';
$fim    = date('Y-m-t', strtotime($inicio));
$rotulo = date('m/Y', strtotime($inicio));

/* =========================================================
 *  RECEITAS — Lavagens pagas no mês
 * ========================================================= */
$st = db()->prepare("
    SELECT p.id, p.entrada_id, p.data_pagamento, p.valor_centavos,
           p.forma_pagamento, p.recibo_numero, p.observacao,
           e.data_entrada, e.hora_entrada, e.placa,
           COALESCE(c.nome, e.cliente_nome_avulso, '—') AS cliente_nome,
           e.cliente_id
    FROM pagamentos p
    LEFT JOIN lavagem_entradas e ON e.id = p.entrada_id
    LEFT JOIN clientes c ON c.id = e.cliente_id
    WHERE p.data_pagamento BETWEEN :ini AND :fim
    ORDER BY p.data_pagamento, p.id
");
$st->execute([':ini' => $inicio, ':fim' => $fim]);
$receitas = $st->fetchAll();

/* Busca os itens (serviços) de cada entrada de uma só vez */
$itensPorEntrada = [];
if ($receitas) {
    $ids = array_values(array_unique(array_filter(array_column($receitas, 'entrada_id'))));
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sti = db()->prepare("SELECT entrada_id, nome FROM lavagem_itens WHERE entrada_id IN ($in) ORDER BY id");
        $sti->execute($ids);
        foreach ($sti->fetchAll() as $row) {
            $itensPorEntrada[(int)$row['entrada_id']][] = $row['nome'];
        }
    }
}

/* =========================================================
 *  DESPESAS — Contas pagas no mês
 * ========================================================= */
$st = db()->prepare("
    SELECT d.*, 
           cat.nome  AS categoria_nome,
           cat.icone AS categoria_icone,
           cat.cor   AS categoria_cor
    FROM pagamentos_despesas d
    LEFT JOIN categorias_pagamento cat ON cat.id = d.categoria_id
    WHERE d.data_pagamento BETWEEN :ini AND :fim
    ORDER BY d.data_pagamento, d.id
");
$st->execute([':ini' => $inicio, ':fim' => $fim]);
$despesas = $st->fetchAll();

/* =========================================================
 *  TOTAIS
 * ========================================================= */
$totalReceitas = 0;
foreach ($receitas as $r) $totalReceitas += (int)$r['valor_centavos'];

$totalDespesas = 0;
foreach ($despesas as $d) $totalDespesas += (int)$d['valor_centavos'];

$saldo = $totalReceitas - $totalDespesas;

/* Quebra de receitas por forma de pagamento */
$recPorForma = [];
foreach ($receitas as $r) {
    $f = $r['forma_pagamento'] ?: 'OUTRO';
    $recPorForma[$f] = ($recPorForma[$f] ?? 0) + (int)$r['valor_centavos'];
}
arsort($recPorForma);

$formas = formas_pagamento();

/* Empresa (cabeçalho do relatório) */
$emp  = empresa();
$logo = empresa_logo_url();

$titulo = 'Administrativo';
require __DIR__ . '/header.php';
?>

<style>
  /* =========================================================
   *  ESTILO DE IMPRESSÃO (A4)
   * ========================================================= */
  @media print {
    @page { size: A4; margin: 12mm 10mm; }

    nav, .no-print, .no-print * { display: none !important; }

    body { background: #fff !important; }
    main { padding: 0 !important; max-width: 100% !important; }

    .card, .report-card {
      box-shadow: none !important;
      border: 1px solid #cbd5e1 !important;
      page-break-inside: avoid;
    }

    .print-page-break { page-break-before: always; }

    table { font-size: 10px !important; }
    thead { background: #e2e8f0 !important; }

    a { color: #000 !important; text-decoration: none !important; }
  }
</style>

<!-- ============ CABEÇALHO DA TELA ============ -->
<div class="no-print flex flex-wrap items-end justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Administrativo</h1>
    <p class="text-sm text-slate-500 mt-1">
      Resumo financeiro consolidado — <strong><?= e($rotulo) ?></strong>
    </p>
  </div>

  <div class="flex items-center gap-2 flex-wrap">
    <form method="get" class="flex items-center gap-2">
      <label class="text-xs text-slate-500 font-medium">Mês</label>
      <input type="month" name="mes" value="<?= e($mesRef) ?>"
             onchange="this.form.submit()"
             class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-400">
      <a href="administrativo.php" class="text-xs text-brand-600 hover:underline font-medium">Mês atual</a>
    </form>

    <button type="button" onclick="window.print()"
            class="rounded-lg bg-brand-500 hover:bg-brand-700 text-white px-4 py-2 text-sm font-semibold shadow-sm">
      🖨️ Imprimir
    </button>
  </div>
</div>

<!-- ============ CABEÇALHO DO RELATÓRIO (aparece na tela e na impressão) ============ -->
<div class="report-card bg-white rounded-2xl shadow-card border border-slate-200 p-6 mb-6">
  <div class="flex items-center gap-4">
    <?php if ($logo): ?>
      <img src="<?= e($logo) ?>" alt="Logo" class="h-16 w-16 object-contain">
    <?php endif; ?>
    <div class="flex-1">
      <div class="text-lg font-bold text-slate-900">
        <?= e($emp['razao_social'] ?: 'Empresa') ?>
      </div>
      <?php if (!empty($emp['nome_fantasia'])): ?>
        <div class="text-sm text-slate-600"><?= e($emp['nome_fantasia']) ?></div>
      <?php endif; ?>
      <div class="text-xs text-slate-500 mt-1">
        <?php if (!empty($emp['cnpj'])): ?>CNPJ: <?= e(formatar_cnpj($emp['cnpj'])) ?><?php endif; ?>
        <?php if (!empty($emp['celular']) || !empty($emp['telefone'])): ?>
          · Tel: <?= e($emp['celular'] ?: $emp['telefone']) ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="text-right text-xs text-slate-500">
      <div class="font-bold text-slate-800 text-sm">Resumo Financeiro</div>
      <div>Período: <strong><?= e($rotulo) ?></strong></div>
      <div>Emitido em <?= e(date('d/m/Y H:i')) ?></div>
    </div>
  </div>
</div>

<!-- ============ KPIs ============ -->
<div class="grid gap-4 sm:grid-cols-3 mb-6">

  <div class="card rounded-2xl bg-white p-5 border border-slate-200 shadow-card">
    <div class="flex items-start justify-between">
      <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Receitas pagas</div>
      <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg bg-emerald-50">💵</div>
    </div>
    <div class="text-2xl font-extrabold text-emerald-700 mt-3">
      <?= e(centavos_para_moeda_brl($totalReceitas)) ?>
    </div>
    <div class="mt-2 text-xs text-slate-500">
      <?= count($receitas) ?> lavagem(ns) paga(s)
    </div>
  </div>

  <div class="card rounded-2xl bg-white p-5 border border-slate-200 shadow-card">
    <div class="flex items-start justify-between">
      <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Despesas pagas</div>
      <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg bg-rose-50">💸</div>
    </div>
    <div class="text-2xl font-extrabold text-rose-700 mt-3">
      <?= e(centavos_para_moeda_brl($totalDespesas)) ?>
    </div>
    <div class="mt-2 text-xs text-slate-500">
      <?= count($despesas) ?> conta(s) paga(s)
    </div>
  </div>

  <div class="card rounded-2xl bg-white p-5 border-2 <?= $saldo >= 0 ? 'border-emerald-200' : 'border-rose-200' ?> shadow-card">
    <div class="flex items-start justify-between">
      <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Saldo do mês</div>
      <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg <?= $saldo >= 0 ? 'bg-emerald-50' : 'bg-rose-50' ?>">
        <?= $saldo >= 0 ? '📈' : '📉' ?>
      </div>
    </div>
    <div class="text-2xl font-extrabold mt-3 <?= $saldo >= 0 ? 'text-emerald-700' : 'text-rose-700' ?>">
      <?= ($saldo < 0 ? '- ' : '') . e(centavos_para_moeda_brl(abs($saldo))) ?>
    </div>
    <div class="mt-2 text-xs text-slate-500">
      Receitas − Despesas
    </div>
  </div>

</div>

<!-- ============ RECEITAS POR FORMA DE PAGAMENTO ============ -->
<?php if ($recPorForma): ?>
<div class="card bg-white rounded-2xl shadow-card border border-slate-200 p-5 mb-6">
  <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-3">Receitas por forma de pagamento</h2>
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
    <?php foreach ($recPorForma as $k => $v): ?>
      <div class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-2">
        <div class="text-xs text-slate-500 font-medium"><?= e($formas[$k] ?? $k) ?></div>
        <div class="font-bold text-slate-800"><?= e(centavos_para_moeda_brl((int)$v)) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ============ RECEITAS (LAVAGENS PAGAS) ============ -->
<div class="card bg-white rounded-2xl shadow-card border border-slate-200 overflow-hidden mb-6">
  <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
    <div>
      <h2 class="font-bold text-slate-800">Receitas — Lavagens pagas</h2>
      <p class="text-xs text-slate-500 mt-0.5">Registro somente leitura · não editável</p>
    </div>
    <div class="text-right">
      <div class="text-xs text-slate-500">Subtotal</div>
      <div class="font-bold text-emerald-700"><?= e(centavos_para_moeda_brl($totalReceitas)) ?></div>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-slate-600">
        <tr>
          <th class="text-left px-4 py-2 font-bold whitespace-nowrap">Pagamento</th>
          <th class="text-left px-4 py-2 font-bold">Recibo</th>
          <th class="text-left px-4 py-2 font-bold">Cliente</th>
          <th class="text-left px-4 py-2 font-bold">Placa</th>
          <th class="text-left px-4 py-2 font-bold">Serviços</th>
          <th class="text-left px-4 py-2 font-bold">Forma</th>
          <th class="text-right px-4 py-2 font-bold">Valor</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php if (!$receitas): ?>
        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">
          Nenhuma receita paga no período.
        </td></tr>
      <?php else: foreach ($receitas as $r): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-2.5 whitespace-nowrap">
            <div class="font-semibold text-slate-800">
              <?= e(date('d/m/Y', strtotime($r['data_pagamento']))) ?>
            </div>
            <?php if ($r['data_entrada']): ?>
              <div class="text-xs text-slate-500">
                entrada <?= e(date('d/m', strtotime($r['data_entrada']))) ?>
              </div>
            <?php endif; ?>
          </td>
          <td class="px-4 py-2.5 whitespace-nowrap">
            <span class="text-xs font-mono text-slate-500"><?= e($r['recibo_numero']) ?></span>
          </td>
          <td class="px-4 py-2.5">
            <div class="font-medium text-slate-800"><?= e($r['cliente_nome']) ?></div>
          </td>
          <td class="px-4 py-2.5">
            <span class="font-mono font-semibold text-slate-800"><?= e(formatar_placa($r['placa'] ?? '')) ?></span>
          </td>
          <td class="px-4 py-2.5 text-xs text-slate-600">
            <?php
              $its = $itensPorEntrada[(int)($r['entrada_id'] ?? 0)] ?? [];
              echo $its ? e(implode(' · ', $its)) : '—';
            ?>
          </td>
          <td class="px-4 py-2.5 text-xs text-slate-600">
            <?= e($formas[$r['forma_pagamento']] ?? $r['forma_pagamento']) ?>
          </td>
          <td class="px-4 py-2.5 text-right font-bold text-emerald-700 whitespace-nowrap">
            <?= e(centavos_para_moeda_brl((int)$r['valor_centavos'])) ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
      <tfoot>
        <tr class="bg-slate-50 border-t-2 border-slate-300">
          <td colspan="6" class="px-4 py-3 text-right font-bold text-slate-700">TOTAL DE RECEITAS</td>
          <td class="px-4 py-3 text-right font-extrabold text-emerald-700 whitespace-nowrap">
            <?= e(centavos_para_moeda_brl($totalReceitas)) ?>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- ============ DESPESAS PAGAS ============ -->
<div class="card bg-white rounded-2xl shadow-card border border-slate-200 overflow-hidden mb-6">
  <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
    <div>
      <h2 class="font-bold text-slate-800">Despesas — Contas pagas</h2>
      <p class="text-xs text-slate-500 mt-0.5">Registro somente leitura · não editável</p>
    </div>
    <div class="text-right">
      <div class="text-xs text-slate-500">Subtotal</div>
      <div class="font-bold text-rose-700"><?= e(centavos_para_moeda_brl($totalDespesas)) ?></div>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-slate-600">
        <tr>
          <th class="text-left px-4 py-2 font-bold whitespace-nowrap">Pagamento</th>
          <th class="text-left px-4 py-2 font-bold">Categoria</th>
          <th class="text-left px-4 py-2 font-bold">Descrição</th>
          <th class="text-left px-4 py-2 font-bold">Favorecido</th>
          <th class="text-left px-4 py-2 font-bold">Forma</th>
          <th class="text-right px-4 py-2 font-bold">Valor</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php if (!$despesas): ?>
        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">
          Nenhuma despesa paga no período.
        </td></tr>
      <?php else: foreach ($despesas as $d): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-2.5 whitespace-nowrap font-semibold text-slate-800">
            <?= e(date('d/m/Y', strtotime($d['data_pagamento']))) ?>
          </td>
          <td class="px-4 py-2.5">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium"
                  style="background: <?= e(($d['categoria_cor'] ?? '#64748b') . '20') ?>;
                         color: <?= e($d['categoria_cor'] ?? '#64748b') ?>">
              <?= e($d['categoria_icone'] ?? '📄') ?> <?= e($d['categoria_nome'] ?? '—') ?>
            </span>
          </td>
          <td class="px-4 py-2.5">
            <div class="font-medium text-slate-800"><?= e($d['descricao']) ?></div>
            <?php if (!empty($d['documento'])): ?>
              <div class="text-xs text-slate-500">Doc: <?= e($d['documento']) ?></div>
            <?php endif; ?>
          </td>
          <td class="px-4 py-2.5 text-slate-700">
            <div class="text-xs text-slate-400 uppercase">
              <?= e(['usuario'=>'Usuário','fornecedor'=>'Fornecedor','cliente'=>'Cliente','avulso'=>'Avulso'][$d['tipo_pessoa']] ?? '—') ?>
            </div>
            <?= e($d['pessoa_nome'] ?: '—') ?>
          </td>
          <td class="px-4 py-2.5 text-xs text-slate-600">
            <?= e($formas[$d['forma_pagamento']] ?? $d['forma_pagamento']) ?>
          </td>
          <td class="px-4 py-2.5 text-right font-bold text-rose-700 whitespace-nowrap">
            <?= e(centavos_para_moeda_brl((int)$d['valor_centavos'])) ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
      <tfoot>
        <tr class="bg-slate-50 border-t-2 border-slate-300">
          <td colspan="5" class="px-4 py-3 text-right font-bold text-slate-700">TOTAL DE DESPESAS</td>
          <td class="px-4 py-3 text-right font-extrabold text-rose-700 whitespace-nowrap">
            <?= e(centavos_para_moeda_brl($totalDespesas)) ?>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- ============ RESUMO FINAL ============ -->
<div class="card rounded-2xl border-2 <?= $saldo >= 0 ? 'border-emerald-300 bg-emerald-50' : 'border-rose-300 bg-rose-50' ?> p-6 mb-4">
  <div class="grid gap-4 sm:grid-cols-3 items-center">
    <div>
      <div class="text-xs font-bold text-slate-600 uppercase tracking-wider">Total de receitas</div>
      <div class="text-xl font-extrabold text-emerald-700 mt-1">
        <?= e(centavos_para_moeda_brl($totalReceitas)) ?>
      </div>
    </div>
    <div>
      <div class="text-xs font-bold text-slate-600 uppercase tracking-wider">Total de despesas</div>
      <div class="text-xl font-extrabold text-rose-700 mt-1">
        − <?= e(centavos_para_moeda_brl($totalDespesas)) ?>
      </div>
    </div>
    <div class="sm:text-right sm:border-l sm:border-slate-300 sm:pl-4">
      <div class="text-xs font-bold text-slate-600 uppercase tracking-wider">Saldo do mês</div>
      <div class="text-2xl font-extrabold mt-1 <?= $saldo >= 0 ? 'text-emerald-800' : 'text-rose-800' ?>">
        <?= ($saldo < 0 ? '- ' : '') . e(centavos_para_moeda_brl(abs($saldo))) ?>
      </div>
    </div>
  </div>
</div>

<!-- ============ RODAPÉ (só impressão) ============ -->
<div class="text-center text-xs text-slate-400 mt-6">
  Documento gerado eletronicamente em <?= e(date('d/m/Y H:i')) ?> — <?= e($emp['razao_social'] ?: '') ?>
</div>

<?php require __DIR__ . '/footer.php'; ?>
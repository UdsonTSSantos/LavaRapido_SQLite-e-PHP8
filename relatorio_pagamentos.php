<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_pagamentos();

$filtros = [
    'status'       => $_GET['status']        ?? '',
    'categoria_id' => (int)($_GET['categoria'] ?? 0),
    'tipo_pessoa'  => $_GET['tipo_pessoa']   ?? '',
    'de'           => $_GET['de']            ?? date('Y-m-01'),
    'ate'          => $_GET['ate']           ?? date('Y-m-t'),
    'q'            => trim((string)($_GET['q'] ?? '')),
];

$pagamentos = listar_pagamentos($filtros);
$categorias = listar_categorias_pagamento();

$totalGeral = 0;
$totalPago  = 0;
foreach ($pagamentos as $p) {
    $totalGeral += (int)$p['valor_centavos'];
    if (!empty($p['data_pagamento'])) $totalPago += (int)$p['valor_centavos'];
}
$totalAberto = $totalGeral - $totalPago;

$emp = empresa();
$logo = empresa_logo_url();

$titulo = 'Relatório de pagamentos';
require __DIR__ . '/header.php';
?>

<style>
  @media print {
    nav, .no-print, form, .no-print * { display: none !important; }
    body { background: #fff !important; }
    .relatorio-card { box-shadow: none !important; border: 1px solid #cbd5e1; }
    table { font-size: 11px; }
  }
</style>

<!-- Filtros (não imprime) -->
<form method="get" class="no-print bg-white rounded-xl shadow p-4 mb-4 flex flex-wrap gap-2 items-end">
  <div class="flex-1 min-w-[200px]">
    <label class="block text-xs font-medium text-slate-500 mb-1">Buscar</label>
    <input type="text" name="q" value="<?= e($filtros['q']) ?>" placeholder="Descrição, favorecido, doc"
           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
  </div>
  <div>
    <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
    <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
      <option value="">Todos</option>
      <option value="pendente" <?= $filtros['status']==='pendente' ? 'selected' : '' ?>>Pendentes</option>
      <option value="atrasado" <?= $filtros['status']==='atrasado' ? 'selected' : '' ?>>Em atraso</option>
      <option value="pago"     <?= $filtros['status']==='pago'     ? 'selected' : '' ?>>Pagos</option>
    </select>
  </div>
  <div>
    <label class="block text-xs font-medium text-slate-500 mb-1">Categoria</label>
    <select name="categoria" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
      <option value="0">Todas</option>
      <?php foreach ($categorias as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $filtros['categoria_id'] === (int)$c['id'] ? 'selected' : '' ?>>
          <?= e($c['icone']) ?> <?= e($c['nome']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="block text-xs font-medium text-slate-500 mb-1">Favorecido</label>
    <select name="tipo_pessoa" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
      <option value="">Todos</option>
      <option value="fornecedor" <?= $filtros['tipo_pessoa']==='fornecedor' ? 'selected' : '' ?>>Fornecedor</option>
      <option value="cliente"    <?= $filtros['tipo_pessoa']==='cliente'    ? 'selected' : '' ?>>Cliente</option>
      <option value="usuario"    <?= $filtros['tipo_pessoa']==='usuario'    ? 'selected' : '' ?>>Usuário</option>
      <option value="avulso"     <?= $filtros['tipo_pessoa']==='avulso'     ? 'selected' : '' ?>>Avulso</option>
    </select>
  </div>
  <div>
    <label class="block text-xs font-medium text-slate-500 mb-1">De</label>
    <input type="date" name="de" value="<?= e($filtros['de']) ?>"
           class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
  </div>
  <div>
    <label class="block text-xs font-medium text-slate-500 mb-1">Até</label>
    <input type="date" name="ate" value="<?= e($filtros['ate']) ?>"
           class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
  </div>
  <button class="rounded-lg bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 text-sm">Filtrar</button>
  <button type="button" onclick="window.print()"
          class="rounded-lg bg-sky-600 hover:bg-sky-500 text-white px-4 py-2 text-sm">Imprimir</button>
</form>

<!-- Relatório -->
<div class="relatorio-card bg-white rounded-xl shadow p-6 sm:p-8">

  <!-- Cabeçalho -->
  <header class="flex items-center gap-4 border-b border-slate-200 pb-4 mb-6">
    <?php if ($logo): ?>
      <img src="<?= e($logo) ?>" alt="Logo" class="h-16 w-16 object-contain">
    <?php endif; ?>
    <div class="flex-1">
      <div class="text-lg font-semibold"><?= e($emp['razao_social'] ?: 'Empresa') ?></div>
      <?php if (!empty($emp['nome_fantasia'])): ?>
        <div class="text-sm text-slate-600"><?= e($emp['nome_fantasia']) ?></div>
      <?php endif; ?>
      <?php if (!empty($emp['cnpj'])): ?>
        <div class="text-xs text-slate-500">CNPJ: <?= e(formatar_cnpj($emp['cnpj'])) ?></div>
      <?php endif; ?>
    </div>
    <div class="text-right text-xs text-slate-500">
      <div class="font-semibold text-slate-700 text-sm">Relatório de Pagamentos</div>
      <div>Período: <?= e(date('d/m/Y', strtotime($filtros['de']))) ?> a <?= e(date('d/m/Y', strtotime($filtros['ate']))) ?></div>
      <div>Emitido em <?= e(date('d/m/Y H:i')) ?></div>
    </div>
  </header>

  <!-- Resumo -->
  <div class="grid grid-cols-3 gap-3 mb-6">
    <div class="border border-slate-200 rounded-lg px-4 py-3">
      <div class="text-xs text-slate-500">Total</div>
      <div class="font-bold text-slate-800"><?= e(centavos_para_moeda_brl($totalGeral)) ?></div>
    </div>
    <div class="border border-emerald-200 rounded-lg px-4 py-3">
      <div class="text-xs text-emerald-700">Pago</div>
      <div class="font-bold text-emerald-800"><?= e(centavos_para_moeda_brl($totalPago)) ?></div>
    </div>
    <div class="border border-amber-200 rounded-lg px-4 py-3">
      <div class="text-xs text-amber-700">Em aberto</div>
      <div class="font-bold text-amber-800"><?= e(centavos_para_moeda_brl($totalAberto)) ?></div>
    </div>
  </div>

  <!-- Tabela -->
  <table class="w-full text-sm border-collapse">
    <thead>
      <tr class="bg-slate-100 text-slate-700">
        <th class="text-left px-3 py-2 border border-slate-200">Vencimento</th>
        <th class="text-left px-3 py-2 border border-slate-200">Categoria</th>
        <th class="text-left px-3 py-2 border border-slate-200">Descrição</th>
        <th class="text-left px-3 py-2 border border-slate-200">Favorecido</th>
        <th class="text-right px-3 py-2 border border-slate-200">Valor</th>
        <th class="text-left px-3 py-2 border border-slate-200">Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$pagamentos): ?>
        <tr><td colspan="6" class="text-center py-8 text-slate-400">Nenhum registro no período.</td></tr>
      <?php else: foreach ($pagamentos as $p):
        $st = status_pagamento($p);
        $lbl = $st === 'pago' ? 'Pago' : ($st === 'atrasado' ? 'Atrasado' : 'Pendente');
      ?>
        <tr>
          <td class="px-3 py-2 border border-slate-200 whitespace-nowrap">
            <?= e(date('d/m/Y', strtotime($p['data_vencimento']))) ?>
          </td>
          <td class="px-3 py-2 border border-slate-200">
            <?= e($p['categoria_icone']) ?> <?= e($p['categoria_nome']) ?>
          </td>
          <td class="px-3 py-2 border border-slate-200"><?= e($p['descricao']) ?></td>
          <td class="px-3 py-2 border border-slate-200"><?= e($p['pessoa_nome']) ?></td>
          <td class="px-3 py-2 border border-slate-200 text-right whitespace-nowrap">
            <?= e(centavos_para_moeda_brl((int)$p['valor_centavos'])) ?>
          </td>
          <td class="px-3 py-2 border border-slate-200 whitespace-nowrap"><?= e($lbl) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
    <tfoot>
      <tr class="bg-slate-50 font-semibold">
        <td colspan="4" class="px-3 py-2 border border-slate-200 text-right">Total geral:</td>
        <td class="px-3 py-2 border border-slate-200 text-right"><?= e(centavos_para_moeda_brl($totalGeral)) ?></td>
        <td class="border border-slate-200"></td>
      </tr>
    </tfoot>
  </table>

  <footer class="mt-6 pt-4 border-t border-slate-200 text-xs text-slate-500 text-center">
    Relatório gerado automaticamente pelo sistema — <?= e(date('d/m/Y H:i')) ?>
  </footer>
</div>

<?php require __DIR__ . '/footer.php'; ?>
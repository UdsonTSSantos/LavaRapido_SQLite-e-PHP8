<?php
/* Alertas de pagamentos — incluir no dashboard principal */
require_once __DIR__ . '/config.php';
ensure_pagamentos();

$__mp = metricas_pagamentos();

$__atrasados = db()->query("
    SELECT p.*, c.nome AS categoria_nome, c.icone AS categoria_icone, c.cor AS categoria_cor
    FROM pagamentos_despesas p
    LEFT JOIN categorias_pagamento c ON c.id = p.categoria_id
    WHERE p.data_pagamento = ''
      AND p.data_vencimento < date('now','localtime')
    ORDER BY p.data_vencimento
    LIMIT 5
")->fetchAll();

$__hoje = db()->query("
    SELECT p.*, c.nome AS categoria_nome, c.icone AS categoria_icone, c.cor AS categoria_cor
    FROM pagamentos_despesas p
    LEFT JOIN categorias_pagamento c ON c.id = p.categoria_id
    WHERE p.data_pagamento = ''
      AND p.data_vencimento = date('now','localtime')
    ORDER BY p.id
")->fetchAll();

$__amanha = db()->query("
    SELECT p.*, c.nome AS categoria_nome, c.icone AS categoria_icone, c.cor AS categoria_cor
    FROM pagamentos_despesas p
    LEFT JOIN categorias_pagamento c ON c.id = p.categoria_id
    WHERE p.data_pagamento = ''
      AND p.data_vencimento = date('now','localtime','+1 day')
    ORDER BY p.id
")->fetchAll();

if (!$__atrasados && !$__hoje && !$__amanha) return;
?>

<div class="mt-8">
  <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-3 px-1">
    Alertas de pagamento
  </h2>

  <?php if ($__atrasados): ?>
    <div class="mb-3 rounded-xl border border-rose-200 bg-rose-50 overflow-hidden">
      <div class="px-5 py-3 flex items-center justify-between border-b border-rose-200">
        <div class="flex items-center gap-2">
          <span class="text-xl">⚠️</span>
          <strong class="text-rose-800">Pagamentos em atraso</strong>
          <span class="text-xs bg-rose-200 text-rose-800 px-2 py-0.5 rounded">
            <?= (int)$__mp['atraso_qtd'] ?> • <?= e(centavos_para_moeda_brl($__mp['atraso_total'])) ?>
          </span>
        </div>
        <a href="pagamentos.php?status=atrasado" class="text-xs text-rose-700 hover:underline font-medium">Ver todos →</a>
      </div>
      <ul class="divide-y divide-rose-100">
        <?php foreach ($__atrasados as $p):
          $dias = (int)((strtotime(date('Y-m-d')) - strtotime($p['data_vencimento'])) / 86400);
        ?>
        <li class="px-5 py-2 flex items-center justify-between text-sm">
          <div class="flex items-center gap-2 min-w-0">
            <span><?= e($p['categoria_icone']) ?></span>
            <span class="font-medium truncate"><?= e($p['descricao']) ?></span>
            <span class="text-rose-600 text-xs whitespace-nowrap"><?= $dias ?> dia(s)</span>
          </div>
          <div class="flex items-center gap-3 whitespace-nowrap">
            <span class="font-semibold text-rose-700"><?= e(centavos_para_moeda_brl((int)$p['valor_centavos'])) ?></span>
            <a href="pagamento_form.php?id=<?= (int)$p['id'] ?>" class="text-sky-600 hover:underline text-xs">Editar</a>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($__hoje): ?>
    <div class="mb-3 rounded-xl border border-amber-200 bg-amber-50 overflow-hidden">
      <div class="px-5 py-3 border-b border-amber-200 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="text-xl">⏰</span>
          <strong class="text-amber-800">Vence hoje</strong>
          <span class="text-xs bg-amber-200 text-amber-800 px-2 py-0.5 rounded">
            <?= (int)$__mp['vence_hoje_qtd'] ?> • <?= e(centavos_para_moeda_brl($__mp['vence_hoje_total'])) ?>
          </span>
        </div>
        <a href="pagamentos.php?status=pendente" class="text-xs text-amber-700 hover:underline font-medium">Ver todos →</a>
      </div>
      <ul class="divide-y divide-amber-100">
        <?php foreach ($__hoje as $p): ?>
        <li class="px-5 py-2 flex items-center justify-between text-sm">
          <div class="flex items-center gap-2 min-w-0">
            <span><?= e($p['categoria_icone']) ?></span>
            <span class="font-medium truncate"><?= e($p['descricao']) ?></span>
            <span class="text-amber-700 text-xs"><?= e($p['pessoa_nome']) ?></span>
          </div>
          <div class="flex items-center gap-3 whitespace-nowrap">
            <span class="font-semibold text-amber-700"><?= e(centavos_para_moeda_brl((int)$p['valor_centavos'])) ?></span>
            <a href="pagamento_form.php?id=<?= (int)$p['id'] ?>" class="text-sky-600 hover:underline text-xs">Editar</a>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($__amanha): ?>
    <div class="rounded-xl border border-sky-200 bg-sky-50 overflow-hidden">
      <div class="px-5 py-3 border-b border-sky-200 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="text-xl">📅</span>
          <strong class="text-sky-800">Vence amanhã</strong>
          <span class="text-xs bg-sky-200 text-sky-800 px-2 py-0.5 rounded">
            <?= (int)$__mp['vence_amanha_qtd'] ?> • <?= e(centavos_para_moeda_brl($__mp['vence_amanha_total'])) ?>
          </span>
        </div>
      </div>
      <ul class="divide-y divide-sky-100">
        <?php foreach ($__amanha as $p): ?>
        <li class="px-5 py-2 flex items-center justify-between text-sm">
          <div class="flex items-center gap-2 min-w-0">
            <span><?= e($p['categoria_icone']) ?></span>
            <span class="font-medium truncate"><?= e($p['descricao']) ?></span>
          </div>
          <div class="flex items-center gap-3 whitespace-nowrap">
            <span class="font-semibold text-sky-700"><?= e(centavos_para_moeda_brl((int)$p['valor_centavos'])) ?></span>
            <a href="pagamento_form.php?id=<?= (int)$p['id'] ?>" class="text-sky-600 hover:underline text-xs">Editar</a>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>
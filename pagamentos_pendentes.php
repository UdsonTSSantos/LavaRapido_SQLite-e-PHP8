<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_entradas();
ensure_lavagens();

$hoje = date('Y-m-d');
$abaAtiva = $_GET['aba'] ?? 'hoje'; // 'hoje' ou 'anteriores'

// Define a query baseada na aba selecionada
if ($abaAtiva === 'anteriores') {
    $sql = "SELECT * FROM lavagem_entradas
            WHERE status IN ('concluida', 'concluido') 
              AND pago = 0 
              AND data_entrada < ?
            ORDER BY data_entrada DESC, hora_entrada DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute([$hoje]);
} else {
    $sql = "SELECT * FROM lavagem_entradas
            WHERE status IN ('concluida', 'concluido') 
              AND pago = 0 
              AND data_entrada = ?
            ORDER BY hora_entrada DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute([$hoje]);
}

$entradas = $stmt->fetchAll();

// Conta quantos tem em cada aba para mostrar nos badges
$totalHoje = db()->query("SELECT COUNT(*) FROM lavagem_entradas WHERE status IN ('concluida', 'concluido') AND pago = 0 AND data_entrada = '$hoje'")->fetchColumn();
$totalAnteriores = db()->query("SELECT COUNT(*) FROM lavagem_entradas WHERE status IN ('concluida', 'concluido') AND pago = 0 AND data_entrada < '$hoje'")->fetchColumn();

$titulo = 'Pagamentos Pendentes';
require __DIR__ . '/header.php';
?>

<div class="max-w-5xl mx-auto">
  
  <div class="mb-6 flex flex-wrap justify-between items-center gap-4">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Pagamentos Pendentes</h1>
      <p class="text-sm text-slate-500">Lavagens concluídas que aguardam a finalização do caixa.</p>
    </div>
    <a href="entradas.php" class="rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 text-sm font-semibold shadow-sm transition">← Voltar para Entradas</a>
  </div>

  <!-- ABAS (TABS) -->
  <div class="flex border-b border-slate-200 mb-6">
    <a href="?aba=hoje" class="px-6 py-3 font-semibold text-sm flex items-center gap-2 border-b-2 transition-colors <?= $abaAtiva === 'hoje' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300' ?>">
      Aguardando Hoje
      <?php if ($totalHoje > 0): ?>
        <span class="bg-emerald-100 text-emerald-800 text-xs px-2 py-0.5 rounded-full"><?= $totalHoje ?></span>
      <?php endif; ?>
    </a>
    
    <a href="?aba=anteriores" class="px-6 py-3 font-semibold text-sm flex items-center gap-2 border-b-2 transition-colors <?= $abaAtiva === 'anteriores' ? 'border-rose-500 text-rose-600' : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300' ?>">
      Entregues / Anteriores
      <?php if ($totalAnteriores > 0): ?>
        <span class="bg-rose-100 text-rose-800 text-xs px-2 py-0.5 rounded-full"><?= $totalAnteriores ?></span>
      <?php endif; ?>
    </a>
  </div>

  <!-- LISTAGEM -->
  <div class="bg-white rounded-2xl shadow-card border border-slate-200 overflow-hidden">
    <?php if (empty($entradas)): ?>
      <div class="text-center py-12 bg-slate-50">
        <svg class="mx-auto h-12 w-12 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h3 class="text-lg font-bold text-slate-700">Tudo limpo por aqui!</h3>
        <p class="text-slate-500 text-sm">Não há lavagens pendentes nesta categoria.</p>
      </div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-wider">
              <th class="px-6 py-4">Cupom</th>
              <th class="px-6 py-4">Data / Hora</th>
              <th class="px-6 py-4">Cliente / Placa</th>
              <th class="px-6 py-4 text-right">Valor Total</th>
              <th class="px-6 py-4 text-center">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($entradas as $e): ?>
              <tr class="hover:bg-slate-50 transition">
                <td class="px-6 py-4">
                  <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-md bg-slate-100 text-slate-700 font-mono font-bold text-sm border border-slate-200">
                    #<?= str_pad($e['id'], 5, '0', STR_PAD_LEFT) ?>
                  </span>
                </td>
                <td class="px-6 py-4">
                  <div class="text-sm font-semibold <?= $abaAtiva === 'anteriores' ? 'text-rose-600' : 'text-slate-800' ?>">
                    <?= e(date('d/m/Y', strtotime($e['data_entrada']))) ?>
                  </div>
                  <div class="text-xs text-slate-500">
                    às <?= e(substr($e['hora_entrada'], 0, 5)) ?>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div class="text-sm font-bold text-slate-800"><?= e($e['cliente_nome_avulso']) ?></div>
                  <div class="text-xs font-mono font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded inline-block mt-1">
                    <?= e(formatar_placa($e['placa'])) ?>
                  </div>
                </td>
                <td class="px-6 py-4 text-right">
                  <div class="text-base font-bold text-emerald-600">
                    <?= centavos_para_moeda($e['total_centavos']) ?>
                  </div>
                  <div class="text-xs text-slate-400">A receber</div>
                </td>
                <td class="px-6 py-4 text-center">
                  <div class="flex items-center justify-center gap-2">
                    <!-- BOTÃO CONSULTAR -->
                    <a href="entrada_lavagem.php?id=<?= $e['id'] ?>" class="inline-flex items-center gap-1 rounded-lg bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 px-3 py-1.5 text-sm font-semibold transition" title="Ver detalhes da lavagem">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                      Consultar
                    </a>

                    <!-- BOTÃO RECEBER -->
                    <a href="receber_pagamento.php?id=<?= $e['id'] ?>" class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1.5 text-sm font-bold shadow-sm transition">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                      Receber
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
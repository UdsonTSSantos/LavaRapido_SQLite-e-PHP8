<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_comissoes();

// Dados da empresa para o cabeçalho
$emp  = empresa();
$logo = empresa_logo_url();

// Filtros
$usuario_id = (int)($_GET['usuario_id'] ?? 0);
$de  = $_GET['de'] ?? date('Y-m-01');
$ate = $_GET['ate'] ?? date('Y-m-t');

// Busca a lista de usuários para o select
$usuarios = db()->query("SELECT id, nome FROM usuarios WHERE ativo = 1 ORDER BY nome COLLATE NOCASE")->fetchAll();

$funcionario = null;
$comissoes = [];
$total = 0;

if ($usuario_id > 0) {
    // Dados do funcionário
    foreach ($usuarios as $u) {
        if ((int)$u['id'] === $usuario_id) {
            $funcionario = $u;
            break;
        }
    }

    // Busca as comissões no período
    $sql = "
        SELECT c.*, e.id AS entrada_id
        FROM comissoes_lancadas c
        INNER JOIN lavagem_entradas e ON e.id = c.entrada_id
        WHERE c.usuario_id = :uid
          AND c.data_entrada BETWEEN :de AND :ate
        ORDER BY c.data_entrada ASC, c.id ASC
    ";
    $st = db()->prepare($sql);
    $st->execute([':uid' => $usuario_id, ':de' => $de, ':ate' => $ate]);
    $comissoes = $st->fetchAll();

    foreach ($comissoes as $c) {
        $total += (int)$c['valor_centavos'];
    }
}

$titulo = 'Recibo de Comissão';
require __DIR__ . '/header.php';
?>

<style>
  @media print {
    @page { size: A4; margin: 15mm; }
    body { background: #fff !important; }
    nav, .no-print, .no-print * { display: none !important; }
    main { padding: 0 !important; max-width: 100% !important; }
    .print-card { box-shadow: none !important; border: none !important; margin: 0 !important; padding: 0 !important; }
  }
</style>

<div class="max-w-4xl mx-auto">
  
  <!-- Filtro (Não imprime) -->
  <div class="no-print bg-white rounded-2xl shadow-card border border-slate-200 p-6 mb-6">
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-xl font-bold text-slate-900">Gerar Recibo de Comissão</h1>
      <a href="comissoes.php" class="text-sm text-brand-600 hover:underline font-semibold">← Voltar</a>
    </div>

    <form method="get" class="flex flex-wrap gap-4 items-end">
      <div class="flex-1 min-w-[200px]">
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Funcionário</label>
        <select name="usuario_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black focus:outline-none focus:ring-2 focus:ring-brand-400">
          <option value="">Selecione...</option>
          <?php foreach ($usuarios as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $usuario_id === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-bold text-slate-700 mb-1.5">De</label>
        <input type="date" name="de" value="<?= e($de) ?>" class="rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black focus:outline-none focus:ring-2 focus:ring-brand-400">
      </div>

      <div>
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Até</label>
        <input type="date" name="ate" value="<?= e($ate) ?>" class="rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black focus:outline-none focus:ring-2 focus:ring-brand-400">
      </div>

      <button type="submit" class="px-6 py-2.5 rounded-lg bg-brand-500 hover:bg-brand-700 text-white font-semibold shadow-sm">Gerar Recibo</button>
    </form>
  </div>

  <!-- Recibo (Aparece se gerado) -->
  <?php if ($funcionario): ?>
    <div class="print-card bg-white rounded-2xl shadow-card border border-slate-200 p-8 sm:p-12">
      
      <!-- Cabeçalho da Empresa -->
      <div class="flex items-center gap-6 border-b-2 border-slate-800 pb-6 mb-6">
        <?php if ($logo): ?>
          <img src="<?= e($logo) ?>" alt="Logo" class="h-20 w-20 object-contain">
        <?php endif; ?>
        <div class="flex-1">
          <h2 class="text-2xl font-extrabold text-slate-900"><?= e($emp['razao_social'] ?: $emp['nome_fantasia']) ?></h2>
          <p class="text-sm text-slate-600"><?= e($emp['endereco']) ?><?= $emp['numero'] ? ', ' . e($emp['numero']) : '' ?> — <?= e($emp['bairro']) ?> — <?= e($emp['cidade']) ?>/<?= e($emp['uf']) ?></p>
          <p class="text-sm text-slate-600">CNPJ: <?= e(formatar_cnpj($emp['cnpj'] ?? '')) ?> | Tel: <?= e($emp['celular'] ?: $emp['telefone']) ?></p>
        </div>
        <div class="text-right">
          <h3 class="text-lg font-bold text-slate-800">RECIBO DE COMISSÃO</h3>
          <p class="text-sm text-slate-500">Emitido em: <?= date('d/m/Y') ?></p>
        </div>
      </div>

      <!-- Dados do Funcionário -->
      <div class="mb-8">
        <p class="text-slate-700 leading-relaxed text-lg">
          Recebi da empresa <strong><?= e($emp['razao_social'] ?: $emp['nome_fantasia']) ?></strong>, 
          a importância total de <strong><?= e(centavos_para_moeda_brl($total)) ?></strong>, 
          referente às comissões pelos serviços realizados no período de 
          <strong><?= date('d/m/Y', strtotime($de)) ?></strong> a <strong><?= date('d/m/Y', strtotime($ate)) ?></strong>.
        </p>
        <div class="mt-4 flex gap-8">
          <div><span class="text-sm font-bold text-slate-500 uppercase tracking-wider">Favorecido:</span> <span class="font-semibold text-slate-900 text-lg"><?= e($funcionario['nome']) ?></span></div>
        </div>
      </div>

      <!-- Tabela de Comissões -->
      <table class="w-full text-sm mb-8 border-collapse">
        <thead>
          <tr class="border-b-2 border-slate-300 bg-slate-50 text-left">
            <th class="py-2 px-3 text-slate-600 font-bold">Data</th>
            <th class="py-2 px-3 text-slate-600 font-bold">Comanda</th>
            <th class="py-2 px-3 text-slate-600 font-bold">Serviço Realizado</th>
            <th class="py-2 px-3 text-right text-slate-600 font-bold">Valor</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$comissoes): ?>
            <tr><td colspan="4" class="py-4 text-center text-slate-400">Nenhuma comissão no período selecionado.</td></tr>
          <?php else: ?>
            <?php foreach ($comissoes as $c): ?>
              <tr class="border-b border-slate-200">
                <td class="py-2 px-3 text-slate-700"><?= date('d/m/Y', strtotime($c['data_entrada'])) ?></td>
                <td class="py-2 px-3 text-slate-700">#<?= str_pad((int)$c['entrada_id'], 6, '0', STR_PAD_LEFT) ?></td>
                <td class="py-2 px-3 text-slate-700"><?= e($c['nome_lavagem']) ?></td>
                <td class="py-2 px-3 text-right font-semibold text-slate-800"><?= e(centavos_para_moeda_brl((int)$c['valor_centavos'])) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr class="border-t-2 border-slate-800">
            <td colspan="3" class="py-3 px-3 text-right font-bold text-slate-800 text-base">TOTAL A RECEBER:</td>
            <td class="py-3 px-3 text-right font-extrabold text-emerald-700 text-lg"><?= e(centavos_para_moeda_brl($total)) ?></td>
          </tr>
        </tfoot>
      </table>

      <!-- Protocolo de Assinatura -->
      <div class="mt-16 pt-8 border-t border-dashed border-slate-300">
        <p class="text-sm text-slate-600 text-center mb-12">
          Declaro que recebi a quantia acima discriminada, dando plena, geral e irrevogável quitação pelo valor recebido.
        </p>

        <div class="flex justify-between items-end px-8">
          <div class="text-center w-64">
            <div class="border-t border-slate-800 w-full mb-2"></div>
            <p class="text-sm font-bold text-slate-800"><?= e($funcionario['nome']) ?></p>
            <p class="text-xs text-slate-500">Favorecido / Recebedor</p>
          </div>
          
          <div class="text-center w-64">
            <div class="border-t border-slate-800 w-full mb-2"></div>
            <p class="text-sm font-bold text-slate-800"><?= e($emp['razao_social'] ?: $emp['nome_fantasia']) ?></p>
            <p class="text-xs text-slate-500">Para: <?= e($emp['razao_social'] ?: $emp['nome_fantasia']) ?></p>
          </div>
        </div>

        <div class="text-center mt-12 text-xs text-slate-400">
          <p><?= e($emp['cidade'] ?: 'Local') ?>, <?= date('d/m/Y') ?></p>
        </div>
      </div>

    </div>

    <!-- Botões de Ação (Não imprimem) -->
    <div class="no-print flex justify-end gap-3 mt-6">
      <button onclick="window.print()" class="px-6 py-3 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold shadow-md transition flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Imprimir Recibo
      </button>
    </div>
  <?php endif; ?>

</div>

<?php require __DIR__ . '/footer.php'; ?>
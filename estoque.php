<?php
require_once __DIR__ . '/config.php';
exigir_login();
exigir_permissao('estoque');
ensure_estoque();

/* ---------- Registrar Entrada Manual ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'entrada') {
    csrf_validar();
    
    $itemId = (int)($_POST['item_id'] ?? 0);
    $qtd    = (float)str_replace(',', '.', $_POST['quantidade'] ?? '0');
    $doc    = trim((string)($_POST['documento'] ?? ''));
    $obs    = trim((string)($_POST['observacao'] ?? ''));

    if ($itemId > 0 && $qtd > 0) {
        movimentar_estoque($itemId, 'entrada', $qtd, $doc, $obs);
        flash('Entrada de estoque registrada com sucesso!', 'sucesso');
    } else {
        flash('Informe uma quantidade válida para entrada.', 'erro');
    }
    header('Location: estoque.php');
    exit;
}

$busca  = trim((string)($_GET['q'] ?? ''));
$where  = '';
$params = [];

if ($busca !== '') {
    $where = 'WHERE nome LIKE :q OR codigo LIKE :q';
    $params[':q'] = '%' . $busca . '%';
}

$stmt = db()->prepare("SELECT * FROM estoque_itens $where ORDER BY nome COLLATE NOCASE");
$stmt->execute($params);
$itens = $stmt->fetchAll();

$titulo = 'Estoque de Peças';
require __DIR__ . '/header.php';
?>

<div class="bg-white rounded-2xl shadow-card border border-slate-200 overflow-hidden">
  <div class="px-5 py-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-xl font-bold text-slate-900">Estoque de Peças</h1>
      <p class="text-xs text-slate-500 mt-0.5"><?= count($itens) ?> produto(s) cadastrado(s)</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <form method="get" class="flex">
        <input type="text" name="q" value="<?= e($busca) ?>" placeholder="Buscar por código ou nome"
               class="w-56 rounded-l-lg border border-slate-300 px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-400">
        <button class="rounded-r-lg bg-slate-800 hover:bg-slate-700 text-white px-4 text-sm font-semibold">Buscar</button>
      </form>
      
      <a href="estoque_xml.php" class="rounded-lg bg-sky-600 hover:bg-sky-500 text-white px-4 py-2 text-sm font-semibold shadow-sm flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
        Importar XML
      </a>
      
      <a href="estoque_form.php" class="rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 text-sm font-semibold whitespace-nowrap shadow-sm">
        + Novo Item
      </a>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-slate-600">
        <tr>
          <th class="text-left px-4 py-2.5 font-bold">Código</th>
          <th class="text-left px-4 py-2.5 font-bold">Nome</th>
          <th class="text-right px-4 py-2.5 font-bold">Qtd</th>
          <th class="text-right px-4 py-2.5 font-bold">Custo</th>
          <th class="text-right px-4 py-2.5 font-bold">Venda</th>
          <th class="text-center px-4 py-2.5 font-bold">Status</th>
          <th class="px-4 py-2.5"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php if (!$itens): ?>
        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">
          Nenhum item no estoque.
        </td></tr>
      <?php else: foreach ($itens as $i): 
        $isBaixo = $i['quantidade'] <= $i['estoque_minimo'];
      ?>
        <tr class="hover:bg-slate-50 transition">
          <td class="px-4 py-3 font-mono font-semibold text-slate-700"><?= e($i['codigo']) ?></td>
          <td class="px-4 py-3 font-semibold text-slate-800"><?= e($i['nome']) ?></td>
          <td class="px-4 py-3 text-right font-bold <?= $isBaixo ? 'text-rose-600' : 'text-slate-800' ?>">
            <?= number_format($i['quantidade'], 2, ',', '.') ?> <span class="text-xs font-normal text-slate-500"><?= e($i['unidade']) ?></span>
          </td>
          <td class="px-4 py-3 text-right text-slate-600"><?= e(centavos_para_moeda_brl((int)$i['preco_custo_centavos'])) ?></td>
          <td class="px-4 py-3 text-right text-slate-600"><?= e(centavos_para_moeda_brl((int)$i['preco_venda_centavos'])) ?></td>
          <td class="px-4 py-3 text-center">
            <?php if ($isBaixo): ?>
              <span class="inline-block text-xs px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 font-bold">Estoque Baixo</span>
            <?php else: ?>
              <span class="inline-block text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-bold">Normal</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 text-right whitespace-nowrap">
            <!-- Botão de ENTRADA -->
            <button type="button" 
                    class="btn-entrada text-emerald-600 hover:underline text-xs font-bold mr-3"
                    data-id="<?= (int)$i['id'] ?>"
                    data-nome="<?= e($i['nome']) ?>"
                    data-unidade="<?= e($i['unidade']) ?>">
              + Entrada
            </button>
            <a href="estoque_form.php?id=<?= (int)$i['id'] ?>" class="text-sky-600 hover:underline text-xs font-semibold">Editar</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ============ MODAL DE ENTRADA DE ESTOQUE ============ -->
<div id="modalEntrada" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="acao" value="entrada">
      <input type="hidden" name="item_id" id="ent_item_id">

      <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50 rounded-t-2xl">
        <h3 class="font-bold text-slate-800">Dar entrada no estoque</h3>
        <button type="button" class="fechar-entrada text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
      </div>

      <div class="px-6 py-5 space-y-4">
        <div class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-3">
          <div class="text-xs text-brand-600 font-semibold mb-0.5">Produto</div>
          <div class="font-bold text-brand-900 text-lg" id="ent_nome"></div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-sm font-bold text-slate-700 mb-1.5">Quantidade *</label>
            <input name="quantidade" id="ent_qtd" type="number" step="0.01" min="0.01" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-bold text-black text-lg focus:outline-none focus:ring-2 focus:ring-brand-400" placeholder="Ex: 5">
          </div>
          <div>
            <label class="block text-sm font-bold text-slate-700 mb-1.5">Documento (NF/Recibo)</label>
            <input name="documento" maxlength="50"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black focus:outline-none focus:ring-2 focus:ring-brand-400" placeholder="Opcional">
          </div>
        </div>

        <div>
          <label class="block text-sm font-bold text-slate-700 mb-1.5">Observação</label>
          <input name="observacao" maxlength="100"
                 class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black focus:outline-none focus:ring-2 focus:ring-brand-400" placeholder="Ex: Compra fornecedor X">
        </div>
      </div>

      <div class="px-6 py-4 border-t border-slate-200 flex justify-end gap-2 bg-slate-50 rounded-b-2xl">
        <button type="button" class="fechar-entrada px-4 py-2.5 rounded-lg bg-white border border-slate-300 text-slate-700 font-semibold hover:bg-slate-100 transition">Cancelar</button>
        <button type="submit" class="px-6 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold shadow-sm transition">Confirmar Entrada</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const modal = document.getElementById('modalEntrada');
  const inId  = document.getElementById('ent_item_id');
  const inNom = document.getElementById('ent_nome');
  const inQtd = document.getElementById('ent_qtd');

  document.querySelectorAll('.btn-entrada').forEach(btn => {
    btn.addEventListener('click', () => {
      inId.value  = btn.dataset.id;
      inNom.textContent = btn.dataset.nome;
      inQtd.value = '';
      modal.classList.remove('hidden');
      setTimeout(() => inQtd.focus(), 100);
    });
  });

  document.querySelectorAll('.fechar-entrada').forEach(el => {
    el.addEventListener('click', () => modal.classList.add('hidden'));
  });

  modal.addEventListener('click', e => {
    if (e.target === modal) modal.classList.add('hidden');
  });
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
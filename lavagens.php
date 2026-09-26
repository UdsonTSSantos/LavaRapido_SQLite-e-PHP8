<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_lavagens();

/* ---------- Excluir (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    csrf_validar();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('DELETE FROM lavagens WHERE id = ?')->execute([$id]);
        flash('Lavagem excluída.', 'sucesso');
    }
    header('Location: lavagens.php');
    exit;
}

/* ---------- Ação rápida: ativar / desativar ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'toggle') {
    csrf_validar();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('UPDATE lavagens SET ativo = CASE ativo WHEN 1 THEN 0 ELSE 1 END,
                              atualizado_em = datetime(\'now\',\'localtime\')
                       WHERE id = ?')->execute([$id]);
        flash('Status atualizado.', 'sucesso');
    }
    header('Location: lavagens.php');
    exit;
}

/* ---------- Busca ---------- */
$busca  = trim((string)($_GET['q'] ?? ''));
$where  = '';
$params = [];
if ($busca !== '') {
    $where = 'WHERE nome LIKE :q OR descricao LIKE :q';
    $params[':q'] = '%' . $busca . '%';
}

$stmt = db()->prepare("SELECT * FROM lavagens $where ORDER BY ordem, nome COLLATE NOCASE");
$stmt->execute($params);
$lavagens = $stmt->fetchAll();

$titulo = 'Lavagens';
require __DIR__ . '/header.php';
?>

<div class="bg-white rounded-xl shadow overflow-hidden">
  <div class="px-5 py-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-xl font-semibold">Tipos de Lavagem</h1>
      <p class="text-xs text-slate-500"><?= count($lavagens) ?> registro(s)</p>
    </div>
    <div class="flex items-center gap-2">
      <form method="get" class="flex">
        <input type="text" name="q" value="<?= e($busca) ?>"
               placeholder="Buscar por nome ou descrição"
               class="w-56 sm:w-72 rounded-l-lg border border-slate-300 px-3 py-2 text-sm
                      focus:outline-none focus:ring-2 focus:ring-sky-500">
        <button class="rounded-r-lg bg-slate-800 hover:bg-slate-700 text-white px-4 text-sm">Buscar</button>
      </form>
      <a href="lavagem_form.php"
         class="rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 text-sm font-medium whitespace-nowrap">
        + Nova
      </a>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-slate-600">
        <tr>
          <th class="text-left px-4 py-2 w-16">Ordem</th>
          <th class="text-left px-4 py-2">Nome</th>
          <th class="text-left px-4 py-2">Descrição</th>
          <th class="text-right px-4 py-2 whitespace-nowrap">Preço</th>
          <th class="text-right px-4 py-2 whitespace-nowrap">Duração</th>
          <th class="text-left px-4 py-2">Status</th>
          <th class="px-4 py-2"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php if (!$lavagens): ?>
        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">
          Nenhum tipo de lavagem cadastrado. Clique em <strong>+ Nova</strong> para começar.
        </td></tr>
      <?php else: foreach ($lavagens as $l): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-2.5 text-slate-500"><?= (int)$l['ordem'] ?></td>
          <td class="px-4 py-2.5 font-medium"><?= e($l['nome']) ?></td>
          <td class="px-4 py-2.5 text-slate-600 max-w-md">
            <?= e(mb_strimwidth($l['descricao'], 0, 120, '…')) ?>
          </td>
          <td class="px-4 py-2.5 text-right font-medium whitespace-nowrap">
            <?= e(centavos_para_moeda_brl((int)$l['preco_centavos'])) ?>
          </td>
          <td class="px-4 py-2.5 text-right text-slate-600 whitespace-nowrap">
            <?= $l['duracao_min'] > 0
                  ? (int)$l['duracao_min'] . ' min'
                  : '—' ?>
          </td>
          <td class="px-4 py-2.5">
            <?php if ($l['ativo']): ?>
              <span class="inline-block text-xs px-2 py-0.5 rounded bg-emerald-100 text-emerald-700">Ativa</span>
            <?php else: ?>
              <span class="inline-block text-xs px-2 py-0.5 rounded bg-slate-200 text-slate-700">Inativa</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-2.5 text-right whitespace-nowrap">
            <a href="lavagem_form.php?id=<?= (int)$l['id'] ?>"
               class="text-sky-600 hover:underline text-xs mr-3">Editar</a>
            <form method="post" class="inline">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="acao" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
              <button class="text-xs <?= $l['ativo'] ? 'text-amber-600' : 'text-emerald-600' ?> hover:underline mr-3">
                <?= $l['ativo'] ? 'Desativar' : 'Ativar' ?>
              </button>
            </form>
            <form method="post" class="inline"
                  onsubmit="return confirm('Excluir a lavagem <?= e(addslashes($l['nome'])) ?>?');">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="acao" value="excluir">
              <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
              <button class="text-rose-600 hover:underline text-xs">Excluir</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
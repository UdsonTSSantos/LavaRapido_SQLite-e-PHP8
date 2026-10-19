<?php
require_once __DIR__ . '/config.php';
$admin = exigir_admin();
ensure_pagamentos();

/* =========================================================
 *  PROCESSAMENTO DE AÇÕES (POST)
 *  Deve ficar antes do header.php
 * ========================================================= */

/* ---------- Excluir ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    csrf_validar();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $st = db()->prepare('SELECT COUNT(*) FROM pagamentos_despesas WHERE categoria_id = ?');
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            flash('Não é possível excluir: existem pagamentos usando esta categoria.', 'erro');
        } else {
            db()->prepare('DELETE FROM categorias_pagamento WHERE id = ?')->execute([$id]);
            flash('Categoria excluída.', 'sucesso');
        }
    }
    header('Location: categorias_pagamento.php');
    exit;
}

/* ---------- Alternar ativo ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'toggle') {
    csrf_validar();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('UPDATE categorias_pagamento SET ativo = CASE ativo WHEN 1 THEN 0 ELSE 1 END WHERE id = ?')
            ->execute([$id]);
        flash('Status atualizado.', 'sucesso');
    }
    header('Location: categorias_pagamento.php');
    exit;
}

/* ---------- Salvar (Criar / Editar) ---------- */
$editando = null;
$erros = [];

if (!empty($_GET['edit'])) {
    $editando = buscar_categoria_pagamento((int)$_GET['edit']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar') {
    csrf_validar();
    $id     = (int)($_POST['id'] ?? 0);
    $nome   = trim((string)($_POST['nome'] ?? ''));
    $cor    = trim((string)($_POST['cor'] ?? '#64748b'));
    $icone  = trim((string)($_POST['icone'] ?? '📄'));
    $ordem  = (int)($_POST['ordem'] ?? 0);

    if ($nome === '') $erros[] = 'Informe o nome da categoria.';
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) $erros[] = 'Cor inválida.';

    if (!$erros) {
        $sql = 'SELECT id FROM categorias_pagamento WHERE nome = ?' . ($id ? ' AND id <> ?' : '');
        $st = db()->prepare($sql);
        $st->execute($id ? [$nome, $id] : [$nome]);
        if ($st->fetch()) $erros[] = 'Já existe uma categoria com esse nome.';
    }

    if (!$erros) {
        if ($id) {
            db()->prepare('UPDATE categorias_pagamento SET nome=?, cor=?, icone=?, ordem=? WHERE id=?')
                ->execute([$nome, $cor, $icone, $ordem, $id]);
            flash('Categoria atualizada.', 'sucesso');
        } else {
            db()->prepare('INSERT INTO categorias_pagamento (nome, cor, icone, ordem) VALUES (?, ?, ?, ?)')
                ->execute([$nome, $cor, $icone, $ordem]);
            flash('Categoria criada.', 'sucesso');
        }
        header('Location: categorias_pagamento.php');
        exit;
    } else {
        // Se houver erro, mantém os dados preenchidos para o usuário corrigir
        $editando = ['id' => $id, 'nome' => $nome, 'cor' => $cor, 'icone' => $icone, 'ordem' => $ordem];
    }
}

/* =========================================================
 *  CARREGAMENTO DA TELA
 * ========================================================= */
$titulo = 'Categorias de pagamento';
require __DIR__ . '/header.php';

$categorias = listar_categorias_pagamento();
?>

<div class="grid gap-6 lg:grid-cols-3">

  <!-- Lista -->
  <div class="lg:col-span-2 bg-white rounded-2xl shadow-card border border-slate-200 overflow-hidden h-fit">
    <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-900">Categorias de pagamento</h1>
        <p class="text-xs text-slate-500 mt-0.5"><?= count($categorias) ?> registro(s)</p>
      </div>
      <a href="pagamentos.php" class="text-sm text-brand-600 hover:underline font-semibold">← Pagamentos</a>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-600">
          <tr>
            <th class="text-left px-4 py-2.5 font-bold w-12">#</th>
            <th class="text-left px-4 py-2.5 font-bold">Nome</th>
            <th class="text-left px-4 py-2.5 font-bold w-24">Cor</th>
            <th class="text-left px-4 py-2.5 font-bold w-24">Status</th>
            <th class="px-4 py-2.5"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($categorias as $c): ?>
          <tr class="hover:bg-slate-50 transition">
            <td class="px-4 py-3 text-2xl"><?= e($c['icone']) ?></td>
            <td class="px-4 py-3 font-semibold text-slate-800"><?= e($c['nome']) ?></td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <span class="inline-block w-5 h-5 rounded shadow-sm border border-slate-200" style="background: <?= e($c['cor']) ?>"></span>
                <span class="text-xs text-slate-500 font-mono"><?= e($c['cor']) ?></span>
              </div>
            </td>
            <td class="px-4 py-3">
              <?php if ($c['ativo']): ?>
                <span class="inline-block text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-bold">Ativa</span>
              <?php else: ?>
                <span class="inline-block text-xs px-2 py-0.5 rounded-full bg-slate-200 text-slate-700 font-bold">Inativa</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <a href="categorias_pagamento.php?edit=<?= (int)$c['id'] ?>#form"
                 class="text-sky-600 hover:underline text-xs font-semibold mr-3">Editar</a>
              <form method="post" class="inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="acao" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button class="text-xs <?= $c['ativo'] ? 'text-amber-600' : 'text-emerald-600' ?> hover:underline font-semibold mr-3">
                  <?= $c['ativo'] ? 'Desativar' : 'Ativar' ?>
                </button>
              </form>
              <form method="post" class="inline"
                    onsubmit="return confirm('Excluir a categoria <?= e(addslashes($c['nome'])) ?>?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button class="text-rose-600 hover:underline text-xs font-semibold">Excluir</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Formulário -->
  <div class="bg-white rounded-2xl shadow-card border border-slate-200 p-5 h-fit" id="form">
    <h2 class="font-bold text-slate-800 mb-4"><?= $editando ? 'Editar categoria' : 'Nova categoria' ?></h2>

    <?php if ($erros): ?>
      <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 text-rose-800 px-3 py-2 text-sm font-semibold">
        <?php foreach ($erros as $e2): ?><div><?= e($e2) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="acao" value="salvar">
      <input type="hidden" name="id" value="<?= (int)($editando['id'] ?? 0) ?>">

      <div>
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Ícone (emoji)</label>
        <input name="icone" maxlength="4" value="<?= e($editando['icone'] ?? '📄') ?>"
               class="w-20 text-center text-2xl rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400 bg-white">
      </div>

      <div>
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Nome *</label>
        <input name="nome" required maxlength="60" value="<?= e($editando['nome'] ?? '') ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-400 bg-white text-black font-semibold">
      </div>

      <div>
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Cor</label>
        <div class="flex items-center gap-3">
          <input type="color" name="cor" value="<?= e($editando['cor'] ?? '#64748b') ?>"
                 class="w-16 h-12 rounded-lg border border-slate-300 cursor-pointer p-1 bg-white">
          <span class="text-sm text-slate-500 font-mono" id="corHex"><?= e($editando['cor'] ?? '#64748b') ?></span>
        </div>
      </div>

      <div>
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Ordem</label>
        <input type="number" name="ordem" min="0" max="9999" value="<?= (int)($editando['ordem'] ?? 99) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-400 bg-white text-black font-semibold">
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <?php if ($editando): ?>
          <a href="categorias_pagamento.php"
             class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-sm font-semibold text-slate-700">Cancelar</a>
        <?php endif; ?>
        <button class="px-4 py-2 rounded-lg bg-brand-500 hover:bg-brand-700 text-white text-sm font-bold shadow-sm">
          <?= $editando ? 'Salvar alterações' : 'Criar categoria' ?>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Atualiza o código HEX na tela ao escolher uma cor no seletor
document.querySelector('input[name="cor"]')?.addEventListener('input', function() {
  document.getElementById('corHex').textContent = this.value;
});
</script>

<?php require __DIR__ . '/footer.php'; ?>
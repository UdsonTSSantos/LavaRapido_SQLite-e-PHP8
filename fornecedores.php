<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_fornecedores();

/* ---------- Excluir (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    csrf_validar();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('DELETE FROM fornecedores WHERE id = ?')->execute([$id]);
        flash('Fornecedor excluído.', 'sucesso');
    }
    header('Location: fornecedores.php');
    exit;
}

/* ---------- Busca ---------- */
$busca  = trim((string)($_GET['q'] ?? ''));
$where  = '';
$params = [];
if ($busca !== '') {
    $where = "WHERE nome LIKE :q2 OR nome_fantasia LIKE :q2
                 OR contato LIKE :q2 OR email LIKE :q2
                 OR cpf_cnpj LIKE :q";
    $params[':q']  = '%' . preg_replace('/\D/', '', $busca) . '%';
    $params[':q2'] = '%' . $busca . '%';
}

$stmt = db()->prepare("SELECT * FROM fornecedores $where ORDER BY nome COLLATE NOCASE");
$stmt->execute($params);
$fornecedores = $stmt->fetchAll();

$titulo = 'Fornecedores';
require __DIR__ . '/header.php';
?>

<div class="bg-white rounded-xl shadow overflow-hidden">
  <div class="px-5 py-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-xl font-semibold">Fornecedores</h1>
      <p class="text-xs text-slate-500"><?= count($fornecedores) ?> registro(s)</p>
    </div>
    <div class="flex items-center gap-2">
      <form method="get" class="flex">
        <input type="text" name="q" value="<?= e($busca) ?>"
               placeholder="Buscar por nome, contato, documento ou e-mail"
               class="w-56 sm:w-80 rounded-l-lg border border-slate-300 px-3 py-2 text-sm
                      focus:outline-none focus:ring-2 focus:ring-sky-500">
        <button class="rounded-r-lg bg-slate-800 hover:bg-slate-700 text-white px-4 text-sm">Buscar</button>
      </form>
      <a href="fornecedor_form.php"
         class="rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 text-sm font-medium whitespace-nowrap">
        + Novo
      </a>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-slate-600">
        <tr>
          <th class="text-left px-4 py-2">Nome</th>
          <th class="text-left px-4 py-2">CNPJ/CPF</th>
          <th class="text-left px-4 py-2">Contato</th>
          <th class="text-left px-4 py-2">Cidade/UF</th>
          <th class="text-left px-4 py-2">Status</th>
          <th class="px-4 py-2"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php if (!$fornecedores): ?>
        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">
          Nenhum fornecedor cadastrado. Clique em <strong>+ Novo</strong> para começar.
        </td></tr>
      <?php else: foreach ($fornecedores as $f): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-2.5">
            <div class="font-medium"><?= e($f['nome']) ?></div>
            <?php if ($f['nome_fantasia']): ?>
              <div class="text-xs text-slate-500"><?= e($f['nome_fantasia']) ?></div>
            <?php endif; ?>
          </td>
          <td class="px-4 py-2.5 whitespace-nowrap">
            <?php if ($f['cpf_cnpj']): ?>
              <div class="text-xs text-slate-400"><?= $f['tipo'] === 'J' ? 'CNPJ' : 'CPF' ?></div>
              <?= e(formatar_cpf_cnpj($f['cpf_cnpj'], $f['tipo'])) ?>
            <?php else: ?>
              <span class="text-xs text-slate-400">—</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-2.5">
            <?php if ($f['contato']): ?><div class="text-xs text-slate-500"><?= e($f['contato']) ?></div><?php endif; ?>
            <?php if ($f['celular']): ?><div><?= e($f['celular']) ?></div><?php endif; ?>
            <?php if ($f['telefone']): ?><div class="text-xs text-slate-500"><?= e($f['telefone']) ?></div><?php endif; ?>
            <?php if ($f['email']): ?><div class="text-xs text-slate-500 break-all"><?= e($f['email']) ?></div><?php endif; ?>
          </td>
          <td class="px-4 py-2.5">
            <?= e($f['cidade']) ?><?= $f['uf'] ? '/' . e($f['uf']) : '' ?>
          </td>
          <td class="px-4 py-2.5">
            <?php if ($f['ativo']): ?>
              <span class="inline-block text-xs px-2 py-0.5 rounded bg-emerald-100 text-emerald-700">Ativo</span>
            <?php else: ?>
              <span class="inline-block text-xs px-2 py-0.5 rounded bg-slate-200 text-slate-700">Inativo</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-2.5 text-right whitespace-nowrap">
            <a href="fornecedor_form.php?id=<?= (int)$f['id'] ?>"
               class="text-sky-600 hover:underline text-xs mr-3">Editar</a>
            <form method="post" class="inline"
                  onsubmit="return confirm('Excluir o fornecedor <?= e(addslashes($f['nome'])) ?>?');">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="acao" value="excluir">
              <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
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
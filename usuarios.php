<?php
require_once __DIR__ . '/config.php';
$admin = exigir_admin();
ensure_usuarios();

/* ---------- Excluir ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    csrf_validar();
    $id = (int)($_POST['id'] ?? 0);
    if ($id === (int)$admin['id']) {
        flash('Você não pode excluir a própria conta.', 'erro');
    } elseif ($id > 0) {
        db()->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
        flash('Usuário excluído.', 'sucesso');
    }
    header('Location: usuarios.php');
    exit;
}

/* ---------- Ativar / desativar ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'toggle') {
    csrf_validar();
    $id = (int)($_POST['id'] ?? 0);
    if ($id === (int)$admin['id']) {
        flash('Você não pode desativar a própria conta.', 'erro');
    } elseif ($id > 0) {
        db()->prepare('UPDATE usuarios SET ativo = CASE ativo WHEN 1 THEN 0 ELSE 1 END WHERE id = ?')
            ->execute([$id]);
        flash('Status atualizado.', 'sucesso');
    }
    header('Location: usuarios.php');
    exit;
}

/* ---------- Gerar nova senha automática ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'gerar_senha') {
    csrf_validar();
    $id = (int)($_POST['id'] ?? 0);

    $u = $id > 0 ? usuario_completo($id) : null;
    if (!$u) {
        flash('Usuário não encontrado.', 'erro');
        header('Location: usuarios.php');
        exit;
    }

    $senhaTemp = gerar_senha_temporaria(10);

    db()->prepare('
        UPDATE usuarios
        SET senha_hash = ?, precisa_trocar_senha = 1
        WHERE id = ?
    ')->execute([password_hash($senhaTemp, PASSWORD_DEFAULT), $id]);

    $_SESSION['senha_gerada'] = [
        'id'      => $id,
        'nome'    => $u['nome'],
        'email'   => $u['email'],
        'celular' => $u['celular'],
        'senha'   => $senhaTemp,
        'acao'    => 'redefinida',
    ];

    header('Location: usuario_senha_gerada.php');
    exit;
}

/* ---------- Busca ---------- */
$busca  = trim((string)($_GET['q'] ?? ''));
$where  = '';
$params = [];
if ($busca !== '') {
    $where = 'WHERE nome LIKE :q OR email LIKE :q OR cpf LIKE :q OR celular LIKE :q';
    $params[':q'] = '%' . $busca . '%';
}

$stmt = db()->prepare(
    "SELECT * FROM usuarios $where ORDER BY nome COLLATE NOCASE, email COLLATE NOCASE"
);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

$titulo = 'Usuários';
require __DIR__ . '/header.php';
?>

<div class="bg-white rounded-xl shadow overflow-hidden">
  <div class="px-5 py-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-xl font-semibold">Usuários</h1>
      <p class="text-xs text-slate-500"><?= count($usuarios) ?> registro(s)</p>
    </div>
    <div class="flex items-center gap-2">
      <form method="get" class="flex">
        <input type="text" name="q" value="<?= e($busca) ?>"
               placeholder="Buscar por nome, e-mail, CPF ou celular"
               class="w-64 sm:w-80 rounded-l-lg border border-slate-300 px-3 py-2 text-sm
                      focus:outline-none focus:ring-2 focus:ring-sky-500">
        <button class="rounded-r-lg bg-slate-800 hover:bg-slate-700 text-white px-4 text-sm">Buscar</button>
      </form>
      <a href="usuario_form.php"
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
          <th class="text-left px-4 py-2">E-mail</th>
          <th class="text-left px-4 py-2">CPF</th>
          <th class="text-left px-4 py-2">Celular</th>
          <th class="text-left px-4 py-2">Perfil</th>
          <th class="text-left px-4 py-2">Status</th>
          <th class="px-4 py-2"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php if (!$usuarios): ?>
        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">
          Nenhum usuário cadastrado. Clique em <strong>+ Novo</strong> para começar.
        </td></tr>
      <?php else: foreach ($usuarios as $usr): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-2.5 font-medium"><?= e($usr['nome'] ?: '—') ?></td>
          <td class="px-4 py-2.5 break-all"><?= e($usr['email']) ?></td>
          <td class="px-4 py-2.5 whitespace-nowrap">
            <?= e($usr['cpf'] ? formatar_doc_usuario($usr['cpf']) : '—') ?>
          </td>
          <td class="px-4 py-2.5 whitespace-nowrap">
            <?= e($usr['celular'] ? formatar_fone_usuario($usr['celular']) : '—') ?>
          </td>
          <td class="px-4 py-2.5"><?= $usr['is_admin'] ? 'Administrador' : 'Usuário' ?></td>
          <td class="px-4 py-2.5">
            <?php if ($usr['ativo']): ?>
              <span class="inline-block text-xs px-2 py-0.5 rounded bg-emerald-100 text-emerald-700">Ativo</span>
            <?php else: ?>
              <span class="inline-block text-xs px-2 py-0.5 rounded bg-slate-200 text-slate-700">Inativo</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-2.5 text-right whitespace-nowrap">
            <a href="usuario_form.php?id=<?= (int)$usr['id'] ?>"
               class="text-sky-600 hover:underline text-xs mr-3">Editar</a>

            <form method="post" class="inline">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="acao" value="gerar_senha">
              <input type="hidden" name="id" value="<?= (int)$usr['id'] ?>">
              <button class="text-slate-700 hover:underline text-xs mr-3">Gerar nova senha</button>
            </form>

            <form method="post" class="inline">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="acao" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$usr['id'] ?>">
              <button class="text-xs <?= $usr['ativo'] ? 'text-amber-600' : 'text-emerald-600' ?> hover:underline mr-3">
                <?= $usr['ativo'] ? 'Desativar' : 'Ativar' ?>
              </button>
            </form>

            <?php if ((int)$usr['id'] !== (int)$admin['id']): ?>
              <form method="post" class="inline"
                    onsubmit="return confirm('Excluir o usuário <?= e(addslashes($usr['email'])) ?>?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="id" value="<?= (int)$usr['id'] ?>">
                <button class="text-rose-600 hover:underline text-xs">Excluir</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
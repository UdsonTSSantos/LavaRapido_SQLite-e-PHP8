<?php
require_once __DIR__ . '/config.php';
$admin = exigir_admin();

$erros     = [];
$novoEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = $_POST['acao'] ?? '';

    // ----- Criar usuário -----
    if ($acao === 'criar') {
        $novoEmail = trim((string)($_POST['email'] ?? ''));
        $senha     = (string)($_POST['senha'] ?? '');

        if (!validar_email($novoEmail)) $erros[] = 'Informe um endereço de e-mail válido.';
        $erros = array_merge($erros, validar_senha($senha));

        if (!$erros) {
            $stmt = db()->prepare('SELECT id FROM usuarios WHERE email = ?');
            $stmt->execute([$novoEmail]);
            if ($stmt->fetch()) {
                $erros[] = 'Já existe um usuário com esse e-mail.';
            } else {
                db()->prepare(
                    'INSERT INTO usuarios (email, senha_hash, is_admin, precisa_trocar_senha)
                     VALUES (?, ?, 0, 1)'
                )->execute([$novoEmail, password_hash($senha, PASSWORD_DEFAULT)]);

                flash('Usuário criado. Ele deverá trocar a senha no primeiro acesso.', 'sucesso');
                header('Location: usuarios.php');
                exit;
            }
        }
    }

    // ----- Ativar / desativar -----
    elseif ($acao === 'toggle_ativo') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$admin['id']) {
            flash('Você não pode desativar a própria conta.', 'erro');
        } else {
            db()->prepare('UPDATE usuarios SET ativo = CASE ativo WHEN 1 THEN 0 ELSE 1 END WHERE id = ?')
                ->execute([$id]);
            flash('Status atualizado.', 'sucesso');
        }
        header('Location: usuarios.php');
        exit;
    }

    // ----- Resetar senha -----
    elseif ($acao === 'resetar_senha') {
        $id   = (int)($_POST['id'] ?? 0);
        $nova = (string)($_POST['nova_senha'] ?? '');
        $errs = validar_senha($nova);
        if ($errs) {
            flash('Senha inválida: ' . implode(' ', $errs), 'erro');
        } else {
            db()->prepare('UPDATE usuarios SET senha_hash = ?, precisa_trocar_senha = 1 WHERE id = ?')
                ->execute([password_hash($nova, PASSWORD_DEFAULT), $id]);
            flash('Senha redefinida. O usuário deverá trocá-la no próximo acesso.', 'sucesso');
        }
        header('Location: usuarios.php');
        exit;
    }
}

$usuarios = db()->query(
    'SELECT id, email, is_admin, ativo, precisa_trocar_senha, criado_em
     FROM usuarios ORDER BY id'
)->fetchAll();

$titulo = 'Usuários';
require __DIR__ . '/header.php';
?>

<div class="grid gap-6 lg:grid-cols-3">

  <!-- Lista -->
  <div class="lg:col-span-2 bg-white rounded-xl shadow overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200">
      <h2 class="font-semibold">Usuários cadastrados</h2>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-600">
          <tr>
            <th class="text-left px-4 py-2">E-mail</th>
            <th class="text-left px-4 py-2">Perfil</th>
            <th class="text-left px-4 py-2">Status</th>
            <th class="text-left px-4 py-2">Senha</th>
            <th class="px-4 py-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($usuarios as $usr): ?>
          <tr>
            <td class="px-4 py-2.5 break-all"><?= e($usr['email']) ?></td>
            <td class="px-4 py-2.5"><?= $usr['is_admin'] ? 'Administrador' : 'Usuário' ?></td>
            <td class="px-4 py-2.5">
              <?php if ($usr['ativo']): ?>
                <span class="inline-block text-xs px-2 py-0.5 rounded bg-emerald-100 text-emerald-700">Ativo</span>
              <?php else: ?>
                <span class="inline-block text-xs px-2 py-0.5 rounded bg-slate-200 text-slate-700">Inativo</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-2.5 text-xs text-slate-500">
              <?= $usr['precisa_trocar_senha'] ? 'Troca pendente' : 'Definida' ?>
            </td>
            <td class="px-4 py-2.5 text-right whitespace-nowrap">
              <button type="button"
                      class="js-reset text-sky-600 hover:underline text-xs mr-2"
                      data-id="<?= (int)$usr['id'] ?>"
                      data-email="<?= e($usr['email']) ?>">
                Redefinir senha
              </button>
              <form method="post" class="inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="acao" value="toggle_ativo">
                <input type="hidden" name="id" value="<?= (int)$usr['id'] ?>">
                <button class="text-xs <?= $usr['ativo'] ? 'text-rose-600' : 'text-emerald-600' ?> hover:underline">
                  <?= $usr['ativo'] ? 'Desativar' : 'Ativar' ?>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Novo usuário -->
  <div class="bg-white rounded-xl shadow p-5">
    <h2 class="font-semibold mb-4">Novo usuário</h2>

    <?php if ($erros): ?>
      <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 text-rose-800 px-3 py-2 text-sm">
        <ul class="list-disc pl-5 space-y-1">
          <?php foreach ($erros as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" class="space-y-4" novalidate>
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="acao" value="criar">

      <div>
        <label class="block text-sm font-medium mb-1" for="email">E-mail</label>
        <input id="email" name="email" type="email" required value="<?= e($novoEmail) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1" for="senha">Senha inicial</label>
        <input id="senha" name="senha" type="text" required minlength="8"
               pattern="(?=.*[A-Za-z])(?=.*\d).{8,}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        <p class="mt-1 text-xs text-slate-500">
          Mínimo 8 caracteres, com letras e números. O usuário deverá trocá-la no primeiro acesso.
        </p>
      </div>

      <button class="w-full rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-medium py-2.5 transition">
        Criar usuário
      </button>
    </form>
  </div>
</div>

<!-- Modal de reset de senha -->
<div id="modalReset" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
    <h3 class="font-semibold mb-1">Redefinir senha</h3>
    <p class="text-sm text-slate-500 mb-4">
      Usuário: <span id="resetEmail" class="font-medium text-slate-700"></span>
    </p>
    <form method="post" class="space-y-4">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="acao" value="resetar_senha">
      <input type="hidden" name="id" id="resetId">
      <div>
        <label class="block text-sm font-medium mb-1" for="nova_senha">Nova senha</label>
        <input id="nova_senha" name="nova_senha" type="text" required minlength="8"
               pattern="(?=.*[A-Za-z])(?=.*\d).{8,}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>
      <div class="flex gap-2 justify-end">
        <button type="button" id="fecharReset"
                class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-sm">Cancelar</button>
        <button class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-500 text-white text-sm">Redefinir</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const modal   = document.getElementById('modalReset');
  const emailEl = document.getElementById('resetEmail');
  const idEl    = document.getElementById('resetId');
  document.querySelectorAll('.js-reset').forEach(btn => {
    btn.addEventListener('click', () => {
      emailEl.textContent = btn.dataset.email;
      idEl.value          = btn.dataset.id;
      modal.classList.remove('hidden');
    });
  });
  document.getElementById('fecharReset')
          .addEventListener('click', () => modal.classList.add('hidden'));
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
<?php
require_once __DIR__ . '/config.php';
$u = exigir_login();

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $atual = (string)($_POST['senha_atual'] ?? '');
    $nova  = (string)($_POST['senha_nova']  ?? '');
    $conf  = (string)($_POST['senha_conf']  ?? '');

    $stmt = db()->prepare('SELECT senha_hash FROM usuarios WHERE id = ?');
    $stmt->execute([$u['id']]);
    $hashAtual = (string)$stmt->fetchColumn();

    if (!password_verify($atual, $hashAtual)) $erros[] = 'Senha atual incorreta.';
    $erros = array_merge($erros, validar_senha($nova));
    if ($nova !== $conf) $erros[] = 'A confirmação não corresponde à nova senha.';
    if (!$erros && password_verify($nova, $hashAtual)) {
        $erros[] = 'A nova senha deve ser diferente da atual.';
    }

    if (!$erros) {
        db()->prepare('UPDATE usuarios SET senha_hash = ?, precisa_trocar_senha = 0 WHERE id = ?')
            ->execute([password_hash($nova, PASSWORD_DEFAULT), $u['id']]);
        flash('Senha alterada com sucesso.', 'sucesso');
        header('Location: dashboard.php');
        exit;
    }
}

$titulo = 'Trocar senha';
require __DIR__ . '/header.php';
?>

<div class="max-w-lg mx-auto">
  <div class="bg-white rounded-xl shadow p-6 sm:p-8">
    <h1 class="text-2xl font-semibold mb-1">Trocar senha</h1>
    <p class="text-sm text-slate-500 mb-6">
      <?= $u['precisa_trocar_senha']
            ? 'Este é seu primeiro acesso: é obrigatório definir uma nova senha.'
            : 'Atualize sua senha de acesso.' ?>
    </p>

    <?php if ($erros): ?>
      <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm">
        <ul class="list-disc pl-5 space-y-1">
          <?php foreach ($erros as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" class="space-y-4" novalidate>
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div>
        <label class="block text-sm font-medium mb-1" for="senha_atual">Senha atual</label>
        <input id="senha_atual" name="senha_atual" type="password" required autocomplete="current-password"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1" for="senha_nova">Nova senha</label>
        <input id="senha_nova" name="senha_nova" type="password" required minlength="8"
               pattern="(?=.*[A-Za-z])(?=.*\d).{8,}" autocomplete="new-password"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        <p class="mt-1 text-xs text-slate-500">Mínimo 8 caracteres, contendo letras e números.</p>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1" for="senha_conf">Confirmar nova senha</label>
        <input id="senha_conf" name="senha_conf" type="password" required minlength="8"
               autocomplete="new-password"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <button class="w-full rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-medium py-2.5 transition">
        Salvar nova senha
      </button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
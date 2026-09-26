<?php
require_once __DIR__ . '/config.php';

if (usuario_logado()) { header('Location: dashboard.php'); exit; }

$erro  = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $email = trim((string)($_POST['email'] ?? ''));
    $senha = (string)($_POST['senha'] ?? '');

    if (!validar_email($email)) {
        $erro = 'Informe um endereço de e-mail válido.';
    } elseif ($senha === '') {
        $erro = 'Informe a senha.';
    } else {
        $stmt = db()->prepare('SELECT * FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        if (!$u || !$u['ativo'] || !password_verify($senha, $u['senha_hash'])) {
            // Mensagem genérica: não revela se o e-mail existe
            $erro = 'Credenciais inválidas.';
        } else {
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = (int)$u['id'];

            if ($u['precisa_trocar_senha']) {
                flash('Primeiro acesso: é obrigatório definir uma nova senha.', 'info');
                header('Location: trocar_senha.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        }
    }
}

$titulo = 'Entrar';
require __DIR__ . '/header.php';
?>

<div class="max-w-md mx-auto mt-6 sm:mt-16">
  <div class="bg-white rounded-xl shadow p-6 sm:p-8">
    <h1 class="text-2xl font-semibold mb-1">Acessar o sistema</h1>
    <p class="text-sm text-slate-500 mb-6">Entre com seu e-mail e senha.</p>

    <?php if ($erro): ?>
      <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm">
        <?= e($erro) ?>
      </div>
    <?php endif; ?>

    <form method="post" class="space-y-4" novalidate>
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div>
        <label for="email" class="block text-sm font-medium mb-1">E-mail</label>
        <input id="email" name="email" type="email" required autocomplete="username"
               value="<?= e($email) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5
                      focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
               placeholder="usuario@dominio.com">
      </div>

      <div>
        <label for="senha" class="block text-sm font-medium mb-1">Senha</label>
        <input id="senha" name="senha" type="password" required minlength="8"
               autocomplete="current-password"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5
                      focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
               placeholder="••••••••">
      </div>

      <button type="submit"
              class="w-full rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-medium py-2.5 transition">
        Entrar
      </button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
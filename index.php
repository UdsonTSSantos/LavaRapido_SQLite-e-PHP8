<?php
require_once __DIR__ . '/config.php';

if (usuario_logado()) { header('Location: dashboard.php'); exit; }

$erro  = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $senha = (string)($_POST['senha'] ?? '');

    // Log de tentativa (ajuda a depurar)
    @file_put_contents(
        APP_ROOT . '/data/login_debug.log',
        date('Y-m-d H:i:s') . " | email=[" . $email . "] | len_senha=" . strlen($senha) . "\n",
        FILE_APPEND
    );

    if (!validar_email($email)) {
        $erro = 'Informe um endereço de e-mail válido.';
    } elseif ($senha === '') {
        $erro = 'Informe a senha.';
    } else {
        if (!function_exists('autenticar')) {
            $erro = 'Erro interno: camada de autenticação indisponível.';
            @file_put_contents(APP_ROOT . '/data/login_debug.log',
                date('Y-m-d H:i:s') . " | ERRO: autenticar() não existe\n", FILE_APPEND);
        } else {
            $r = autenticar($email, $senha);

            @file_put_contents(APP_ROOT . '/data/login_debug.log',
                date('Y-m-d H:i:s') . " | autenticar retornou ok=" . ($r['ok'] ? '1' : '0')
                . " erro=" . ($r['erro'] ?? '-') . "\n", FILE_APPEND);

            if (!$r['ok']) {
                $erro = $r['erro'] ?? 'Credenciais inválidas.';
            } else {
                $u = $r['usuario'];

                if (defined('AUTH_MODE') && AUTH_MODE === 'api') {
                    $u = sincronizar_usuario_local($u);
                }

                session_regenerate_id(true);
                $_SESSION['usuario_id'] = (int)$u['id'];
                if (!empty($r['token'])) $_SESSION['api_token'] = $r['token'];

                if (!empty($u['precisa_trocar_senha'])) {
                    flash('Primeiro acesso: é obrigatório definir uma nova senha.', 'info');
                    header('Location: trocar_senha.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit;
            }
        }
    }
}

/* ---------- Dados da empresa ---------- */
try {
    $__emp  = empresa();
    $__logo = empresa_logo_url();
} catch (Throwable $e) {
    $__emp = []; $__logo = null;
}

$__nomeEmpresa = $__emp['nome_fantasia'] ?: ($__emp['razao_social'] ?: 'Sistema');
$__celular     = $__emp['celular'] ?: ($__emp['telefone'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Acesso ao sistema • <?= e($__nomeEmpresa) ?></title>
<?php if ($__logo): ?>
  <link rel="icon" href="<?= e($__logo) ?>">
  <link rel="apple-touch-icon" href="<?= e($__logo) ?>">
<?php endif; ?>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full bg-white text-slate-800 antialiased">

<div class="min-h-screen grid lg:grid-cols-2">
  <aside class="hidden lg:flex flex-col justify-between bg-blue-700 text-white px-10 xl:px-16 py-12">
    <div></div>
    <div>
      <h1 class="text-3xl xl:text-4xl font-bold leading-tight mb-3">Bem-vindo(a) de volta</h1>
      <p class="text-blue-100 text-base xl:text-lg leading-relaxed max-w-md">
        Acesse o sistema com o usuário e a senha cadastrados pelo administrador.
      </p>
    </div>
    <div class="text-blue-200 text-xs"><?= e($__nomeEmpresa) ?> — Sistema de gestão</div>
  </aside>

  <main class="flex flex-col items-center justify-center px-6 sm:px-10 py-10">
    <div class="w-full max-w-sm">
      <div class="flex justify-center mb-6">
        <?php if ($__logo): ?>
          <img src="<?= e($__logo) ?>" alt="<?= e($__nomeEmpresa) ?>" class="h-24 max-w-[220px] object-contain">
        <?php else: ?>
          <div class="h-24 w-24 rounded-full bg-blue-700 text-white flex items-center justify-center text-3xl font-bold">
            <?= e(mb_substr($__nomeEmpresa, 0, 1)) ?>
          </div>
        <?php endif; ?>
      </div>

      <h2 class="text-2xl font-bold text-center text-slate-900">Acesso ao sistema</h2>
      <p class="text-sm text-slate-500 text-center mt-1 mb-8">Entre com as credenciais fornecidas pelo administrador.</p>

      <?php if ($erro): ?>
        <div class="mb-5 rounded-md border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm"><?= e($erro) ?></div>
      <?php endif; ?>
      <?php if ($f = flash()): ?>
        <div class="mb-5 rounded-md border px-4 py-3 text-sm
          <?= $f['tipo']==='erro' ? 'bg-rose-50 border-rose-200 text-rose-800' : ($f['tipo']==='sucesso' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-sky-50 border-sky-200 text-sky-800') ?>">
          <?= e($f['msg']) ?>
        </div>
      <?php endif; ?>

      <form method="post" class="space-y-4" autocomplete="off" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div>
          <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Usuário</label>
          <input id="email" name="email" type="email" required autocomplete="off"
                 value="<?= e($email) ?>"
                 class="w-full rounded-lg border border-slate-300 px-3.5 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600">
        </div>
        <div>
          <label for="senha" class="block text-sm font-medium text-slate-700 mb-1.5">Senha</label>
          <input id="senha" name="senha" type="password" required minlength="8" autocomplete="off"
                 class="w-full rounded-lg border border-slate-300 px-3.5 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600">
        </div>
        <button type="submit"
                class="w-full rounded-lg bg-blue-700 hover:bg-blue-800 text-white font-semibold py-3 transition shadow-sm">
          Entrar
        </button>
      </form>

      <?php if ($__celular): ?>
        <?php
          $fone = preg_replace('/\D/', '', $__celular);
          $foneFmt = strlen($fone)===11 ? '(' . substr($fone,0,2) . ') ' . substr($fone,2,5) . '-' . substr($fone,7)
                    : (strlen($fone)===10 ? '(' . substr($fone,0,2) . ') ' . substr($fone,4,4) . '-' . substr($fone,8) : $__celular);
          $zap = '55' . $fone;
        ?>
        <a href="https://api.whatsapp.com/send?phone=<?= e($zap) ?>" target="_blank" rel="noopener"
           class="mt-6 flex items-center justify-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 px-4 py-3 text-sm font-medium transition">
          Suporte <?= e($__nomeEmpresa) ?>: <?= e($foneFmt) ?>
        </a>
      <?php endif; ?>

      <p class="mt-8 text-center text-xs text-slate-400">
        © <?= date('Y') ?> <?= e($__nomeEmpresa) ?>. Todos os direitos reservados.
      </p>
    </div>
  </main>
</div>
</body>
</html>
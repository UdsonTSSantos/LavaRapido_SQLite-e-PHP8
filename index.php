<?php
require_once __DIR__ . '/config.php';

if (usuario_logado()) { header('Location: dashboard.php'); exit; }

$erro  = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $senha = (string)($_POST['senha'] ?? '');

    if (!validar_email($email)) {
        $erro = 'Informe um endereço de e-mail válido.';
    } elseif ($senha === '') {
        $erro = 'Informe a senha.';
    } else {
        $r = autenticar($email, $senha);

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

try { $__emp = empresa(); $__logo = empresa_logo_url(); }
catch (Throwable $e) { $__emp = []; $__logo = null; }

$__nomeEmpresa = $__emp['nome_fantasia'] ?: ($__emp['razao_social'] ?: 'Sistema');
$__celular     = $__emp['celular'] ?: ($__emp['telefone'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Acesso • <?= e($__nomeEmpresa) ?></title>
<?php if ($__logo): ?>
  <link rel="icon" href="<?= e($__logo) ?>">
  <link rel="apple-touch-icon" href="<?= e($__logo) ?>">
<?php endif; ?>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        brand: {
          50:'#f0fbff', 100:'#b6ffff', 200:'#7cdaf9', 300:'#4dc9f5',
          400:'#0cb7f2', 500:'#0979b0', 600:'#076a9c', 700:'#004173',
          800:'#003256', 900:'#002039',
        }
      },
      fontFamily: { sans: ['Inter','ui-sans-serif','system-ui','sans-serif'] }
    }
  }
};
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  html, body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }

  /* Campos do login */
  .login-input {
    font-size: 1rem;
    font-weight: 600;
    color: #000;
    background: #fff;
    border: 1.5px solid #cbd5e1;
    border-radius: 0.75rem;
    padding: 0.85rem 1rem;
    width: 100%;
    transition: border-color .15s, box-shadow .15s;
  }
  .login-input::placeholder { color: #94a3b8; font-weight: 500; }
  .login-input:focus {
    outline: none;
    border-color: #0cb7f2;
    box-shadow: 0 0 0 4px rgba(12,183,242,.2);
  }

  /* Fundo decorativo do lado direito */
  .login-side::before {
    content:'';
    position:absolute; inset:0;
    background:
      radial-gradient(600px circle at 90% 10%, rgba(12,183,242,.12), transparent 60%),
      radial-gradient(500px circle at 10% 90%, rgba(124,218,249,.18), transparent 60%);
    pointer-events:none;
  }
</style>
</head>
<body class="h-full bg-white text-slate-800 antialiased">

<div class="min-h-screen grid lg:grid-cols-2">

  <!-- ============ LADO ESQUERDO (marca) ============ -->
  <aside class="hidden lg:flex flex-col justify-between text-white px-10 xl:px-16 py-12 relative overflow-hidden"
         style="background: linear-gradient(160deg, #004173 0%, #0979b0 60%, #0cb7f2 130%);">
    <!-- Detalhes decorativos -->
    <div class="absolute -top-20 -right-20 w-96 h-96 rounded-full" style="background: rgba(182,255,255,.08)"></div>
    <div class="absolute -bottom-24 -left-20 w-80 h-80 rounded-full" style="background: rgba(255,255,255,.06)"></div>

    <div class="relative z-10 flex items-center gap-3">
      <?php if ($__logo): ?>
        <img src="<?= e($__logo) ?>" alt="Logo" class="h-11 w-11 object-contain bg-white/95 rounded-xl p-1 shadow-md">
      <?php endif; ?>
      <span class="font-bold text-lg tracking-tight"><?= e($__nomeEmpresa) ?></span>
    </div>

    <div class="relative z-10">
      <h1 class="text-4xl xl:text-5xl font-extrabold leading-[1.1] mb-4 tracking-tight">
        Bem-vindo(a)<br>de volta.
      </h1>
      <p class="text-white/85 text-base xl:text-lg leading-relaxed max-w-md">
        Acesse o painel com as credenciais fornecidas pelo administrador e continue de onde parou.
      </p>

      <div class="mt-8 flex gap-2 flex-wrap">
        <span class="text-xs px-3 py-1.5 rounded-full bg-white/15 backdrop-blur-sm font-medium">Clientes</span>
        <span class="text-xs px-3 py-1.5 rounded-full bg-white/15 backdrop-blur-sm font-medium">Lavagens</span>
        <span class="text-xs px-3 py-1.5 rounded-full bg-white/15 backdrop-blur-sm font-medium">Pagamentos</span>
        <span class="text-xs px-3 py-1.5 rounded-full bg-white/15 backdrop-blur-sm font-medium">Relatórios</span>
      </div>
    </div>

    <div class="relative z-10 text-white/70 text-xs">
      © <?= date('Y') ?> <?= e($__nomeEmpresa) ?> — Todos os direitos reservados.
    </div>
  </aside>

  <!-- ============ LADO DIREITO (formulário) ============ -->
  <main class="login-side relative flex flex-col items-center justify-center px-6 sm:px-10 py-10 bg-white">
    <div class="w-full max-w-md relative z-10">

      <!-- Logo (mobile + desktop) -->
      <div class="flex justify-center mb-8">
        <?php if ($__logo): ?>
          <img src="<?= e($__logo) ?>" alt="<?= e($__nomeEmpresa) ?>"
               class="h-24 max-w-[220px] object-contain">
        <?php else: ?>
          <div class="h-20 w-20 rounded-2xl text-white flex items-center justify-center text-3xl font-bold shadow-lg"
               style="background: linear-gradient(135deg, #004173, #0cb7f2);">
            <?= e(mb_substr($__nomeEmpresa, 0, 1)) ?>
          </div>
        <?php endif; ?>
      </div>

      <h2 class="text-2xl font-extrabold text-center text-slate-900 tracking-tight">Acesso ao sistema</h2>
      <p class="text-sm text-slate-500 text-center mt-2 mb-8">
        Entre com as credenciais fornecidas pelo administrador.
      </p>

      <?php if ($erro): ?>
        <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm font-medium">
          <?= e($erro) ?>
        </div>
      <?php endif; ?>

      <?php if ($f = flash()): ?>
        <div class="mb-5 rounded-xl border px-4 py-3 text-sm font-medium
          <?= $f['tipo']==='erro' ? 'bg-rose-50 border-rose-200 text-rose-800'
              : ($f['tipo']==='sucesso' ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
              : 'bg-brand-50 border-brand-200 text-brand-700') ?>">
          <?= e($f['msg']) ?>
        </div>
      <?php endif; ?>

      <form method="post" class="space-y-5" autocomplete="off" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div>
          <label for="email" class="block text-sm font-semibold text-slate-800 mb-2">Usuário</label>
          <input id="email" name="email" type="email" required autocomplete="off"
                 value="<?= e($email) ?>" placeholder="seu@email.com"
                 class="login-input">
        </div>

        <div>
          <label for="senha" class="block text-sm font-semibold text-slate-800 mb-2">Senha</label>
          <input id="senha" name="senha" type="password" required autocomplete="off"
                 placeholder="••••••••"
                 class="login-input">
        </div>

        <button type="submit"
                class="w-full rounded-xl text-white font-bold py-3.5 transition shadow-md hover:shadow-lg"
                style="background: linear-gradient(135deg, #0979b0 0%, #0cb7f2 100%);">
          Entrar
        </button>
      </form>

      <?php if ($__celular): ?>
        <?php
          $fone = preg_replace('/\D/', '', $__celular);
          $foneFmt = strlen($fone)===11
              ? '(' . substr($fone,0,2) . ') ' . substr($fone,2,5) . '-' . substr($fone,7)
              : (strlen($fone)===10
                  ? '(' . substr($fone,0,2) . ') ' . substr($fone,2,4) . '-' . substr($fone,6)
                  : $__celular);
          $zap = '55' . $fone;
        ?>
        <a href="https://api.whatsapp.com/send?phone=<?= e($zap) ?>" target="_blank" rel="noopener"
           class="mt-7 flex items-center justify-center gap-2 rounded-xl border border-brand-100 bg-brand-50
                  hover:bg-brand-100 text-brand-700 px-4 py-3 text-sm font-semibold transition">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.573-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884"/>
          </svg>
          Suporte: <?= e($foneFmt) ?>
        </a>
      <?php endif; ?>

      <p class="mt-8 text-center text-xs text-slate-400 lg:hidden">
        © <?= date('Y') ?> <?= e($__nomeEmpresa) ?>
      </p>
    </div>
  </main>
</div>

</body>
</html>
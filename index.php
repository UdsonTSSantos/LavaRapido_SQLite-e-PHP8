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
  
  /* Fundo com imagem e overlay azulado */
  .bg-login {
    background-image: url('./uploads/img/img_loginLava2.WEBP');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
  }
  .bg-login::before {
    content: '';
    position: absolute;
    inset: 0;
    
    z-index: 1;
  }

  /* Efeito Glassmorphism */
  .glass-panel {
    background: rgba(255, 255, 255, 0.4);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.6);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  }

  /* Campos de input transparentes com fundo branco */
  .login-input {
    font-size: 1rem;
    color: #1e293b;
    background: #ffffff;
    border: none;
    border-radius: 0.5rem;
    padding: 0.875rem 1rem;
    width: 100%;
    box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.05);
    transition: box-shadow .15s;
  }
  .login-input:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(12, 183, 242, 0.4);
  }
</style>
</head>
<body class="h-full antialiased relative bg-login overflow-hidden flex items-center justify-center sm:justify-start">

  <!-- Logo no canto superior esquerdo (opcional) -->
  

  <!-- Container do Card de Login (alinhado à esquerda) -->
  <div class="relative z-20 w-full max-w-[420px] sm:ml-12 md:ml-24 px-4 sm:px-0">
    
    <!-- Card de Vidro -->
    <div class="glass-panel rounded-[2rem] p-8 sm:p-10">
      
      <!-- Cabeçalho do Card -->
      <div class="text-center mb-8">
        <h1 class="text-[1.7rem] font-extrabold text-brand-700 tracking-tight">
          Login de acesso
        </h1>
        <p class="text-[1.1rem] font-semibold text-brand-500 mt-1">
          Informe as suas credenciais
        </p>
      </div>

      <!-- Mensagens de Erro / Alerta -->
      <?php if ($erro): ?>
        <div class="mb-5 rounded-lg bg-rose-100/90 text-rose-800 px-4 py-3 text-sm font-bold shadow-sm backdrop-blur-sm">
          <?= e($erro) ?>
        </div>
      <?php endif; ?>

      <?php if ($f = flash()): ?>
        <div class="mb-5 rounded-lg px-4 py-3 text-sm font-bold shadow-sm backdrop-blur-sm
          <?= $f['tipo']==='erro' ? 'bg-rose-100/90 text-rose-800'
              : ($f['tipo']==='sucesso' ? 'bg-emerald-100/90 text-emerald-800'
              : 'bg-brand-100/90 text-brand-800') ?>">
          <?= e($f['msg']) ?>
        </div>
      <?php endif; ?>

      <!-- Formulário -->
      <form method="post" class="space-y-6" autocomplete="off" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <!-- Campo Usuário -->
        <div>
          <label for="email" class="flex items-center gap-2 text-brand-700 font-bold mb-2 text-[0.95rem]">
            <!-- Ícone de Usuário -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
            Informe o seu login:
          </label>
          <input id="email" name="email" type="email" required autocomplete="off"
                 value="<?= e($email) ?>" placeholder=""
                 class="login-input">
        </div>

        <!-- Campo Senha -->
        <div>
          <label for="senha" class="flex items-center gap-2 text-brand-700 font-bold mb-2 text-[0.95rem]">
            <!-- Ícone de Cadeado -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            Informe sua senha:
          </label>
          <input id="senha" name="senha" type="password" required autocomplete="off"
                 placeholder=""
                 class="login-input">
        </div>

        <!-- Botão Login -->
        <button type="submit"
                class="w-full rounded-lg text-white font-extrabold text-lg py-3.5 transition shadow-lg hover:shadow-xl hover:-translate-y-0.5"
                style="background: linear-gradient(90deg, #004173 0%, #0cb7f2 100%);">
          Login
        </button>
      </form>

      <!-- Suporte -->
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
        <div class="mt-8 text-center">
          <a href="https://api.whatsapp.com/send?phone=<?= e($zap) ?>" target="_blank" rel="noopener"
             class="inline-flex items-center gap-2 text-sm font-semibold text-brand-700 hover:text-brand-900 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
              <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.573-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884"/>
            </svg>
            AST7 Suporte: <?= e($foneFmt) ?>
          </a>
        </div>
      <?php endif; ?>

    </div>
  </div>

</body>
</html>
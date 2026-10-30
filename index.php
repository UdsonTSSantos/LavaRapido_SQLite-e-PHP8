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
        // 1. Descobre a qual empresa (banco) esse e-mail pertence
        $tenant = function_exists('descobrir_tenant_por_email') ? descobrir_tenant_por_email($email) : null;

        if (function_exists('descobrir_tenant_por_email') && !$tenant) {
            $erro = 'Credenciais inválidas.';
        } else {
            // 2. "Chaveia" a sessão para apontar para o banco da empresa (se multi-tenant)
            if (function_exists('definir_tenant') && $tenant) {
                definir_tenant($tenant);
            }
            
            // 3. Autentica no banco correto
            $r = autenticar($email, $senha);

            if (!$r['ok']) {
                if (function_exists('definir_tenant')) definir_tenant(null);
                $erro = $r['erro'] ?? 'Credenciais inválidas.';
            } else {
                $u = $r['usuario'];
                
                session_regenerate_id(true);
                if (function_exists('definir_tenant') && $tenant) {
                    definir_tenant($tenant); // Restaura o tenant após regenerar a sessão
                }
                $_SESSION['usuario_id'] = (int)$u['id'];

                if (!empty($u['precisa_trocar_senha'])) {
                    flash('Primeiro acesso: é obrigatório definir uma nova senha.', 'info');
                    header('Location: trocar_senha.php');
                } else {
                    // Redirecionamento Inteligente
                    if ($u['is_admin']) {
                        header('Location: dashboard.php');
                    } else {
                        header('Location: agendamentos.php');
                    }
                }
                exit;
            }
        }
    }
}

try { $__emp = empresa(); $__logo = empresa_logo_url(); }
catch (Throwable $e) { $__emp = []; $__logo = null; }

$__nomeEmpresa = $__emp['nome_fantasia'] ?? '';
if ($__nomeEmpresa === '') $__nomeEmpresa = $__emp['razao_social'] ?? '';
if ($__nomeEmpresa === '') $__nomeEmpresa = 'Sistema';
$__celular = $__emp['celular'] ?? ($__emp['telefone'] ?? '');
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
  html, body { 
    font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; 
    height: 100%;
    margin: 0;
  }
  
  /* Fundo com a imagem do carro */
  .bg-login {
    background-image: url('./uploads/img/img_lavaRapido.WEBP');
    background-size: cover;
    background-position: center right;
    background-repeat: no-repeat;
    background-color: #3b82f6; /* Fallback azul */
  }

  /* Efeito Glassmorphism no Card */
  .glass-panel {
    background: rgba(255, 255, 255, 0.65); /* Branco translúcido */
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.8);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
  }

  /* Campos de input (Ajustados para negrito, preto e maiores) */
  .login-input {
    font-size: 1.15rem;
    font-weight: 700;
    color: #000000;
    background: #ffffff;
    border: 1.5px solid #cbd5e1;
    border-radius: 0.5rem;
    padding: 0.875rem 1rem;
    width: 100%;
    box-shadow: inset 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    transition: border-color .15s, box-shadow .15s;
  }
  .login-input::placeholder {
    color: #94a3b8;
    font-weight: 500;
  }
  .login-input:focus {
    outline: none;
    border-color: #0cb7f2;
    box-shadow: 0 0 0 3px rgba(12, 183, 242, 0.2);
  }
</style>
</head>
<body class="antialiased relative bg-login overflow-hidden flex items-center">

  <!-- Container Principal (Flex para alinhar à esquerda) -->
  <div class="w-full flex items-center min-h-screen relative z-20 px-4 sm:px-12 md:px-24">
    
    <!-- Card de Login -->
    <div class="glass-panel rounded-[20px] p-8 sm:p-10 w-full max-w-[380px]">
      
      <!-- Cabeçalho -->
      <div class="text-center mb-8">
        <h1 class="text-[1.6rem] font-extrabold text-brand-700 tracking-tight">
          Login de acesso
        </h1>
        <p class="text-[1.05rem] font-semibold text-brand-500 mt-1">
          Informe as suas credenciais
        </p>
      </div>

      <!-- Mensagens de Erro / Alerta -->
      <?php if ($erro): ?>
        <div class="mb-5 rounded-lg bg-rose-100 text-rose-800 px-4 py-3 text-sm font-bold shadow-sm">
          <?= e($erro) ?>
        </div>
      <?php endif; ?>

      <?php if ($f = flash()): ?>
        <div class="mb-5 rounded-lg px-4 py-3 text-sm font-bold shadow-sm
          <?= $f['tipo']==='erro' ? 'bg-rose-100 text-rose-800'
              : ($f['tipo']==='sucesso' ? 'bg-emerald-100 text-emerald-800'
              : 'bg-brand-100 text-brand-800') ?>">
          <?= e($f['msg']) ?>
        </div>
      <?php endif; ?>

      <!-- Formulário -->
      <form method="post" class="space-y-5" autocomplete="off" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <!-- Campo Usuário -->
        <div>
          <label for="email" class="flex items-center gap-2 text-brand-800 font-bold mb-1.5 text-[0.9rem]">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
          <label for="senha" class="flex items-center gap-2 text-brand-800 font-bold mb-1.5 text-[0.9rem]">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
        <div class="pt-2">
          <button type="submit"
                  class="w-full rounded-lg text-white font-bold text-lg py-3 transition shadow-md hover:shadow-lg hover:opacity-95"
                  style="background: linear-gradient(90deg, #0b6a9e 0%, #0cb7f2 100%);">
            Login
          </button>
        </div>
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
        <div class="mt-6 text-center">
          <a href="https://api.whatsapp.com/send?phone=<?= e($zap) ?>" target="_blank" rel="noopener"
             class="inline-flex items-center gap-2 text-xs font-semibold text-brand-800 hover:text-brand-900 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
              <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
            </svg>
            AST7 Suporte: <?= e($foneFmt) ?>
          </a>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- Rodapé Global -->
  <div class="absolute bottom-6 w-full text-center z-20">
    <p class="text-[0.85rem] text-slate-700 font-medium">
      <span class="text-blue-600 font-extrabold text-lg tracking-tighter mr-1">AST7</span> 
      © Copyright <?= date('Y') ?> - AST7 softwares - 
      <a href="http://www.ast7net.com.br" target="_blank" class="hover:underline">www.ast7net.com.br</a>
    </p>
  </div>

</body>
</html>
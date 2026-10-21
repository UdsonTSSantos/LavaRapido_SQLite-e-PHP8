<?php
/* ---------- Bloqueio de acesso direto ---------- */
if (basename($_SERVER['PHP_SELF'] ?? '') === basename(__FILE__)) {
    header('Location: index.php');
    exit;
}

/** @var string $titulo */
$titulo = $titulo ?? 'Sistema';
$u      = usuario_logado();

$__emp  = [];
$__logo = null;
try {
    $__emp  = empresa();
    $__logo = empresa_logo_url();
} catch (Throwable $e) {
    $__emp = []; $__logo = null;
}

$__nome = $__emp['nome_fantasia'] ?? '';
if ($__nome === '') $__nome = $__emp['razao_social'] ?? '';
if ($__nome === '') $__nome = 'Sistema';

$nomeTopo = '';
$atual    = basename($_SERVER['PHP_SELF'] ?? '');

if ($u) {
    $nomeTopo = $u['email'];
    try {
        $st = db()->prepare('SELECT nome FROM usuarios WHERE id = ?');
        $st->execute([(int)$_SESSION['usuario_id']]);
        $n = $st->fetchColumn();
        if ($n) $nomeTopo = $n;
    } catch (Throwable $e) { /* ignora */ }
}

function menu_cls(string $href, array $arquivos, string $atual): string {
    $ativo = ($atual === $href) || in_array($atual, $arquivos, true);
    return $ativo
        ? 'px-3.5 py-2 rounded-lg bg-white text-brand-700 font-bold shadow-sm'
        : 'px-3.5 py-2 rounded-lg text-white/85 hover:text-white hover:bg-white/15 transition font-medium';
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($titulo) ?> • <?= e($__nome) ?></title>
<?php if ($__logo): ?>
  <link rel="icon" href="<?= e($__logo) ?>">
  <link rel="apple-touch-icon" href="<?= e($__logo) ?>">
<?php endif; ?>

<script src="https://cdn.tailwindcss.com"></script>
<script>
/* =========================================================
 *  PALETA DO SISTEMA
 * ========================================================= */
tailwind.config = {
  theme: {
    extend: {
      colors: {
        brand: {
          50:  '#f0fbff',
          100: '#b6ffff',
          200: '#7cdaf9',
          300: '#4dc9f5',
          400: '#0cb7f2',
          500: '#0979b0',
          600: '#076a9c',
          700: '#004173',
          800: '#003256',
          900: '#002039',
        }
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif']
      },
      boxShadow: {
        card: '0 1px 2px rgba(0,65,115,.05), 0 8px 24px rgba(0,65,115,.06)',
        soft: '0 1px 3px rgba(0,65,115,.08)',
      }
    }
  }
};
</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
  :root {
    --brand-700: #004173;
    --brand-500: #0979b0;
    --brand-400: #0cb7f2;
    --brand-200: #7cdaf9;
    --brand-100: #b6ffff;
  }

  html, body {
    font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif;
  }

  /* =========================================================
   *  CAMPOS DE FORMULÁRIO — padrão global do sistema
   * ========================================================= */
  input:not([type="checkbox"]):not([type="radio"]):not([type="color"]):not([type="file"]):not([type="submit"]):not([type="button"]):not([type="hidden"]),
  select,
  textarea {
    font-size: 1rem;
    font-weight: 600;
    color: #000000;
    background-color: #ffffff;
    border: 1.5px solid #cbd5e1;
    border-radius: 0.625rem;
    padding: 0.7rem 0.875rem;
    transition: border-color .15s, box-shadow .15s, background-color .15s;
    width: 100%;
    line-height: 1.35;
  }

  input::placeholder,
  textarea::placeholder {
    color: #94a3b8;
    font-weight: 500;
  }

  input:focus,
  select:focus,
  textarea:focus {
    outline: none;
    border-color: var(--brand-400);
    box-shadow: 0 0 0 4px rgba(12, 183, 242, 0.18);
    background-color: #ffffff;
  }

  input:disabled,
  select:disabled,
  textarea:disabled {
    background-color: #f1f5f9;
    color: #64748b;
    cursor: not-allowed;
  }

  input[type="checkbox"],
  input[type="radio"] {
    width: 1.15rem;
    height: 1.15rem;
    accent-color: var(--brand-400);
    cursor: pointer;
    vertical-align: middle;
  }

  label {
    font-weight: 600;
    color: #0f172a;
  }

  /* Botões primários */
  .btn-primary {
    background-color: var(--brand-500);
    color: #fff;
    font-weight: 600;
    padding: 0.7rem 1.15rem;
    border-radius: 0.625rem;
    transition: background-color .15s, transform .05s;
    box-shadow: 0 1px 2px rgba(0,65,115,.15);
  }
  .btn-primary:hover { background-color: var(--brand-700); }

  /* Scrollbar discreta */
  ::-webkit-scrollbar { width: 10px; height: 10px; }
  ::-webkit-scrollbar-track { background: #f1f5f9; }
  ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
  ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
</head>
<body class="h-full bg-slate-50 text-slate-800 antialiased">

<?php if ($u): ?>
<nav class="sticky top-0 z-40 text-white shadow-lg"
     style="background: linear-gradient(135deg, #004173 0%, #0979b0 100%);">
  <div class="max-w-7xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
    
    <!-- Logo e Nome da Empresa -->
    <a href="<?= $u['is_admin'] ? 'dashboard.php' : 'agendamentos.php' ?>" class="flex items-center gap-3 font-bold tracking-tight text-lg">
      <?php if ($__logo): ?>
        <img src="<?= e($__logo) ?>" alt="Logo"
             class="h-9 w-9 object-contain bg-white/95 rounded-lg p-0.5 shadow-sm">
      <?php endif; ?>
      <span><?= e($__nome) ?></span>
    </a>

    <!-- Menu de Navegação -->
    <div class="flex items-center gap-1 text-sm flex-wrap">
      
      <!-- Dashboard (sempre visível) -->
      <a href="dashboard.php" class="<?= menu_cls('dashboard.php', ['dashboard_operacional.php'], $atual) ?>">Dashboard</a>

      <!-- Agendamentos -->
      <?php if (function_exists('tem_permissao') && tem_permissao('entradas')): ?>
        <a href="agendamentos.php" class="<?= menu_cls('agendamentos.php', ['agendamento_form.php'], $atual) ?>">Agendamentos</a>
      <?php endif; ?>

      <!-- Módulos operacionais -->
      <?php if (function_exists('tem_permissao') && tem_permissao('clientes')): ?>
        <a href="clientes.php" class="<?= menu_cls('clientes.php', ['cliente_form.php', 'cliente_historico.php'], $atual) ?>">Clientes</a>
      <?php endif; ?>

      <?php if (function_exists('tem_permissao') && tem_permissao('lavagens')): ?>
        <a href="lavagens.php" class="<?= menu_cls('lavagens.php', ['lavagem_form.php'], $atual) ?>">Lavagens</a>
      <?php endif; ?>

      <?php if (function_exists('tem_permissao') && tem_permissao('entradas')): ?>
        <a href="entradas.php" class="<?= menu_cls('entradas.php', ['entrada_lavagem.php', 'cupom_lavagem.php', 'recibo.php'], $atual) ?>">Entradas</a>
      <?php endif; ?>

      <?php if (function_exists('tem_permissao') && tem_permissao('estoque')): ?>
        <a href="estoque.php" class="<?= menu_cls('estoque.php', ['estoque_form.php', 'estoque_xml.php', 'estoque_relatorio.php', 'estoque_inventario.php'], $atual) ?>">Estoque</a>
      <?php endif; ?>

      <?php if (function_exists('tem_permissao') && tem_permissao('fornecedores')): ?>
        <a href="fornecedores.php" class="<?= menu_cls('fornecedores.php', ['fornecedor_form.php'], $atual) ?>">Fornecedores</a>
      <?php endif; ?>

      <?php if (function_exists('tem_permissao') && tem_permissao('pagamentos')): ?>
        <a href="pagamentos.php" class="<?= menu_cls('pagamentos.php', [
              'pagamento_form.php', 'dashboard_pagamentos.php',
              'relatorio_pagamentos.php', 'categorias_pagamento.php',
           ], $atual) ?>">Pagamentos</a>
      <?php endif; ?>

      <?php if (function_exists('tem_permissao') && tem_permissao('comissoes')): ?>
        <a href="comissoes.php" class="<?= menu_cls('comissoes.php', ['comissoes_recibo.php'], $atual) ?>">Comissões</a>
      <?php endif; ?>

      <?php if (function_exists('tem_permissao') && tem_permissao('administrativo')): ?>
        <a href="administrativo.php" class="<?= menu_cls('administrativo.php', [], $atual) ?>">Administrativo</a>
      <?php endif; ?>

      <!-- Área exclusiva do Administrador -->
      <?php if ($u['is_admin']): ?>
        <span class="mx-1.5 h-5 w-px bg-white/25 hidden md:inline-block"></span>
        <a href="usuarios.php" class="<?= menu_cls('usuarios.php', ['usuario_form.php','usuario_senha_gerada.php'], $atual) ?>">Usuários</a>
        <a href="empresa.php"  class="<?= menu_cls('empresa.php',  [], $atual) ?>">Empresa</a>
      <?php endif; ?>

      <!-- Sessão -->
      <span class="mx-1.5 h-5 w-px bg-white/25 hidden md:inline-block"></span>
      <span class="hidden md:inline text-white/90 font-medium pr-2">
        <?= e($nomeTopo) ?><?= $u['is_admin'] ? ' · admin' : '' ?>
      </span>
      <a href="trocar_senha.php" class="px-3.5 py-2 rounded-lg text-white/85 hover:text-white hover:bg-white/15 transition font-medium">Trocar senha</a>
      <a href="logout.php"       class="px-3.5 py-2 rounded-lg bg-rose-500 hover:bg-rose-600 text-white font-semibold transition shadow-sm">Sair</a>
    </div>
  </div>
</nav>
<?php endif; ?>

<main class="max-w-7xl mx-auto px-4 py-6 sm:py-8">

<?php if ($f = flash()): ?>
  <div class="fade-up mb-5 rounded-xl border px-4 py-3 text-sm font-medium shadow-sm
    <?= $f['tipo'] === 'erro' ? 'bg-rose-50 border-rose-200 text-rose-800'
        : ($f['tipo'] === 'sucesso' ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
        : 'bg-brand-50 border-brand-200 text-brand-700') ?>">
    <?= e($f['msg']) ?>
  </div>
<?php endif; ?>
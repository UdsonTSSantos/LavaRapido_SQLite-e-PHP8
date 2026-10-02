<?php


/* =========================================================
 *  GUARDA GLOBAL: se o header está sendo renderizado,
 *  o usuário PRECISA estar logado.
 * ========================================================= */
if (empty($_SESSION['usuario_id']) && basename($_SERVER['PHP_SELF'] ?? '') !== 'index.php') {
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
    $__emp  = [];
    $__logo = null;
}

$__nome = $__emp['nome_fantasia'] ?? '';
if ($__nome === '') $__nome = $__emp['razao_social'] ?? '';
if ($__nome === '') $__nome = 'Sistema';

/* Nome do usuário logado + item de menu ativo */
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

/** Helper: classe do link de menu conforme página atual */
function menu_cls(string $href, array $arquivos, string $atual): string {
    $ativo = ($atual === $href) || in_array($atual, $arquivos, true);
    return $ativo
        ? 'px-3 py-1.5 rounded bg-sky-600 text-white'
        : 'px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600';
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
</head>
<body class="h-full bg-slate-100 text-slate-800 antialiased">

<?php if ($u): ?>
<nav class="bg-slate-900 text-white">
  <div class="max-w-6xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
    <a href="dashboard.php" class="flex items-center gap-3 font-semibold tracking-wide">
      <?php if ($__logo): ?>
        <img src="<?= e($__logo) ?>" alt="Logo" class="h-8 w-8 object-contain bg-white rounded p-0.5">
      <?php endif; ?>
      <span><?= e($__nome) ?></span>
    </a>

    <div class="flex items-center gap-2 text-sm flex-wrap">

      <!-- Operacional (visível a todos) -->
      <a href="clientes.php"     class="<?= menu_cls('clientes.php',     ['cliente_form.php'], $atual) ?>">Clientes</a>
      <a href="lavagens.php"     class="<?= menu_cls('lavagens.php',     ['lavagem_form.php'], $atual) ?>">Lavagens</a>
      <a href="entradas.php"     class="<?= menu_cls('entradas.php',     ['entrada_lavagem.php'], $atual) ?>">Entradas</a>
      <a href="fornecedores.php" class="<?= menu_cls('fornecedores.php', ['fornecedor_form.php'], $atual) ?>">Fornecedores</a>
      <a href="pagamentos.php"   class="<?= menu_cls('pagamentos.php',   [
            'pagamento_form.php',
            'dashboard_pagamentos.php',
            'relatorio_pagamentos.php',
            'categorias_pagamento.php',
         ], $atual) ?>">Pagamentos</a>

      <!-- Administrativo -->
      <?php if ($u['is_admin']): ?>
        <a href="usuarios.php" class="<?= menu_cls('usuarios.php', ['usuario_form.php','usuario_senha_gerada.php'], $atual) ?>">Usuários</a>
        <a href="empresa.php"  class="<?= menu_cls('empresa.php',  [], $atual) ?>">Empresa</a>
      <?php endif; ?>

      <!-- Sessão -->
      <span class="hidden md:inline text-slate-300 ml-2">
        <?= e($nomeTopo) ?><?= $u['is_admin'] ? ' • admin' : '' ?>
      </span>
      <a href="trocar_senha.php" class="px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Trocar senha</a>
      <a href="logout.php"       class="px-3 py-1.5 rounded bg-rose-600 hover:bg-rose-500">Sair</a>
    </div>
  </div>
</nav>
<?php endif; ?>

<main class="max-w-6xl mx-auto px-4 py-6 sm:py-10">

<?php if ($f = flash()): ?>
  <div class="mb-4 rounded-md border px-4 py-3 text-sm
    <?= $f['tipo'] === 'erro' ? 'bg-rose-50 border-rose-200 text-rose-800'
        : ($f['tipo'] === 'sucesso' ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
        : 'bg-sky-50 border-sky-200 text-sky-800') ?>">
    <?= e($f['msg']) ?>
  </div>
<?php endif; ?>
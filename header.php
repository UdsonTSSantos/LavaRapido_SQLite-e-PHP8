<?php
/** @var string $titulo */
$titulo  = $titulo ?? 'Sistema';
$u       = usuario_logado();
$__emp   = empresa();
$__logo  = empresa_logo_url();
$__nome  = $__emp['nome_fantasia'] ?? '';
if ($__nome === '') $__nome = $__emp['razao_social'] ?? '';
if ($__nome === '') $__nome = 'Sistema';
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
<a href="clientes.php" class="px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Clientes</a>
</head>
<body class="h-full bg-slate-100 text-slate-800 antialiased">
<?php if ($u): ?>
<nav class="bg-slate-900 text-white">
  <div class="max-w-5xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
    <a href="dashboard.php" class="flex items-center gap-3 font-semibold tracking-wide">
      <?php if ($__logo): ?>
        <img src="<?= e($__logo) ?>" alt="Logo" class="h-8 w-8 object-contain bg-white rounded p-0.5">
      <?php endif; ?>
      <span><?= e($__nome) ?></span>
    </a>
    <div class="flex items-center gap-2 text-sm flex-wrap">
      <span class="hidden sm:inline text-slate-300"><?= e($u['email']) ?><?= $u['is_admin'] ? ' • admin' : '' ?></span>
      <?php if ($u['is_admin']): ?>
        <a href="empresa.php" class="px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Empresa</a>
        <a href="usuarios.php" class="px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Usuários</a>
        <a href="lavagens.php" class="px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Lavagens</a>
        <a href="entradas.php" class="px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Entradas</a>
      <?php endif; ?>
      <a href="trocar_senha.php" class="px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Trocar senha</a>
      <a href="logout.php" class="px-3 py-1.5 rounded bg-rose-600 hover:bg-rose-500">Sair</a>
    </div>
  </div>
</nav>
<?php endif; ?>
<main class="max-w-5xl mx-auto px-4 py-6 sm:py-10">
<?php if ($f = flash()): ?>
  <div class="mb-4 rounded-md border px-4 py-3 text-sm
    <?= $f['tipo'] === 'erro' ? 'bg-rose-50 border-rose-200 text-rose-800'
        : ($f['tipo'] === 'sucesso' ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
        : 'bg-sky-50 border-sky-200 text-sky-800') ?>">
    <?= e($f['msg']) ?>
  </div>
<?php endif; ?>
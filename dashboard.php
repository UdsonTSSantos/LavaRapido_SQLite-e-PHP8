<?php
require_once __DIR__ . '/config.php';
$u = exigir_login();
$titulo = 'Painel';
require __DIR__ . '/header.php';

$nomeUsuario = $u['email'];
try {
    $st = db()->prepare('SELECT nome FROM usuarios WHERE id = ?');
    $st->execute([(int)$_SESSION['usuario_id']]);
    $n = $st->fetchColumn();
    if ($n) $nomeUsuario = $n;
} catch (Throwable $e) {}

$primeiroNome = explode(' ', trim($nomeUsuario))[0] ?? $nomeUsuario;
$hora = (int)date('H');
$saudacao = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');
?>

<!-- ============ HERO ============ -->
<div class="fade-up relative overflow-hidden rounded-2xl text-white shadow-lg mb-8"
     style="background: linear-gradient(135deg, #004173 0%, #0979b0 60%, #0cb7f2 130%);">
  <div class="absolute -top-16 -right-16 w-72 h-72 rounded-full" style="background: rgba(182,255,255,.1)"></div>
  <div class="absolute -bottom-20 -left-10 w-64 h-64 rounded-full" style="background: rgba(255,255,255,.06)"></div>

  <div class="relative z-10 px-6 sm:px-8 py-8">
    <p class="text-white/80 text-sm font-medium"><?= e($saudacao) ?>,</p>
    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight mt-1"><?= e($primeiroNome) ?> 👋</h1>
    <p class="text-white/85 text-sm mt-3 max-w-xl">
      Bem-vindo(a) ao painel. Aqui você pode registrar entradas, gerenciar clientes, acompanhar pagamentos e muito mais.
    </p>

    <?php if ($u['is_admin']): ?>
      <span class="inline-flex items-center gap-1.5 mt-4 text-xs px-3 py-1.5 rounded-full bg-white/15 backdrop-blur-sm font-semibold">
        <span class="w-1.5 h-1.5 rounded-full bg-brand-100"></span>
        Administrador
      </span>
    <?php endif; ?>
  </div>
</div>

<?php if ($u['precisa_trocar_senha']): ?>
  <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3 text-sm font-medium">
    <strong>Primeiro acesso:</strong> é obrigatório
    <a href="trocar_senha.php" class="underline font-bold">definir uma nova senha</a>
    antes de continuar.
  </div>
<?php endif; ?>

<!-- ============ ATALHOS OPERACIONAIS ============ -->
<h2 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-3 px-1">Operação</h2>
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 mb-8">

  <?php
  $cards = [
    [
      'href'  => 'entrada_lavagem.php',
      'titulo'=> 'Nova entrada',
      'desc'  => 'Registrar veículo para lavagem.',
      'icon'  => '+',
      'bg'    => 'linear-gradient(135deg,#0979b0,#0cb7f2)',
    ],
    [
      'href'  => 'entradas.php',
      'titulo'=> 'Entradas',
      'desc'  => 'Status, pagamentos e recibos.',
      'icon'  => '🚗',
      'bg'    => 'linear-gradient(135deg,#004173,#0979b0)',
    ],
    [
      'href'  => 'clientes.php',
      'titulo'=> 'Clientes',
      'desc'  => 'Cadastro, busca e edição.',
      'icon'  => '👤',
      'bg'    => 'linear-gradient(135deg,#4dc9f5,#0cb7f2)',
    ],
    [
      'href'  => 'lavagens.php',
      'titulo'=> 'Tipos de lavagem',
      'desc'  => 'Serviços, preços e duração.',
      'icon'  => '🧼',
      'bg'    => 'linear-gradient(135deg,#0979b0,#7cdaf9)',
    ],
    [
      'href'  => 'fornecedores.php',
      'titulo'=> 'Fornecedores',
      'desc'  => 'Cadastro e dados bancários.',
      'icon'  => '📦',
      'bg'    => 'linear-gradient(135deg,#004173,#4dc9f5)',
    ],
    [
      'href'  => 'pagamentos.php',
      'titulo'=> 'Pagamentos',
      'desc'  => 'Contas a pagar e alertas.',
      'icon'  => '💸',
      'bg'    => 'linear-gradient(135deg,#0cb7f2,#b6ffff)',
    ],
    [
      'href'  => 'administrativo.php',
      'titulo'=> 'Administrativo',
      'desc'  => 'Receitas x despesas pagas + impressão.',
      'icon'  => '📒',
      'bg'    => 'linear-gradient(135deg,#004173,#0979b0)',
],
  ];

  foreach ($cards as $c): ?>
    <a href="<?= e($c['href']) ?>"
       class="fade-up group block rounded-2xl bg-white p-5 border border-slate-200/80 shadow-card hover:shadow-lg hover:-translate-y-0.5 transition-all">
      <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-xl text-white flex items-center justify-center text-xl font-bold shadow-sm flex-shrink-0"
             style="background: <?= e($c['bg']) ?>;">
          <?= e($c['icon']) ?>
        </div>
        <div class="min-w-0 flex-1">
          <div class="font-bold text-slate-900 group-hover:text-brand-700 transition"><?= e($c['titulo']) ?></div>
          <div class="text-xs text-slate-500 mt-1"><?= e($c['desc']) ?></div>
        </div>
        <div class="text-slate-300 group-hover:text-brand-400 transition text-lg">→</div>
      </div>
    </a>
  <?php endforeach; ?>

</div>

<?php if ($u['is_admin']): ?>
  <!-- ============ ADMINISTRAÇÃO ============ -->
  <h2 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-3 px-1">Administração</h2>
  <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 mb-8">

    <a href="usuarios.php"
       class="fade-up group block rounded-2xl bg-white p-5 border border-slate-200/80 shadow-card hover:shadow-lg hover:-translate-y-0.5 transition-all">
      <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-xl font-bold flex-shrink-0">
          👥
        </div>
        <div class="min-w-0 flex-1">
          <div class="font-bold text-slate-900 group-hover:text-brand-700 transition">Usuários</div>
          <div class="text-xs text-slate-500 mt-1">Criar, editar e gerar senha.</div>
        </div>
      </div>
    </a>

    <a href="empresa.php"
       class="fade-up group block rounded-2xl bg-white p-5 border border-slate-200/80 shadow-card hover:shadow-lg hover:-translate-y-0.5 transition-all">
      <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-xl font-bold flex-shrink-0">
          🏢
        </div>
        <div class="min-w-0 flex-1">
          <div class="font-bold text-slate-900 group-hover:text-brand-700 transition">Empresa</div>
          <div class="text-xs text-slate-500 mt-1">Logo, CNPJ, endereço e contatos.</div>
        </div>
      </div>
    </a>

    <a href="dashboard_pagamentos.php"
       class="fade-up group block rounded-2xl bg-white p-5 border border-slate-200/80 shadow-card hover:shadow-lg hover:-translate-y-0.5 transition-all">
      <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-xl font-bold flex-shrink-0">
          📊
        </div>
        <div class="min-w-0 flex-1">
          <div class="font-bold text-slate-900 group-hover:text-brand-700 transition">Dashboard financeiro</div>
          <div class="text-xs text-slate-500 mt-1">Gráficos e indicadores.</div>
        </div>
      </div>
    </a>

  </div>
<?php endif; ?>

<!-- ============ ALERTAS DE PAGAMENTO ============ -->
<?php require __DIR__ . '/_alertas_pagamentos.php'; ?>

<?php require __DIR__ . '/footer.php'; ?>
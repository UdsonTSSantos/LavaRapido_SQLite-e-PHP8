<?php
require_once __DIR__ . '/config.php';
$u = exigir_login();
$titulo = 'Painel';
require __DIR__ . '/header.php';
?>

<div class="bg-white rounded-xl shadow p-6 sm:p-8">
  <h1 class="text-2xl font-semibold mb-2">Bem-vindo(a)</h1>
  <p class="text-slate-600">
    Você está autenticado como <strong><?= e($u['email']) ?></strong>.
    <?php if ($u['is_admin']): ?>
      <span class="inline-block text-xs px-2 py-0.5 rounded bg-sky-100 text-sky-700 ml-1">Administrador</span>
    <?php endif; ?>
  </p>

  <?php if ($u['precisa_trocar_senha']): ?>
    <div class="mt-5 rounded-md border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3 text-sm">
      <strong>Primeiro acesso:</strong> é obrigatório
      <a href="trocar_senha.php" class="underline font-medium">definir uma nova senha</a>
      antes de continuar.
    </div>
  <?php endif; ?>
</div>

<!-- ============ ATALHOS OPERACIONAIS (todos os usuários) ============ -->
<h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mt-8 mb-3 px-1">
  Operação
</h2>
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">

  <a href="entrada_lavagem.php"
     class="block rounded-lg border border-slate-200 bg-white p-5 hover:border-sky-400 hover:bg-sky-50 transition shadow-sm">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-lg">+</div>
      <div>
        <div class="font-semibold">Nova entrada</div>
        <div class="text-xs text-slate-500">Registrar veículo para lavagem.</div>
      </div>
    </div>
  </a>

  <a href="entradas.php"
     class="block rounded-lg border border-slate-200 bg-white p-5 hover:border-sky-400 hover:bg-sky-50 transition shadow-sm">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-lg bg-sky-100 text-sky-700 flex items-center justify-center font-bold text-lg">🚗</div>
      <div>
        <div class="font-semibold">Entradas de lavagem</div>
        <div class="text-xs text-slate-500">Status, pagamentos e recibos.</div>
      </div>
    </div>
  </a>

  <a href="clientes.php"
     class="block rounded-lg border border-slate-200 bg-white p-5 hover:border-sky-400 hover:bg-sky-50 transition shadow-sm">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-lg">👤</div>
      <div>
        <div class="font-semibold">Clientes</div>
        <div class="text-xs text-slate-500">Cadastro, busca e edição.</div>
      </div>
    </div>
  </a>

  <a href="lavagens.php"
     class="block rounded-lg border border-slate-200 bg-white p-5 hover:border-sky-400 hover:bg-sky-50 transition shadow-sm">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-lg bg-cyan-100 text-cyan-700 flex items-center justify-center font-bold text-lg">🧼</div>
      <div>
        <div class="font-semibold">Tipos de lavagem</div>
        <div class="text-xs text-slate-500">Serviços, preços e duração.</div>
      </div>
    </div>
  </a>

  <a href="fornecedores.php"
     class="block rounded-lg border border-slate-200 bg-white p-5 hover:border-sky-400 hover:bg-sky-50 transition shadow-sm">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-lg">📦</div>
      <div>
        <div class="font-semibold">Fornecedores</div>
        <div class="text-xs text-slate-500">Cadastro e dados bancários.</div>
      </div>
    </div>
  </a>

  <a href="relatorio.php"
     class="block rounded-lg border border-slate-200 bg-white p-5 hover:border-sky-400 hover:bg-sky-50 transition shadow-sm">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center font-bold text-lg">📄</div>
      <div>
        <div class="font-semibold">Relatórios</div>
        <div class="text-xs text-slate-500">Exemplo de relatório com logo.</div>
      </div>
    </div>
  </a>

</div>

<?php if ($u['is_admin']): ?>
  <!-- ============ ADMINISTRAÇÃO ============ -->
  <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mt-8 mb-3 px-1">
    Administração
  </h2>
  <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">

    <a href="usuarios.php"
       class="block rounded-lg border border-slate-200 bg-white p-5 hover:border-sky-400 hover:bg-sky-50 transition shadow-sm">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-slate-200 text-slate-700 flex items-center justify-center font-bold text-lg">👥</div>
        <div>
          <div class="font-semibold">Usuários</div>
          <div class="text-xs text-slate-500">Criar, editar, gerar senha.</div>
        </div>
      </div>
    </a>

    <a href="empresa.php"
       class="block rounded-lg border border-slate-200 bg-white p-5 hover:border-sky-400 hover:bg-sky-50 transition shadow-sm">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-slate-200 text-slate-700 flex items-center justify-center font-bold text-lg">🏢</div>
        <div>
          <div class="font-semibold">Cadastro da empresa</div>
          <div class="text-xs text-slate-500">Logo, CNPJ e contatos.</div>
        </div>
      </div>
    </a>

  </div>
<?php endif; ?>

<?php require __DIR__ . '/_alertas_pagamentos.php'; ?>

<?php require __DIR__ . '/footer.php'; ?>
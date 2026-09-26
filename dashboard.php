<?php
require_once __DIR__ . '/config.php';
$u = exigir_login();
$titulo = 'Painel';
require __DIR__ . '/header.php';
?>

<div class="bg-white rounded-xl shadow p-6 sm:p-8">
  <h1 class="text-2xl font-semibold mb-2">Bem-vindo(a)</h1>
  <p class="text-slate-600">Você está autenticado como <strong><?= e($u['email']) ?></strong>.</p>

  <?php if ($u['is_admin']): ?>
    <div class="mt-6 grid gap-3 sm:grid-cols-3">
      <a href="empresa.php" class="block rounded-lg border border-slate-200 p-4 hover:border-sky-400 hover:bg-sky-50 transition">
        <div class="font-medium">Cadastro da Empresa</div>
        <div class="text-xs text-slate-500 mt-1">Logo, CNPJ, endereço e contatos.</div>
      </a>
      <a href="usuarios.php" class="block rounded-lg border border-slate-200 p-4 hover:border-sky-400 hover:bg-sky-50 transition">
        <div class="font-medium">Usuários</div>
        <div class="text-xs text-slate-500 mt-1">Criar, desativar e redefinir senhas.</div>
      </a>
      <a href="relatorio.php" class="block rounded-lg border border-slate-200 p-4 hover:border-sky-400 hover:bg-sky-50 transition">
        <div class="font-medium">Relatório de exemplo</div>
        <div class="text-xs text-slate-500 mt-1">Demonstra o uso do logo no cabeçalho.</div>
      </a>
      <a href="clientes.php" class="block rounded-lg border border-slate-200 p-4 hover:border-sky-400 hover:bg-sky-50 transition">
        <div class="font-medium">Clientes</div>
        <div class="text-xs text-slate-500 mt-1">Cadastro, busca e edição de clientes.</div>
      </a>
      <a href="entrada_lavagem.php" class="block rounded-lg border border-slate-200 p-4 hover:border-sky-400 hover:bg-sky-50 transition">
        <div class="font-medium">Nova entrada de lavagem</div>
        <div class="text-xs text-slate-500 mt-1">Registrar veículo, serviços e pagamento.</div>
      </a>
      <a href="entradas.php" class="block rounded-lg border border-slate-200 p-4 hover:border-sky-400 hover:bg-sky-50 transition">
        <div class="font-medium">Entradas de lavagem</div>
        <div class="text-xs text-slate-500 mt-1">Listagem, status e pagamentos.</div>
      </a>
      <a href="fornecedores.php" class="block rounded-lg border border-slate-200 p-4 hover:border-sky-400 hover:bg-sky-50 transition">
        <div class="font-medium">Fornecedores</div>
        <div class="text-xs text-slate-500 mt-1">Cadastro, busca e edição de fornecedores.</div>
      </a>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/footer.php'; ?>
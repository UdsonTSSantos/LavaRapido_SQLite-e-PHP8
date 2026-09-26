<?php
require_once __DIR__ . '/config.php';
exigir_login();

$emp  = empresa();
$logo = empresa_logo_url();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Relatório • <?= e($emp['nome_fantasia'] ?: $emp['razao_social']) ?></title>
<?php if ($logo): ?><link rel="icon" href="<?= e($logo) ?>"><?php endif; ?>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  @media print {
    .no-print { display: none !important; }
    body { background: #fff !important; }
  }
</style>
</head>
<body class="bg-slate-100 p-4 sm:p-8 text-slate-800">

<div class="max-w-3xl mx-auto bg-white shadow rounded-xl p-6 sm:p-8">

  <!-- Cabeçalho do relatório com logo -->
  <header class="flex items-center gap-4 border-b border-slate-200 pb-4 mb-6">
    <?php if ($logo): ?>
      <img src="<?= e($logo) ?>" alt="Logo" class="h-20 w-20 object-contain">
    <?php endif; ?>
    <div class="flex-1">
      <div class="text-lg font-semibold"><?= e($emp['razao_social'] ?: 'Empresa') ?></div>
      <?php if (!empty($emp['nome_fantasia'])): ?>
        <div class="text-sm text-slate-600"><?= e($emp['nome_fantasia']) ?></div>
      <?php endif; ?>
      <div class="text-xs text-slate-500 mt-1">
        <?php if (!empty($emp['cnpj'])): ?>CNPJ: <?= e(formatar_cnpj($emp['cnpj'])) ?> • <?php endif; ?>
        <?php if (!empty($emp['telefone'])): ?>Tel: <?= e($emp['telefone']) ?> • <?php endif; ?>
        <?php if (!empty($emp['celular'])): ?>Cel: <?= e($emp['celular']) ?> • <?php endif; ?>
        <?php if (!empty($emp['email'])): ?><?= e($emp['email']) ?><?php endif; ?>
      </div>
      <?php if (!empty($emp['endereco'])): ?>
        <div class="text-xs text-slate-500">
          <?= e($emp['endereco']) ?><?= !empty($emp['numero']) ? ', ' . e($emp['numero']) : '' ?>
          <?= !empty($emp['bairro']) ? ' — ' . e($emp['bairro']) : '' ?>
          <?= !empty($emp['cidade']) ? ' — ' . e($emp['cidade']) : '' ?>
          <?= !empty($emp['uf']) ? '/' . e($emp['uf']) : '' ?>
          <?= !empty($emp['cep']) ? ' • CEP: ' . e($emp['cep']) : '' ?>
        </div>
      <?php endif; ?>
    </div>
  </header>

  <h1 class="text-xl font-semibold mb-4">Relatório de exemplo</h1>
  <p class="text-sm text-slate-600">
    Este é um exemplo de como o logo e os dados da empresa aparecem no cabeçalho
    dos relatórios. Ao imprimir, o cabeçalho é mantido e os botões são ocultados.
  </p>

  <table class="mt-6 w-full text-sm border border-slate-200">
    <thead class="bg-slate-50">
      <tr><th class="text-left px-3 py-2">Item</th><th class="text-right px-3 py-2">Valor</th></tr>
    </thead>
    <tbody>
      <tr class="border-t"><td class="px-3 py-2">Exemplo 1</td><td class="px-3 py-2 text-right">R$ 100,00</td></tr>
      <tr class="border-t"><td class="px-3 py-2">Exemplo 2</td><td class="px-3 py-2 text-right">R$ 250,00</td></tr>
      <tr class="border-t font-semibold"><td class="px-3 py-2">Total</td><td class="px-3 py-2 text-right">R$ 350,00</td></tr>
    </tbody>
  </table>

  <footer class="mt-8 pt-4 border-t border-slate-200 text-xs text-slate-500 text-center">
    Emitido em <?= date('d/m/Y H:i') ?>
  </footer>

  <div class="no-print mt-6 flex justify-end gap-2">
    <a href="dashboard.php" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-sm">Voltar</a>
    <button onclick="window.print()" class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-500 text-white text-sm">Imprimir</button>
  </div>
</div>

</body>
</html>
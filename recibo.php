<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_entradas();

$entrada_id = (int)($_GET['entrada'] ?? 0);
$recibo_num = trim((string)($_GET['recibo'] ?? ''));

if ($entrada_id <= 0) {
    flash('Entrada inválida.', 'erro');
    header('Location: entradas.php');
    exit;
}

/* Entrada */
$st = db()->prepare("
    SELECT e.*, c.nome AS cliente_nome, c.celular AS cliente_celular,
           c.cpf_cnpj AS cliente_doc, c.tipo AS cliente_tipo
    FROM lavagem_entradas e
    LEFT JOIN clientes c ON c.id = e.cliente_id
    WHERE e.id = ?
");
$st->execute([$entrada_id]);
$entrada = $st->fetch();
if (!$entrada) {
    flash('Entrada não encontrada.', 'erro');
    header('Location: entradas.php');
    exit;
}

/* Itens */
$st = db()->prepare('SELECT * FROM lavagem_itens WHERE entrada_id = ? ORDER BY id');
$st->execute([$entrada_id]);
$itens = $st->fetchAll();

/* Pagamento (o mais recente; se recibo_num foi passado, pega esse) */
if ($recibo_num !== '') {
    $st = db()->prepare('SELECT * FROM pagamentos WHERE entrada_id = ? AND recibo_numero = ? LIMIT 1');
    $st->execute([$entrada_id, $recibo_num]);
} else {
    $st = db()->prepare('SELECT * FROM pagamentos WHERE entrada_id = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$entrada_id]);
}
$pagamento = $st->fetch();

if (!$pagamento) {
    flash('Nenhum pagamento registrado para esta entrada.', 'erro');
    header('Location: entradas.php');
    exit;
}

$emp  = empresa();
$logo = empresa_logo_url();
$formas = formas_pagamento();
$forma_label = $formas[$pagamento['forma_pagamento']] ?? $pagamento['forma_pagamento'];

/* ---------- Texto do recibo (para WhatsApp) ---------- */
$linhas = [];
$linhas[] = ($emp['nome_fantasia'] ?: $emp['razao_social']);
if (!empty($emp['cnpj']))        $linhas[] = 'CNPJ: ' . formatar_cnpj($emp['cnpj']);
if (!empty($emp['telefone']))    $linhas[] = 'Tel: ' . $emp['telefone'];
if (!empty($emp['endereco'])) {
    $end = $emp['endereco'];
    if (!empty($emp['numero'])) $end .= ', ' . $emp['numero'];
    if (!empty($emp['bairro'])) $end .= ' — ' . $emp['bairro'];
    $linhas[] = $end;
}
$linhas[] = '';
$linhas[] = 'RECIBO ' . $pagamento['recibo_numero'];
$linhas[] = 'Data: ' . date('d/m/Y', strtotime($pagamento['data_pagamento']));
$linhas[] = 'Cliente: ' . ($entrada['cliente_nome'] ?? '—');
$linhas[] = 'Placa: ' . formatar_placa($entrada['placa']);
$linhas[] = '';
$linhas[] = 'Serviços:';
foreach ($itens as $it) {
    $linhas[] = '• ' . $it['nome'] . ' — ' . centavos_para_moeda_brl((int)$it['preco_centavos']);
}
$linhas[] = '';
$linhas[] = 'Subtotal: ' . centavos_para_moeda_brl((int)$entrada['subtotal_centavos']);
if ((int)$entrada['desconto_centavos'] > 0) {
    $linhas[] = 'Desconto: -' . centavos_para_moeda_brl((int)$entrada['desconto_centavos']);
}
$linhas[] = 'TOTAL: ' . centavos_para_moeda_brl((int)$entrada['total_centavos']);
$linhas[] = 'Forma: ' . $forma_label;
$linhas[] = '';
$linhas[] = 'Obrigado pela preferência!';
$texto_whatsapp = implode("\n", $linhas);

/* Telefone para WhatsApp (celular do cliente, só dígitos, com 55) */
$telWhats = '';
if (!empty($entrada['cliente_celular'])) {
    $telWhats = preg_replace('/\D/', '', $entrada['cliente_celular']);
    if ($telWhats !== '' && !str_starts_with($telWhats, '55')) {
        $telWhats = '55' . $telWhats;
    }
}
$whatsUrl = 'https://api.whatsapp.com/send?'
          . ($telWhats ? 'phone=' . $telWhats . '&' : '')
          . 'text=' . rawurlencode($texto_whatsapp);

/* ---------- Layout do recibo ---------- */
$titulo = 'Recibo ' . $pagamento['recibo_numero'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title><?= e($titulo) ?></title>
<?php if ($logo): ?><link rel="icon" href="<?= e($logo) ?>"><?php endif; ?>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  body { background: #f1f5f9; }
  .recibo { width: 80mm; max-width: 100%; margin: 0 auto; background: #fff; padding: 8mm 6mm; }
  .recibo * { font-size: 11px; line-height: 1.35; }
  .recibo h1 { font-size: 15px; }
  .recibo h2 { font-size: 13px; }
  .recibo .linha { border-top: 1px dashed #94a3b8; margin: 6px 0; }
  .recibo .tot  { font-size: 13px; font-weight: 700; }

  @media print {
    @page { size: 80mm auto; margin: 0; }
    html, body { background: #fff !important; }
    .no-print { display: none !important; }
    .recibo { width: 80mm; margin: 0; padding: 4mm 3mm; box-shadow: none !important; border: 0 !important; }
    .recibo * { font-size: 11px; }
  }
</style>
</head>
<body class="py-6">

<div class="no-print max-w-md mx-auto mb-4 px-4 flex flex-wrap gap-2 justify-center">
  <a href="entradas.php" class="px-4 py-2 rounded-lg bg-slate-200 hover:bg-slate-300 text-sm">← Voltar</a>
  <button onclick="window.print()" class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-500 text-white text-sm">Imprimir (80 mm)</button>
  <a href="<?= e($whatsUrl) ?>" target="_blank" rel="noopener"
     class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm">
    Enviar no WhatsApp
  </a>
  <button id="btnCopiar" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-white text-sm">
    Copiar texto
  </button>
</div>

<div class="recibo shadow rounded">
  <!-- Cabeçalho -->
  <div class="text-center">
    <?php if ($logo): ?>
      <img src="<?= e($logo) ?>" alt="Logo" style="max-height: 22mm; margin: 0 auto 4px;">
    <?php endif; ?>
    <h1 class="font-bold"><?= e($emp['nome_fantasia'] ?: $emp['razao_social']) ?></h1>
    <?php if (!empty($emp['cnpj'])): ?>
      <div>CNPJ: <?= e(formatar_cnpj($emp['cnpj'])) ?></div>
    <?php endif; ?>
    <?php if (!empty($emp['endereco'])): ?>
      <div><?= e($emp['endereco']) ?><?= $emp['numero'] ? ', ' . e($emp['numero']) : '' ?></div>
      <div><?= e($emp['bairro']) ?><?= $emp['cidade'] ? ' — ' . e($emp['cidade']) : '' ?><?= $emp['uf'] ? '/' . e($emp['uf']) : '' ?></div>
    <?php endif; ?>
    <?php if (!empty($emp['telefone']) || !empty($emp['celular'])): ?>
      <div>Tel: <?= e($emp['celular'] ?: $emp['telefone']) ?></div>
    <?php endif; ?>
  </div>

  <div class="linha"></div>

  <!-- Identificação do recibo -->
  <div class="text-center">
    <h2 class="font-bold">RECIBO DE PAGAMENTO</h2>
    <div><strong><?= e($pagamento['recibo_numero']) ?></strong></div>
  </div>

  <div class="linha"></div>

  <!-- Dados -->
  <table class="w-full">
    <tr><td class="align-top pr-2">Data:</td><td class="text-right"><?= e(date('d/m/Y', strtotime($pagamento['data_pagamento']))) ?></td></tr>
    <tr><td class="align-top pr-2">Cliente:</td><td class="text-right"><?= e($entrada['cliente_nome'] ?? '—') ?></td></tr>
    <tr><td class="align-top pr-2">Placa:</td><td class="text-right font-mono"><?= e(formatar_placa($entrada['placa'])) ?></td></tr>
    <tr><td class="align-top pr-2">Forma:</td><td class="text-right"><?= e($forma_label) ?></td></tr>
  </table>

  <div class="linha"></div>

  <!-- Itens -->
  <table class="w-full">
    <thead>
      <tr>
        <th class="text-left">Serviço</th>
        <th class="text-right">Valor</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($itens as $it): ?>
        <tr>
          <td class="align-top pr-2"><?= e($it['nome']) ?></td>
          <td class="text-right whitespace-nowrap"><?= e(centavos_para_moeda((int)$it['preco_centavos'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="linha"></div>

  <!-- Totais -->
  <table class="w-full">
    <tr><td>Subtotal</td><td class="text-right"><?= e(centavos_para_moeda((int)$entrada['subtotal_centavos'])) ?></td></tr>
    <?php if ((int)$entrada['desconto_centavos'] > 0): ?>
      <tr><td>Desconto</td><td class="text-right">- <?= e(centavos_para_moeda((int)$entrada['desconto_centavos'])) ?></td></tr>
    <?php endif; ?>
    <tr class="tot"><td>TOTAL</td><td class="text-right"><?= e(centavos_para_moeda_brl((int)$entrada['total_centavos'])) ?></td></tr>
  </table>

  <div class="linha"></div>

  <!-- Rodapé -->
  <div class="text-center">
    <div>Obrigado pela preferência!</div>
    <div style="margin-top:4px;">Emitido em <?= e(date('d/m/Y H:i')) ?></div>
    <div style="margin-top:6px; font-size: 10px; color:#64748b;">
      Documento não fiscal — Recibo simples
    </div>
  </div>
</div>

<script>
document.getElementById('btnCopiar').addEventListener('click', async () => {
  try {
    await navigator.clipboard.writeText(<?= json_encode($texto_whatsapp, JSON_UNESCAPED_UNICODE) ?>);
    alert('Texto copiado!');
  } catch (e) {
    alert('Não foi possível copiar. Selecione manualmente.');
  }
});
</script>
</body>
</html>
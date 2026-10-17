<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_entradas();
ensure_lavagem_veiculos();

$entradaId = (int)($_GET['entrada'] ?? 0);
if ($entradaId <= 0) {
    flash('Entrada inválida.', 'erro');
    header('Location: entradas.php');
    exit;
}

$st = db()->prepare("
    SELECT e.*, c.nome AS cliente_nome_cadastro, c.celular AS cliente_celular_cad,
           c.cpf_cnpj AS cliente_doc, c.tipo AS cliente_tipo
    FROM lavagem_entradas e
    LEFT JOIN clientes c ON c.id = e.cliente_id
    WHERE e.id = ?
");
$st->execute([$entradaId]);
$entrada = $st->fetch();
if (!$entrada) {
    flash('Entrada não encontrada.', 'erro');
    header('Location: entradas.php');
    exit;
}

$veiculos = listar_veiculos_entrada($entradaId);

/* Itens por veículo */
foreach ($veiculos as &$vv) {
    $sti = db()->prepare('SELECT nome, preco_centavos FROM lavagem_itens WHERE veiculo_id = ? ORDER BY id');
    $sti->execute([(int)$vv['id']]);
    $vv['itens'] = $sti->fetchAll();
}
unset($vv);

$emp  = empresa();
$logo = empresa_logo_url();

$nomeCliente = $entrada['cliente_nome_cadastro'] ?: ($entrada['cliente_nome_avulso'] ?: '—');
$telCliente  = $entrada['cliente_celular_cad'] ?: ($entrada['cliente_celular'] ?? '');

$autoPrint = !empty($_GET['auto']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Cupom #<?= (int)$entrada['id'] ?></title>
<?php if ($logo): ?><link rel="icon" href="<?= e($logo) ?>"><?php endif; ?>
<style>
  * { box-sizing: border-box; }
  body {
    margin: 0;
    padding: 0;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    color: #000;
    background: #f1f5f9;
  }
  .cupom {
    width: 80mm;
    margin: 12px auto;
    padding: 4mm 3mm;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,.1);
  }
  .center { text-align: center; }
  .right  { text-align: right; }
  .bold   { font-weight: bold; }
  .logo   { max-width: 100%; max-height: 20mm; margin: 0 auto 4px; display: block; }
  h1 { font-size: 15px; margin: 0 0 2px; }
  h2 { font-size: 12px; margin: 6px 0 2px; text-transform: uppercase; }
  .linha  { border-top: 1px dashed #000; margin: 6px 0; }
  .linha2 { border-top: 1px solid #000; margin: 6px 0; }
  table   { width: 100%; border-collapse: collapse; font-size: 12px; }
  td      { vertical-align: top; padding: 1px 0; }
  .veiculo-box { margin: 6px 0; }
  .veiculo-titulo { font-weight: bold; font-size: 13px; text-transform: uppercase; border-bottom: 1px solid #000; padding-bottom: 2px; margin-bottom: 3px; }
  .item-row td:first-child { padding-right: 6px; }
  .total-linha td { font-size: 14px; font-weight: bold; padding-top: 4px; border-top: 1px solid #000; }
  .footer { font-size: 10px; margin-top: 8px; }

  /* Botões de ação (só na tela) */
  .no-print {
    max-width: 80mm;
    margin: 12px auto 0;
    display: flex;
    gap: 6px;
    justify-content: center;
  }
  .no-print button, .no-print a {
    padding: 8px 14px;
    border: 1px solid #cbd5e1;
    background: #fff;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    color: #1e293b;
  }
  .no-print .primary {
    background: #0979b0;
    border-color: #0979b0;
    color: #fff;
  }

  @media print {
    @page { size: 80mm auto; margin: 0; }
    html, body { background: #fff !important; }
    .cupom { box-shadow: none; margin: 0; padding: 2mm 2mm; }
    .no-print { display: none !important; }
  }
</style>
</head>
<body<?= $autoPrint ? ' onload="setTimeout(function(){window.print();},250);"' : '' ?>>

<div class="no-print">
  <button type="button" class="primary" onclick="window.print()">🖨️ Imprimir</button>
  <a href="entradas.php">← Voltar</a>
</div>

<div class="cupom">

  <!-- ============ CABEÇALHO ============ -->
  <div class="center">
    <?php if ($logo): ?>
      <img src="<?= e($logo) ?>" alt="Logo" class="logo">
    <?php endif; ?>
    <h1><?= e($emp['nome_fantasia'] ?: ($emp['razao_social'] ?: 'Lava Rápido')) ?></h1>
    <?php if (!empty($emp['cnpj'])): ?>
      <div>CNPJ: <?= e(formatar_cnpj($emp['cnpj'])) ?></div>
    <?php endif; ?>
    <?php if (!empty($emp['endereco'])): ?>
      <div><?= e($emp['endereco']) ?><?= $emp['numero'] ? ', ' . e($emp['numero']) : '' ?></div>
    <?php endif; ?>
    <?php if (!empty($emp['bairro']) || !empty($emp['cidade'])): ?>
      <div>
        <?= e($emp['bairro']) ?><?= $emp['cidade'] ? ' — ' . e($emp['cidade']) : '' ?><?= $emp['uf'] ? '/' . e($emp['uf']) : '' ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($emp['celular']) || !empty($emp['telefone'])): ?>
      <div>Tel: <?= e($emp['celular'] ?: $emp['telefone']) ?></div>
    <?php endif; ?>
  </div>

  <div class="linha2"></div>

  <div class="center bold" style="font-size: 13px;">COMANDA DE LAVAGEM</div>
  <div class="center">Nº <?= str_pad((string)$entrada['id'], 6, '0', STR_PAD_LEFT) ?></div>

  <div class="linha"></div>

  <!-- ============ DADOS DA COMANDA ============ -->
  <table>
    <tr><td>Data:</td><td class="right"><?= e(date('d/m/Y', strtotime($entrada['data_entrada']))) ?></td></tr>
    <tr><td>Hora:</td><td class="right"><?= e(substr($entrada['hora_entrada'], 0, 5)) ?></td></tr>
    <?php if (!empty($entrada['previsao_saida_datetime'])): ?>
      <tr><td>Prev. saída:</td><td class="right"><?= e(date('d/m H:i', strtotime($entrada['previsao_saida_datetime']))) ?></td></tr>
    <?php endif; ?>
    <tr><td>Cliente:</td><td class="right"><?= e($nomeCliente) ?></td></tr>
    <?php if ($telCliente): ?>
      <tr><td>Telefone:</td><td class="right"><?= e($telCliente) ?></td></tr>
    <?php endif; ?>
  </table>

  <div class="linha2"></div>

  <!-- ============ VEÍCULOS ============ -->
  <?php foreach ($veiculos as $idx => $vv): ?>
    <?php
      $num = $idx + 1;
      $marca = $vv['manual_marca'];
      $modelo = $vv['manual_modelo'];
      // Se veio do APIBrasil, buscar do cache
      if ($vv['veiculo_id']) {
          $stv = db()->prepare('SELECT marca, modelo, cor, ano_modelo FROM veiculos WHERE id = ?');
          $stv->execute([(int)$vv['veiculo_id']]);
          $vm = $stv->fetch();
          if ($vm) {
              if (!$marca)  $marca  = $vm['marca'];
              if (!$modelo) $modelo = $vm['modelo'];
          }
      }
      $desc = trim($marca . ' ' . $modelo);
    ?>
    <div class="veiculo-box">
      <div class="veiculo-titulo">
        Veículo <?= $num ?> · <?= e(formatar_placa($vv['placa'])) ?>
      </div>
      <?php if ($desc): ?>
        <div><?= e($desc) ?><?= $vv['manual_ano'] ? ' · ' . e($vv['manual_ano']) : '' ?><?= $vv['manual_cor'] ? ' · ' . e($vv['manual_cor']) : '' ?></div>
      <?php endif; ?>

      <table style="margin-top:3px;">
        <?php foreach ($vv['itens'] as $it): ?>
          <tr class="item-row">
            <td><?= e($it['nome']) ?></td>
            <td class="right" style="white-space:nowrap;"><?= e(centavos_para_moeda((int)$it['preco_centavos'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td class="right bold" style="padding-top:3px;">Subtotal:</td>
          <td class="right bold" style="white-space:nowrap;"><?= e(centavos_para_moeda((int)($vv['subtotal'] ?? 0))) ?></td>
        </tr>
      </table>
    </div>
    <?php if ($idx < count($veiculos) - 1): ?>
      <div class="linha"></div>
    <?php endif; ?>
  <?php endforeach; ?>

  <div class="linha2"></div>

  <!-- ============ TOTAIS ============ -->
  <table>
    <tr><td>Subtotal geral:</td><td class="right"><?= e(centavos_para_moeda((int)$entrada['subtotal_centavos'])) ?></td></tr>
    <?php if ((int)$entrada['desconto_centavos'] > 0): ?>
      <tr><td>Desconto<?= (int)$entrada['desconto_percentual'] > 0 ? ' (' . (int)$entrada['desconto_percentual'] . '%)' : '' ?>:</td>
          <td class="right">- <?= e(centavos_para_moeda((int)$entrada['desconto_centavos'])) ?></td></tr>
    <?php endif; ?>
    <tr class="total-linha">
      <td>TOTAL:</td>
      <td class="right">R$ <?= e(centavos_para_moeda((int)$entrada['total_centavos'])) ?></td>
    </tr>
  </table>

  <?php if (!empty($entrada['observacoes'])): ?>
    <div class="linha"></div>
    <div class="bold">Observações:</div>
    <div><?= nl2br(e($entrada['observacoes'])) ?></div>
  <?php endif; ?>

  <?php if (!empty($entrada['avarias'])): ?>
    <div class="linha"></div>
    <div class="bold">Avarias registradas:</div>
    <div><?= nl2br(e($entrada['avarias'])) ?></div>
  <?php endif; ?>

  <div class="linha2"></div>

  <!-- ============ RODAPÉ ============ -->
  <div class="center footer">
    <div>Obrigado pela preferência!</div>
    <div style="margin-top:4px;">Emitido em <?= e(date('d/m/Y H:i')) ?></div>
    <div style="margin-top:6px; font-weight:bold;">DOCUMENTO NÃO FISCAL</div>
    <div style="margin-top:2px;">Este cupom não é documento fiscal.</div>
  </div>

</div>

</body>
</html>
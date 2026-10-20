<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_entradas();
ensure_lavagem_veiculos();
ensure_lavagem_pecas();

$entradaId = (int)($_GET['entrada'] ?? 0);
$st = db()->prepare("SELECT e.*, c.nome AS cli_nome, c.celular AS cli_cel FROM lavagem_entradas e LEFT JOIN clientes c ON c.id = e.cliente_id WHERE e.id = ?");
$st->execute([$entradaId]);
$entrada = $st->fetch();
if (!$entrada) { die('Entrada não encontrada.'); }

$veiculos = listar_veiculos_entrada($entradaId);

foreach ($veiculos as &$vv) {
    // Busca Serviços
    $sti = db()->prepare('SELECT nome, preco_centavos FROM lavagem_itens WHERE veiculo_id = ?');
    $sti->execute([(int)$vv['id']]);
    $vv['itens'] = $sti->fetchAll();

    // Busca Peças
    $stp = db()->prepare('SELECT * FROM lavagem_pecas WHERE veiculo_id = ?');
    $stp->execute([(int)$vv['id']]);
    $vv['pecas'] = $stp->fetchAll();
    
    // Monta a descrição do veículo (Modelo, Cor, Marca, Ano)
    $marca  = $vv['manual_marca'];
    $modelo = $vv['manual_modelo'];
    $cor    = $vv['manual_cor'];
    $ano    = $vv['manual_ano'];
    
    // Se não tiver preenchido manualmente, puxa do cache da APIBrasil (tabela veiculos)
    if ($vv['veiculo_id']) {
        $stv = db()->prepare('SELECT marca, modelo, cor, ano_modelo FROM veiculos WHERE id = ?');
        $stv->execute([(int)$vv['veiculo_id']]);
        $vm = $stv->fetch();
        if ($vm) {
            if (!$marca)  $marca  = $vm['marca'];
            if (!$modelo) $modelo = $vm['modelo'];
            if (!$cor)    $cor    = $vm['cor'];
            if (!$ano)    $ano    = $vm['ano_modelo'];
        }
    }

    $descVeiculo = trim($marca . ' ' . $modelo);
    if ($ano)  $descVeiculo .= ($descVeiculo ? ' · ' : '') . $ano;
    if ($cor)  $descVeiculo .= ($descVeiculo ? ' · ' : '') . $cor;

    $vv['descricao'] = $descVeiculo;
}
unset($vv);

$emp = empresa(); $logo = empresa_logo_url();

// Verifica se é para abrir a janela de impressão automaticamente
$autoPrint = !empty($_GET['auto']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Cupom #<?= (int)$entrada['id'] ?></title>
<style>
  body { font-family: 'Courier New', monospace; font-size: 12px; margin:0; padding:0; background:#f1f5f9; }
  .cupom { width: 80mm; margin: 12px auto; padding: 4mm; background:#fff; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
  .center { text-align: center; } .right { text-align: right; } .bold { font-weight: bold; }
  h1 { font-size: 15px; margin: 0 0 2px; } .linha { border-top: 1px dashed #000; margin: 6px 0; }
  table { width: 100%; border-collapse: collapse; } td { padding: 1px 0; vertical-align: top; }
  .veiculo-titulo { font-weight: bold; border-bottom: 1px solid #000; margin-top: 6px; padding-bottom: 2px; text-transform: uppercase; font-size: 11px; }
  .veiculo-desc { font-size: 11px; margin-bottom: 4px; text-transform: uppercase; }
  .no-print { max-width: 80mm; margin: 12px auto; display: flex; gap: 6px; justify-content: center; }
  
  @media print { 
    @page { size: 80mm auto; margin: 0; } 
    .no-print { display: none; } 
    .cupom { box-shadow: none; margin: 0; padding: 2mm; } 
  }
</style>
</head>
<body <?= $autoPrint ? 'onload="setTimeout(function(){window.print();}, 300);"' : '' ?>>

<div class="no-print">
    <button type="button" onclick="window.print()" style="padding:8px 16px; font-weight:bold; cursor:pointer;">Reimprimir Cupom</button>
    <a href="entradas.php" style="padding:8px 16px; text-decoration:none; color:#333; border:1px solid #ccc; border-radius:4px; background:#fff;">Voltar para Lista</a>
</div>

<div class="cupom">
  <div class="center">
    <?php if($logo): ?><img src="<?= e($logo) ?>" style="max-height:40px;margin-bottom:5px;"><br><?php endif; ?>
    <h1><?= e($emp['nome_fantasia'] ?: $emp['razao_social']) ?></h1>
    <div>CNPJ: <?= e(formatar_cnpj($emp['cnpj'] ?? '')) ?></div>
  </div>
  
  <div class="linha"></div>
  <div class="center bold">COMANDA DE LAVAGEM Nº <?= str_pad((int)$entrada['id'], 6, '0', STR_PAD_LEFT) ?></div>
  <div class="linha"></div>
  
  <table>
    <tr><td>Cliente:</td><td class="right"><?= e($entrada['cli_nome'] ?: $entrada['cliente_nome_avulso']) ?></td></tr>
    <tr><td>Data:</td><td class="right"><?= date('d/m/Y H:i', strtotime($entrada['data_entrada'].' '.$entrada['hora_entrada'])) ?></td></tr>
  </table>

  <?php foreach ($veiculos as $idx => $v): ?>
    <div class="veiculo-titulo">Veículo <?= $idx+1 ?> · <?= e(formatar_placa($v['placa'])) ?></div>
    
    <!-- EXIBE OS DADOS DO VEÍCULO AQUI -->
    <?php if (!empty($v['descricao'])): ?>
      <div class="veiculo-desc"><?= e($v['descricao']) ?></div>
    <?php endif; ?>
    
    <table style="margin-top:3px;">
      <?php foreach ($v['itens'] as $it): ?>
        <tr><td><?= e($it['nome']) ?></td><td class="right" style="white-space:nowrap;"><?= e(centavos_para_moeda((int)$it['preco_centavos'])) ?></td></tr>
      <?php endforeach; ?>
      
      <?php if ($v['pecas']): ?>
        <tr><td colspan="2" style="padding-top:4px; font-size:10px; font-weight:bold;">Peças Utilizadas:</td></tr>
        <?php foreach ($v['pecas'] as $p): ?>
          <tr>
            <td style="font-size:10px;"><?= e($p['nome']) ?> (<?= number_format($p['quantidade'], 2, ',', '.') ?>x)</td>
            <td class="right" style="font-size:10px; white-space:nowrap;"><?= e(centavos_para_moeda((int)$p['total_centavos'])) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </table>
  <?php endforeach; ?>

  <div class="linha"></div>
  <table>
    <tr><td>Subtotal:</td><td class="right"><?= e(centavos_para_moeda((int)$entrada['subtotal_centavos'])) ?></td></tr>
    <?php if ((int)$entrada['desconto_centavos'] > 0): ?>
      <tr><td>Desconto:</td><td class="right">- <?= e(centavos_para_moeda((int)$entrada['desconto_centavos'])) ?></td></tr>
    <?php endif; ?>
    <tr class="bold" style="font-size:14px;"><td style="padding-top:4px;">TOTAL:</td><td class="right" style="padding-top:4px;">R$ <?= e(centavos_para_moeda((int)$entrada['total_centavos'])) ?></td></tr>
  </table>
  
  <div class="linha"></div>
  <div class="center" style="font-size:9px;">DOCUMENTO NÃO FISCAL<br>Obrigado pela preferência!</div>
</div>
</body>
</html>
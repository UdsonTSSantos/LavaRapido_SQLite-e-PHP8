<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_entradas();

header('Content-Type: application/json; charset=utf-8');

$clienteId = (int)($_GET['cliente_id'] ?? 0);
if ($clienteId <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Cliente inválido.']);
    exit;
}

$de  = trim((string)($_GET['de']  ?? ''));
$ate = trim((string)($_GET['ate'] ?? ''));

/* ---------- Dados do cliente ---------- */
$st = db()->prepare('SELECT id, nome, celular, telefone, cpf_cnpj, tipo FROM clientes WHERE id = ?');
$st->execute([$clienteId]);
$cliente = $st->fetch();
if (!$cliente) {
    echo json_encode(['ok' => false, 'erro' => 'Cliente não encontrado.']);
    exit;
}

/* ---------- Filtro de datas ---------- */
$where  = ['e.cliente_id = :cid'];
$params = [':cid' => $clienteId];

if ($de !== '')  { $where[] = 'e.data_entrada >= :de';  $params[':de']  = $de; }
if ($ate !== '') { $where[] = 'e.data_entrada <= :ate'; $params[':ate'] = $ate; }

$sqlWhere = 'WHERE ' . implode(' AND ', $where);

/* ---------- Entradas ---------- */
$sql = "SELECT e.id, e.data_entrada, e.hora_entrada, e.placa, e.status, e.pago,
               e.total_centavos, e.subtotal_centavos, e.desconto_centavos,
               e.veiculo_manual_marca, e.veiculo_manual_modelo,
               v.marca AS v_marca, v.modelo AS v_modelo
        FROM lavagem_entradas e
        LEFT JOIN veiculos v ON v.id = e.veiculo_id
        $sqlWhere
        ORDER BY e.data_entrada DESC, e.hora_entrada DESC, e.id DESC";

$st = db()->prepare($sql);
$st->execute($params);
$entradas = $st->fetchAll();

/* ---------- Itens de cada entrada ---------- */
$entradaIds = array_column($entradas, 'id');
$itensPorEntrada = [];
if ($entradaIds) {
    $in = implode(',', array_fill(0, count($entradaIds), '?'));
    $sti = db()->prepare("
        SELECT entrada_id, nome, preco_centavos
        FROM lavagem_itens
        WHERE entrada_id IN ($in)
        ORDER BY id
    ");
    $sti->execute($entradaIds);
    foreach ($sti->fetchAll() as $row) {
        $itensPorEntrada[(int)$row['entrada_id']][] = [
            'nome'  => $row['nome'],
            'preco' => (int)$row['preco_centavos'],
        ];
    }
}

/* ---------- Monta resposta ---------- */
$linhas = [];
$totalGeral = 0;
$qtdLavagens = 0;

foreach ($entradas as $e) {
    $marca  = $e['v_marca']  ?: $e['veiculo_manual_marca'];
    $modelo = $e['v_modelo'] ?: $e['veiculo_manual_modelo'];
    $veiculo = trim($marca . ' ' . $modelo);
    if ($veiculo === '') $veiculo = '—';

    $itens = $itensPorEntrada[(int)$e['id']] ?? [];
    $servicos = $itens ? implode(' · ', array_column($itens, 'nome')) : '—';

    $total = (int)$e['total_centavos'];
    $totalGeral += $total;
    $qtdLavagens++;

    $linhas[] = [
        'id'          => (int)$e['id'],
        'data'        => $e['data_entrada'],
        'hora'        => substr($e['hora_entrada'], 0, 5),
        'data_fmt'    => date('d/m/Y', strtotime($e['data_entrada'])),
        'veiculo'     => $veiculo,
        'placa'       => formatar_placa($e['placa']),
        'servicos'    => $servicos,
        'valor'       => $total,
        'valor_fmt'   => centavos_para_moeda_brl($total),
        'subtotal_fmt'=> centavos_para_moeda_brl((int)$e['subtotal_centavos']),
        'desconto_fmt'=> (int)$e['desconto_centavos'] > 0
                          ? centavos_para_moeda_brl((int)$e['desconto_centavos'])
                          : null,
        'status'      => $e['status'],
        'pago'        => (int)$e['pago'],
    ];
}

echo json_encode([
    'ok'           => true,
    'cliente'      => [
        'id'      => (int)$cliente['id'],
        'nome'    => $cliente['nome'],
        'celular' => $cliente['celular'] ?: $cliente['telefone'],
        'doc'     => $cliente['cpf_cnpj'],
    ],
    'linhas'       => $linhas,
    'qtd'          => $qtdLavagens,
    'total'        => $totalGeral,
    'total_fmt'    => centavos_para_moeda_brl($totalGeral),
    'filtro'       => ['de' => $de, 'ate' => $ate],
], JSON_UNESCAPED_UNICODE);
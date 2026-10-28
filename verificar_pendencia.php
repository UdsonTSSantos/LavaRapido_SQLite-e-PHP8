<?php
require_once __DIR__ . '/config.php';
exigir_login();

header('Content-Type: application/json');

$placa = normalizar_placa($_GET['placa'] ?? '');

if (!$placa) {
    echo json_encode(['ok' => false]);
    exit;
}

// Busca a última entrada não paga, que não esteja cancelada
$stmt = db()->prepare("SELECT id, data_entrada, total_centavos FROM lavagem_entradas WHERE placa = ? AND pago = 0 AND status != 'cancelado' ORDER BY id DESC LIMIT 1");
$stmt->execute([$placa]);
$entrada = $stmt->fetch();

if ($entrada) {
    // Pega os serviços dessa entrada
    $stItens = db()->prepare("SELECT nome, preco_centavos FROM lavagem_itens WHERE entrada_id = ?");
    $stItens->execute([$entrada['id']]);
    $itens = $stItens->fetchAll();

    $itensFormatados = [];
    foreach ($itens as $it) {
        $itensFormatados[] = [
            'nome' => $it['nome'],
            'preco' => centavos_para_moeda($it['preco_centavos'])
        ];
    }

    echo json_encode([
        'ok' => true,
        'entrada' => [
            'id' => str_pad($entrada['id'], 5, '0', STR_PAD_LEFT),
            'data' => date('d/m/Y', strtotime($entrada['data_entrada'])),
            'total' => centavos_para_moeda($entrada['total_centavos']),
            'itens' => $itensFormatados
        ]
    ]);
} else {
    echo json_encode(['ok' => false]);
}
exit;
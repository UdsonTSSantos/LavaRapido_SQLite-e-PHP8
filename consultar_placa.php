<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_entradas();

header('Content-Type: application/json; charset=utf-8');

$placa = normalizar_placa((string)($_GET['placa'] ?? ''));

if (!validar_placa($placa)) {
    echo json_encode(['ok' => false, 'erro' => 'Placa inválida.']);
    exit;
}

// Contadores sempre retornam (mesmo se a consulta falhar)
$contadores = [
    'mes'   => contar_lavagens_mes($placa),
    'total' => contar_lavagens_total($placa),
];

// Verifica cache local primeiro (30 dias)
$vLocal = buscar_veiculo_por_placa($placa);
if ($vLocal) {
    echo json_encode([
        'ok'         => true,
        'cache'      => true,
        'dados'      => $vLocal,
        'contadores' => $contadores,
    ]);
    exit;
}

// Consulta a APIBrasil
$r = apibrasil_consultar_placa($placa);
if (!$r['ok']) {
    echo json_encode([
        'ok'         => false,
        'erro'       => $r['erro'],
        'contadores' => $contadores,
    ]);
    exit;
}

echo json_encode([
    'ok'         => true,
    'cache'      => false,
    'dados'      => $r['dados'],
    'contadores' => $contadores,
]);
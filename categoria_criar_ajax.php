<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_pagamentos();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

try {
    csrf_validar();
} catch (Throwable $e) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'erro' => 'Sessão expirada.']);
    exit;
}

$nome  = trim((string)($_POST['nome']  ?? ''));
$icone = trim((string)($_POST['icone'] ?? '📄'));
$cor   = trim((string)($_POST['cor']   ?? '#64748b'));

if ($nome === '') {
    echo json_encode(['ok' => false, 'erro' => 'Informe o nome da categoria.']);
    exit;
}
if (mb_strlen($nome) > 60) {
    echo json_encode(['ok' => false, 'erro' => 'Nome muito longo (máx. 60).']);
    exit;
}
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) {
    $cor = '#64748b';
}
if (mb_strlen($icone) > 4) {
    $icone = mb_substr($icone, 0, 4);
}

$st = db()->prepare('SELECT id FROM categorias_pagamento WHERE nome = ? COLLATE NOCASE');
$st->execute([$nome]);
if ($st->fetch()) {
    echo json_encode(['ok' => false, 'erro' => 'Já existe uma categoria com esse nome.']);
    exit;
}

db()->prepare('INSERT INTO categorias_pagamento (nome, cor, icone, ordem) VALUES (?, ?, ?, 50)')
    ->execute([$nome, $cor, $icone]);

$id = (int)db()->lastInsertId();

echo json_encode([
    'ok' => true,
    'categoria' => [
        'id'    => $id,
        'nome'  => $nome,
        'cor'   => $cor,
        'icone' => $icone,
    ],
]);
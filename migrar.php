<?php
require_once __DIR__ . '/config.php';

$pdo = db();
$resultado = [];

/* ---------- Colunas esperadas em lavagem_entradas ---------- */
$colunas = $pdo->query("PRAGMA table_info(lavagem_entradas)")
               ->fetchAll(PDO::FETCH_COLUMN, 1);

if (!$colunas) {
    die('❌ Tabela lavagem_entradas não existe. Rode o init.php primeiro.');
}

$novas = [
    'cliente_nome_avulso' => "TEXT NOT NULL DEFAULT ''",
    'cliente_celular'     => "TEXT NOT NULL DEFAULT ''",
    'sms_enviado'         => "INTEGER NOT NULL DEFAULT 0",
    'sms_enviado_em'      => "TEXT NOT NULL DEFAULT ''",
];

foreach ($novas as $col => $def) {
    if (in_array($col, $colunas, true)) {
        $resultado[] = "ℹ️  Coluna <strong>$col</strong> já existe.";
        continue;
    }
    try {
        $pdo->exec("ALTER TABLE lavagem_entradas ADD COLUMN $col $def");
        $resultado[] = "✅ Coluna <strong>$col</strong> adicionada.";
    } catch (PDOException $ex) {
        $resultado[] = "❌ Erro ao adicionar <strong>$col</strong>: " . $ex->getMessage();
    }
}

/* ---------- Relatório ---------- */
echo "<h2>Migração lavagem_entradas</h2><ul>";
foreach ($resultado as $r) echo "<li>$r</li>";
echo "</ul>";

echo "<h3>Colunas atuais:</h3><pre>";
foreach ($pdo->query("PRAGMA table_info(lavagem_entradas)")->fetchAll() as $c) {
    echo htmlspecialchars($c['name'] . '  ' . $c['type']) . "\n";
}
echo "</pre>";
<?php
require_once __DIR__ . '/config.php';
echo "<pre>";
echo "Colunas de lavagem_entradas:\n";
foreach (db()->query("PRAGMA table_info(lavagem_entradas)")->fetchAll() as $c) {
    echo "  - {$c['name']} ({$c['type']})\n";
}
echo "</pre>";
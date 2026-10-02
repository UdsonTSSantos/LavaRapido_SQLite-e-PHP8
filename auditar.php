<?php
$dir = __DIR__;
$files = glob($dir . '/*.php');

echo "<h2>Auditoria de arquivos PHP</h2>";
echo "<table border='1' cellpadding='6' style='border-collapse:collapse;font-family:monospace'>";
echo "<tr><th>Arquivo</th><th>Protegido?</th></tr>";

foreach ($files as $f) {
    $nome = basename($f);
    if (in_array($nome, ['auditar.php', 'index.php', 'logout.php', 'config.php', 'functions.php'])) {
        echo "<tr><td>$nome</td><td>— (isento)</td></tr>";
        continue;
    }
    $src = file_get_contents($f);
    $temExigir = (str_contains($src, 'exigir_login') || str_contains($src, 'exigir_admin'));
    $cls = $temExigir ? 'green' : 'red';
    $txt = $temExigir ? '✓ OK' : '✗ SEM PROTEÇÃO';
    echo "<tr><td>$nome</td><td style='color:$cls'>$txt</td></tr>";
}
echo "</table>";
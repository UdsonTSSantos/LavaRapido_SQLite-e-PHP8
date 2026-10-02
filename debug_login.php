<?php
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== CONFIGURAÇÃO ===\n";
echo "AUTH_MODE    : " . (defined('AUTH_MODE')    ? AUTH_MODE    : '(não definido)') . "\n";
echo "AUTH_API_URL : " . (defined('AUTH_API_URL') ? AUTH_API_URL : '(não definido)') . "\n\n";

echo "=== FUNÇÕES ===\n";
echo "autenticar         : " . (function_exists('autenticar')         ? 'SIM' : 'NÃO') . "\n";
echo "autenticar_local   : " . (function_exists('autenticar_local')   ? 'SIM' : 'NÃO') . "\n";
echo "autenticar_via_api : " . (function_exists('autenticar_via_api') ? 'SIM' : 'NÃO') . "\n\n";

echo "=== USUÁRIOS NO BANCO ===\n";
try {
    $rows = db()->query('SELECT id, email, ativo, is_admin, length(senha_hash) AS tamanho_hash FROM usuarios ORDER BY id')->fetchAll();
    if (!$rows) {
        echo "  ⚠️  NENHUM usuário cadastrado!\n";
    } else {
        foreach ($rows as $r) {
            echo "  #{$r['id']}  {$r['email']}  ativo={$r['ativo']}  admin={$r['is_admin']}  hash_len={$r['tamanho_hash']}\n";
        }
    }
} catch (Throwable $e) {
    echo "  ❌ ERRO: " . $e->getMessage() . "\n";
}

echo "\n=== TESTE DE LOGIN (admin) ===\n";
$email = 'suporte@ast7.com.br';
$senha = 'P4v@1H:3n#9r2B';

$st = db()->prepare('SELECT * FROM usuarios WHERE email = ? LIMIT 1');
$st->execute([$email]);
$u = $st->fetch();

if (!$u) {
    echo "❌ Usuário '$email' NÃO EXISTE no banco.\n";
} else {
    echo "Usuário encontrado: #{$u['id']}\n";
    echo "Ativo              : {$u['ativo']}\n";
    echo "senha_hash         : " . substr($u['senha_hash'], 0, 30) . "...\n";
    echo "password_verify    : " . (password_verify($senha, $u['senha_hash']) ? '✅ SENHA CORRETA' : '❌ SENHA NÃO CONFERE') . "\n";
}

echo "\n=== TESTE autenticar() ===\n";
if (function_exists('autenticar')) {
    $r = autenticar($email, $senha);
    echo print_r($r, true) . "\n";
}
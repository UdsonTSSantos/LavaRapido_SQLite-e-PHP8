<?php
/* Remove blocos duplicados de funções de autenticação do functions.php */

$arquivo = __DIR__ . '/functions.php';
$src = file_get_contents($arquivo);

/* Funções que queremos garantir que existam apenas 1 vez */
$funcoes = [
    'autenticar',
    'autenticar_local',
    'autenticar_via_api',
    'sincronizar_usuario_local',
];

$relatorio = [];

foreach ($funcoes as $fn) {
    // Encontra todas as posições de "function nome("
    $pattern = '/\nfunction\s+' . preg_quote($fn, '/') . '\s*\(/';
    preg_match_all($pattern, $src, $m, PREG_OFFSET_CAPTURE);

    if (count($m[0]) <= 1) {
        $relatorio[] = "✓ $fn — 1 ocorrência (ok)";
        continue;
    }

    $relatorio[] = "⚠ $fn — " . count($m[0]) . " ocorrências. Removendo as duplicatas...";

    // Vamos manter a PRIMEIRA, remover todas as demais.
    // Para remover cada duplicata, precisamos apagar do começo do bloco
    // até o fechamento da chave correspondente.

    // Recomeça a análise do início para pegar cada bloco e seu fim
    $ocorrencias = [];
    $offset = 0;
    while (($pos = strpos($src, "function $fn(", $offset)) !== false) {
        // Volta até o comentário anterior (bloco de comentário /** ou //)
        $inicio = $pos;
        $antes  = substr($src, 0, $pos);
        // Procura o último "/*" ou "//" antes da função, se estiver em poucas linhas
        $ultimoComentario = strrpos($antes, "/*");
        if ($ultimoComentario !== false && ($pos - $ultimoComentario) < 500) {
            $inicio = $ultimoComentario;
        }

        // Encontra o final da função — busca a chave de fechamento no nível 0
        $i = strpos($src, '{', $pos);
        $nivel = 0;
        $fim = $i;
        for ($j = $i; $j < strlen($src); $j++) {
            if ($src[$j] === '{') $nivel++;
            elseif ($src[$j] === '}') {
                $nivel--;
                if ($nivel === 0) { $fim = $j; break; }
            }
        }
        $fim++; // inclui o }

        $ocorrencias[] = ['inicio' => $inicio, 'fim' => $fim];
        $offset = $fim;
    }

    // Remove da última para a primeira (mantém a primeira)
    if (count($ocorrencias) > 1) {
        for ($k = count($ocorrencias) - 1; $k >= 1; $k--) {
            $ini = $ocorrencias[$k]['inicio'];
            $fim = $ocorrencias[$k]['fim'];
            $src = substr($src, 0, $ini) . substr($src, $fim);
        }
    }
}

file_put_contents($arquivo, $src);

echo "<pre>";
foreach ($relatorio as $linha) echo htmlspecialchars($linha) . "\n";
echo "\n✅ Limpeza concluída. Verifique o arquivo e recarregue o sistema.\n";
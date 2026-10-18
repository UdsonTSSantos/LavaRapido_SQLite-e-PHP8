<?php
require_once __DIR__ . '/config.php';
exigir_login();
exigir_permissao('clientes');

/* Força a resposta como JSON desde o início, mesmo em caso de erro */
header('Content-Type: application/json; charset=utf-8');

/* Captura qualquer erro do PHP e devolve como JSON */
set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'   => false,
        'erro' => 'Erro no servidor: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
});
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    ensure_entradas();
    if (function_exists('ensure_lavagem_veiculos')) {
        ensure_lavagem_veiculos();
    }
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'erro' => 'Erro ao preparar tabelas: ' . $e->getMessage()]);
    exit;
}

$clienteId = (int)($_GET['cliente_id'] ?? 0);
if ($clienteId <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Cliente inválido.']);
    exit;
}

$de  = trim((string)($_GET['de']  ?? ''));
$ate = trim((string)($_GET['ate'] ?? ''));

/* ---------- Dados do cliente ---------- */
$st = db()->prepare('SELECT id, nome, celular, telefone, cpf_cnpj, tipo, email FROM clientes WHERE id = ?');
$st->execute([$clienteId]);
$cliente = $st->fetch();
if (!$cliente) {
    echo json_encode(['ok' => false, 'erro' => 'Cliente não encontrado.']);
    exit;
}

/* ---------- Detecta se a tabela lavagem_veiculos existe ---------- */
$temMultiVeiculo = false;
try {
    $r = db()->query("SELECT name FROM sqlite_master WHERE type='table' AND name='lavagem_veiculos'")->fetchColumn();
    $temMultiVeiculo = !empty($r);
} catch (Throwable $e) { $temMultiVeiculo = false; }

/* =========================================================
 *  MODO 1 — Com multi-veículo (nova estrutura)
 * ========================================================= */
if ($temMultiVeiculo) {

    $where  = ['e.cliente_id = :cid'];
    $params = [':cid' => $clienteId];
    if ($de !== '')  { $where[] = 'e.data_entrada >= :de';  $params[':de']  = $de; }
    if ($ate !== '') { $where[] = 'e.data_entrada <= :ate'; $params[':ate'] = $ate; }
    $sqlWhere = 'WHERE ' . implode(' AND ', $where);

    $sql = "SELECT
                e.id         AS entrada_id,
                e.data_entrada,
                e.hora_entrada,
                e.status,
                e.pago,
                e.total_centavos,
                e.subtotal_centavos,
                e.desconto_centavos,
                v.id         AS vid,
                v.placa      AS v_placa,
                v.manual_marca,
                v.manual_modelo,
                v.manual_cor,
                v.manual_ano,
                v.veiculo_id,
                ve.marca     AS cache_marca,
                ve.modelo    AS cache_modelo,
                ve.cor       AS cache_cor,
                ve.ano_modelo AS cache_ano
            FROM lavagem_entradas e
            INNER JOIN lavagem_veiculos v ON v.entrada_id = e.id
            LEFT JOIN veiculos ve ON ve.id = v.veiculo_id
            $sqlWhere
            ORDER BY e.data_entrada DESC, e.hora_entrada DESC, e.id DESC, v.ordem";

    $st = db()->prepare($sql);
    $st->execute($params);
    $linhasBrutas = $st->fetchAll();

    /* ---------- Itens por veículo ---------- */
    $itensPorVeiculo = [];
    $vids = array_filter(array_column($linhasBrutas, 'vid'));
    if ($vids) {
        $in = implode(',', array_fill(0, count($vids), '?'));
        $sti = db()->prepare("SELECT veiculo_id, nome, preco_centavos FROM lavagem_itens WHERE veiculo_id IN ($in) ORDER BY id");
        $sti->execute(array_values($vids));
        foreach ($sti->fetchAll() as $row) {
            $itensPorVeiculo[(int)$row['veiculo_id']][] = $row;
        }
    }

    /* ---------- Monta linhas ---------- */
    $linhas = [];
    $totalGeral = 0;
    $comandas = [];

    foreach ($linhasBrutas as $e) {
        $marca   = $e['cache_marca']    ?: $e['manual_marca'];
        $modelo  = $e['cache_modelo']   ?: $e['manual_modelo'];
        $cor     = $e['cache_cor']      ?: $e['manual_cor'];
        $ano     = $e['cache_ano']      ?: $e['manual_ano'];
        $veiculo = trim($marca . ' ' . $modelo);
        if ($veiculo === '') $veiculo = '—';

        $itens = $itensPorVeiculo[(int)$e['vid']] ?? [];
        $servicos = $itens ? implode(' · ', array_column($itens, 'nome')) : '—';
        $subtotalVeic = 0;
        foreach ($itens as $it) $subtotalVeic += (int)$it['preco_centavos'];

        $entradaId = (int)$e['entrada_id'];
        if (!isset($comandas[$entradaId])) {
            $comandas[$entradaId] = true;
            $totalGeral += (int)$e['total_centavos'];
        }

        $linhas[] = [
            'entrada_id'  => $entradaId,
            'vid'         => (int)$e['vid'],
            'data_fmt'    => date('d/m/Y', strtotime($e['data_entrada'])),
            'hora'        => substr($e['hora_entrada'], 0, 5),
            'veiculo'     => $veiculo,
            'placa'       => function_exists('formatar_placa')
                                ? formatar_placa($e['v_placa'])
                                : $e['v_placa'],
            'servicos'    => $servicos,
            'valor'       => $subtotalVeic,
            'valor_fmt'   => centavos_para_moeda_brl($subtotalVeic),
            'ano_cor'     => trim($ano . ($cor ? ' · ' . $cor : '')),
            'status'      => $e['status'],
            'pago'        => (int)$e['pago'],
        ];
    }

    $qtdLavagens = count($comandas);

/* =========================================================
 *  MODO 2 — Estrutura antiga (1 placa por comanda)
 * ========================================================= */
} else {

    $where  = ['e.cliente_id = :cid'];
    $params = [':cid' => $clienteId];
    if ($de !== '')  { $where[] = 'e.data_entrada >= :de';  $params[':de']  = $de; }
    if ($ate !== '') { $where[] = 'e.data_entrada <= :ate'; $params[':ate'] = $ate; }
    $sqlWhere = 'WHERE ' . implode(' AND ', $where);

    $sql = "SELECT e.*, 
                   v.marca AS v_marca, v.modelo AS v_modelo, v.cor AS v_cor, v.ano_modelo AS v_ano
            FROM lavagem_entradas e
            LEFT JOIN veiculos v ON v.id = e.veiculo_id
            $sqlWhere
            ORDER BY e.data_entrada DESC, e.hora_entrada DESC, e.id DESC";

    $st = db()->prepare($sql);
    $st->execute($params);
    $entradas = $st->fetchAll();

    /* Itens por entrada */
    $itensPorEntrada = [];
    if ($entradas) {
        $ids = array_column($entradas, 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sti = db()->prepare("SELECT entrada_id, nome, preco_centavos FROM lavagem_itens WHERE entrada_id IN ($in) ORDER BY id");
        $sti->execute($ids);
        foreach ($sti->fetchAll() as $row) {
            $itensPorEntrada[(int)$row['entrada_id']][] = $row;
        }
    }

    $linhas = [];
    $totalGeral = 0;

    foreach ($entradas as $e) {
        $marca   = $e['v_marca']  ?: $e['veiculo_manual_marca'];
        $modelo  = $e['v_modelo'] ?: $e['veiculo_manual_modelo'];
        $cor     = $e['v_cor']    ?: $e['veiculo_manual_cor'];
        $ano     = $e['v_ano']    ?: $e['veiculo_manual_ano'];
        $veiculo = trim($marca . ' ' . $modelo);
        if ($veiculo === '') $veiculo = '—';

        $itens = $itensPorEntrada[(int)$e['id']] ?? [];
        $servicos = $itens ? implode(' · ', array_column($itens, 'nome')) : '—';

        $totalGeral += (int)$e['total_centavos'];

        $linhas[] = [
            'entrada_id'  => (int)$e['id'],
            'vid'         => 0,
            'data_fmt'    => date('d/m/Y', strtotime($e['data_entrada'])),
            'hora'        => substr($e['hora_entrada'], 0, 5),
            'veiculo'     => $veiculo,
            'placa'       => function_exists('formatar_placa')
                                ? formatar_placa($e['placa'])
                                : $e['placa'],
            'servicos'    => $servicos,
            'valor'       => (int)$e['total_centavos'],
            'valor_fmt'   => centavos_para_moeda_brl((int)$e['total_centavos']),
            'ano_cor'     => trim($ano . ($cor ? ' · ' . $cor : '')),
            'status'      => $e['status'],
            'pago'        => (int)$e['pago'],
        ];
    }

    $qtdLavagens = count($entradas);
}

/* =========================================================
 *  RESPOSTA
 * ========================================================= */
echo json_encode([
    'ok'        => true,
    'cliente'   => [
        'id'      => (int)$cliente['id'],
        'nome'    => $cliente['nome'],
        'celular' => $cliente['celular'] ?: $cliente['telefone'],
        'doc'     => $cliente['cpf_cnpj'],
        'email'   => $cliente['email'] ?? '',
    ],
    'linhas'    => $linhas,
    'qtd'       => $qtdLavagens,
    'total'     => $totalGeral,
    'total_fmt' => centavos_para_moeda_brl($totalGeral),
    'filtro'    => ['de' => $de, 'ate' => $ate],
], JSON_UNESCAPED_UNICODE);
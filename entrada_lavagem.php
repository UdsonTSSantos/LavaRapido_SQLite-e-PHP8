<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_entradas();
ensure_lavagens();
ensure_clientes();
ensure_lavagem_veiculos();
ensure_lavagem_pecas();
ensure_estoque();
ensure_comissoes();

$u = usuario_logado();

$id      = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$editar  = $id > 0;
$entrada = null;
$veiculosExistentes = [];

if ($editar) {
    $st = db()->prepare('SELECT * FROM lavagem_entradas WHERE id = ?');
    $st->execute([$id]);
    $entrada = $st->fetch();

    if (!$entrada) {
        flash('Entrada não encontrada.', 'erro');
        header('Location: entradas.php');
        exit;
    }
    $veiculosExistentes = listar_veiculos_entrada($id);
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['salvar', 'salvar_imprimir'])) {
    csrf_validar();
    $imprimirAgora = ($_POST['acao'] === 'salvar_imprimir');

    $cliente_id      = (int)($_POST['cliente_id'] ?? 0);
    $cliente_nome    = trim((string)($_POST['cliente_nome'] ?? ''));
    $cliente_celular = trim((string)($_POST['cliente_celular'] ?? ''));
    $data_entrada    = trim((string)($_POST['data_entrada'] ?? date('Y-m-d')));
    $hora_entrada    = trim((string)($_POST['hora_entrada'] ?? date('H:i')));
    $horas_prev      = max(0, (int)($_POST['previsao_horas'] ?? 0));
    $observacoes     = trim((string)($_POST['observacoes'] ?? ''));
    $avarias         = trim((string)($_POST['avarias'] ?? ''));
    $desconto_tipo   = $_POST['desconto_tipo'] ?? 'valor';
    $desconto_raw    = (string)($_POST['desconto'] ?? '0');

    $ordens = array_map('intval', (array)($_POST['veiculos_ordens'] ?? []));

    if ($cliente_nome === '')    $erros[] = 'Informe o nome do cliente.';
    if ($data_entrada === '')    $erros[] = 'Informe a data de entrada.';
    if ($hora_entrada === '')    $erros[] = 'Informe a hora de entrada.';
    if (!$ordens)                $erros[] = 'Adicione pelo menos um veículo.';

    if ($cliente_id > 0) {
        $stC = db()->prepare('SELECT id FROM clientes WHERE id = ? AND ativo = 1');
        $stC->execute([$cliente_id]);
        if (!$stC->fetch()) $cliente_id = 0;
    }

    $veiculos = [];
    $subtotalGeral = 0;

    if (!$erros) {
        foreach ($ordens as $ord) {
            $placa    = normalizar_placa((string)($_POST["placa_$ord"] ?? ''));
            $m_marca  = trim((string)($_POST["manual_marca_$ord"]  ?? ''));
            $m_modelo = trim((string)($_POST["manual_modelo_$ord"] ?? ''));
            $m_cor    = trim((string)($_POST["manual_cor_$ord"]    ?? ''));
            $m_ano    = trim((string)($_POST["manual_ano_$ord"]    ?? ''));
            
            $servicos_ids = array_map('intval', (array)($_POST["servicos_$ord"] ?? []));
            $pecas_ids    = array_map('intval', (array)($_POST["pecas_$ord"] ?? []));
            $pecas_qtde   = (array)($_POST["peca_qtd_$ord"] ?? []);
            $pecas_desc   = (array)($_POST["peca_desc_$ord"] ?? []);
            
            $servicos_equipe = $_POST["servico_equipe_$ord"] ?? [];

            if (!validar_placa($placa)) {
                $erros[] = "Veículo {$ord}: informe uma placa válida.";
                continue;
            }

            $itens = [];
            $subtotalV = 0;
            
            if ($servicos_ids) {
                $servicos_ids = array_values(array_unique($servicos_ids));
                $in = implode(',', array_fill(0, count($servicos_ids), '?'));
                $st = db()->prepare("SELECT * FROM lavagens WHERE id IN ($in) AND ativo = 1");
                $st->execute($servicos_ids);
                foreach ($st->fetchAll() as $lav) {
                    $eqIds = [];
                    if (!empty($servicos_equipe[$lav['id']])) {
                        $eqIds = array_filter(explode(',', $servicos_equipe[$lav['id']]));
                    }

                    $itens[] = [
                        'lavagem_id'        => (int)$lav['id'],
                        'nome'              => $lav['nome'],
                        'preco_centavos'    => (int)$lav['preco_centavos'],
                        'paga_comissao'     => (int)($lav['paga_comissao'] ?? 1),
                        'comissao_centavos' => (int)($lav['comissao_centavos'] ?? 0),
                        'equipe'            => $eqIds
                    ];
                    $subtotalV += (int)$lav['preco_centavos'];
                }
            }

            $pecas = [];
            if ($pecas_ids) {
                foreach ($pecas_ids as $k => $pid) {
                    $qtd = (float)str_replace(',', '.', $pecas_qtde[$k] ?? 1);
                    $descCent = moeda_para_centavos($pecas_desc[$k] ?? '0');
                    if ($qtd <= 0) continue;
                    
                    $stP = db()->prepare("SELECT * FROM estoque_itens WHERE id = ?");
                    $stP->execute([$pid]);
                    $item = $stP->fetch();
                    
                    if ($item) {
                        $totalItem = ((int)$item['preco_venda_centavos'] * $qtd) - $descCent;
                        if ($totalItem < 0) $totalItem = 0;
                        $pecas[] = [
                            'id' => (int)$item['id'], 'nome' => $item['nome'], 'qtd' => $qtd,
                            'preco_centavos' => (int)$item['preco_venda_centavos'], 'desconto_centavos' => $descCent, 'total_centavos' => (int)$totalItem
                        ];
                        $subtotalV += $totalItem;
                    }
                }
            }

            if (!$itens && !$pecas) {
                $erros[] = "Veículo {$ord}: adicione pelo menos um serviço ou peça.";
                continue;
            }

            $veiculos[] = [
                'ordem' => $ord, 'placa' => $placa, 'manual_marca' => $m_marca, 'manual_modelo' => $m_modelo,
                'manual_cor' => $m_cor, 'manual_ano' => $m_ano, 'itens' => $itens, 'pecas' => $pecas, 'subtotal' => $subtotalV,
            ];
            $subtotalGeral += $subtotalV;
        }
    }

    $desc_centavos = 0; $desc_percent  = 0;
    if (!$erros) {
        if ($desconto_tipo === 'percentual') {
            $desc_percent  = max(0, min(100, (int)preg_replace('/\D/', '', $desconto_raw)));
            $desc_centavos = (int)round($subtotalGeral * $desc_percent / 100);
        } else {
            $desc_centavos = moeda_para_centavos($desconto_raw);
            if ($desc_centavos > $subtotalGeral) $desc_centavos = $subtotalGeral;
        }
    }
    $total = max(0, $subtotalGeral - $desc_centavos);

    $prev_dt = '';
    if (!$erros && $horas_prev > 0) {
        $ts = strtotime("$data_entrada $hora_entrada");
        if ($ts !== false) $prev_dt = date('Y-m-d H:i:s', $ts + ($horas_prev * 3600));
    }

    if (!$erros) {
        $placaPrincipal = $veiculos[0]['placa'];
        $veiculoPrincipal = null;
        $vLocal = buscar_veiculo_por_placa($placaPrincipal);
        if ($vLocal) $veiculoPrincipal = (int)$vLocal['id'];

        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($editar) {
                $stOldPecas = $pdo->prepare("SELECT estoque_item_id, quantidade FROM lavagem_pecas WHERE entrada_id = ?");
                $stOldPecas->execute([$id]);
                foreach ($stOldPecas->fetchAll() as $old) {
                    $pdo->prepare("UPDATE estoque_itens SET quantidade = quantidade + ? WHERE id = ?")->execute([$old['quantidade'], $old['estoque_item_id']]);
                }
                $pdo->prepare('DELETE FROM lavagem_pecas WHERE entrada_id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM comissoes_lancadas WHERE entrada_id = ?')->execute([$id]);
                
                $pdo->prepare("
                    UPDATE lavagem_entradas SET
                        cliente_id = :cid, cliente_nome_avulso = :cna, cliente_celular = :ccel,
                        placa = :placa, veiculo_id = :vid, data_entrada = :data, hora_entrada = :hora,
                        previsao_saida_horas = :phoras, previsao_saida_datetime = :pdt,
                        observacoes = :obs, avarias = :avarias,
                        subtotal_centavos = :sub, desconto_centavos = :desc, desconto_percentual = :descp,
                        total_centavos = :total, atualizado_em = datetime('now','localtime')
                    WHERE id = :id
                ")->execute([
                    ':cid' => $cliente_id ?: null, ':cna' => $cliente_nome, ':ccel'=> $cliente_celular,
                    ':placa'=> $placaPrincipal, ':vid' => $veiculoPrincipal, ':data'=> $data_entrada, ':hora'=> $hora_entrada,
                    ':phoras'=> $horas_prev, ':pdt' => $prev_dt, ':obs' => $observacoes, ':avarias'=> $avarias,
                    ':sub' => $subtotalGeral, ':desc'=> $desc_centavos, ':descp'=> $desc_percent, ':total'=> $total, ':id'  => $id,
                ]);

                $pdo->prepare('DELETE FROM lavagem_itens WHERE entrada_id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM lavagem_veiculos WHERE entrada_id = ?')->execute([$id]);

                $entrada_id = $id;
                $mensagem = 'Entrada atualizada com sucesso.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO lavagem_entradas
                        (cliente_id, cliente_nome_avulso, cliente_celular, placa, veiculo_id,
                         data_entrada, hora_entrada, previsao_saida_horas, previsao_saida_datetime,
                         observacoes, avarias, status, subtotal_centavos, desconto_centavos, desconto_percentual,
                         total_centavos, pago, criado_por)
                    VALUES
                        (:cid, :cna, :ccel, :placa, :vid, :data, :hora, :phoras, :pdt,
                         :obs, :avarias, 'aberta', :sub, :desc, :descp, :total, 0, :uid)
                ");
                $stmt->execute([
                    ':cid' => $cliente_id ?: null, ':cna' => $cliente_nome, ':ccel'=> $cliente_celular,
                    ':placa'=> $placaPrincipal, ':vid' => $veiculoPrincipal, ':data'=> $data_entrada, ':hora'=> $hora_entrada,
                    ':phoras'=> $horas_prev, ':pdt' => $prev_dt, ':obs' => $observacoes, ':avarias'=> $avarias,
                    ':sub' => $subtotalGeral, ':desc'=> $desc_centavos, ':descp'=> $desc_percent, ':total'=> $total, ':uid' => (int)$u['id'],
                ]);
                $entrada_id = (int)$pdo->lastInsertId();
                $mensagem = 'Entrada registrada com sucesso.';
            }

            $insV = $pdo->prepare("INSERT INTO lavagem_veiculos (entrada_id, ordem, placa, veiculo_id, manual_marca, manual_modelo, manual_cor, manual_ano) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insI = $pdo->prepare("INSERT INTO lavagem_itens (entrada_id, veiculo_id, lavagem_id, nome, preco_centavos) VALUES (?, ?, ?, ?, ?)");
            $insP = $pdo->prepare("INSERT INTO lavagem_pecas (entrada_id, veiculo_id, estoque_item_id, nome, quantidade, preco_unitario_centavos, desconto_centavos, total_centavos) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insC = $pdo->prepare("INSERT INTO comissoes_lancadas (entrada_id, item_id, usuario_id, nome_lavagem, valor_centavos, data_entrada) VALUES (?, ?, ?, ?, ?, ?)");

            foreach ($veiculos as $v) {
                $vLocal = buscar_veiculo_por_placa($v['placa']);
                $vid = $vLocal ? (int)$vLocal['id'] : null;

                if (!$vid && ($v['manual_marca'] || $v['manual_modelo'] || $v['manual_cor'] || $v['manual_ano'])) {
                    $pdo->prepare("INSERT INTO veiculos (placa, marca, modelo, cor, ano_modelo, origem) VALUES (?, ?, ?, ?, ?, 'manual') ON CONFLICT(placa) DO UPDATE SET marca=excluded.marca, modelo=excluded.modelo, cor=excluded.cor, ano_modelo=excluded.ano_modelo, atualizado_em=datetime('now','localtime')")->execute([$v['placa'], $v['manual_marca'], $v['manual_modelo'], $v['manual_cor'], $v['manual_ano']]);
                    $vLocal = buscar_veiculo_por_placa($v['placa']);
                    $vid = $vLocal ? (int)$vLocal['id'] : null;
                } elseif ($vid && ($v['manual_modelo'] || $v['manual_cor'])) {
                     $pdo->prepare("UPDATE veiculos SET modelo=?, cor=?, atualizado_em=datetime('now','localtime') WHERE id=?")->execute([$v['manual_modelo'], $v['manual_cor'], $vid]);
                }

                $insV->execute([$entrada_id, $v['ordem'], $v['placa'], $vid, $v['manual_marca'], $v['manual_modelo'], $v['manual_cor'], $v['manual_ano']]);
                $novoVeiculoId = (int)$pdo->lastInsertId();

                foreach ($v['itens'] as $it) {
                    $insI->execute([$entrada_id, $novoVeiculoId, $it['lavagem_id'], $it['nome'], $it['preco_centavos']]);
                    $itemId = (int)$pdo->lastInsertId();

                    // GERA COMISSÃO SE TIVER EQUIPE E SE O SERVIÇO PAGA COMISSÃO
                    if ($it['paga_comissao'] && !empty($it['equipe']) && $it['comissao_centavos'] > 0) {
                        $qtdPessoas = count($it['equipe']);
                        $valorDividido = (int) round($it['comissao_centavos'] / $qtdPessoas);
                        
                        if ($valorDividido > 0) {
                            foreach ($it['equipe'] as $uid) {
                                $insC->execute([$entrada_id, $itemId, (int)$uid, $it['nome'], $valorDividido, $data_entrada]);
                            }
                        }
                    }
                }
                
                foreach ($v['pecas'] as $p) {
                    $insP->execute([$entrada_id, $novoVeiculoId, $p['id'], $p['nome'], $p['qtd'], $p['preco_centavos'], $p['desconto_centavos'], $p['total_centavos']]);
                    $pdo->prepare("UPDATE estoque_itens SET quantidade = quantidade - ? WHERE id = ?")->execute([$p['qtd'], $p['id']]);
                }
            }

            $pdo->commit();
            flash($mensagem, 'sucesso');
            
            if ($imprimirAgora) {
                header('Location: cupom_lavagem.php?entrada=' . $entrada_id . '&auto=1');
            } else {
                header('Location: entradas.php?cupom=' . $entrada_id);
            }
            exit;
        } catch (Throwable $ex) {
            $pdo->rollBack();
            $erros[] = 'Erro ao salvar: ' . $ex->getMessage();
        }
    }
}

$clientes = db()->query('SELECT id, nome, celular, telefone FROM clientes WHERE ativo = 1 ORDER BY nome COLLATE NOCASE')->fetchAll();
$servicos = db()->query('SELECT * FROM lavagens WHERE ativo = 1 ORDER BY nome COLLATE NOCASE')->fetchAll();
$estoque  = db()->query('SELECT * FROM estoque_itens WHERE ativo = 1 ORDER BY nome COLLATE NOCASE')->fetchAll();
$usuarios = db()->query('SELECT id, nome FROM usuarios WHERE ativo = 1 ORDER BY nome COLLATE NOCASE')->fetchAll();

$v = [
    'cliente_id' => $entrada['cliente_id'] ?? 0, 'cliente_nome' => $entrada['cliente_nome_avulso'] ?? '',
    'cliente_celular' => $entrada['cliente_celular'] ?? '', 'data_entrada' => $entrada['data_entrada'] ?? date('Y-m-d'),
    'hora_entrada' => $entrada['hora_entrada'] ?? date('H:i'), 'previsao_horas' => (int)($entrada['previsao_saida_horas'] ?? 0),
    'observacoes' => $entrada['observacoes'] ?? '', 'avarias' => $entrada['avarias'] ?? '',
];

$descPercent = (int)($entrada['desconto_percentual'] ?? 0); $descCent = (int)($entrada['desconto_centavos'] ?? 0);
if ($descPercent > 0) { $v['desconto_tipo'] = 'percentual'; $v['desconto'] = (string)$descPercent; } 
else { $v['desconto_tipo'] = 'valor'; $v['desconto'] = $descCent > 0 ? centavos_para_moeda($descCent) : '0,00'; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') { $v['desconto_tipo'] = $_POST['desconto_tipo'] ?? $v['desconto_tipo']; $v['desconto'] = $_POST['desconto'] ?? $v['desconto']; }

$veiculosForm = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($ordens)) {
    foreach ($ordens as $ord) { 
        $servicos_ids_post = array_map('intval', (array)($_POST["servicos_$ord"] ?? []));
        $servicos_equipe_post = $_POST["servico_equipe_$ord"] ?? [];
        
        $itens_render = [];
        if ($servicos_ids_post) {
            $in = implode(',', array_fill(0, count($servicos_ids_post), '?'));
            $st = db()->prepare("SELECT * FROM lavagens WHERE id IN ($in)");
            $st->execute($servicos_ids_post);
            foreach ($st->fetchAll() as $lav) {
                $itens_render[] = [
                    'lavagem_id' => $lav['id'],
                    'nome' => $lav['nome'],
                    'preco_centavos' => $lav['preco_centavos'],
                    'paga_comissao' => $lav['paga_comissao'],
                    'comissao_centavos' => $lav['comissao_centavos'],
                    'equipe' => $servicos_equipe_post[$lav['id']] ?? ''
                ];
            }
        }

        $pecas_ids_post = array_map('intval', (array)($_POST["pecas_$ord"] ?? []));
        $pecas_qtde_post = (array)($_POST["peca_qtd_$ord"] ?? []);
        $pecas_desc_post = (array)($_POST["peca_desc_$ord"] ?? []);
        
        $pecas_render = [];
        if ($pecas_ids_post) {
            foreach ($pecas_ids_post as $k => $pid) {
                $stP = db()->prepare("SELECT * FROM estoque_itens WHERE id = ?");
                $stP->execute([$pid]);
                $item = $stP->fetch();
                if ($item) {
                    $qtd = (float)str_replace(',', '.', $pecas_qtde_post[$k] ?? 1);
                    $descCent = moeda_para_centavos($pecas_desc_post[$k] ?? '0');
                    $pecas_render[] = [
                        'id' => $item['id'], 'nome' => $item['nome'], 'qtd' => $qtd,
                        'preco_centavos' => $item['preco_venda_centavos'], 'desconto_centavos' => $descCent, 'total_centavos' => ((int)$item['preco_venda_centavos'] * $qtd) - $descCent
                    ];
                }
            }
        }

        $veiculosForm[] = [
            'ordem' => $ord, 'placa' => normalizar_placa((string)($_POST["placa_$ord"] ?? '')), 
            'manual_marca' => (string)($_POST["manual_marca_$ord"] ?? ''), 'manual_modelo' => (string)($_POST["manual_modelo_$ord"] ?? ''), 
            'manual_cor' => (string)($_POST["manual_cor_$ord"] ?? ''), 'manual_ano' => (string)($_POST["manual_ano_$ord"] ?? ''), 
            'itens' => $itens_render, 'pecas' => $pecas_render
        ]; 
    }
} elseif ($editar && $veiculosExistentes) {
    foreach ($veiculosExistentes as $vv) {
        $st = db()->prepare('SELECT id, lavagem_id, nome, preco_centavos FROM lavagem_itens WHERE veiculo_id = ? ORDER BY id'); 
        $st->execute([(int)$vv['id']]);
        $stP = db()->prepare('SELECT estoque_item_id as id, nome, quantidade as qtd, preco_unitario_centavos as preco_centavos, desconto_centavos, total_centavos FROM lavagem_pecas WHERE veiculo_id = ? ORDER BY id'); 
        $stP->execute([(int)$vv['id']]);
        
        $itensBanco = $st->fetchAll();
        foreach($itensBanco as &$itB) {
            $stC = db()->prepare("SELECT GROUP_CONCAT(usuario_id) FROM comissoes_lancadas WHERE item_id = ?");
            $stC->execute([$itB['id']]);
            $itB['equipe'] = $stC->fetchColumn() ?: '';
        }
        
        $veiculosForm[] = [
            'ordem' => (int)$vv['ordem'], 'placa' => $vv['placa'], 'manual_marca' => $vv['manual_marca'], 
            'manual_modelo' => $vv['manual_modelo'], 'manual_cor' => $vv['manual_cor'], 'manual_ano' => $vv['manual_ano'], 
            'itens' => $itensBanco, 'pecas' => $stP->fetchAll()
        ];
    }
}
if (!$veiculosForm) { 
    $veiculosForm[] = ['ordem' => 1, 'placa' => '', 'manual_marca' => '', 'manual_modelo' => '', 'manual_cor' => '', 'manual_ano' => '', 'itens' => [], 'pecas' => []]; 
}

$servicosJS = array_map(fn($s) => [
    'id' => (int)$s['id'], 
    'nome' => $s['nome'], 
    'preco' => (int)$s['preco_centavos'], 
    'paga' => (int)($s['paga_comissao'] ?? 1),
    'comissao' => (int)($s['comissao_centavos'] ?? 0)
], $servicos);

$estoqueJS  = array_map(fn($e) => ['id' => (int)$e['id'], 'nome' => $e['nome'], 'preco' => (int)$e['preco_venda_centavos'], 'qtd' => (float)$e['quantidade']], $estoque);
$usuariosJS = array_map(fn($u) => ['id' => (int)$u['id'], 'nome' => $u['nome']], $usuarios);

$titulo = $editar ? 'Editar entrada' : 'Nova entrada';
require __DIR__ . '/header.php';
?>

<div class="max-w-5xl mx-auto">
  <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
    <div>
      <h1 class="text-2xl font-bold text-slate-900"><?= $editar ? 'Editar entrada' : 'Nova entrada' ?></h1>
      <p class="text-sm text-slate-500"><?= $editar ? 'Altere os dados e salve.' : 'Registre um ou mais veículos na mesma comanda.' ?></p>
    </div>
    <div class="flex items-center gap-3 text-right">
      <div class="text-xs text-slate-500 leading-tight"><div><span class="font-semibold text-slate-700" id="hdrData"><?= e(date('d/m/Y', strtotime($v['data_entrada']))) ?></span> · <span id="hdrHora"><?= e(substr($v['hora_entrada'], 0, 5)) ?></span></div></div>
      <a href="entradas.php" class="text-sm text-brand-600 hover:underline font-semibold whitespace-nowrap">← Voltar</a>
    </div>
  </div>

  <form method="post" id="formEntrada" class="bg-white rounded-2xl shadow-card border border-slate-200 p-6 sm:p-8 space-y-6" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= $editar ? (int)$id : '' ?>">
    <input type="hidden" name="cliente_id" id="cliente_id" value="<?= (int)$v['cliente_id'] ?>">
    <input type="hidden" name="data_entrada" id="dataEntrada" value="<?= e($v['data_entrada']) ?>">
    <input type="hidden" name="hora_entrada" id="horaEntrada" value="<?= e(substr($v['hora_entrada'], 0, 5)) ?>">

    <?php if ($erros): ?>
      <div class="rounded-lg border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm font-semibold">
        <ul class="list-disc pl-5 space-y-1"><?php foreach ($erros as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <section class="grid gap-4 sm:grid-cols-2">
      <h2 class="sm:col-span-2 text-sm font-bold text-slate-700 uppercase tracking-wider">Cliente</h2>
      <div>
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Nome do cliente *</label>
        <input type="text" name="cliente_nome" id="cliente_nome" list="listaClientes" required value="<?= e($v['cliente_nome']) ?>" placeholder="Digite o nome..." class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-400 font-semibold text-black">
        <datalist id="listaClientes">
          <?php foreach ($clientes as $c): ?><option data-id="<?= (int)$c['id'] ?>" data-celular="<?= e($c['celular'] ?: $c['telefone']) ?>" data-nome="<?= e($c['nome']) ?>" value="<?= e($c['nome']) ?>"></option><?php endforeach; ?>
        </datalist>
      </div>
      <div>
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Celular</label>
        <input type="text" name="cliente_celular" id="cliente_celular" inputmode="numeric" maxlength="16" value="<?= e($v['cliente_celular']) ?>" placeholder="(00) 00000-0000" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-400 font-semibold text-black">
      </div>
    </section>

    <section class="pt-4 border-t border-slate-200">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Veículos</h2>
        <button type="button" id="btnAddVeiculo" class="rounded-lg bg-brand-500 hover:bg-brand-700 text-white px-4 py-2 text-sm font-semibold shadow-sm">+ Adicionar veículo</button>
      </div>
      <div id="veiculosWrap" class="space-y-4"></div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 pt-4 border-t border-slate-200">
      <h2 class="sm:col-span-2 text-sm font-bold text-slate-700 uppercase tracking-wider">Observações</h2>
      <div>
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Observações do cliente</label>
        <textarea name="observacoes" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black"><?= e($v['observacoes']) ?></textarea>
      </div>
      <div>
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Avarias</label>
        <textarea name="avarias" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black"><?= e($v['avarias']) ?></textarea>
      </div>
    </section>

    <section class="pt-4 border-t border-slate-200">
      <div class="grid gap-4 lg:grid-cols-5">
        <div class="lg:col-span-3">
          <label class="block text-sm font-bold text-slate-700 mb-1.5">Desconto Geral (R$ ou %)</label>
          <div class="flex gap-2 items-stretch">
            <select name="desconto_tipo" id="desconto_tipo" class="w-24 flex-shrink-0 rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-bold text-black focus:outline-none focus:ring-2 focus:ring-brand-400">
              <option value="valor" <?= $v['desconto_tipo'] === 'valor' ? 'selected' : '' ?>>R$</option>
              <option value="percentual" <?= $v['desconto_tipo'] === 'percentual' ? 'selected' : '' ?>>%</option>
            </select>
            <input name="desconto" id="desconto" inputmode="numeric" value="<?= e($v['desconto']) ?>" placeholder="0,00" class="flex-1 min-w-0 rounded-lg border border-slate-300 px-4 py-2.5 text-base font-semibold text-black focus:outline-none focus:ring-2 focus:ring-brand-400">
          </div>
        </div>
        <div class="lg:col-span-2 rounded-xl border border-slate-300 bg-slate-50 px-5 py-4">
          <div class="flex justify-between text-sm"><span class="text-slate-500">Subtotal</span><span id="subtotalView" class="font-semibold">R$ 0,00</span></div>
          <div class="flex justify-between text-sm mt-1"><span class="text-slate-500">Desconto</span><span id="descView" class="font-semibold text-rose-600">- R$ 0,00</span></div>
          <div class="flex justify-between text-lg font-bold mt-3 pt-3 border-t border-slate-300"><span>Total</span><span id="totalView">R$ 0,00</span></div>
        </div>
      </div>
    </section>

    <div class="flex justify-end gap-2 pt-2 flex-wrap">
      <a href="entradas.php" class="px-4 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-sm font-semibold">Cancelar</a>
      
      <button type="submit" name="acao" value="salvar_imprimir" class="px-5 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-white font-semibold shadow-sm flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Imprimir cupom
      </button>

      <button type="submit" name="acao" value="salvar" class="px-5 py-2.5 rounded-lg bg-brand-500 hover:bg-brand-700 text-white font-semibold shadow-sm">
        Registrar entrada
      </button>
    </div>
  </form>
</div>

<!-- MODAL DE EQUIPE -->
<div id="modalEquipe" class="hidden fixed inset-0 bg-black/50 z-[9999] flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm">
    <div class="px-5 py-3 border-b border-slate-200 flex justify-between items-center bg-slate-50 rounded-t-2xl">
      <h3 class="font-bold text-slate-800">Quem realizou?</h3>
      <button type="button" class="btn-fechar-eq text-slate-400 hover:text-slate-700 text-xl leading-none">&times;</button>
    </div>
    <div class="px-5 py-4 space-y-3 max-h-[60vh] overflow-y-auto" id="listaEquipe">
      <!-- Preenchido via JS -->
    </div>
    <div class="px-5 py-3 border-t border-slate-200 flex justify-end gap-2 bg-slate-50 rounded-b-2xl">
      <button type="button" class="btn-fechar-eq px-4 py-2 rounded-lg bg-white border border-slate-300 text-slate-700 font-semibold text-sm">Cancelar</button>
      <button type="button" onclick="salvarEquipe()" class="px-4 py-2 rounded-lg bg-brand-500 text-white font-semibold text-sm shadow-sm">Confirmar</button>
    </div>
  </div>
</div>

<script>
  var SERVICOS = <?= json_encode($servicosJS, JSON_UNESCAPED_UNICODE) ?>;
  var ESTOQUE  = <?= json_encode($estoqueJS, JSON_UNESCAPED_UNICODE) ?>;
  var USUARIOS = <?= json_encode($usuariosJS, JSON_UNESCAPED_UNICODE) ?>;
  var VEICULOS_INICIAIS = <?= json_encode($veiculosForm, JSON_UNESCAPED_UNICODE) ?>;

  function soDigitos(v) { return String(v || '').replace(/\D+/g, ''); }
  function brl(c) { return 'R$ ' + (c / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
  function escapeHtml(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

  var inputAlvo = null;
  var btnAlvo = null;

  // FUNÇÃO GLOBAL PARA ABRIR O MODAL
  function abrirModal(btn, inputId, valorComissao) {
    if (valorComissao <= 0) {
        alert('Atenção: O valor desta comissão está zerado no cadastro do serviço.\n\nVá em "Lavagens", edite este serviço e informe um valor de comissão para que o funcionário receba.');
    }
    btnAlvo = btn;
    inputAlvo = document.getElementById(inputId);
    var sel = inputAlvo.value.split(',').filter(Boolean);
    
    var listaEq = document.getElementById('listaEquipe');
    listaEq.innerHTML = '';
    USUARIOS.forEach(function(u) {
      var check = sel.includes(String(u.id)) ? 'checked' : '';
      var html = '<label class="flex items-center gap-3 cursor-pointer p-2 hover:bg-slate-50 rounded">'
               + '<input type="checkbox" value="' + u.id + '" ' + check + ' class="w-4 h-4 text-brand-500 rounded border-slate-300">'
               + '<span class="text-sm font-semibold text-slate-700">' + escapeHtml(u.nome) + '</span>'
               + '</label>';
      listaEq.insertAdjacentHTML('beforeend', html);
    });
    document.getElementById('modalEquipe').classList.remove('hidden');
  }

  // FUNÇÃO GLOBAL PARA SALVAR A EQUIPE
  function salvarEquipe() {
    var sel = [];
    document.getElementById('listaEquipe').querySelectorAll('input:checked').forEach(function(c) { sel.push(c.value); });
    if (inputAlvo && btnAlvo) {
      inputAlvo.value = sel.join(',');
      btnAlvo.innerHTML = sel.length > 0 ? '👤 Equipe Definida' : '👤 Quem fez?';
      btnAlvo.className = sel.length > 0 ? 'btn-equipe bg-emerald-100 text-emerald-700 px-2 py-1 rounded text-[11px] font-bold ml-2 whitespace-nowrap' : 'btn-equipe bg-slate-100 text-slate-600 px-2 py-1 rounded text-[11px] font-bold ml-2 whitespace-nowrap';
    }
    document.getElementById('modalEquipe').classList.add('hidden');
  }

  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-fechar-eq').forEach(function(el) {
      el.addEventListener('click', function() { document.getElementById('modalEquipe').classList.add('hidden'); });
    });

    var inputNome = document.getElementById('cliente_nome');
    var inputCel  = document.getElementById('cliente_celular');
    var hiddenCli = document.getElementById('cliente_id');
    
    if (inputCel) { 
        var aplicarCel = function () { inputCel.value = String(inputCel.value||'').replace(/\D+/g,'').slice(0,11); if(inputCel.value.length>2) inputCel.value = '(' + inputCel.value.slice(0,2) + ') ' + inputCel.value.slice(2); }; 
        inputCel.addEventListener('input', aplicarCel); inputCel.addEventListener('blur', aplicarCel); aplicarCel(); 
    }
    
    function vincularCliente() {
      var val = inputNome.value.trim(); var achou = null;
      document.getElementById('listaClientes').querySelectorAll('option').forEach(function (o) { if (o.dataset.nome === val) achou = o; });
      if (achou) { hiddenCli.value = achou.dataset.id; if (!inputCel.value.trim() && achou.dataset.celular) { inputCel.value = achou.dataset.celular; inputCel.dispatchEvent(new Event('input')); } } else { hiddenCli.value = 0; }
    }
    inputNome.addEventListener('input', vincularCliente); inputNome.addEventListener('change', vincularCliente);

    var wrap = document.getElementById('veiculosWrap');
    var proximaOrd = 1;

    function htmlServicosOpcoes() { var html = '<option value="">Selecione um serviço...</option>'; SERVICOS.forEach(function (s) { html += '<option value="' + s.id + '" data-preco="' + s.preco + '" data-nome="' + escapeHtml(s.nome) + '" data-paga="' + s.paga + '">' + escapeHtml(s.nome) + ' — ' + brl(s.preco) + '</option>'; }); return html; }
    function htmlPecasOpcoes() { var html = '<option value="">Selecione uma peça...</option>'; ESTOQUE.forEach(function (p) { var aviso = p.qtd <= 2 ? ' ⚠️ (Apenas ' + p.qtd + ')' : ''; html += '<option value="' + p.id + '" data-preco="' + p.preco + '" data-nome="' + escapeHtml(p.nome) + '">' + escapeHtml(p.nome) + ' — ' + brl(p.preco) + aviso + '</option>'; }); return html; }

    function htmlBlocoVeiculo(ord, dados) {
      dados = dados || {};
      var servicosPreenchidos = ''; if (dados.itens) { dados.itens.forEach(function (it) { 
          var sv = SERVICOS.find(x=>x.id == it.lavagem_id) || {paga:0, comissao:0};
          servicosPreenchidos += htmlServicoSelecionado(ord, it.lavagem_id, it.nome, parseInt(it.preco_centavos, 10), sv.paga, it.equipe || '', sv.comissao); 
      }); }
      var pecasPreenchidas = ''; if (dados.pecas) { dados.pecas.forEach(function (p) { pecasPreenchidas += htmlPecaSelecionada(ord, p.id, p.nome, p.qtd, parseInt(p.preco_centavos, 10), parseInt(p.desconto_centavos, 10)); }); }

      return ''
      + '<div class="veiculo-block border border-slate-200 rounded-xl p-4 space-y-4 bg-slate-50/50" data-ordem="' + ord + '">'
      +   '<input type="hidden" name="veiculos_ordens[]" value="' + ord + '">'
      +   '<div class="flex items-center justify-between"><h3 class="text-sm font-bold text-slate-800">Veículo ' + ord + '</h3><button type="button" class="btn-remover-veiculo text-rose-600 hover:underline text-xs font-semibold hidden">Remover</button></div>'
      +   '<div class="grid gap-3 sm:grid-cols-6">'
      +     '<div class="sm:col-span-3"><label class="block text-xs font-bold text-slate-600 mb-1">Placa *</label><div class="relative"><input name="placa_' + ord + '" maxlength="8" value="' + escapeHtml(dados.placa || '') + '" class="input-placa w-full rounded-lg border border-slate-300 px-3 py-2.5 uppercase font-semibold text-black"><span class="placa-status absolute right-3 top-2.5 text-xs text-slate-400"></span></div></div>'
      +     '<div class="sm:col-span-3"><div class="grid grid-cols-2 gap-2 text-xs"><div class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5"><div class="text-sky-700 font-medium">Lavagens neste mês</div><div class="text-lg font-bold text-sky-900 cont-mes">0</div></div><div class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5"><div class="text-indigo-700 font-medium">Total</div><div class="text-lg font-bold text-indigo-900 cont-total">0</div></div></div></div>'
      +   '</div>'
      +   '<div class="grid gap-3 sm:grid-cols-6">'
      +     '<div class="sm:col-span-3"><label class="block text-xs font-bold text-slate-600 mb-1">Modelo</label><input name="manual_modelo_' + ord + '" value="' + escapeHtml(dados.manual_modelo || '') + '" class="input-modelo w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-semibold text-black" placeholder="Ex: Fiat Uno"></div>'
      +     '<div class="sm:col-span-3"><label class="block text-xs font-bold text-slate-600 mb-1">Cor</label><input name="manual_cor_' + ord + '" value="' + escapeHtml(dados.manual_cor || '') + '" class="input-cor w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-semibold text-black" placeholder="Ex: Prata"></div>'
      +   '</div>'
      +   '<details class="manual-box"><summary class="cursor-pointer text-xs text-slate-600 font-medium">Placa não encontrada? Adicionar Marca e Ano manualmente</summary><div class="mt-2 grid gap-2 sm:grid-cols-4"><div class="sm:col-span-2"><input name="manual_marca_' + ord + '" placeholder="Marca" value="' + escapeHtml(dados.manual_marca || '') + '" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div><div class="sm:col-span-2"><input name="manual_ano_' + ord + '" placeholder="Ano" value="' + escapeHtml(dados.manual_ano || '') + '" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div></div></details>'
      +   '<div class="pt-2 border-t border-slate-200 mt-2"><label class="block text-xs font-bold text-slate-600 mb-1">Serviços</label><div class="flex flex-wrap gap-2"><select class="sel-servico flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">' + htmlServicosOpcoes() + '</select><button type="button" class="btn-add-servico rounded-lg bg-sky-600 hover:bg-sky-500 text-white px-4 py-2 text-sm font-semibold whitespace-nowrap">+ Adicionar</button></div><div class="lista-servicos mt-2 rounded-lg border border-slate-200 divide-y divide-slate-100 bg-white empty:border-0">' + servicosPreenchidos + '</div></div>'
      +   '<div class="pt-2 border-t border-slate-200"><label class="block text-xs font-bold text-slate-600 mb-1">Peças Utilizadas</label><div class="flex flex-wrap gap-2"><select class="sel-peca flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">' + htmlPecasOpcoes() + '</select><button type="button" class="btn-add-peca rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 text-sm font-semibold whitespace-nowrap">+ Add Peça</button></div><div class="lista-pecas mt-2 rounded-lg border border-slate-200 divide-y divide-slate-100 bg-white empty:border-0">' + pecasPreenchidas + '</div></div>'
      +   '<div class="text-right text-xs text-slate-500 font-semibold pt-2">Subtotal deste veículo: <span class="subtotal-veiculo text-slate-800 text-sm">' + brl(dados.subtotal || 0) + '</span></div>'
      + '</div>';
    }

    function htmlServicoSelecionado(ord, id, nome, preco, pagaComissao, equipeIds, valorComissao) {
      var btnEq = '';
      if (pagaComissao) {
          var texto = equipeIds ? '👤 Equipe Definida' : '👤 Quem fez?';
          var classe = equipeIds ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600';
          // Passa o valor da comissão para checar se está zerado
          btnEq = '<button type="button" onclick="abrirModal(this, \'equipe_' + ord + '_' + id + '\', ' + valorComissao + ')" class="btn-equipe ' + classe + ' px-2 py-1 rounded text-[11px] font-bold ml-2 whitespace-nowrap">' + texto + '</button>';
      }
      return '<div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 px-3 py-2" data-id="' + id + '" data-preco="' + preco + '">'
      + '<input type="hidden" name="servicos_' + ord + '[]" value="' + id + '">'
      + '<input type="hidden" name="servico_equipe_' + ord + '[' + id + ']" id="equipe_' + ord + '_' + id + '" value="' + equipeIds + '">'
      + '<div class="flex-1 text-sm font-semibold text-slate-800 flex items-center justify-between w-full">' + escapeHtml(nome) + btnEq + '</div>'
      + '<div class="text-sm font-bold text-slate-800">' + brl(preco) + '</div>'
      + '<button type="button" class="btn-remover-servico text-rose-600 hover:underline text-xs">Remover</button></div>';
    }

    function htmlPecaSelecionada(ord, id, nome, qtd, preco, descCent) { var subtotal = (preco * qtd) - descCent; return '<div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 px-3 py-2 bg-emerald-50/30 peca-row" data-id="' + id + '" data-preco="' + preco + '"><input type="hidden" name="pecas_' + ord + '[]" value="' + id + '"><div class="flex-1 text-sm font-semibold text-slate-800 w-full">' + escapeHtml(nome) + '</div><div class="flex items-center gap-1 text-xs"><span class="text-slate-500">Qtd:</span><input type="number" name="peca_qtd_' + ord + '[]" value="' + qtd + '" step="0.01" class="input-qtd-peca w-16 rounded border border-slate-300 px-2 py-1 text-center font-bold text-black"></div><div class="flex items-center gap-1 text-xs"><span class="text-slate-500">Desc:</span><span class="text-slate-400">R$</span><input type="text" name="peca_desc_' + ord + '[]" value="' + (descCent > 0 ? (descCent/100).toFixed(2).replace('.',',') : '') + '" placeholder="0,00" class="input-desc-peca w-16 rounded border border-slate-300 px-2 py-1 text-right font-semibold text-black"></div><div class="w-20 text-right text-sm font-bold text-emerald-700 subtotal-peca" data-total="' + subtotal + '">' + brl(subtotal) + '</div><button type="button" class="btn-remover-peca text-rose-600 hover:underline text-xs">Remover</button></div>'; }

    function adicionarBloco(dados) { dados = dados || {}; var ord = proximaOrd++; var tmp = document.createElement('div'); tmp.innerHTML = htmlBlocoVeiculo(ord, dados).trim(); var bloco = tmp.firstElementChild; wrap.appendChild(bloco); atualizarNumeracao(); recalcularGeral(); if (dados.placa && dados.placa.length === 7) setTimeout(function () { consultarPlaca(bloco); }, 150); return bloco; }
    function atualizarNumeracao() { var blocos = wrap.querySelectorAll('.veiculo-block'); blocos.forEach(function (b, i) { b.querySelector('h3').textContent = 'Veículo ' + (i + 1); var btn = b.querySelector('.btn-remover-veiculo'); if (blocos.length > 1) btn.classList.remove('hidden'); else btn.classList.add('hidden'); }); }

    wrap.addEventListener('click', function (e) {
      var t = e.target;

      if (t.closest('.btn-remover-veiculo')) { 
        var b = t.closest('.veiculo-block'); 
        if (wrap.querySelectorAll('.veiculo-block').length <= 1) return; 
        b.remove(); 
        atualizarNumeracao(); 
        recalcularGeral(); 
        return; 
      }

      if (t.closest('.btn-add-servico')) { 
        var b = t.closest('.veiculo-block'); 
        var sel = b.querySelector('.sel-servico'); 
        if (!sel.value) return; 
        var opt = sel.options[sel.selectedIndex]; 
        var lista = b.querySelector('.lista-servicos'); 
        if (lista.querySelector('[data-id="'+sel.value+'"]')) { alert('Serviço já adicionado.'); return; }
        var tmp = document.createElement('div'); 
        var sv = SERVICOS.find(x=>x.id == opt.value) || {comissao:0};
        tmp.innerHTML = htmlServicoSelecionado(b.dataset.ordem, opt.value, opt.dataset.nome, parseInt(opt.dataset.preco), parseInt(opt.dataset.paga), '', sv.comissao); 
        lista.appendChild(tmp.firstElementChild); 
        sel.value = ''; 
        sel.focus(); 
        recalcularGeral(); 
        return; 
      }

      if (t.closest('.btn-add-peca')) { 
        var b = t.closest('.veiculo-block'); 
        var sel = b.querySelector('.sel-peca'); 
        if (!sel.value) return; 
        var opt = sel.options[sel.selectedIndex]; 
        var lista = b.querySelector('.lista-pecas'); 
        if (lista.querySelector('[data-id="'+sel.value+'"]')) { alert('Peça já adicionada. Altere a quantidade.'); return; } 
        var tmp = document.createElement('div'); 
        tmp.innerHTML = htmlPecaSelecionada(b.dataset.ordem, opt.value, opt.dataset.nome, 1, parseInt(opt.dataset.preco), 0); 
        lista.appendChild(tmp.firstElementChild); 
        sel.value = ''; 
        sel.focus(); 
        recalcularGeral(); 
        return; 
      }

      if (t.closest('.btn-remover-servico')) { 
        t.closest('[data-id]').remove(); 
        recalcularGeral(); 
        return; 
      }

      if (t.closest('.btn-remover-peca')) { 
        t.closest('[data-id]').remove(); 
        recalcularGeral(); 
        return; 
      }
    });

    wrap.addEventListener('input', function(e) {
      if (e.target.classList.contains('input-qtd-peca') || e.target.classList.contains('input-desc-peca')) {
        var row = e.target.closest('.peca-row'); var preco = parseInt(row.dataset.preco); var qtd = parseFloat(row.querySelector('.input-qtd-peca').value) || 0; var descStr = row.querySelector('.input-desc-peca').value; var descCent = 0;
        if (descStr.includes(',')) { var p = descStr.split(','); descCent = (parseInt(p[0].replace(/\D/g, ''))||0) * 100 + (parseInt((p[1]+'00').slice(0,2))||0); } else { descCent = (parseInt(descStr.replace(/\D/g, ''))||0) * 100; }
        var subtotal = (preco * qtd) - descCent; if (subtotal < 0) subtotal = 0;
        row.querySelector('.subtotal-peca').textContent = brl(subtotal); recalcularGeral();
      }
    });

    var descInput = document.getElementById('desconto'); var tipoDesc = document.getElementById('desconto_tipo');
    tipoDesc.addEventListener('change', function() { descInput.value = (tipoDesc.value === 'percentual') ? '0' : ''; recalcularGeral(); });
    descInput.addEventListener('input', function() { if (tipoDesc.value === 'percentual') descInput.value = soDigitos(descInput.value).slice(0, 3); else descInput.value = descInput.value.replace(/[^\d.,]/g, ''); recalcularGeral(); });
    descInput.addEventListener('blur', function() { if (tipoDesc.value === 'percentual') descInput.value = String(Math.min(100, parseInt(soDigitos(descInput.value)||'0'))); else descInput.value = formatarMoedaBR(descInput.value); recalcularGeral(); });

    function recalcularGeral() {
      var subGeral = 0;
      wrap.querySelectorAll('.veiculo-block').forEach(function(b) { var subV = 0; b.querySelectorAll('.lista-servicos [data-preco]').forEach(function(el) { subV += parseInt(el.dataset.preco)||0; }); b.querySelectorAll('.subtotal-peca').forEach(function(el) { subV += parseInt(el.dataset.total)||0; }); b.querySelector('.subtotal-veiculo').textContent = brl(subV); subGeral += subV; });
      var desc = 0;
      if (tipoDesc.value === 'percentual') { var p = Math.max(0, Math.min(100, parseInt(soDigitos(descInput.value))||0)); desc = Math.round(subGeral * p / 100); } 
      else { var pStr = descInput.value; if (pStr.includes(',')) { var p = pStr.split(','); desc = ((parseInt(p[0].replace(/\D/g,''))||0)*100) + (parseInt((p[1]+'00').slice(0,2))||0); } else { desc = (parseInt(pStr.replace(/\D/g,''))||0)*100; } if (desc > subGeral) desc = subGeral; }
      document.getElementById('subtotalView').textContent = brl(subGeral); document.getElementById('descView').textContent = '- ' + brl(desc); document.getElementById('totalView').textContent = brl(Math.max(0, subGeral - desc));
    }

    function consultarPlaca(bloco) {
      var input = bloco.querySelector('.input-placa'); if (!input) return; var status = bloco.querySelector('.placa-status'); status.textContent = '...';
      fetch('consultar_placa.php?placa=' + encodeURIComponent(input.value)).then(r=>r.json()).then(d => { 
          if (d.contadores) { bloco.querySelector('.cont-mes').textContent = d.contadores.mes; bloco.querySelector('.cont-total').textContent = d.contadores.total; } 
          if (d.ok) { 
              status.textContent = '✓'; status.className='placa-status absolute right-3 top-2.5 text-xs text-emerald-600'; 
              var inputModelo = bloco.querySelector('.input-modelo'); var inputCor = bloco.querySelector('.input-cor');
              if (inputModelo && d.dados.modelo) inputModelo.value = d.dados.modelo;
              if (inputCor && d.dados.cor) inputCor.value = d.dados.cor;
          } else { 
              status.textContent = '!'; status.className='placa-status absolute right-3 top-2.5 text-xs text-amber-600'; bloco.querySelector('.manual-box').open = true; 
          } 
      }).catch(()=>{ 
          status.textContent = '!'; status.className='placa-status absolute right-3 top-2.5 text-xs text-amber-600'; bloco.querySelector('.manual-box').open = true; 
      });
    }
    wrap.addEventListener('input', function(e) { if (e.target.classList.contains('input-placa')) { e.target.value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 7); var b = e.target.closest('.veiculo-block'); clearTimeout(b.__timer); if (e.target.value.length === 7) b.__timer = setTimeout(()=>consultarPlaca(b), 350); } });

    if (VEICULOS_INICIAIS.length) VEICULOS_INICIAIS.forEach(v => adicionarBloco(v)); else adicionarBloco({ placa: '', itens: [], pecas: [] });
  });
</script>

<?php require __DIR__ . '/footer.php'; ?>
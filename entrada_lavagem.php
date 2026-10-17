<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_entradas();
ensure_lavagens();
ensure_clientes();
ensure_lavagem_veiculos();

$u = usuario_logado();

/* =========================================================
 *  Modo edição?
 * ========================================================= */
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

/* =========================================================
 *  POST: salvar
 * ========================================================= */
$imprimirCupom = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['salvar', 'salvar_imprimir'], true)) {
    csrf_validar();
    $imprimirCupom = ($_POST['acao'] === 'salvar_imprimir');

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

    /* ============ Monta veículos ============ */
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

            if (!validar_placa($placa)) {
                $erros[] = "Veículo {$ord}: informe uma placa válida.";
                continue;
            }
            if (!$servicos_ids) {
                $erros[] = "Veículo {$ord}: selecione ao menos um serviço.";
                continue;
            }

            $servicos_ids = array_values(array_unique($servicos_ids));
            $in = implode(',', array_fill(0, count($servicos_ids), '?'));
            $st = db()->prepare("SELECT * FROM lavagens WHERE id IN ($in) AND ativo = 1");
            $st->execute($servicos_ids);

            $itens = [];
            $subtotalV = 0;
            foreach ($st->fetchAll() as $lav) {
                $itens[] = [
                    'lavagem_id'     => (int)$lav['id'],
                    'nome'           => $lav['nome'],
                    'preco_centavos' => (int)$lav['preco_centavos'],
                ];
                $subtotalV += (int)$lav['preco_centavos'];
            }
            if (!$itens) {
                $erros[] = "Veículo {$ord}: nenhum serviço válido.";
                continue;
            }

            $veiculos[] = [
                'ordem'         => $ord,
                'placa'         => $placa,
                'manual_marca'  => $m_marca,
                'manual_modelo' => $m_modelo,
                'manual_cor'    => $m_cor,
                'manual_ano'    => $m_ano,
                'itens'         => $itens,
                'subtotal'      => $subtotalV,
            ];
            $subtotalGeral += $subtotalV;
        }
    }

    /* ============ Desconto ============ */
    $desc_centavos = 0;
    $desc_percent  = 0;
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

    /* ============ Previsão ============ */
    $prev_dt = '';
    if (!$erros && $horas_prev > 0) {
        $ts = strtotime("$data_entrada $hora_entrada");
        if ($ts !== false) $prev_dt = date('Y-m-d H:i:s', $ts + ($horas_prev * 3600));
    }

    /* ============ Persiste ============ */
    if (!$erros) {
        $placaPrincipal = $veiculos[0]['placa'];
        $veiculoPrincipal = null;
        $vLocal = buscar_veiculo_por_placa($placaPrincipal);
        if ($vLocal) $veiculoPrincipal = (int)$vLocal['id'];

        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($editar) {
                $pdo->prepare("
                    UPDATE lavagem_entradas SET
                        cliente_id = :cid,
                        cliente_nome_avulso = :cna,
                        cliente_celular = :ccel,
                        placa = :placa,
                        veiculo_id = :vid,
                        data_entrada = :data,
                        hora_entrada = :hora,
                        previsao_saida_horas = :phoras,
                        previsao_saida_datetime = :pdt,
                        observacoes = :obs,
                        avarias = :avarias,
                        subtotal_centavos = :sub,
                        desconto_centavos = :desc,
                        desconto_percentual = :descp,
                        total_centavos = :total,
                        atualizado_em = datetime('now','localtime')
                    WHERE id = :id
                ")->execute([
                    ':cid' => $cliente_id ?: null,
                    ':cna' => $cliente_nome,
                    ':ccel'=> $cliente_celular,
                    ':placa'=> $placaPrincipal,
                    ':vid' => $veiculoPrincipal,
                    ':data'=> $data_entrada,
                    ':hora'=> $hora_entrada,
                    ':phoras'=> $horas_prev,
                    ':pdt' => $prev_dt,
                    ':obs' => $observacoes,
                    ':avarias'=> $avarias,
                    ':sub' => $subtotalGeral,
                    ':desc'=> $desc_centavos,
                    ':descp'=> $desc_percent,
                    ':total'=> $total,
                    ':id'  => $id,
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
                         observacoes, avarias, status,
                         subtotal_centavos, desconto_centavos, desconto_percentual,
                         total_centavos, pago, criado_por)
                    VALUES
                        (:cid, :cna, :ccel, :placa, :vid,
                         :data, :hora, :phoras, :pdt,
                         :obs, :avarias, 'aberta',
                         :sub, :desc, :descp,
                         :total, 0, :uid)
                ");
                $stmt->execute([
                    ':cid' => $cliente_id ?: null,
                    ':cna' => $cliente_nome,
                    ':ccel'=> $cliente_celular,
                    ':placa'=> $placaPrincipal,
                    ':vid' => $veiculoPrincipal,
                    ':data'=> $data_entrada,
                    ':hora'=> $hora_entrada,
                    ':phoras'=> $horas_prev,
                    ':pdt' => $prev_dt,
                    ':obs' => $observacoes,
                    ':avarias'=> $avarias,
                    ':sub' => $subtotalGeral,
                    ':desc'=> $desc_centavos,
                    ':descp'=> $desc_percent,
                    ':total'=> $total,
                    ':uid' => (int)$u['id'],
                ]);
                $entrada_id = (int)$pdo->lastInsertId();
                $mensagem = 'Entrada registrada com sucesso.';
            }

            $insV = $pdo->prepare("
                INSERT INTO lavagem_veiculos
                    (entrada_id, ordem, placa, veiculo_id,
                     manual_marca, manual_modelo, manual_cor, manual_ano)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insI = $pdo->prepare("
                INSERT INTO lavagem_itens (entrada_id, veiculo_id, lavagem_id, nome, preco_centavos)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($veiculos as $v) {
                $vLocal = buscar_veiculo_por_placa($v['placa']);
                $vid = $vLocal ? (int)$vLocal['id'] : null;

                if (!$vid && ($v['manual_marca'] || $v['manual_modelo'] || $v['manual_cor'] || $v['manual_ano'])) {
                    $pdo->prepare("
                        INSERT INTO veiculos (placa, marca, modelo, cor, ano_modelo, origem)
                        VALUES (?, ?, ?, ?, ?, 'manual')
                        ON CONFLICT(placa) DO UPDATE SET
                            marca=excluded.marca, modelo=excluded.modelo,
                            cor=excluded.cor, ano_modelo=excluded.ano_modelo,
                            atualizado_em=datetime('now','localtime')
                    ")->execute([
                        $v['placa'], $v['manual_marca'], $v['manual_modelo'],
                        $v['manual_cor'], $v['manual_ano'],
                    ]);
                    $vLocal = buscar_veiculo_por_placa($v['placa']);
                    $vid = $vLocal ? (int)$vLocal['id'] : null;
                }

                $insV->execute([
                    $entrada_id, $v['ordem'], $v['placa'], $vid,
                    $v['manual_marca'], $v['manual_modelo'], $v['manual_cor'], $v['manual_ano'],
                ]);
                $novoVeiculoId = (int)$pdo->lastInsertId();

                foreach ($v['itens'] as $it) {
                    $insI->execute([
                        $entrada_id, $novoVeiculoId, $it['lavagem_id'],
                        $it['nome'], $it['preco_centavos'],
                    ]);
                }
            }

            $pdo->commit();

            flash($mensagem, 'sucesso');
            if ($imprimirCupom) {
                // Abre o cupom direto para impressão
                header('Location: cupom_lavagem.php?entrada=' . $entrada_id . '&auto=1');
            } else {
                // Volta para a listagem (com popup opcional)
                header('Location: entradas.php?cupom=' . $entrada_id);
            }
            exit;

        } catch (Throwable $ex) {
            $pdo->rollBack();
            $erros[] = 'Erro ao salvar: ' . $ex->getMessage();
        }
    }
}

/* =========================================================
 *  Dados para a tela
 * ========================================================= */
$clientes = db()->query(
    'SELECT id, nome, cpf_cnpj, tipo, celular, telefone, email
     FROM clientes WHERE ativo = 1 ORDER BY nome COLLATE NOCASE'
)->fetchAll();

$servicos = db()->query(
    'SELECT * FROM lavagens WHERE ativo = 1 ORDER BY nome COLLATE NOCASE'
)->fetchAll();

/* ---------- Valores iniciais ---------- */
$v = [
    'cliente_id'      => $entrada['cliente_id']          ?? 0,
    'cliente_nome'    => $entrada['cliente_nome_avulso'] ?? '',
    'cliente_celular' => $entrada['cliente_celular']     ?? '',
    'data_entrada'    => $entrada['data_entrada']        ?? date('Y-m-d'),
    'hora_entrada'    => $entrada['hora_entrada']        ?? date('H:i'),
    'previsao_horas'  => (int)($entrada['previsao_saida_horas'] ?? 0),
    'observacoes'     => $entrada['observacoes']         ?? '',
    'avarias'         => $entrada['avarias']             ?? '',
];

$descPercent = (int)($entrada['desconto_percentual'] ?? 0);
$descCent    = (int)($entrada['desconto_centavos']   ?? 0);
if ($descPercent > 0) {
    $v['desconto_tipo'] = 'percentual';
    $v['desconto']      = (string)$descPercent;
} else {
    $v['desconto_tipo'] = 'valor';
    $v['desconto']      = $descCent > 0 ? centavos_para_moeda($descCent) : '0,00';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['desconto_tipo'] = $_POST['desconto_tipo'] ?? $v['desconto_tipo'];
    $v['desconto']      = $_POST['desconto']      ?? $v['desconto'];
}

/* ---------- Veículos pré-carregados ---------- */
$veiculosForm = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($ordens)) {
    foreach ($ordens as $ord) {
        $veiculosForm[] = [
            'ordem'         => $ord,
            'placa'         => normalizar_placa((string)($_POST["placa_$ord"] ?? '')),
            'manual_marca'  => (string)($_POST["manual_marca_$ord"]  ?? ''),
            'manual_modelo' => (string)($_POST["manual_modelo_$ord"] ?? ''),
            'manual_cor'    => (string)($_POST["manual_cor_$ord"]    ?? ''),
            'manual_ano'    => (string)($_POST["manual_ano_$ord"]    ?? ''),
            'itens'         => [],
        ];
    }
} elseif ($editar && $veiculosExistentes) {
    foreach ($veiculosExistentes as $vv) {
        $st = db()->prepare('SELECT lavagem_id, nome, preco_centavos FROM lavagem_itens WHERE veiculo_id = ? ORDER BY id');
        $st->execute([(int)$vv['id']]);
        $veiculosForm[] = [
            'ordem'         => (int)$vv['ordem'],
            'placa'         => $vv['placa'],
            'manual_marca'  => $vv['manual_marca'],
            'manual_modelo' => $vv['manual_modelo'],
            'manual_cor'    => $vv['manual_cor'],
            'manual_ano'    => $vv['manual_ano'],
            'itens'         => $st->fetchAll(),
        ];
    }
}

if (!$veiculosForm) {
    $veiculosForm[] = [
        'ordem' => 1, 'placa' => '',
        'manual_marca' => '', 'manual_modelo' => '',
        'manual_cor' => '', 'manual_ano' => '',
        'itens' => [],
    ];
}

/* Serviços em formato para o JS */
$servicosJS = array_map(function ($s) {
    return [
        'id'    => (int)$s['id'],
        'nome'  => $s['nome'],
        'preco' => (int)$s['preco_centavos'],
    ];
}, $servicos);

$titulo = $editar ? 'Editar entrada de lavagem' : 'Nova entrada de lavagem';
require __DIR__ . '/header.php';
?>

<div class="max-w-5xl mx-auto">

  <!-- ============ BARRA SUPERIOR DISCRETA ============ -->
  <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">
        <?= $editar ? 'Editar entrada' : 'Nova entrada' ?>
      </h1>
      <p class="text-sm text-slate-500">
        <?= $editar ? 'Altere os dados e salve as modificações.' : 'Registre um ou mais veículos na mesma comanda.' ?>
      </p>
    </div>

    <div class="flex items-center gap-3 text-right">
      <div class="text-xs text-slate-500 leading-tight">
        <div>
          <span class="font-semibold text-slate-700" id="hdrData">
            <?= e(date('d/m/Y', strtotime($v['data_entrada']))) ?>
          </span>
          · <span id="hdrHora"><?= e(substr($v['hora_entrada'], 0, 5)) ?></span>
        </div>
        <div>
          Lavagens no mês: <span class="font-semibold text-brand-700" id="hdrContMes">0</span>
          · Total: <span class="font-semibold text-brand-700" id="hdrContTotal">0</span>
        </div>
      </div>
      <a href="entradas.php" class="text-sm text-brand-600 hover:underline font-semibold whitespace-nowrap">← Voltar</a>
    </div>
  </div>

  <form method="post" id="formEntrada" class="bg-white rounded-2xl shadow-card border border-slate-200 p-6 sm:p-8 space-y-6" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="acao" value="salvar">
    <input type="hidden" name="id" value="<?= $editar ? (int)$id : '' ?>">
    <input type="hidden" name="cliente_id" id="cliente_id" value="<?= (int)$v['cliente_id'] ?>">

    <?php if ($editar && ($entrada['pago'] ?? 0)): ?>
      <div class="rounded-lg border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3 text-sm">
        <strong>Atenção:</strong> esta entrada já foi marcada como <strong>paga</strong>.
      </div>
    <?php endif; ?>

    <?php if ($erros): ?>
      <div class="rounded-lg border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm">
        <ul class="list-disc pl-5 space-y-1">
          <?php foreach ($erros as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- ============ CLIENTE ============ -->
    <section class="grid gap-4 sm:grid-cols-2">
      <h2 class="sm:col-span-2 text-sm font-bold text-slate-700 uppercase tracking-wider">Cliente</h2>

      <div>
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Nome do cliente *</label>
        <input type="text" name="cliente_nome" id="cliente_nome" list="listaClientes"
               required autocomplete="off" maxlength="150"
               value="<?= e($v['cliente_nome']) ?>"
               placeholder="Digite o nome ou escolha da lista..."
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
        <datalist id="listaClientes">
          <?php foreach ($clientes as $c): ?>
            <option data-id="<?= (int)$c['id'] ?>"
                    data-celular="<?= e($c['celular'] ?: $c['telefone']) ?>"
                    data-nome="<?= e($c['nome']) ?>"
                    value="<?= e($c['nome']) ?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>

      <div>
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Celular do cliente</label>
        <input type="text" name="cliente_celular" id="cliente_celular"
               inputmode="numeric" maxlength="16"
               value="<?= e($v['cliente_celular']) ?>"
               placeholder="(00) 00000-0000"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
        <p class="mt-1 text-xs text-slate-500">Usado para avisar por SMS quando o veículo estiver pronto.</p>
      </div>
    </section>

    <!-- ============ VEÍCULOS ============ -->
    <section class="pt-4 border-t border-slate-200">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Veículos</h2>
        <button type="button" id="btnAddVeiculo"
                class="rounded-lg bg-brand-500 hover:bg-brand-700 text-white px-4 py-2 text-sm font-semibold shadow-sm">
          + Adicionar veículo
        </button>
      </div>

      <div id="veiculosWrap" class="space-y-4"></div>
    </section>

    <!-- ============ DATAS ============ -->
        <!-- Datas (ocultas — preenchidas automaticamente com o momento atual) -->
    <input type="hidden" name="data_entrada"   id="dataEntrada"  value="<?= e($v['data_entrada']) ?>">
    <input type="hidden" name="hora_entrada"   id="horaEntrada"  value="<?= e(substr($v['hora_entrada'], 0, 5)) ?>">
    <input type="hidden" name="previsao_horas" id="previsaoHoras" value="<?= (int)$v['previsao_horas'] ?>">

    <!-- ============ OBSERVAÇÕES ============ -->
    <section class="grid gap-4 sm:grid-cols-2 pt-4 border-t border-slate-200">
      <h2 class="sm:col-span-2 text-sm font-bold text-slate-700 uppercase tracking-wider">Observações</h2>
      <div>
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Observações do cliente</label>
        <textarea name="observacoes" rows="3" maxlength="1000"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2.5"><?= e($v['observacoes']) ?></textarea>
      </div>
      <div>
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Avarias / itens no veículo</label>
        <textarea name="avarias" rows="3" maxlength="1000"
                  placeholder="Ex.: riscado na porta direita, som no porta-malas..."
                  class="w-full rounded-lg border border-slate-300 px-3 py-2.5"><?= e($v['avarias']) ?></textarea>
      </div>
    </section>

    <!-- ============ DESCONTO E TOTAL ============ -->
        
    <section class="pt-4 border-t border-slate-200">
      <div class="grid gap-4 lg:grid-cols-5">

        <!-- Desconto — ocupa 3/5 da largura -->
        <div class="lg:col-span-3">
          <label class="block text-sm font-bold text-slate-700 mb-1.5">Desconto (aplicado no total)</label>
          <div class="flex gap-2 items-stretch">
            <select name="desconto_tipo" id="desconto_tipo"
                    class="w-24 flex-shrink-0 rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-bold text-black
                           focus:outline-none focus:ring-2 focus:ring-brand-400">
              <option value="valor"      <?= $v['desconto_tipo'] === 'valor'      ? 'selected' : '' ?>>R$</option>
              <option value="percentual" <?= $v['desconto_tipo'] === 'percentual' ? 'selected' : '' ?>>%</option>
            </select>
            <input name="desconto" id="desconto" inputmode="numeric"
                   value="<?= e($v['desconto']) ?>"
                   placeholder="0,00"
                   class="flex-1 min-w-0 rounded-lg border border-slate-300 px-4 py-2.5 text-base font-semibold text-black
                          focus:outline-none focus:ring-2 focus:ring-brand-400">
          </div>
          <p class="mt-1 text-xs text-slate-500">
            Digite o valor e pressione Tab — o campo formata automaticamente.
          </p>
        </div>

        <!-- Totais — ocupa 2/5 da largura -->
        <div class="lg:col-span-2 rounded-xl border border-slate-300 bg-slate-50 px-5 py-4">
          <div class="flex justify-between text-sm">
            <span class="text-slate-500">Subtotal</span>
            <span id="subtotalView" class="font-semibold">R$ 0,00</span>
          </div>
          <div class="flex justify-between text-sm mt-1">
            <span class="text-slate-500">Desconto</span>
            <span id="descView" class="font-semibold text-rose-600">- R$ 0,00</span>
          </div>
          <div class="flex justify-between text-lg font-bold mt-3 pt-3 border-t border-slate-300">
            <span>Total</span>
            <span id="totalView">R$ 0,00</span>
          </div>
        </div>

      </div>
    </section>

        <div class="flex justify-end gap-2 pt-2 flex-wrap">
      <a href="entradas.php"
         class="px-4 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-sm font-semibold">
        Cancelar
      </a>

      <button type="submit" name="acao" value="salvar_imprimir"
              class="px-5 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-white font-semibold shadow-sm">
        🖨️ Imprimir cupom
      </button>

      <button type="submit" name="acao" value="salvar"
              class="px-5 py-2.5 rounded-lg bg-brand-500 hover:bg-brand-700 text-white font-semibold shadow-sm">
        <?= $editar ? 'Salvar alterações' : 'Registrar entrada' ?>
      </button>
    </div>
  </form>
</div>

<script>
(function () {
  /* =========================================================
   *  HELPERS
   * ========================================================= */
  function soDigitos(v) { return String(v || '').replace(/\D+/g, ''); }

  function brl(centavos) {
    return 'R$ ' + (centavos / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function formatarMoedaBR(v) {
    v = String(v || '').trim();
    if (v === '') return '';
    if (v.indexOf(',') !== -1) {
      var partes = v.split(',');
      var int = partes[0].replace(/\D/g, '') || '0';
      var dec = (partes[1].replace(/\D/g, '') + '00').slice(0, 2);
      return int.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + dec;
    }
    var dig = v.replace(/\D/g, '');
    if (dig === '') return '';
    return dig.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',00';
  }

  function mascararCelular(v) {
    v = soDigitos(v).slice(0, 11);
    if (v.length <= 2) return v;
    if (v.length <= 7) return '(' + v.slice(0, 2) + ') ' + v.slice(2);
    if (v.length <= 10) return '(' + v.slice(0, 2) + ') ' + v.slice(2, 6) + '-' + v.slice(6);
    return '(' + v.slice(0, 2) + ') ' + v.slice(2, 7) + '-' + v.slice(7);
  }

  function escapeHtml(s) {
    return String(s || '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  /* =========================================================
   *  DADOS DO SERVIDOR
   * ========================================================= */
  var SERVICOS = <?= json_encode($servicosJS, JSON_UNESCAPED_UNICODE) ?>;
  var VEICULOS_INICIAIS = <?= json_encode($veiculosForm, JSON_UNESCAPED_UNICODE) ?>;

  /* =========================================================
   *  CLIENTE
   * ========================================================= */
  var inputNome = document.getElementById('cliente_nome');
  var inputCel  = document.getElementById('cliente_celular');
  var hiddenCli = document.getElementById('cliente_id');
  var listaCli  = document.getElementById('listaClientes');

  if (inputCel) {
    var aplicarCel = function () { inputCel.value = mascararCelular(inputCel.value); };
    inputCel.addEventListener('input', aplicarCel);
    inputCel.addEventListener('blur',  aplicarCel);
    aplicarCel();
  }

  function vincularCliente() {
    var val = inputNome.value.trim();
    var achou = null;
    listaCli.querySelectorAll('option').forEach(function (o) {
      if (o.dataset.nome === val) achou = o;
    });
    if (achou) {
      hiddenCli.value = achou.dataset.id;
      if (!inputCel.value.trim() && achou.dataset.celular) {
        inputCel.value = achou.dataset.celular;
        inputCel.dispatchEvent(new Event('input'));
      }
    } else {
      hiddenCli.value = 0;
    }
  }
  inputNome.addEventListener('input', vincularCliente);
  inputNome.addEventListener('change', vincularCliente);

  /* =========================================================
   *  CONSTRUÇÃO DO BLOCO DE VEÍCULO (HTML como string)
   * ========================================================= */
  var wrap       = document.getElementById('veiculosWrap');
  var btnAdd     = document.getElementById('btnAddVeiculo');
  var proximaOrd = 1;

  function htmlServicoSelecionado(ord, id, nome, preco) {
    return ''
      + '<div class="flex items-center gap-3 px-3 py-2" data-id="' + id + '" data-preco="' + preco + '">'
      +   '<input type="hidden" name="servicos_' + ord + '[]" value="' + id + '">'
      +   '<div class="flex-1 text-sm font-semibold text-slate-800">' + escapeHtml(nome) + '</div>'
      +   '<div class="text-sm font-bold text-slate-800 whitespace-nowrap">' + brl(preco) + '</div>'
      +   '<button type="button" class="btn-remover text-rose-600 hover:underline text-xs font-semibold">Remover</button>'
      + '</div>';
  }

  function htmlOpcoesServico() {
    var html = '<option value="">Selecione um serviço...</option>';
    SERVICOS.forEach(function (s) {
      html += '<option value="' + s.id + '" data-preco="' + s.preco + '" data-nome="' + escapeHtml(s.nome) + '">'
           + escapeHtml(s.nome) + ' — ' + brl(s.preco)
           + '</option>';
    });
    return html;
  }

  function htmlBlocoVeiculo(ord, dados) {
    dados = dados || {};
    var servicosPreenchidos = '';
    if (dados.itens && dados.itens.length) {
      dados.itens.forEach(function (it) {
        servicosPreenchidos += htmlServicoSelecionado(ord, it.lavagem_id, it.nome, parseInt(it.preco_centavos, 10) || 0);
      });
    }

    return ''
    + '<div class="veiculo-block border border-slate-200 rounded-xl p-4 space-y-3 bg-slate-50/50" data-ordem="' + ord + '">'
    +   '<input type="hidden" name="veiculos_ordens[]" value="' + ord + '">'

    +   '<div class="flex items-center justify-between">'
    +     '<h3 class="text-sm font-bold text-slate-800">Veículo <span class="num-veiculo">' + ord + '</span></h3>'
    +     '<button type="button" class="btn-remover-veiculo text-rose-600 hover:underline text-xs font-semibold hidden">Remover</button>'
    +   '</div>'

    +   '<div class="grid gap-3 sm:grid-cols-6">'
    +     '<div class="sm:col-span-3">'
    +       '<label class="block text-xs font-bold text-slate-600 mb-1">Placa *</label>'
    +       '<div class="relative">'
    +         '<input name="placa_' + ord + '" maxlength="8" autocomplete="off" placeholder="ABC1D23" '
    +                'value="' + escapeHtml(dados.placa || '') + '" '
    +                'class="input-placa w-full rounded-lg border border-slate-300 px-3 py-2.5 uppercase tracking-wider font-semibold text-black">'
    +         '<span class="placa-status absolute right-3 top-2.5 text-xs text-slate-400"></span>'
    +       '</div>'
    +     '</div>'
    +     '<div class="sm:col-span-3">'
    +       '<div class="grid grid-cols-2 gap-2 text-xs">'
    +         '<div class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5">'
    +           '<div class="text-sky-700 font-medium">Lavagens neste mês</div>'
    +           '<div class="text-lg font-bold text-sky-900 cont-mes">0</div>'
    +         '</div>'
    +         '<div class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5">'
    +           '<div class="text-indigo-700 font-medium">Total</div>'
    +           '<div class="text-lg font-bold text-indigo-900 cont-total">0</div>'
    +         '</div>'
    +       '</div>'
    +     '</div>'
    +   '</div>'

    +   '<div class="info-veiculo hidden text-xs text-slate-600 grid grid-cols-2 sm:grid-cols-4 gap-2">'
    +     '<div><span class="text-slate-500">Marca:</span> <strong class="v-marca">—</strong></div>'
    +     '<div><span class="text-slate-500">Modelo:</span> <strong class="v-modelo">—</strong></div>'
    +     '<div><span class="text-slate-500">Ano:</span> <strong class="v-ano">—</strong></div>'
    +     '<div><span class="text-slate-500">Cor:</span> <strong class="v-cor">—</strong></div>'
    +   '</div>'
    +   '<p class="placa-msg text-xs text-slate-400"></p>'

    +   '<details class="manual-box">'
    +     '<summary class="cursor-pointer text-xs text-slate-600 hover:text-brand-600 font-medium">'
    +       'Placa não encontrada? Informe os dados manualmente'
    +     '</summary>'
    +     '<div class="mt-2 grid gap-2 sm:grid-cols-4">'
    +       '<div><label class="block text-[10px] font-bold text-slate-500 mb-0.5">Marca</label>'
    +         '<input name="manual_marca_'  + ord + '" maxlength="40" value="' + escapeHtml(dados.manual_marca  || '') + '" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>'
    +       '<div><label class="block text-[10px] font-bold text-slate-500 mb-0.5">Modelo</label>'
    +         '<input name="manual_modelo_' + ord + '" maxlength="60" value="' + escapeHtml(dados.manual_modelo || '') + '" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>'
    +       '<div><label class="block text-[10px] font-bold text-slate-500 mb-0.5">Cor</label>'
    +         '<input name="manual_cor_'    + ord + '" maxlength="30" value="' + escapeHtml(dados.manual_cor    || '') + '" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>'
    +       '<div><label class="block text-[10px] font-bold text-slate-500 mb-0.5">Ano</label>'
    +         '<input name="manual_ano_'    + ord + '" maxlength="10" value="' + escapeHtml(dados.manual_ano    || '') + '" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>'
    +     '</div>'
    +   '</details>'

    +   '<div>'
    +     '<label class="block text-xs font-bold text-slate-600 mb-1">Serviços deste veículo *</label>'
    +     '<div class="flex flex-wrap sm:flex-nowrap gap-2">'
    +       '<select class="sel-servico flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">'
    +         htmlOpcoesServico()
    +       '</select>'
    +       '<button type="button" class="btn-add-servico rounded-lg bg-sky-600 hover:bg-sky-500 text-white px-4 py-2 text-sm font-semibold whitespace-nowrap">'
    +         '+ Adicionar'
    +       '</button>'
    +     '</div>'
    +     '<div class="lista-servicos mt-2 rounded-lg border border-slate-200 divide-y divide-slate-100 bg-white empty:border-0">'
    +       servicosPreenchidos
    +     '</div>'
    +     '<p class="lista-vazia text-xs text-slate-400 mt-1"' + (servicosPreenchidos ? ' style="display:none"' : '') + '>'
    +       'Nenhum serviço adicionado.'
    +     '</p>'
    +   '</div>'

    +   '<div class="text-right text-xs text-slate-500">'
    +     'Subtotal deste veículo: <strong class="subtotal-veiculo text-slate-800">R$ 0,00</strong>'
    +   '</div>'
    + '</div>';
  }

  function adicionarBloco(dados) {
    dados = dados || {};
    var ord = proximaOrd++;
    var html = htmlBlocoVeiculo(ord, dados);

    var tmp = document.createElement('div');
    tmp.innerHTML = html.trim();
    var bloco = tmp.firstElementChild;
    wrap.appendChild(bloco);

    atualizarNumeracao();
    recalcularBloco(bloco);
    recalcularGeral();

    // Se já tem placa válida, consulta
    var placaInput = bloco.querySelector('.input-placa');
    if (placaInput && placaInput.value.length === 7) {
      setTimeout(function () { consultarPlaca(bloco); }, 150);
    }

    return bloco;
  }

  function atualizarNumeracao() {
    var blocos = wrap.querySelectorAll('.veiculo-block');
    blocos.forEach(function (b, i) {
      var span = b.querySelector('.num-veiculo');
      if (span) span.textContent = String(i + 1);
      var btnRem = b.querySelector('.btn-remover-veiculo');
      if (btnRem) {
        if (blocos.length > 1) btnRem.classList.remove('hidden');
        else btnRem.classList.add('hidden');
      }
    });
    recalcularGeral();
  }

  btnAdd.addEventListener('click', function (e) {
    e.preventDefault();
    adicionarBloco({ placa: '', itens: [] });
  });

  /* =========================================================
   *  DELEGAÇÃO DE EVENTOS NO WRAP
   * ========================================================= */
  wrap.addEventListener('click', function (e) {
    var t = e.target;

    /* Remover veículo */
    if (t.classList.contains('btn-remover-veiculo')) {
      var bloco = t.closest('.veiculo-block');
      if (wrap.querySelectorAll('.veiculo-block').length <= 1) return;
      bloco.remove();
      atualizarNumeracao();
      return;
    }

    /* Adicionar serviço */
    if (t.classList.contains('btn-add-servico')) {
      var bloco2 = t.closest('.veiculo-block');
      var sel = bloco2.querySelector('.sel-servico');
      if (!sel.value) return;
      var opt = sel.options[sel.selectedIndex];
      var id  = opt.value;
      var nome = opt.dataset.nome;
      var preco = parseInt(opt.dataset.preco, 10) || 0;
      var lista = bloco2.querySelector('.lista-servicos');

      /* Verifica duplicata */
      var existe = false;
      lista.querySelectorAll('[data-id]').forEach(function (el) {
        if (el.dataset.id === String(id)) existe = true;
      });
      if (existe) { alert('Esse serviço já foi adicionado.'); return; }

      var ord = bloco2.dataset.ordem;
      var tmp = document.createElement('div');
      tmp.innerHTML = htmlServicoSelecionado(ord, id, nome, preco);
      lista.appendChild(tmp.firstElementChild);

      atualizarVazioServicos(bloco2);
      recalcularBloco(bloco2);
      recalcularGeral();

      sel.value = '';
      sel.focus();
      return;
    }

    /* Remover serviço */
    if (t.classList.contains('btn-remover')) {
      var row = t.closest('[data-id]');
      var bloco3 = t.closest('.veiculo-block');
      row.remove();
      atualizarVazioServicos(bloco3);
      recalcularBloco(bloco3);
      recalcularGeral();
      return;
    }
  });

  /* Enter no select de serviço adiciona */
  wrap.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.classList.contains('sel-servico')) {
      e.preventDefault();
      var btn = e.target.parentNode.querySelector('.btn-add-servico');
      if (btn) btn.click();
    }
  });

  /* Input de placa */
  wrap.addEventListener('input', function (e) {
    if (!e.target.classList.contains('input-placa')) return;
    var input = e.target;
    input.value = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 7);

    var bloco = input.closest('.veiculo-block');
    if (!bloco) return;

    var ord = bloco.dataset.ordem;
    clearTimeout(bloco.__timer);
    if (input.value.length === 7) {
      bloco.__timer = setTimeout(function () { consultarPlaca(bloco); }, 350);
    }
  });

  function atualizarVazioServicos(bloco) {
    var lista = bloco.querySelector('.lista-servicos');
    var vazio = bloco.querySelector('.lista-vazia');
    if (!lista || !vazio) return;
    vazio.style.display = lista.children.length === 0 ? '' : 'none';
  }

  /* =========================================================
   *  CONSULTA APIBRASIL
   * ========================================================= */
  function consultarPlaca(bloco) {
    var input = bloco.querySelector('.input-placa');
    if (!input) return;
    var statusEl  = bloco.querySelector('.placa-status');
    var msgEl     = bloco.querySelector('.placa-msg');
    var infoEl    = bloco.querySelector('.info-veiculo');
    var manualBox = bloco.querySelector('.manual-box');
    var contMes   = bloco.querySelector('.cont-mes');
    var contTotal = bloco.querySelector('.cont-total');

    var p = input.value;
    if (statusEl) {
      statusEl.textContent = '...';
      statusEl.className = 'placa-status absolute right-3 top-2.5 text-xs text-slate-400';
    }
    if (msgEl) msgEl.textContent = 'Consultando APIBrasil...';
    if (infoEl) infoEl.classList.add('hidden');

    fetch('consultar_placa.php?placa=' + encodeURIComponent(p))
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.contadores && contMes && contTotal) {
          contMes.textContent   = d.contadores.mes;
          contTotal.textContent = d.contadores.total;
          if (bloco === wrap.querySelector('.veiculo-block')) {
            var hcm = document.getElementById('hdrContMes');
            var hct = document.getElementById('hdrContTotal');
            if (hcm) hcm.textContent = d.contadores.mes;
            if (hct) hct.textContent = d.contadores.total;
          }
        }
        if (d.ok) {
          if (statusEl) {
            statusEl.textContent = '✓';
            statusEl.className = 'placa-status absolute right-3 top-2.5 text-xs text-emerald-600';
          }
          if (msgEl) msgEl.textContent = d.cache ? 'Dados do cache local.' : 'Dados obtidos da APIBrasil.';
          bloco.querySelector('.v-marca').textContent  = d.dados.marca  || '—';
          bloco.querySelector('.v-modelo').textContent = d.dados.modelo || '—';
          bloco.querySelector('.v-ano').textContent    = (d.dados.ano_modelo || d.dados.ano_fabricacao || '—');
          bloco.querySelector('.v-cor').textContent    = d.dados.cor    || '—';
          if (infoEl) infoEl.classList.remove('hidden');
        } else {
          if (statusEl) {
            statusEl.textContent = '!';
            statusEl.className = 'placa-status absolute right-3 top-2.5 text-xs text-amber-600';
          }
          if (msgEl) msgEl.textContent = d.erro || 'Não foi possível consultar.';
          if (manualBox) manualBox.open = true;
        }
      })
      .catch(function () {
        if (statusEl) {
          statusEl.textContent = '!';
          statusEl.className = 'placa-status absolute right-3 top-2.5 text-xs text-amber-600';
        }
        if (msgEl) msgEl.textContent = 'Falha de conexão.';
        if (manualBox) manualBox.open = true;
      });
  }

  /* =========================================================
   *  DESCONTO E TOTAIS
   * ========================================================= */
  var descInput  = document.getElementById('desconto');
  var tipoDesc   = document.getElementById('desconto_tipo');
  var subtotalEl = document.getElementById('subtotalView');
  var descEl     = document.getElementById('descView');
  var totalEl    = document.getElementById('totalView');

  tipoDesc.addEventListener('change', function () {
    descInput.value = (tipoDesc.value === 'percentual') ? '0' : '';
    recalcularGeral();
  });

  descInput.addEventListener('focus', function () {
    if (tipoDesc.value === 'valor' && (descInput.value === '0,00' || descInput.value === '0')) {
      descInput.value = '';
    }
  });

  descInput.addEventListener('input', function () {
    if (tipoDesc.value === 'percentual') {
      descInput.value = soDigitos(descInput.value).slice(0, 3);
    } else {
      descInput.value = descInput.value.replace(/[^\d.,]/g, '');
    }
    recalcularGeral();
  });

  descInput.addEventListener('blur', function () {
    if (tipoDesc.value === 'percentual') {
      var p = Math.min(100, parseInt(soDigitos(descInput.value) || '0', 10));
      descInput.value = String(p);
    } else {
      descInput.value = formatarMoedaBR(descInput.value);
    }
    recalcularGeral();
  });

  function recalcularBloco(bloco) {
    var sub = 0;
    bloco.querySelectorAll('.lista-servicos [data-preco]').forEach(function (el) {
      sub += parseInt(el.dataset.preco, 10) || 0;
    });
    var elSub = bloco.querySelector('.subtotal-veiculo');
    if (elSub) elSub.textContent = brl(sub);
    return sub;
  }

  function recalcularGeral() {
    var subGeral = 0;
    wrap.querySelectorAll('.veiculo-block').forEach(function (b) {
      subGeral += recalcularBloco(b);
    });

    var desc = 0;
    if (tipoDesc.value === 'percentual') {
      var p = Math.max(0, Math.min(100, parseInt(soDigitos(descInput.value), 10) || 0));
      desc = Math.round(subGeral * p / 100);
    } else {
      desc = parseInt(soDigitos(descInput.value), 10) || 0;
      if (desc > subGeral) desc = subGeral;
    }
    var total = Math.max(0, subGeral - desc);

    subtotalEl.textContent = brl(subGeral);
    descEl.textContent     = '- ' + brl(desc);
    totalEl.textContent    = brl(total);
  }

  /* =========================================================
   *  CABEÇALHO
   * ========================================================= */
    /* Atualiza o cabeçalho discreto com a data/hora atual (uma vez no load) */
  (function atualizarCabecalhoInicial() {
    var dataEl = document.getElementById('dataEntrada');
    var horaEl = document.getElementById('horaEntrada');

    if (dataEl && dataEl.value) {
      var partes = dataEl.value.split('-');
      var hd = document.getElementById('hdrData');
      if (hd) hd.textContent = partes[2] + '/' + partes[1] + '/' + partes[0];
    }
    if (horaEl && horaEl.value) {
      var hh = document.getElementById('hdrHora');
      if (hh) hh.textContent = horaEl.value;
    }
  })();

  /* =========================================================
   *  INICIALIZA
   * ========================================================= */
  if (VEICULOS_INICIAIS && VEICULOS_INICIAIS.length) {
    VEICULOS_INICIAIS.forEach(function (vv) {
      adicionarBloco({
        placa:         vv.placa,
        manual_marca:  vv.manual_marca,
        manual_modelo: vv.manual_modelo,
        manual_cor:    vv.manual_cor,
        manual_ano:    vv.manual_ano,
        itens:         vv.itens || [],
      });
    });
  } else {
    adicionarBloco({ placa: '', itens: [] });
  }

  atualizarCabecalho();
  recalcularGeral();
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
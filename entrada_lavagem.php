<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_entradas();
ensure_lavagens();
ensure_clientes();

$u = usuario_logado();

/* =========================================================
 *  Modo edição?
 * ========================================================= */
$id      = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$editar  = $id > 0;
$entrada = null;
$itensAtuais = [];

if ($editar) {
    $st = db()->prepare('SELECT * FROM lavagem_entradas WHERE id = ?');
    $st->execute([$id]);
    $entrada = $st->fetch();

    if (!$entrada) {
        flash('Entrada não encontrada.', 'erro');
        header('Location: entradas.php');
        exit;
    }

    $st = db()->prepare('SELECT * FROM lavagem_itens WHERE entrada_id = ? ORDER BY id');
    $st->execute([$id]);
    $itensAtuais = $st->fetchAll();
}

$erros = [];

/* ---------- POST: salvar (novo ou edição) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar') {
    csrf_validar();

    $cliente_id      = (int)($_POST['cliente_id'] ?? 0);
    $cliente_nome    = trim((string)($_POST['cliente_nome'] ?? ''));
    $cliente_celular = trim((string)($_POST['cliente_celular'] ?? ''));
    $placa           = normalizar_placa((string)($_POST['placa'] ?? ''));
    $data_entrada    = trim((string)($_POST['data_entrada'] ?? date('Y-m-d')));
    $hora_entrada    = trim((string)($_POST['hora_entrada'] ?? date('H:i')));
    $horas_prev      = max(0, (int)($_POST['previsao_horas'] ?? 0));
    $observacoes     = trim((string)($_POST['observacoes'] ?? ''));
    $avarias         = trim((string)($_POST['avarias'] ?? ''));
    $desconto_tipo   = $_POST['desconto_tipo'] ?? 'valor';
    $desconto_raw    = (string)($_POST['desconto'] ?? '0');
    $servicos_ids    = array_map('intval', (array)($_POST['servicos'] ?? []));

    $m_marca  = trim((string)($_POST['manual_marca']  ?? ''));
    $m_modelo = trim((string)($_POST['manual_modelo'] ?? ''));
    $m_cor    = trim((string)($_POST['manual_cor']    ?? ''));
    $m_ano    = trim((string)($_POST['manual_ano']    ?? ''));

    if ($cliente_nome === '')    $erros[] = 'Informe o nome do cliente.';
    if (!validar_placa($placa))  $erros[] = 'Informe uma placa válida (ABC1234 ou ABC1D23).';
    if (!$servicos_ids)          $erros[] = 'Selecione ao menos um serviço.';
    if ($data_entrada === '')    $erros[] = 'Informe a data de entrada.';
    if ($hora_entrada === '')    $erros[] = 'Informe a hora de entrada.';

    if ($cliente_id > 0) {
        $stC = db()->prepare('SELECT id FROM clientes WHERE id = ? AND ativo = 1');
        $stC->execute([$cliente_id]);
        if (!$stC->fetch()) $cliente_id = 0;
    }

    /* Monta itens com preços atuais */
    $itens = [];
    $subtotal = 0;
    if (!$erros) {
        $servicos_ids = array_values(array_unique($servicos_ids));
        $in = implode(',', array_fill(0, count($servicos_ids), '?'));
        $st = db()->prepare("SELECT * FROM lavagens WHERE id IN ($in) AND ativo = 1");
        $st->execute($servicos_ids);
        foreach ($st->fetchAll() as $lav) {
            $itens[] = [
                'lavagem_id'     => (int)$lav['id'],
                'nome'           => $lav['nome'],
                'preco_centavos' => (int)$lav['preco_centavos'],
            ];
            $subtotal += (int)$lav['preco_centavos'];
        }
        if (!$itens) $erros[] = 'Nenhum dos serviços escolhidos está ativo.';
    }

    /* Desconto */
    $desc_centavos = 0;
    $desc_percent  = 0;
    if (!$erros) {
        if ($desconto_tipo === 'percentual') {
            $desc_percent  = max(0, min(100, (int)preg_replace('/\D/', '', $desconto_raw)));
            $desc_centavos = (int)round($subtotal * $desc_percent / 100);
        } else {
            $desc_centavos = moeda_para_centavos($desconto_raw);
            if ($desc_centavos > $subtotal) $desc_centavos = $subtotal;
        }
    }
    $total = max(0, $subtotal - $desc_centavos);

    /* Previsão de saída */
    $prev_dt = '';
    if (!$erros && $horas_prev > 0) {
        $ts = strtotime("$data_entrada $hora_entrada");
        if ($ts !== false) $prev_dt = date('Y-m-d H:i:s', $ts + ($horas_prev * 3600));
    }

    if (!$erros) {
        /* Veículo: cache local ou dados manuais */
        $veiculo_id = null;
        $vLocal = buscar_veiculo_por_placa($placa);
        if ($vLocal) {
            $veiculo_id = (int)$vLocal['id'];
        } elseif ($m_marca !== '' || $m_modelo !== '' || $m_cor !== '' || $m_ano !== '') {
            db()->prepare("
                INSERT INTO veiculos (placa, marca, modelo, cor, ano_modelo, origem)
                VALUES (?, ?, ?, ?, ?, 'manual')
                ON CONFLICT(placa) DO UPDATE SET
                    marca=excluded.marca, modelo=excluded.modelo,
                    cor=excluded.cor, ano_modelo=excluded.ano_modelo,
                    atualizado_em=datetime('now','localtime')
            ")->execute([$placa, $m_marca, $m_modelo, $m_cor, $m_ano]);
            $vLocal = buscar_veiculo_por_placa($placa);
            $veiculo_id = $vLocal ? (int)$vLocal['id'] : null;
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($editar) {
                /* ---------- UPDATE ---------- */
                $pdo->prepare("
                    UPDATE lavagem_entradas SET
                        cliente_id = :cid,
                        cliente_nome_avulso = :cna,
                        cliente_celular = :ccel,
                        placa = :placa,
                        veiculo_id = :vid,
                        veiculo_manual_marca = :mmarca,
                        veiculo_manual_modelo = :mmodelo,
                        veiculo_manual_cor = :mcor,
                        veiculo_manual_ano = :mano,
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
                    ':cid'    => $cliente_id ?: null,
                    ':cna'    => $cliente_nome,
                    ':ccel'   => $cliente_celular,
                    ':placa'  => $placa,
                    ':vid'    => $veiculo_id,
                    ':mmarca' => $m_marca,
                    ':mmodelo'=> $m_modelo,
                    ':mcor'   => $m_cor,
                    ':mano'   => $m_ano,
                    ':data'   => $data_entrada,
                    ':hora'   => $hora_entrada,
                    ':phoras' => $horas_prev,
                    ':pdt'    => $prev_dt,
                    ':obs'    => $observacoes,
                    ':avarias'=> $avarias,
                    ':sub'    => $subtotal,
                    ':desc'   => $desc_centavos,
                    ':descp'  => $desc_percent,
                    ':total'  => $total,
                    ':id'     => $id,
                ]);

                /* Substitui itens: apaga os antigos e insere os novos */
                $pdo->prepare('DELETE FROM lavagem_itens WHERE entrada_id = ?')->execute([$id]);

                $ins = $pdo->prepare(
                    'INSERT INTO lavagem_itens (entrada_id, lavagem_id, nome, preco_centavos)
                     VALUES (?, ?, ?, ?)'
                );
                foreach ($itens as $it) {
                    $ins->execute([$id, $it['lavagem_id'], $it['nome'], $it['preco_centavos']]);
                }
                $entrada_id = $id;
                $mensagem = 'Entrada atualizada com sucesso.';
            } else {
                /* ---------- INSERT ---------- */
                $stmt = $pdo->prepare("
                    INSERT INTO lavagem_entradas
                        (cliente_id, cliente_nome_avulso, cliente_celular, placa, veiculo_id,
                         veiculo_manual_marca, veiculo_manual_modelo, veiculo_manual_cor, veiculo_manual_ano,
                         data_entrada, hora_entrada, previsao_saida_horas, previsao_saida_datetime,
                         observacoes, avarias, status,
                         subtotal_centavos, desconto_centavos, desconto_percentual,
                         total_centavos, pago, criado_por)
                    VALUES
                        (:cid, :cna, :ccel, :placa, :vid,
                         :mmarca, :mmodelo, :mcor, :mano,
                         :data, :hora, :phoras, :pdt,
                         :obs, :avarias, 'aberta',
                         :sub, :desc, :descp,
                         :total, 0, :uid)
                ");
                $stmt->execute([
                    ':cid'    => $cliente_id ?: null,
                    ':cna'    => $cliente_nome,
                    ':ccel'   => $cliente_celular,
                    ':placa'  => $placa,
                    ':vid'    => $veiculo_id,
                    ':mmarca' => $m_marca,
                    ':mmodelo'=> $m_modelo,
                    ':mcor'   => $m_cor,
                    ':mano'   => $m_ano,
                    ':data'   => $data_entrada,
                    ':hora'   => $hora_entrada,
                    ':phoras' => $horas_prev,
                    ':pdt'    => $prev_dt,
                    ':obs'    => $observacoes,
                    ':avarias'=> $avarias,
                    ':sub'    => $subtotal,
                    ':desc'   => $desc_centavos,
                    ':descp'  => $desc_percent,
                    ':total'  => $total,
                    ':uid'    => (int)$u['id'],
                ]);
                $entrada_id = (int)$pdo->lastInsertId();

                $ins = $pdo->prepare(
                    'INSERT INTO lavagem_itens (entrada_id, lavagem_id, nome, preco_centavos)
                     VALUES (?, ?, ?, ?)'
                );
                foreach ($itens as $it) {
                    $ins->execute([$entrada_id, $it['lavagem_id'], $it['nome'], $it['preco_centavos']]);
                }
                $mensagem = 'Entrada registrada com sucesso.';
            }

            $pdo->commit();

            flash($mensagem, 'sucesso');
            header('Location: entradas.php');
            exit;
        } catch (Throwable $ex) {
            $pdo->rollBack();
            $erros[] = 'Erro ao salvar: ' . $ex->getMessage();
        }
    }

    /* Se deu erro no POST, reaproveita os valores digitados */
    $entrada = array_merge($entrada ?? [], [
        'cliente_id'          => $cliente_id,
        'cliente_nome_avulso' => $cliente_nome,
        'cliente_celular'     => $cliente_celular,
        'placa'               => $placa,
        'data_entrada'        => $data_entrada,
        'hora_entrada'        => $hora_entrada,
        'previsao_saida_horas'=> $horas_prev,
        'observacoes'         => $observacoes,
        'avarias'             => $avarias,
        'veiculo_manual_marca'=> $m_marca,
        'veiculo_manual_modelo'=> $m_modelo,
        'veiculo_manual_cor'  => $m_cor,
        'veiculo_manual_ano'  => $m_ano,
    ]);
}

/* ---------- Dados para a tela ---------- */
$clientes = db()->query(
    'SELECT id, nome, cpf_cnpj, tipo, celular, telefone, email
     FROM clientes WHERE ativo = 1 ORDER BY nome COLLATE NOCASE'
)->fetchAll();

$servicos = db()->query(
    'SELECT * FROM lavagens WHERE ativo = 1 ORDER BY nome COLLATE NOCASE'
)->fetchAll();

/* ---------- Valores iniciais do formulário ---------- */
$v = [
    'cliente_id'          => $entrada['cliente_id']          ?? 0,
    'cliente_nome'        => $entrada['cliente_nome_avulso'] ?? '',
    'cliente_celular'     => $entrada['cliente_celular']     ?? '',
    'placa'               => $entrada['placa']               ?? '',
    'data_entrada'        => $entrada['data_entrada']        ?? date('Y-m-d'),
    'hora_entrada'        => $entrada['hora_entrada']        ?? date('H:i'),
    'previsao_horas'      => (int)($entrada['previsao_saida_horas'] ?? 0),
    'observacoes'         => $entrada['observacoes']         ?? '',
    'avarias'             => $entrada['avarias']             ?? '',
    'manual_marca'        => $entrada['veiculo_manual_marca']  ?? '',
    'manual_modelo'       => $entrada['veiculo_manual_modelo'] ?? '',
    'manual_cor'          => $entrada['veiculo_manual_cor']    ?? '',
    'manual_ano'          => $entrada['veiculo_manual_ano']    ?? '',
];

/* Desconto */
$descPercent = (int)($entrada['desconto_percentual'] ?? 0);
$descCent    = (int)($entrada['desconto_centavos']   ?? 0);
if ($descPercent > 0) {
    $v['desconto_tipo'] = 'percentual';
    $v['desconto']      = (string)$descPercent;
} else {
    $v['desconto_tipo'] = 'valor';
    $v['desconto']      = $descCent > 0 ? centavos_para_moeda($descCent) : '0,00';
}

/* Quando estamos em POST com erro, sobrescreve os valores do desconto */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['desconto_tipo'] = $_POST['desconto_tipo'] ?? $v['desconto_tipo'];
    $v['desconto']      = $_POST['desconto']      ?? $v['desconto'];
}

$pago = (int)($entrada['pago'] ?? 0);

$titulo = $editar ? 'Editar entrada de lavagem' : 'Nova entrada de lavagem';
require __DIR__ . '/header.php';
?>

<script>
/* ========= MÁSCARAS ========= */
(function () {
  const soDigitos = v => (v || '').replace(/\D+/g, '');
  const Mask = {
    celular(v) {
      v = soDigitos(v).slice(0, 11);
      v = v.replace(/^(\d{2})(\d)/, '($1) $2');
      v = v.replace(/(\d{5})(\d)/, '$1-$2');
      return v;
    },
    money(v) {
      v = soDigitos(v);
      if (v === '') return '';
      v = v.padStart(3, '0');
      const int = v.slice(0, -2), dec = v.slice(-2);
      return int.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + dec;
    }
  };
  window.Mask = Mask;
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-mask]').forEach(el => {
      const fn = Mask[el.dataset.mask];
      if (typeof fn !== 'function') return;
      const exec = () => { el.value = fn(el.value); };
      el.addEventListener('input', exec);
      el.addEventListener('blur',  exec);
      exec();
    });
  });
})();
</script>

<div class="max-w-5xl mx-auto">
  <form method="post" id="formEntrada" class="bg-white rounded-xl shadow p-6 sm:p-8 space-y-6" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="acao" value="salvar">
    <input type="hidden" name="id" value="<?= $editar ? (int)$id : '' ?>">
    <input type="hidden" name="cliente_id" id="cliente_id" value="<?= (int)$v['cliente_id'] ?>">

    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold"><?= $editar ? 'Editar entrada' : 'Nova entrada' ?></h1>
        <p class="text-sm text-slate-500">
          <?= $editar ? 'Altere os dados e salve as modificações.' : 'Registre a entrada do veículo para lavagem.' ?>
        </p>
      </div>
      <a href="entradas.php" class="text-sm text-sky-600 hover:underline">← Voltar</a>
    </div>

    <?php if ($editar && $pago): ?>
      <div class="rounded-md border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3 text-sm">
        <strong>Atenção:</strong> esta entrada já foi marcada como <strong>paga</strong>.
        Alterações nos serviços e valores <u>não</u> são refletidas automaticamente no pagamento já registrado.
      </div>
    <?php endif; ?>

    <?php if ($erros): ?>
      <div class="rounded-md border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm">
        <ul class="list-disc pl-5 space-y-1">
          <?php foreach ($erros as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- ============ CLIENTE ============ -->
    <section class="grid gap-4 sm:grid-cols-2 pt-2 border-t border-slate-200">
      <h2 class="sm:col-span-2 text-sm font-semibold text-slate-700">Cliente</h2>

      <div>
        <label class="block text-sm font-medium mb-1">Nome do cliente *</label>
        <input type="text" name="cliente_nome" id="cliente_nome" list="listaClientes"
               required autocomplete="off" maxlength="150"
               value="<?= e($v['cliente_nome']) ?>"
               placeholder="Digite o nome ou escolha da lista..."
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        <datalist id="listaClientes">
          <?php foreach ($clientes as $c): ?>
            <option data-id="<?= (int)$c['id'] ?>"
                    data-celular="<?= e($c['celular'] ?: $c['telefone']) ?>"
                    data-nome="<?= e($c['nome']) ?>"
                    value="<?= e($c['nome']) ?>"></option>
          <?php endforeach; ?>
        </datalist>
        <p class="mt-1 text-xs text-slate-500">
          Você pode <strong>escolher da lista</strong> (alfabética) ou <strong>digitar livremente</strong>.
        </p>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Celular do cliente</label>
        <input type="text" name="cliente_celular" id="cliente_celular" data-mask="celular"
               inputmode="numeric" maxlength="16"
               value="<?= e($v['cliente_celular']) ?>"
               placeholder="(00) 00000-0000"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        <p class="mt-1 text-xs text-slate-500">
          Usado para avisar por SMS quando o veículo estiver pronto.
        </p>
      </div>

      <div class="sm:col-span-2" id="clienteInfo" <?= (int)$v['cliente_id'] > 0 ? '' : 'hidden' ?>>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm">
          <div class="font-medium text-emerald-800" id="cliInfoNome">
            <?php
              if ((int)$v['cliente_id'] > 0) {
                  foreach ($clientes as $c) {
                      if ((int)$c['id'] === (int)$v['cliente_id']) {
                          echo 'Vinculado: ' . e($c['nome']);
                          break;
                      }
                  }
              }
            ?>
          </div>
          <div class="text-xs text-emerald-700">Cliente vinculado ao cadastro — celular preenchido automaticamente.</div>
        </div>
      </div>
    </section>

    <!-- ============ VEÍCULO / PLACA ============ -->
    <section class="grid gap-4 sm:grid-cols-6 pt-2 border-t border-slate-200">
      <h2 class="sm:col-span-6 text-sm font-semibold text-slate-700">Veículo</h2>

      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">Placa *</label>
        <div class="relative">
          <input name="placa" id="placa" required maxlength="8" autocomplete="off"
                 placeholder="ABC1D23"
                 value="<?= e(normalizar_placa($v['placa'])) ?>"
                 class="w-full rounded-lg border border-slate-300 px-3 py-2.5 uppercase tracking-wider focus:outline-none focus:ring-2 focus:ring-sky-500">
          <span id="placaStatus" class="absolute right-3 top-2.5 text-xs text-slate-400"></span>
        </div>
        <p class="mt-1 text-xs text-slate-500">Ao digitar, consulta automaticamente a base APIBrasil.</p>
      </div>

      <div class="sm:col-span-4">
        <div class="grid gap-2 sm:grid-cols-2" id="veiculoInfo" hidden>
          <div class="text-sm"><span class="text-slate-500">Marca:</span> <strong id="vMarca"></strong></div>
          <div class="text-sm"><span class="text-slate-500">Modelo:</span> <strong id="vModelo"></strong></div>
          <div class="text-sm"><span class="text-slate-500">Ano:</span> <strong id="vAno"></strong></div>
          <div class="text-sm"><span class="text-slate-500">Cor:</span> <strong id="vCor"></strong></div>
        </div>
        <p id="placaMsg" class="text-xs text-slate-400"></p>
      </div>

      <div class="sm:col-span-3">
        <div class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3">
          <div class="text-xs text-sky-700">Lavagens neste mês</div>
          <div class="text-2xl font-bold text-sky-900" id="contMes">0</div>
        </div>
      </div>
      <div class="sm:col-span-3">
        <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3">
          <div class="text-xs text-indigo-700">Total de lavagens</div>
          <div class="text-2xl font-bold text-indigo-900" id="contTotal">0</div>
        </div>
      </div>

      <details class="sm:col-span-6 mt-2" id="manualBox" <?= (empty($v['manual_marca']) && empty($v['manual_modelo']) && empty($v['manual_cor']) && empty($v['manual_ano'])) ? '' : 'open' ?>>
        <summary class="cursor-pointer text-sm text-slate-600 hover:text-sky-600">
          Placa não encontrada? Informe os dados do veículo manualmente
        </summary>
        <div class="mt-3 grid gap-3 sm:grid-cols-4">
          <div><label class="block text-xs font-medium mb-1">Marca</label>
            <input name="manual_marca" maxlength="40" value="<?= e($v['manual_marca']) ?>"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
          <div><label class="block text-xs font-medium mb-1">Modelo</label>
            <input name="manual_modelo" maxlength="60" value="<?= e($v['manual_modelo']) ?>"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
          <div><label class="block text-xs font-medium mb-1">Cor</label>
            <input name="manual_cor" maxlength="30" value="<?= e($v['manual_cor']) ?>"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
          <div><label class="block text-xs font-medium mb-1">Ano</label>
            <input name="manual_ano" maxlength="10" value="<?= e($v['manual_ano']) ?>"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
        </div>
      </details>
    </section>

    <!-- ============ DATAS ============ -->
    <section class="grid gap-4 sm:grid-cols-6 pt-2 border-t border-slate-200">
      <h2 class="sm:col-span-6 text-sm font-semibold text-slate-700">Entrada e previsão</h2>

      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">Data de entrada *</label>
        <input type="date" name="data_entrada" required value="<?= e($v['data_entrada']) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>
      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">Hora de entrada *</label>
        <input type="time" name="hora_entrada" required value="<?= e(substr($v['hora_entrada'], 0, 5)) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>
      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">Previsão de saída (horas)</label>
        <input type="number" name="previsao_horas" min="0" max="240" step="1"
               value="<?= (int)$v['previsao_horas'] ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        <p class="mt-1 text-xs text-slate-500">0 = não definida.</p>
      </div>
    </section>

    <!-- ============ OBSERVAÇÕES ============ -->
    <section class="grid gap-4 sm:grid-cols-2 pt-2 border-t border-slate-200">
      <h2 class="sm:col-span-2 text-sm font-semibold text-slate-700">Observações</h2>
      <div>
        <label class="block text-sm font-medium mb-1">Observações do cliente</label>
        <textarea name="observacoes" rows="3" maxlength="1000"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500"><?= e($v['observacoes']) ?></textarea>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Avarias / itens no veículo</label>
        <textarea name="avarias" rows="3" maxlength="1000"
                  placeholder="Ex.: riscado na porta direita, som no porta-malas..."
                  class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500"><?= e($v['avarias']) ?></textarea>
      </div>
    </section>

    <!-- ============ SERVIÇOS ============ -->
    <section class="pt-2 border-t border-slate-200">
      <h2 class="text-sm font-semibold text-slate-700 mb-3">Serviços a realizar *</h2>

      <?php if (!$servicos): ?>
        <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
          Nenhum serviço ativo. Cadastre em <a href="lavagens.php" class="underline">Lavagens</a>.
        </p>
      <?php else: ?>
        <div class="flex flex-wrap sm:flex-nowrap gap-2">
          <select id="selServico"
                  class="flex-1 rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
            <option value="">Selecione um serviço da lista (ordem alfabética)...</option>
            <?php foreach ($servicos as $s): ?>
              <option value="<?= (int)$s['id'] ?>"
                      data-preco="<?= (int)$s['preco_centavos'] ?>"
                      data-nome="<?= e($s['nome']) ?>">
                <?= e($s['nome']) ?> — <?= e(centavos_para_moeda_brl((int)$s['preco_centavos'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="button" id="btnAddServico"
                  class="rounded-lg bg-sky-600 hover:bg-sky-500 text-white px-5 py-2.5 text-sm font-medium whitespace-nowrap">
            + Adicionar
          </button>
        </div>

        <div id="listaServicos"
             class="mt-4 rounded-lg border border-slate-200 divide-y divide-slate-100 empty:border-0">
          <?php foreach ($itensAtuais as $it): ?>
            <div class="flex items-center gap-3 px-4 py-3"
                 data-id="<?= (int)$it['lavagem_id'] ?>"
                 data-preco="<?= (int)$it['preco_centavos'] ?>">
              <input type="hidden" name="servicos[]" value="<?= (int)$it['lavagem_id'] ?>">
              <div class="flex-1 text-sm font-medium"><?= e($it['nome']) ?></div>
              <div class="text-sm font-semibold whitespace-nowrap"><?= e(centavos_para_moeda_brl((int)$it['preco_centavos'])) ?></div>
              <button type="button" class="btn-remover text-rose-600 hover:underline text-xs">Remover</button>
            </div>
          <?php endforeach; ?>
        </div>
        <p id="listaVazia" class="text-xs text-slate-400 mt-3"
           style="<?= $itensAtuais ? 'display:none' : '' ?>">
          Nenhum serviço adicionado ainda.
        </p>
      <?php endif; ?>
    </section>

    <!-- ============ TOTAIS ============ -->
    <section class="pt-2 border-t border-slate-200">
      <div class="grid gap-4 sm:grid-cols-3">
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium mb-1">Desconto</label>
          <div class="flex gap-2">
            <select name="desconto_tipo" id="desconto_tipo"
                    class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
              <option value="valor"      <?= $v['desconto_tipo'] === 'valor'      ? 'selected' : '' ?>>R$</option>
              <option value="percentual" <?= $v['desconto_tipo'] === 'percentual' ? 'selected' : '' ?>>%</option>
            </select>
            <input name="desconto" id="desconto" inputmode="numeric"
                   value="<?= e($v['desconto']) ?>"
                   class="flex-1 rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
          </div>
        </div>
        <div class="rounded-lg border border-slate-300 bg-slate-50 px-4 py-3">
          <div class="flex justify-between text-sm"><span class="text-slate-500">Subtotal</span>
            <span id="subtotal" class="font-medium">R$ 0,00</span></div>
          <div class="flex justify-between text-sm"><span class="text-slate-500">Desconto</span>
            <span id="descView" class="font-medium text-rose-600">- R$ 0,00</span></div>
          <div class="flex justify-between text-lg font-bold mt-2 pt-2 border-t border-slate-300">
            <span>Total</span><span id="totalView">R$ 0,00</span></div>
        </div>
      </div>
    </section>

    <div class="flex justify-end gap-2 pt-2">
      <a href="entradas.php" class="px-4 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-sm font-medium">Cancelar</a>
      <button class="px-5 py-2.5 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-medium">
        <?= $editar ? 'Salvar alterações' : 'Registrar entrada' ?>
      </button>
    </div>
  </form>
</div>

<script>
(function () {
  const soDigitos = v => (v || '').replace(/\D+/g, '');
  const maskMoney = v => {
    v = soDigitos(v);
    if (v === '') return '';
    v = v.padStart(3, '0');
    const int = v.slice(0, -2), dec = v.slice(-2);
    return int.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + dec;
  };
  const brl = c => 'R$ ' + (c / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');

  /* ========== CLIENTE ========== */
  const inputNome = document.getElementById('cliente_nome');
  const inputCel  = document.getElementById('cliente_celular');
  const hiddenCli = document.getElementById('cliente_id');
  const infoCli   = document.getElementById('clienteInfo');
  const infoNome  = document.getElementById('cliInfoNome');
  const listaCli  = document.getElementById('listaClientes');

  function tentarVincularCliente() {
    const val = inputNome.value.trim();
    let achou = null;
    listaCli.querySelectorAll('option').forEach(o => {
      if (o.dataset.nome === val) achou = o;
    });
    if (achou) {
      hiddenCli.value = achou.dataset.id;
      infoNome.textContent = 'Vinculado: ' + achou.dataset.nome;
      infoCli.hidden = false;
      if (!inputCel.value.trim() && achou.dataset.celular) {
        inputCel.value = achou.dataset.celular;
      }
    } else {
      hiddenCli.value = 0;
      infoCli.hidden = true;
    }
  }
  inputNome.addEventListener('input', tentarVincularCliente);
  inputNome.addEventListener('change', tentarVincularCliente);

  /* ========== PLACA → APIBRASIL ========== */
  const placa = document.getElementById('placa');
  const placaStatus = document.getElementById('placaStatus');
  const placaMsg = document.getElementById('placaMsg');
  const vInfo = document.getElementById('veiculoInfo');
  const manualBox = document.getElementById('manualBox');
  const contMes = document.getElementById('contMes');
  const contTotal = document.getElementById('contTotal');
  let timerPlaca = null;

  placa.addEventListener('input', () => {
    placa.value = placa.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 7);
    clearTimeout(timerPlaca);
    if (placa.value.length === 7) {
      timerPlaca = setTimeout(consultarPlaca, 350);
    } else {
      placaStatus.textContent = '';
      placaMsg.textContent = '';
    }
  });

  async function consultarPlaca() {
    const p = placa.value;
    placaStatus.textContent = '...';
    placaStatus.className = 'absolute right-3 top-2.5 text-xs text-slate-400';
    placaMsg.textContent = 'Consultando APIBrasil...';
    vInfo.hidden = true;
    try {
      const r = await fetch('consultar_placa.php?placa=' + encodeURIComponent(p));
      const d = await r.json();
      if (d.contadores) {
        contMes.textContent = d.contadores.mes;
        contTotal.textContent = d.contadores.total;
      }
      if (d.ok) {
        placaStatus.textContent = '✓';
        placaStatus.className = 'absolute right-3 top-2.5 text-xs text-emerald-600';
        placaMsg.textContent = d.cache ? 'Dados do cache local.' : 'Dados obtidos da APIBrasil.';
        document.getElementById('vMarca').textContent  = d.dados.marca  || '—';
        document.getElementById('vModelo').textContent = d.dados.modelo || '—';
        document.getElementById('vAno').textContent    = (d.dados.ano_modelo || d.dados.ano_fabricacao || '—');
        document.getElementById('vCor').textContent    = d.dados.cor    || '—';
        vInfo.hidden = false;
      } else {
        placaStatus.textContent = '!';
        placaStatus.className = 'absolute right-3 top-2.5 text-xs text-amber-600';
        placaMsg.textContent = d.erro || 'Não foi possível consultar.';
        manualBox.open = true;
      }
    } catch (e) {
      placaStatus.textContent = '!';
      placaStatus.className = 'absolute right-3 top-2.5 text-xs text-amber-600';
      placaMsg.textContent = 'Falha de conexão.';
      manualBox.open = true;
    }
  }

  /* Se já existe placa (modo edição), carrega contadores e info do cache */
  if (placa.value.length === 7) {
    consultarPlaca();
  }

  /* ========== SERVIÇOS ========== */
  const selServico = document.getElementById('selServico');
  const btnAdd     = document.getElementById('btnAddServico');
  const listaEl    = document.getElementById('listaServicos');
  const listaVazia = document.getElementById('listaVazia');
  const adicionados = new Set();

  // Popula o set com os itens já existentes (edição)
  listaEl.querySelectorAll('[data-id]').forEach(el => adicionados.add(el.dataset.id));

  function atualizarEstadoVazio() {
    if (!listaVazia) return;
    listaVazia.style.display = listaEl.children.length === 0 ? '' : 'none';
  }

  function adicionarServico() {
    if (!selServico || !selServico.value) return;
    const id = selServico.value;
    if (adicionados.has(id)) { alert('Esse serviço já foi adicionado.'); return; }
    const opt   = selServico.options[selServico.selectedIndex];
    const nome  = opt.dataset.nome;
    const preco = parseInt(opt.dataset.preco, 10) || 0;
    adicionados.add(id);

    const row = document.createElement('div');
    row.className = 'flex items-center gap-3 px-4 py-3';
    row.dataset.id    = id;
    row.dataset.preco = preco;
    row.innerHTML = `
      <input type="hidden" name="servicos[]" value="${id}">
      <div class="flex-1 text-sm font-medium">${nome}</div>
      <div class="text-sm font-semibold whitespace-nowrap">${brl(preco)}</div>
      <button type="button" class="btn-remover text-rose-600 hover:underline text-xs">Remover</button>
    `;
    listaEl.appendChild(row);
    selServico.value = '';
    selServico.focus();
    atualizarEstadoVazio();
    recalcular();
  }

  btnAdd?.addEventListener('click', adicionarServico);
  selServico?.addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); adicionarServico(); }
  });
  listaEl?.addEventListener('click', e => {
    if (!e.target.classList.contains('btn-remover')) return;
    const row = e.target.closest('[data-id]');
    adicionados.delete(row.dataset.id);
    row.remove();
    atualizarEstadoVazio();
    recalcular();
  });

  /* ========== DESCONTO ========== */
  const descInput = document.getElementById('desconto');
  const tipoDesc  = document.getElementById('desconto_tipo');
  const subtotalEl = document.getElementById('subtotal');
  const descEl     = document.getElementById('descView');
  const totalEl    = document.getElementById('totalView');

  function aplicarMascaraDesconto() {
    if (tipoDesc.value === 'percentual') {
      descInput.value = soDigitos(descInput.value).slice(0, 3);
    } else {
      descInput.value = maskMoney(descInput.value);
    }
  }
  aplicarMascaraDesconto();

  descInput.addEventListener('input', () => { aplicarMascaraDesconto(); recalcular(); });
  tipoDesc.addEventListener('change', () => {
    if (tipoDesc.value === 'percentual') {
      descInput.value = '0';
    } else {
      descInput.value = '0,00';
    }
    recalcular();
  });

  /* ========== TOTAIS ========== */
  function recalcular() {
    let sub = 0;
    listaEl.querySelectorAll('[data-preco]').forEach(el => {
      sub += parseInt(el.dataset.preco, 10) || 0;
    });
    let desc = 0;
    if (tipoDesc.value === 'percentual') {
      const p = Math.max(0, Math.min(100, parseInt(soDigitos(descInput.value), 10) || 0));
      desc = Math.round(sub * p / 100);
    } else {
      desc = parseInt(soDigitos(descInput.value), 10) || 0;
      if (desc > sub) desc = sub;
    }
    const total = Math.max(0, sub - desc);
    subtotalEl.textContent = brl(sub);
    descEl.textContent     = '- ' + brl(desc);
    totalEl.textContent    = brl(total);
  }

  atualizarEstadoVazio();
  recalcular();
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
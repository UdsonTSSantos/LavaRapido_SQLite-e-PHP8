<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_entradas();

/* ---------- Variáveis usadas em toda a página ---------- */
$formas = formas_pagamento();

/* =========================================================
 *  POST: salvar pagamento
 * ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'pagar') {
    csrf_validar();

    $entrada_id = (int)($_POST['entrada_id'] ?? 0);
    $valor_raw  = (string)($_POST['valor'] ?? '0');
    $data_pgto  = trim((string)($_POST['data_pagamento'] ?? date('Y-m-d')));
    $forma      = (string)($_POST['forma_pagamento'] ?? '');
    $obs        = trim((string)($_POST['observacao'] ?? ''));

    $erros = [];
    if ($entrada_id <= 0)        $erros[] = 'Entrada inválida.';
    if (!isset($formas[$forma])) $erros[] = 'Forma de pagamento inválida.';
    if ($data_pgto === '')       $erros[] = 'Informe a data de pagamento.';

    $valor_cent = moeda_para_centavos($valor_raw);
    if ($valor_cent <= 0) $erros[] = 'Informe um valor válido.';

    $entrada = null;
    if (!$erros) {
        $st = db()->prepare('SELECT * FROM lavagem_entradas WHERE id = ?');
        $st->execute([$entrada_id]);
        $entrada = $st->fetch();
        if (!$entrada) $erros[] = 'Entrada não encontrada.';
    }

    if (!$erros) {
        $recibo = gerar_numero_recibo();
        $u = usuario_logado();

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                INSERT INTO pagamentos
                    (entrada_id, cliente_id, placa, data_pagamento,
                     valor_centavos, forma_pagamento, observacao,
                     recibo_numero, criado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $entrada_id,
                (int)($entrada['cliente_id'] ?? 0) ?: null,
                $entrada['placa'],
                $data_pgto,
                $valor_cent,
                $forma,
                $obs,
                $recibo,
                (int)$u['id'],
            ]);

            $pdo->prepare("
                UPDATE lavagem_entradas
                SET pago = 1, status = 'entregue',
                    atualizado_em = datetime('now','localtime')
                WHERE id = ?
            ")->execute([$entrada_id]);

            $pdo->commit();

            flash('Pagamento registrado. Recibo: ' . $recibo, 'sucesso');
            header('Location: recibo.php?entrada=' . $entrada_id . '&recibo=' . urlencode($recibo));
            exit;
        } catch (Throwable $ex) {
            $pdo->rollBack();
            flash('Erro ao registrar pagamento: ' . $ex->getMessage(), 'erro');
            header('Location: entradas.php');
            exit;
        }
    }

    if ($erros) {
        flash(implode(' ', $erros), 'erro');
        header('Location: entradas.php');
        exit;
    }
}

/* =========================================================
 *  POST: alterar status (aberta / concluida / entregue)
 * ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'status') {
    csrf_validar();
    $id     = (int)($_POST['id'] ?? 0);
    $status = (string)($_POST['status'] ?? 'aberta');
    $permitidos = ['aberta', 'concluida', 'entregue'];

    if ($id > 0 && in_array($status, $permitidos, true)) {
        db()->prepare("
            UPDATE lavagem_entradas
            SET status = ?, atualizado_em = datetime('now','localtime')
            WHERE id = ?
        ")->execute([$status, $id]);

        if ($status === 'concluida') {
            $st = db()->prepare('SELECT * FROM lavagem_entradas WHERE id = ?');
            $st->execute([$id]);
            $entrada = $st->fetch();

            if ($entrada
                && (int)$entrada['sms_enviado'] === 0
                && !empty($entrada['cliente_celular'])) {

                $msg = sms_veiculo_pronto($entrada);
                $r   = enviar_sms($entrada['cliente_celular'], $msg);

                if (!empty($r['ok'])) {
                    db()->prepare("
                        UPDATE lavagem_entradas
                        SET sms_enviado = 1,
                            sms_enviado_em = datetime('now','localtime')
                        WHERE id = ?
                    ")->execute([$id]);
                    flash('Status atualizado. SMS enviado para ' . $entrada['cliente_celular'] . '.', 'sucesso');
                } else {
                    flash(
                        'Status atualizado, mas houve falha no SMS: '
                        . ($r['erro'] ?? 'erro desconhecido'),
                        'erro'
                    );
                }
            } else {
                flash('Status atualizado.', 'sucesso');
            }
        } else {
            flash('Status atualizado.', 'sucesso');
        }
    }
    header('Location: entradas.php');
    exit;
}

/* =========================================================
 *  Busca / filtros
 * ========================================================= */
$busca  = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$where  = [];
$params = [];

if ($busca !== '') {
    $where[] = "(e.placa LIKE :q OR c.nome LIKE :q OR e.cliente_nome_avulso LIKE :q OR e.veiculo_manual_modelo LIKE :q)";
    $params[':q'] = '%' . $busca . '%';
}
if (in_array($status, ['aberta', 'concluida', 'entregue'], true)) {
    $where[] = 'e.status = :st';
    $params[':st'] = $status;
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT e.*, c.nome AS cliente_nome_cadastro
        FROM lavagem_entradas e
        LEFT JOIN clientes c ON c.id = e.cliente_id
        $sqlWhere
        ORDER BY e.data_entrada DESC, e.hora_entrada DESC, e.id DESC
        LIMIT 500";

$st = db()->prepare($sql);
$st->execute($params);
$entradas = $st->fetchAll();

$titulo = 'Entradas de lavagem';
require __DIR__ . '/header.php';
?>

<div class="bg-white rounded-xl shadow overflow-hidden">
  <div class="px-5 py-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-xl font-semibold">Entradas de lavagem</h1>
      <p class="text-xs text-slate-500"><?= count($entradas) ?> registro(s)</p>
    </div>
    <div class="flex items-center gap-2">
      <form method="get" class="flex flex-wrap gap-2">
        <input type="text" name="q" value="<?= e($busca) ?>" placeholder="Buscar por placa ou cliente"
               class="w-56 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
        <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
          <option value="">Todos os status</option>
          <option value="aberta"    <?= $status === 'aberta'    ? 'selected' : '' ?>>Aberta</option>
          <option value="concluida" <?= $status === 'concluida' ? 'selected' : '' ?>>Concluída</option>
          <option value="entregue"  <?= $status === 'entregue'  ? 'selected' : '' ?>>Entregue</option>
        </select>
        <button class="rounded-lg bg-slate-800 hover:bg-slate-700 text-white px-4 text-sm">Filtrar</button>
      </form>
      <a href="entrada_lavagem.php"
         class="rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 text-sm font-medium whitespace-nowrap">
        + Nova entrada
      </a>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-slate-600">
        <tr>
          <th class="text-left px-4 py-2">Entrada</th>
          <th class="text-left px-4 py-2">Cliente</th>
          <th class="text-left px-4 py-2">Placa</th>
          <th class="text-right px-4 py-2">Total</th>
          <th class="text-left px-4 py-2">Status</th>
          <th class="px-4 py-2"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php if (!$entradas): ?>
        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">
          Nenhuma entrada registrada.
        </td></tr>
      <?php else: ?>
        <?php foreach ($entradas as $en): ?>
          <?php
            // Nome a exibir: cliente do cadastro ou nome avulso
            $nomeExibir = $en['cliente_nome_cadastro'] ?: ($en['cliente_nome_avulso'] ?: '—');
            $celExibir  = $en['cliente_celular'] ?? '';
            $ehAvulso   = empty($en['cliente_id']);
          ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-2.5 text-xs">
              <div class="font-medium"><?= e(date('d/m/Y', strtotime($en['data_entrada']))) ?></div>
              <div class="text-slate-500"><?= e(substr($en['hora_entrada'], 0, 5)) ?></div>
              <?php if ($en['previsao_saida_datetime']): ?>
                <div class="text-slate-400">prev. <?= e(date('d/m H:i', strtotime($en['previsao_saida_datetime']))) ?></div>
              <?php endif; ?>
            </td>
            <td class="px-4 py-2.5">
              <div class="font-medium">
                <?= e($nomeExibir) ?>
                <?php if ($ehAvulso && $nomeExibir !== '—'): ?>
                  <span class="text-[10px] uppercase text-slate-400 ml-1">avulso</span>
                <?php endif; ?>
              </div>
              <?php if ($celExibir): ?>
                <div class="text-xs text-slate-500"><?= e($celExibir) ?></div>
              <?php endif; ?>
            </td>
            <td class="px-4 py-2.5">
              <div class="font-mono font-medium"><?= e(formatar_placa($en['placa'])) ?></div>
              <?php $desc = trim(($en['veiculo_manual_marca'] ?? '') . ' ' . ($en['veiculo_manual_modelo'] ?? '')); ?>
              <?php if ($desc): ?><div class="text-xs text-slate-500"><?= e($desc) ?></div><?php endif; ?>
            </td>
            <td class="px-4 py-2.5 text-right font-medium whitespace-nowrap">
              <?= e(centavos_para_moeda_brl((int)$en['total_centavos'])) ?>
              <?php if ((int)$en['desconto_centavos'] > 0): ?>
                <div class="text-xs text-rose-600 font-normal">
                  - <?= e(centavos_para_moeda_brl((int)$en['desconto_centavos'])) ?>
                </div>
              <?php endif; ?>
            </td>
            <td class="px-4 py-2.5">
              <div class="flex flex-col gap-1">
                <?php if ($en['status'] === 'aberta'): ?>
                  <span class="inline-block text-xs px-2 py-0.5 rounded bg-amber-100 text-amber-700 w-fit">Aberta</span>
                <?php elseif ($en['status'] === 'concluida'): ?>
                  <span class="inline-block text-xs px-2 py-0.5 rounded bg-sky-100 text-sky-700 w-fit">Concluída</span>
                <?php else: ?>
                  <span class="inline-block text-xs px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 w-fit">Entregue</span>
                <?php endif; ?>
                <?php if ($en['pago']): ?>
                  <span class="inline-block text-xs px-2 py-0.5 rounded bg-emerald-600 text-white w-fit">Pago</span>
                <?php endif; ?>
              </div>
            </td>
            <td class="px-4 py-2.5 text-right whitespace-nowrap">
              <!-- Editar -->
              <a href="entrada_lavagem.php?id=<?= (int)$en['id'] ?>"
                 class="text-slate-700 hover:underline text-xs mr-2">Editar</a>

              <!-- Concluir -->
              <?php if ($en['status'] === 'aberta'): ?>
                <form method="post" class="inline">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="acao" value="status">
                  <input type="hidden" name="id" value="<?= (int)$en['id'] ?>">
                  <input type="hidden" name="status" value="concluida">
                  <button class="text-sky-600 hover:underline text-xs mr-2">Concluir</button>
                </form>
              <?php endif; ?>

              <!-- Entregar -->
              <?php if ($en['status'] === 'concluida'): ?>
                <form method="post" class="inline">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="acao" value="status">
                  <input type="hidden" name="id" value="<?= (int)$en['id'] ?>">
                  <input type="hidden" name="status" value="entregue">
                  <button class="text-emerald-600 hover:underline text-xs mr-2">Entregar</button>
                </form>
              <?php endif; ?>

              <!-- Pagar / Recibo -->
              <?php if (!$en['pago']): ?>
                <button type="button"
                        class="btn-pagar text-emerald-700 hover:underline text-xs mr-2"
                        data-id="<?= (int)$en['id'] ?>"
                        data-placa="<?= e(formatar_placa($en['placa'])) ?>"
                        data-cliente="<?= e($nomeExibir) ?>"
                        data-total="<?= e(centavos_para_moeda((int)$en['total_centavos'])) ?>">
                  Marcar como pago
                </button>
              <?php else: ?>
                <a href="recibo.php?entrada=<?= (int)$en['id'] ?>"
                   class="text-slate-600 hover:underline text-xs mr-2">Recibo</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ============ MODAL DE PAGAMENTO ============ -->
<div id="modalPagar" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-lg">
    <form method="post" id="formPagar">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="acao" value="pagar">
      <input type="hidden" name="entrada_id" id="pgEntradaId">

      <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
        <h3 class="font-semibold">Registrar pagamento</h3>
        <button type="button" id="fecharModal" class="text-slate-400 hover:text-slate-700 text-xl leading-none">&times;</button>
      </div>

      <div class="px-6 py-4 space-y-4">
        <div class="rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 text-sm">
          <div class="flex justify-between"><span class="text-slate-500">Cliente:</span>
            <strong id="pgCliente"></strong></div>
          <div class="flex justify-between"><span class="text-slate-500">Placa:</span>
            <strong id="pgPlaca" class="font-mono"></strong></div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
          <div>
            <label class="block text-sm font-medium mb-1">Data do pagamento *</label>
            <input type="date" name="data_pagamento" required
                   value="<?= date('Y-m-d') ?>"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Valor pago (R$) *</label>
            <input name="valor" id="pgValor" required inputmode="numeric"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Forma de pagamento *</label>
          <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
            <?php foreach ($formas as $k => $label): ?>
              <label class="cursor-pointer">
                <input type="radio" name="forma_pagamento" value="<?= e($k) ?>" class="peer sr-only"
                       <?= $k === 'PIX' ? 'checked' : '' ?>>
                <span class="block text-center px-2 py-2 rounded-lg border border-slate-300 text-xs
                             peer-checked:bg-sky-600 peer-checked:text-white peer-checked:border-sky-600">
                  <?= e($label) ?>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Observação</label>
          <input name="observacao" maxlength="200"
                 class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        </div>
      </div>

      <div class="px-6 py-4 border-t border-slate-200 flex justify-end gap-2">
        <button type="button" id="cancelarModal"
                class="px-4 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-sm">Cancelar</button>
        <button class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-medium">
          Salvar e gerar recibo
        </button>
      </div>
    </form>
  </div>
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

  const modal = document.getElementById('modalPagar');
  const valor = document.getElementById('pgValor');

  document.querySelectorAll('.btn-pagar').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('pgEntradaId').value     = btn.dataset.id;
      document.getElementById('pgPlaca').textContent   = btn.dataset.placa;
      document.getElementById('pgCliente').textContent = btn.dataset.cliente;
      valor.value = btn.dataset.total;
      modal.classList.remove('hidden');
    });
  });

  const fechar = () => modal.classList.add('hidden');
  document.getElementById('fecharModal').addEventListener('click', fechar);
  document.getElementById('cancelarModal').addEventListener('click', fechar);
  modal.addEventListener('click', e => { if (e.target === modal) fechar(); });

  valor.addEventListener('input', () => { valor.value = maskMoney(valor.value); });
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
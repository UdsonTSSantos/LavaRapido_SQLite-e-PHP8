<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_pagamentos();

$formas = formas_pagamento();

/* =========================================================
 *  AÇÕES
 * ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = $_POST['acao'] ?? '';

    /* ---------- Salvar rápido (form inline) ---------- */
    if ($acao === 'salvar_rapido') {
        $tipo_pessoa     = (string)($_POST['tipo_pessoa'] ?? '');
        $pessoa_id       = (int)($_POST['pessoa_id'] ?? 0);
        $pessoa_nome     = trim((string)($_POST['pessoa_nome'] ?? ''));
        $descricao       = trim((string)($_POST['descricao'] ?? ''));
        $valor_raw       = (string)($_POST['valor'] ?? '0');
        $data_vencimento = trim((string)($_POST['data_vencimento'] ?? ''));
        $categoria_id    = (int)($_POST['categoria_id'] ?? 0);
        $pago            = !empty($_POST['pago_chk']);
        $data_pagamento  = trim((string)($_POST['data_pagamento'] ?? ''));
        $forma_pagamento = trim((string)($_POST['forma_pagamento'] ?? ''));

        $erros = [];
        if (!in_array($tipo_pessoa, ['avulso','cliente','fornecedor','usuario'], true)) {
            $erros[] = 'Escolha o tipo de favorecido.';
        }
        if ($tipo_pessoa === 'avulso') {
            if ($pessoa_nome === '') $erros[] = 'Informe o nome do favorecido.';
        } else {
            if ($pessoa_id <= 0 || $pessoa_nome === '') $erros[] = 'Escolha o favorecido.';
        }
        if ($descricao === '')       $erros[] = 'Informe a descrição.';
        if ($categoria_id <= 0)      $erros[] = 'Escolha a categoria.';
        if ($data_vencimento === '') $erros[] = 'Informe o vencimento.';

        $valor_centavos = moeda_para_centavos($valor_raw);
        if ($valor_centavos <= 0) $erros[] = 'Informe um valor válido.';

        if ($pago) {
            if ($data_pagamento === '')  $erros[] = 'Informe a data do pagamento.';
            if ($forma_pagamento === '' || !isset($formas[$forma_pagamento])) {
                $erros[] = 'Informe a forma de pagamento.';
            }
        }

        if ($erros) {
            flash(implode(' ', $erros), 'erro');
        } else {
            $u = usuario_logado();
            db()->prepare("
                INSERT INTO pagamentos_despesas
                    (tipo_pessoa, pessoa_id, pessoa_nome, categoria_id, descricao,
                     valor_centavos, data_vencimento, data_pagamento, forma_pagamento,
                     criado_por)
                VALUES
                    (:tp, :pid, :pn, :cat, :desc, :val, :venc, :pgto, :forma, :uid)
            ")->execute([
                ':tp'   => $tipo_pessoa,
                ':pid'  => $tipo_pessoa === 'avulso' ? null : $pessoa_id,
                ':pn'   => $pessoa_nome,
                ':cat'  => $categoria_id,
                ':desc' => $descricao,
                ':val'  => $valor_centavos,
                ':venc' => $data_vencimento,
                ':pgto' => $pago ? $data_pagamento : '',
                ':forma'=> $pago ? $forma_pagamento : '',
                ':uid'  => (int)$u['id'],
            ]);
            flash('Pagamento cadastrado.', 'sucesso');
        }
        header('Location: pagamentos.php');
        exit;
    }

    /* ---------- Marcar pago ---------- */
    if ($acao === 'marcar_pago') {
        $id    = (int)($_POST['id'] ?? 0);
        $data  = (string)($_POST['data_pagamento'] ?? date('Y-m-d'));
        $forma = (string)($_POST['forma_pagamento'] ?? 'PIX');
        if ($id > 0 && $data !== '' && isset($formas[$forma])) {
            db()->prepare("
                UPDATE pagamentos_despesas
                SET data_pagamento = ?, forma_pagamento = ?,
                    atualizado_em = datetime('now','localtime')
                WHERE id = ?
            ")->execute([$data, $forma, $id]);
            flash('Pagamento registrado.', 'sucesso');
        }
        header('Location: pagamentos.php');
        exit;
    }

    /* ---------- Desfazer pagamento ---------- */
    if ($acao === 'desfazer_pago') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db()->prepare("
                UPDATE pagamentos_despesas
                SET data_pagamento = '', forma_pagamento = '',
                    atualizado_em = datetime('now','localtime')
                WHERE id = ?
            ")->execute([$id]);
            flash('Pagamento desfeito.', 'sucesso');
        }
        header('Location: pagamentos.php');
        exit;
    }

    /* ---------- Excluir ---------- */
    if ($acao === 'excluir') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db()->prepare('DELETE FROM pagamentos_despesas WHERE id = ?')->execute([$id]);
            flash('Pagamento excluído.', 'sucesso');
        }
        header('Location: pagamentos.php');
        exit;
    }
}

/* =========================================================
 *  DADOS
 * ========================================================= */
$anoMes = date('Y-m');

$pagoMes = (int)db()->query("
    SELECT COALESCE(SUM(valor_centavos),0) FROM pagamentos_despesas
    WHERE data_pagamento LIKE '" . $anoMes . "%'
")->fetchColumn();

$aberto = (int)db()->query("
    SELECT COALESCE(SUM(valor_centavos),0) FROM pagamentos_despesas
    WHERE data_pagamento = ''
")->fetchColumn();

$categorias = listar_categorias_pagamento(true);

$pessoas = [
    'cliente'    => db()->query("SELECT id, nome, cpf_cnpj AS doc FROM clientes WHERE ativo=1 ORDER BY nome COLLATE NOCASE")->fetchAll(),
    'fornecedor' => db()->query("SELECT id, nome, cpf_cnpj AS doc FROM fornecedores WHERE ativo=1 ORDER BY nome COLLATE NOCASE")->fetchAll(),
    'usuario'    => db()->query("SELECT id, nome, email AS doc FROM usuarios WHERE ativo=1 ORDER BY nome COLLATE NOCASE")->fetchAll(),
];

/* Lista simples: apenas os campos que serão exibidos */
$pagamentos = db()->query("
    SELECT p.id, p.data_vencimento, p.data_pagamento, p.forma_pagamento,
           p.tipo_pessoa, p.pessoa_nome, p.valor_centavos,
           p.descricao
    FROM pagamentos_despesas p
    ORDER BY p.data_vencimento DESC, p.id DESC
    LIMIT 200
")->fetchAll();

$titulo = 'Pagamentos';
require __DIR__ . '/header.php';
?>

<!-- ============ CABEÇALHO COMPACTO ============ -->
<div class="bg-white rounded-xl shadow px-5 py-4 mb-4 flex flex-wrap items-center justify-between gap-4">
  <div class="flex items-center gap-6">
    <div>
      <h1 class="text-lg font-semibold leading-tight">Contas a pagar</h1>
      <p class="text-xs text-slate-500"><?= count($pagamentos) ?> registro(s)</p>
    </div>
    <div class="pl-6 border-l border-slate-200">
      <div class="text-xs text-slate-500">Pago</div>
      <div class="font-bold text-emerald-700"><?= e(centavos_para_moeda_brl($pagoMes)) ?></div>
    </div>
    <div>
      <div class="text-xs text-slate-500">Em aberto</div>
      <div class="font-bold text-amber-700"><?= e(centavos_para_moeda_brl($aberto)) ?></div>
    </div>
  </div>

  <div class="flex items-center gap-2">
    <a href="dashboard_pagamentos.php"
       class="rounded-lg bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 text-sm font-medium">Dashboard</a>
    <a href="relatorio_pagamentos.php"
       class="rounded-lg bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 text-sm font-medium">Relatório</a>
    <a href="categorias_pagamento.php"
       class="rounded-lg bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 text-sm font-medium">Categorias</a>
  </div>
</div>

<!-- ============ FORMULÁRIO COMPACTO (2 LINHAS) ============ -->
<div class="bg-white rounded-xl shadow px-5 py-4 mb-4">
  <form method="post" id="formRapido" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="acao" value="salvar_rapido">
    <input type="hidden" name="data_pagamento"  id="hidDataPgto"  value="">
    <input type="hidden" name="forma_pagamento" id="hidFormaPgto" value="">

    <!-- LINHA 1: Favorecido | Buscar | Valor -->
    <div class="grid grid-cols-12 gap-3 mb-3">

      <div class="col-span-12 sm:col-span-3">
        <label class="block text-xs font-medium text-slate-500 mb-1">Favorecido</label>
        <select name="tipo_pessoa" id="tipoFav" required
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500">
          <option value="">— Tipo —</option>
          <option value="avulso">Avulso</option>
          <option value="cliente">Cliente</option>
          <option value="fornecedor">Fornecedor</option>
          <option value="usuario">Usuário</option>
        </select>
      </div>

      <div class="col-span-12 sm:col-span-6" id="blocoNome" hidden>
        <label class="block text-xs font-medium text-slate-500 mb-1">Nome</label>
        <select id="pessoaSel"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500"></select>
        <input id="pessoaAvulso" maxlength="150"
               placeholder="Nome do favorecido"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
               hidden>
      </div>

      <div class="col-span-12 sm:col-span-3" id="blocoVazio">
        <label class="block text-xs font-medium text-slate-500 mb-1 invisible">placeholder</label>
        <div class="text-xs text-slate-400 py-2">Escolha o tipo de favorecido</div>
      </div>

      <div class="col-span-12 sm:col-span-6">
        <label class="block text-xs font-medium text-slate-500 mb-1">Buscar</label>
        <input name="descricao" required maxlength="200"
               placeholder="Descrição, favorecido ou documento"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div class="col-span-12 sm:col-span-3">
        <label class="block text-xs font-medium text-slate-500 mb-1">R$ valor</label>
        <input name="valor" required inputmode="numeric" data-mask="money"
               placeholder="0,00"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-right focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>
    </div>

    <!-- LINHA 2: Vencimento | Categoria | Pago | Salvar -->
    <div class="grid grid-cols-12 gap-3">

      <div class="col-span-6 sm:col-span-3">
        <label class="block text-xs font-medium text-slate-500 mb-1">Vencimento</label>
        <input type="date" name="data_vencimento" required value="<?= date('Y-m-d') ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div class="col-span-6 sm:col-span-5">
        <label class="block text-xs font-medium text-slate-500 mb-1">Categoria</label>
        <div class="flex gap-1">
          <select name="categoria_id" id="categoriaSel" required
                  class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500">
            <option value="">— Selecione —</option>
            <?php foreach ($categorias as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= e($c['icone']) ?> <?= e($c['nome']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" id="btnNovaCat" title="Nova categoria"
                  class="w-9 rounded-lg bg-sky-100 hover:bg-sky-200 text-sky-700 font-bold text-lg leading-none">+</button>
        </div>
      </div>

      <div class="col-span-3 sm:col-span-2 text-center">
        <label class="block text-xs font-medium text-slate-500 mb-1">Pago</label>
        <input type="checkbox" id="chkPago"
               class="w-6 h-6 mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer">
      </div>

      <div class="col-span-9 sm:col-span-2 flex items-end">
        <button class="w-full px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-sm">
          Salvar
        </button>
      </div>
    </div>
  </form>
</div>

<!-- ============ LISTA SIMPLIFICADA ============ -->
<div class="bg-white rounded-xl shadow overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-slate-600">
        <tr>
          <th class="text-left px-4 py-2 w-40">Vencimento</th>
          <th class="text-left px-4 py-2">Favorecido</th>
          <th class="text-right px-4 py-2 w-40">Valor</th>
          <th class="px-4 py-2 w-48"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php if (!$pagamentos): ?>
        <tr><td colspan="4" class="px-4 py-10 text-center text-slate-400">
          Nenhum pagamento cadastrado ainda. Use o formulário acima para começar.
        </td></tr>
      <?php else: foreach ($pagamentos as $p):
        $st = status_pagamento($p);
        $cor = $st === 'pago' ? 'text-emerald-700'
             : ($st === 'atrasado' ? 'text-rose-700' : 'text-amber-700');
        $lbl = $st === 'pago' ? 'Pago' : ($st === 'atrasado' ? 'Atrasado' : 'Pendente');
      ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-2.5 whitespace-nowrap">
            <div class="font-medium <?= $cor ?>"><?= e(date('d/m/Y', strtotime($p['data_vencimento']))) ?></div>
            <div class="text-xs text-slate-400"><?= e($lbl) ?></div>
          </td>
          <td class="px-4 py-2.5">
            <div class="font-medium"><?= e($p['pessoa_nome'] ?: '—') ?></div>
            <div class="text-xs text-slate-500 truncate">
              <?= e($p['descricao']) ?>
              <?php if ($p['forma_pagamento']): ?>
                · <?= e($formas[$p['forma_pagamento']] ?? $p['forma_pagamento']) ?>
              <?php endif; ?>
            </div>
          </td>
          <td class="px-4 py-2.5 text-right font-medium whitespace-nowrap">
            <?= e(centavos_para_moeda_brl((int)$p['valor_centavos'])) ?>
          </td>
          <td class="px-4 py-2.5 text-right whitespace-nowrap">
            <a href="pagamento_form.php?id=<?= (int)$p['id'] ?>"
               class="text-sky-600 hover:underline text-xs mr-3">Editar</a>

            <?php if ($p['data_pagamento']): ?>
              <form method="post" class="inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="acao" value="desfazer_pago">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="text-amber-600 hover:underline text-xs mr-3"
                        onclick="return confirm('Desfazer o pagamento?')">Desfazer</button>
              </form>
            <?php else: ?>
              <button type="button"
                      class="btn-marcar-pago text-emerald-700 hover:underline text-xs mr-3"
                      data-id="<?= (int)$p['id'] ?>"
                      data-desc="<?= e($p['descricao']) ?>"
                      data-valor="<?= e(centavos_para_moeda_brl((int)$p['valor_centavos'])) ?>">
                Marcar pago
              </button>
            <?php endif; ?>

            <form method="post" class="inline"
                  onsubmit="return confirm('Excluir este pagamento?');">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="acao" value="excluir">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button class="text-rose-600 hover:underline text-xs">Excluir</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ============ MODAL: REGISTRAR PAGAMENTO ============ -->
<div id="modalPago" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
      <h3 class="font-semibold">Registrar pagamento</h3>
      <button type="button" id="fecharModalPago" class="text-slate-400 hover:text-slate-700 text-xl leading-none">&times;</button>
    </div>

    <div class="px-6 py-4 space-y-4">
      <div>
        <label class="block text-sm font-medium mb-1">Data do pagamento *</label>
        <input type="date" id="pgData" value="<?= date('Y-m-d') ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Forma de pagamento *</label>
        <div class="grid grid-cols-3 gap-2">
          <?php foreach ($formas as $k => $lbl): ?>
            <label class="cursor-pointer">
              <input type="radio" name="forma_tmp" value="<?= e($k) ?>" class="peer sr-only"
                     <?= $k === 'PIX' ? 'checked' : '' ?>>
              <span class="block text-center px-2 py-2 rounded-lg border border-slate-300 text-xs
                           peer-checked:bg-sky-600 peer-checked:text-white peer-checked:border-sky-600">
                <?= e($lbl) ?>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="px-6 py-4 border-t border-slate-200 flex justify-end gap-2">
      <button type="button" id="cancelarModalPago"
              class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-sm">Cancelar</button>
      <button type="button" id="confirmarModalPago"
              class="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-medium">
        Confirmar
      </button>
    </div>
  </div>
</div>

<!-- ============ MODAL: MARCAR PAGO (lista) ============ -->
<div id="modalMarcar" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="acao" value="marcar_pago">
      <input type="hidden" name="id" id="marcarId">

      <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
        <h3 class="font-semibold">Marcar como pago</h3>
        <button type="button" class="fecharMarcar text-slate-400 hover:text-slate-700 text-xl leading-none">&times;</button>
      </div>

      <div class="px-6 py-4 space-y-4">
        <div class="rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 text-sm">
          <div class="flex justify-between"><span class="text-slate-500">Descrição:</span>
            <strong id="marcarDesc"></strong></div>
          <div class="flex justify-between"><span class="text-slate-500">Valor:</span>
            <strong id="marcarValor" class="text-emerald-700"></strong></div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Data do pagamento *</label>
          <input type="date" name="data_pagamento" required value="<?= date('Y-m-d') ?>"
                 class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Forma de pagamento *</label>
          <div class="grid grid-cols-3 gap-2">
            <?php foreach ($formas as $k => $lbl): ?>
              <label class="cursor-pointer">
                <input type="radio" name="forma_pagamento" value="<?= e($k) ?>" class="peer sr-only"
                       <?= $k === 'PIX' ? 'checked' : '' ?>>
                <span class="block text-center px-2 py-2 rounded-lg border border-slate-300 text-xs
                             peer-checked:bg-sky-600 peer-checked:text-white peer-checked:border-sky-600">
                  <?= e($lbl) ?>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="px-6 py-4 border-t border-slate-200 flex justify-end gap-2">
        <button type="button" class="fecharMarcar px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-sm">Cancelar</button>
        <button class="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-medium">Salvar</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ MODAL: NOVA CATEGORIA ============ -->
<div id="modalCat" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
      <h3 class="font-semibold">Nova categoria</h3>
      <button type="button" id="fecharModalCat" class="text-slate-400 hover:text-slate-700 text-xl leading-none">&times;</button>
    </div>

    <div class="px-6 py-4 space-y-4">
      <div>
        <label class="block text-sm font-medium mb-1">Nome *</label>
        <input id="catNome" maxlength="60" required
               placeholder="Ex.: Energia, Combustível..."
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium mb-1">Ícone</label>
          <input id="catIcone" maxlength="4" value="📄"
                 class="w-full text-center text-2xl rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-sky-500">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Cor</label>
          <input type="color" id="catCor" value="#64748b"
                 class="w-full h-12 rounded-lg border border-slate-300 cursor-pointer">
        </div>
      </div>

      <div id="catErro" class="hidden text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-md px-3 py-2"></div>
    </div>

    <div class="px-6 py-4 border-t border-slate-200 flex justify-end gap-2">
      <button type="button" id="cancelarModalCat"
              class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-sm">Cancelar</button>
      <button type="button" id="confirmarModalCat"
              class="px-5 py-2 rounded-lg bg-sky-600 hover:bg-sky-500 text-white text-sm font-medium">
        Criar categoria
      </button>
    </div>
  </div>
</div>

<script>
(function () {
  
  /* ===== MÁSCARA DE MOEDA INTELIGENTE =====
   - Durante a digitação: aceita apenas números, vírgula e ponto
   - Ao sair do campo (blur): formata para 1.234,56
   - "200"       vira "200,00"
   - "200,50"    vira "200,50"
   - "1500,50"   vira "1.500,50"
   - "1.500,50"  vira "1.500,50"
============================================ */
function formatarMoedaBR(v) {
  v = String(v || '').trim();
  if (v === '') return '';

  // Tem vírgula? Trata como decimal brasileiro
  if (v.includes(',')) {
    let [int, dec] = v.split(',');
    int = int.replace(/\D/g, '') || '0';
    dec = (dec.replace(/\D/g, '') + '00').slice(0, 2);
    int = int.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return int + ',' + dec;
  }

  // Sem vírgula: só dígitos (e opcionalmente pontos de milhar)
  const dig = v.replace(/\D/g, '');
  if (dig === '') return '';
  return dig.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',00';
}

document.querySelectorAll('[data-mask="money"]').forEach(el => {
  // Ao digitar: permite apenas dígitos, vírgula e ponto
  el.addEventListener('input', function () {
    const pos = this.selectionStart;
    const antes = this.value.length;
    this.value = this.value.replace(/[^\d.,]/g, '');
    // Reposiciona o cursor se algum caractere foi removido
    if (this.value.length !== antes) {
      this.setSelectionRange(Math.max(0, pos - 1), Math.max(0, pos - 1));
    }
  });

  // Ao focar: se estiver "0,00", limpa para digitar do zero
  el.addEventListener('focus', function () {
    if (this.value === '0,00' || this.value === '0.00') this.value = '';
  });

  // Ao perder o foco: aplica a formatação final
  el.addEventListener('blur', function () {
    this.value = formatarMoedaBR(this.value);
  });
});

  /* ========= FAVORECIDO ========= */
  const pessoas   = <?= json_encode($pessoas, JSON_UNESCAPED_UNICODE) ?>;
  const tipoEl    = document.getElementById('tipoFav');
  const blocoNome = document.getElementById('blocoNome');
  const blocoVazio= document.getElementById('blocoVazio');
  const selEl     = document.getElementById('pessoaSel');
  const avulsoEl  = document.getElementById('pessoaAvulso');

  function renderizarPessoas() {
    const tipo = tipoEl.value;
    if (!tipo) {
      blocoNome.hidden = true;
      blocoVazio.hidden = false;
      return;
    }
    blocoNome.hidden = false;
    blocoVazio.hidden = true;

    if (tipo === 'avulso') {
      selEl.hidden = true;
      selEl.removeAttribute('name');
      avulsoEl.hidden = false;
      avulsoEl.setAttribute('name', 'pessoa_nome');
      avulsoEl.value = '';
      avulsoEl.focus();
    } else {
      avulsoEl.hidden = true;
      avulsoEl.removeAttribute('name');
      selEl.hidden = false;
      selEl.setAttribute('name', 'pessoa_id');

      selEl.innerHTML = '<option value="">— Selecione —</option>';
      (pessoas[tipo] || []).forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.nome + (p.doc ? ' — ' + p.doc : '');
        opt.dataset.nome = p.nome;
        selEl.appendChild(opt);
      });
    }
  }
  tipoEl.addEventListener('change', renderizarPessoas);

  /* Ao enviar, injeta pessoa_nome hidden para tipos não-avulsos */
  const formRapido = document.getElementById('formRapido');
  formRapido.addEventListener('submit', () => {
    const tipo = tipoEl.value;
    if (tipo && tipo !== 'avulso') {
      const opt = selEl.options[selEl.selectedIndex];
      const nomeEscolhido = opt && opt.dataset.nome ? opt.dataset.nome : '';
      let hid = formRapido.querySelector('input[name="pessoa_nome"][type="hidden"]');
      if (!hid) {
        hid = document.createElement('input');
        hid.type = 'hidden';
        hid.name = 'pessoa_nome';
        formRapido.appendChild(hid);
      }
      hid.value = nomeEscolhido;
    }
  });

  /* ========= CHECKBOX PAGO ========= */
  const modalPago    = document.getElementById('modalPago');
  const chkPago      = document.getElementById('chkPago');
  const pgData       = document.getElementById('pgData');
  const hidDataPgto  = document.getElementById('hidDataPgto');
  const hidFormaPgto = document.getElementById('hidFormaPgto');

  chkPago.addEventListener('change', () => {
    if (chkPago.checked) modalPago.classList.remove('hidden');
  });

  function cancelarPago() {
    chkPago.checked = false;
    hidDataPgto.value  = '';
    hidFormaPgto.value = '';
    modalPago.classList.add('hidden');
  }
  function confirmarPago() {
    const data  = pgData.value;
    const forma = document.querySelector('input[name="forma_tmp"]:checked')?.value || '';
    if (!data || !forma) { alert('Informe a data e a forma de pagamento.'); return; }
    hidDataPgto.value  = data;
    hidFormaPgto.value = forma;
    modalPago.classList.add('hidden');
  }
  document.getElementById('fecharModalPago').addEventListener('click', cancelarPago);
  document.getElementById('cancelarModalPago').addEventListener('click', cancelarPago);
  document.getElementById('confirmarModalPago').addEventListener('click', confirmarPago);

  /* ========= MARCAR PAGO (lista) ========= */
  const modalMarcar = document.getElementById('modalMarcar');
  document.querySelectorAll('.btn-marcar-pago').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('marcarId').value          = btn.dataset.id;
      document.getElementById('marcarDesc').textContent  = btn.dataset.desc;
      document.getElementById('marcarValor').textContent = btn.dataset.valor;
      modalMarcar.classList.remove('hidden');
    });
  });
  document.querySelectorAll('.fecharMarcar').forEach(b =>
    b.addEventListener('click', () => modalMarcar.classList.add('hidden'))
  );

  /* ========= NOVA CATEGORIA ========= */
  const modalCat   = document.getElementById('modalCat');
  const catErro    = document.getElementById('catErro');

  function abrirCat() {
    document.getElementById('catNome').value  = '';
    document.getElementById('catIcone').value = '📄';
    document.getElementById('catCor').value   = '#64748b';
    catErro.classList.add('hidden');
    modalCat.classList.remove('hidden');
    setTimeout(() => document.getElementById('catNome').focus(), 50);
  }
  function fecharCat() { modalCat.classList.add('hidden'); }

  document.getElementById('btnNovaCat').addEventListener('click', abrirCat);
  document.getElementById('fecharModalCat').addEventListener('click', fecharCat);
  document.getElementById('cancelarModalCat').addEventListener('click', fecharCat);

  document.getElementById('confirmarModalCat').addEventListener('click', async () => {
    const nome  = document.getElementById('catNome').value.trim();
    const icone = document.getElementById('catIcone').value.trim() || '📄';
    const cor   = document.getElementById('catCor').value;

    if (nome === '') {
      catErro.textContent = 'Informe o nome da categoria.';
      catErro.classList.remove('hidden');
      return;
    }

    const fd = new FormData();
    fd.append('csrf',  '<?= e(csrf_token()) ?>');
    fd.append('nome',  nome);
    fd.append('icone', icone);
    fd.append('cor',   cor);

    try {
      const r = await fetch('categoria_criar_ajax.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (!j.ok) {
        catErro.textContent = j.erro || 'Erro ao criar.';
        catErro.classList.remove('hidden');
        return;
      }
      const sel = document.getElementById('categoriaSel');
      const opt = document.createElement('option');
      opt.value = j.categoria.id;
      opt.textContent = j.categoria.icone + ' ' + j.categoria.nome;
      opt.selected = true;
      sel.appendChild(opt);
      fecharCat();
    } catch (e) {
      catErro.textContent = 'Falha de conexão.';
      catErro.classList.remove('hidden');
    }
  });
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
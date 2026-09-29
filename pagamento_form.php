<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_pagamentos();
ensure_clientes();
ensure_fornecedores();
ensure_usuarios();

$id     = (int)($_GET['id'] ?? 0);
$editar = $id > 0;
$pag    = $editar ? buscar_pagamento($id) : null;

if ($editar && !$pag) {
    flash('Pagamento não encontrado.', 'erro');
    header('Location: pagamentos.php');
    exit;
}

$erros = [];

$dados = $pag ?? [
    'tipo_pessoa'   => 'fornecedor',
    'pessoa_id'     => 0,
    'pessoa_nome'   => '',
    'categoria_id'  => 0,
    'descricao'     => '',
    'documento'     => '',
    'valor_centavos'=> 0,
    'data_vencimento'=> date('Y-m-d'),
    'data_pagamento'=> '',
    'forma_pagamento'=> '',
    'observacoes'   => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $dados = [
        'tipo_pessoa'    => (string)($_POST['tipo_pessoa'] ?? 'avulso'),
        'pessoa_id'      => (int)($_POST['pessoa_id'] ?? 0),
        'pessoa_nome'    => trim((string)($_POST['pessoa_nome'] ?? '')),
        'categoria_id'   => (int)($_POST['categoria_id'] ?? 0),
        'descricao'      => trim((string)($_POST['descricao'] ?? '')),
        'documento'      => trim((string)($_POST['documento'] ?? '')),
        'valor_centavos' => moeda_para_centavos((string)($_POST['valor'] ?? '0')),
        'data_vencimento'=> trim((string)($_POST['data_vencimento'] ?? '')),
        'data_pagamento' => trim((string)($_POST['data_pagamento'] ?? '')),
        'forma_pagamento'=> trim((string)($_POST['forma_pagamento'] ?? '')),
        'observacoes'    => trim((string)($_POST['observacoes'] ?? '')),
    ];

    if (!in_array($dados['tipo_pessoa'], ['usuario','fornecedor','cliente','avulso'], true)) {
        $dados['tipo_pessoa'] = 'avulso';
    }
    if ($dados['categoria_id'] <= 0)  $erros[] = 'Escolha uma categoria.';
    if ($dados['valor_centavos'] <= 0) $erros[] = 'Informe um valor válido.';
    if ($dados['data_vencimento'] === '') $erros[] = 'Informe a data de vencimento.';
    if ($dados['descricao'] === '')   $erros[] = 'Informe uma descrição.';

    // se pessoa do tipo avulso, exige nome
    if ($dados['tipo_pessoa'] === 'avulso' && $dados['pessoa_nome'] === '') {
        $erros[] = 'Informe o nome do favorecido.';
    }

    // se data de pagamento preenchida, exige forma de pagamento
    if ($dados['data_pagamento'] !== '' && $dados['forma_pagamento'] === '') {
        $erros[] = 'Informe a forma de pagamento.';
    }

    if (!$erros) {
        $pdo = db();
        if ($editar) {
            $pdo->prepare("
                UPDATE pagamentos_despesas SET
                    tipo_pessoa = :tp, pessoa_id = :pid, pessoa_nome = :pn,
                    categoria_id = :cat, descricao = :desc, documento = :doc,
                    valor_centavos = :val, data_vencimento = :venc,
                    data_pagamento = :pgto, forma_pagamento = :forma,
                    observacoes = :obs,
                    atualizado_em = datetime('now','localtime')
                WHERE id = :id
            ")->execute([
                ':tp'   => $dados['tipo_pessoa'],
                ':pid'  => $dados['pessoa_id'] ?: null,
                ':pn'   => $dados['pessoa_nome'],
                ':cat'  => $dados['categoria_id'],
                ':desc' => $dados['descricao'],
                ':doc'  => $dados['documento'],
                ':val'  => $dados['valor_centavos'],
                ':venc' => $dados['data_vencimento'],
                ':pgto' => $dados['data_pagamento'],
                ':forma'=> $dados['forma_pagamento'],
                ':obs'  => $dados['observacoes'],
                ':id'   => $id,
            ]);
            flash('Pagamento atualizado.', 'sucesso');
        } else {
            $u = usuario_logado();
            $pdo->prepare("
                INSERT INTO pagamentos_despesas
                    (tipo_pessoa, pessoa_id, pessoa_nome, categoria_id, descricao, documento,
                     valor_centavos, data_vencimento, data_pagamento, forma_pagamento,
                     observacoes, criado_por)
                VALUES
                    (:tp, :pid, :pn, :cat, :desc, :doc, :val, :venc, :pgto, :forma, :obs, :uid)
            ")->execute([
                ':tp'   => $dados['tipo_pessoa'],
                ':pid'  => $dados['pessoa_id'] ?: null,
                ':pn'   => $dados['pessoa_nome'],
                ':cat'  => $dados['categoria_id'],
                ':desc' => $dados['descricao'],
                ':doc'  => $dados['documento'],
                ':val'  => $dados['valor_centavos'],
                ':venc' => $dados['data_vencimento'],
                ':pgto' => $dados['data_pagamento'],
                ':forma'=> $dados['forma_pagamento'],
                ':obs'  => $dados['observacoes'],
                ':uid'  => (int)$u['id'],
            ]);
            flash('Pagamento cadastrado.', 'sucesso');
        }
        header('Location: pagamentos.php');
        exit;
    }
}

/* ---------- Dados para a tela ---------- */
$categorias = listar_categorias_pagamento(true);
$pessoas = [
    'usuario'    => db()->query("SELECT id, nome, email AS doc FROM usuarios WHERE ativo=1 ORDER BY nome")->fetchAll(),
    'fornecedor' => db()->query("SELECT id, nome, cpf_cnpj AS doc FROM fornecedores WHERE ativo=1 ORDER BY nome COLLATE NOCASE")->fetchAll(),
    'cliente'    => db()->query("SELECT id, nome, cpf_cnpj AS doc FROM clientes WHERE ativo=1 ORDER BY nome COLLATE NOCASE")->fetchAll(),
];
$formas = formas_pagamento();

$titulo = $editar ? 'Editar pagamento' : 'Novo pagamento';
require __DIR__ . '/header.php';
?>

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
  document.addEventListener('DOMContentLoaded', () => {
    const el = document.querySelector('[data-mask="money"]');
    if (!el) return;
    const exec = () => { el.value = maskMoney(el.value); };
    el.addEventListener('input', exec);
    el.addEventListener('blur',  exec);
    exec();
  });
})();
</script>

<div class="max-w-3xl mx-auto">
  <form method="post" class="bg-white rounded-xl shadow p-6 sm:p-8 space-y-6" novalidate id="formPg">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold"><?= $editar ? 'Editar pagamento' : 'Novo pagamento' ?></h1>
        <p class="text-sm text-slate-500">Contas a pagar — vincule a um usuário, fornecedor, cliente ou avulso.</p>
      </div>
      <a href="pagamentos.php" class="text-sm text-sky-600 hover:underline">← Voltar</a>
    </div>

    <?php if ($erros): ?>
      <div class="rounded-md border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm">
        <ul class="list-disc pl-5 space-y-1">
          <?php foreach ($erros as $e2): ?><li><?= e($e2) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- ============ FAVORECIDO ============ -->
    <section class="grid gap-4 sm:grid-cols-2 pt-2 border-t border-slate-200">
      <h2 class="sm:col-span-2 text-sm font-semibold text-slate-700">Favorecido</h2>

      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-2">Tipo de pessoa *</label>
        <div class="flex flex-wrap gap-2">
          <?php
            $tipos = [
              'fornecedor' => 'Fornecedor',
              'cliente'    => 'Cliente',
              'usuario'    => 'Usuário',
              'avulso'     => 'Avulso',
            ];
            foreach ($tipos as $k => $lbl): ?>
            <label class="cursor-pointer">
              <input type="radio" name="tipo_pessoa" value="<?= e($k) ?>" class="peer sr-only"
                     <?= $dados['tipo_pessoa'] === $k ? 'checked' : '' ?>>
              <span class="inline-block px-4 py-2 rounded-lg border border-slate-300 text-sm
                           peer-checked:bg-sky-600 peer-checked:text-white peer-checked:border-sky-600">
                <?= e($lbl) ?>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Select para fornecedor/cliente/usuario -->
      <div class="sm:col-span-2" id="blocoSelect">
        <label class="block text-sm font-medium mb-1">Selecione</label>
        <select name="pessoa_id" id="pessoaSelect"
                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
          <option value="0">— Selecione —</option>
        </select>
        <input type="hidden" name="pessoa_nome" id="pessoaNome" value="<?= e($dados['pessoa_nome']) ?>">
      </div>

      <!-- Campo avulso -->
      <div class="sm:col-span-2" id="blocoAvulso" hidden>
        <label class="block text-sm font-medium mb-1">Nome do favorecido *</label>
        <input id="pessoaAvulso" maxlength="150"
               value="<?= $dados['tipo_pessoa'] === 'avulso' ? e($dados['pessoa_nome']) : '' ?>"
               placeholder="Ex.: João da Silva, Prefeitura, CPFL..."
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>
    </section>

    <!-- ============ DADOS ============ -->
    <section class="grid gap-4 sm:grid-cols-2 pt-2 border-t border-slate-200">
      <h2 class="sm:col-span-2 text-sm font-semibold text-slate-700">Dados do pagamento</h2>

      <div>
        <label class="block text-sm font-medium mb-1">Categoria *</label>
        <select name="categoria_id" required
                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
          <option value="">— Selecione —</option>
          <?php foreach ($categorias as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (int)$dados['categoria_id'] === (int)$c['id'] ? 'selected' : '' ?>>
              <?= e($c['icone']) ?> <?= e($c['nome']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Valor (R$) *</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 text-sm pointer-events-none">R$</span>
          <input name="valor" data-mask="money" required inputmode="numeric"
                 value="<?= e($dados['valor_centavos'] ? centavos_para_moeda((int)$dados['valor_centavos']) : '') ?>"
                 placeholder="0,00"
                 class="w-full rounded-lg border border-slate-300 pl-9 pr-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        </div>
      </div>

      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">Descrição *</label>
        <input name="descricao" required maxlength="200" value="<?= e($dados['descricao']) ?>"
               placeholder="Ex.: Fatura de energia - Setembro/2026"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Documento / Boleto</label>
        <input name="documento" maxlength="60" value="<?= e($dados['documento']) ?>"
               placeholder="Ex.: NF 12345 / Código de barras"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Data de vencimento *</label>
        <input type="date" name="data_vencimento" required value="<?= e($dados['data_vencimento']) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div class="sm:col-span-2 rounded-lg border border-slate-200 bg-slate-50 p-4">
        <div class="flex items-center gap-3 mb-3">
          <input type="checkbox" id="chkPago" <?= $dados['data_pagamento'] ? 'checked' : '' ?>
                 class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
          <label for="chkPago" class="text-sm font-medium cursor-pointer">Já foi pago?</label>
        </div>

        <div id="blocoPago" class="grid gap-3 sm:grid-cols-2" <?= $dados['data_pagamento'] ? '' : 'hidden' ?>>
          <div>
            <label class="block text-sm font-medium mb-1">Data do pagamento</label>
            <input type="date" name="data_pagamento" value="<?= e($dados['data_pagamento'] ?: date('Y-m-d')) ?>"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Forma de pagamento</label>
            <select name="forma_pagamento"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
              <option value="">— Selecione —</option>
              <?php foreach ($formas as $k => $lbl): ?>
                <option value="<?= e($k) ?>" <?= $dados['forma_pagamento'] === $k ? 'selected' : '' ?>>
                  <?= e($lbl) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">Observações</label>
        <textarea name="observacoes" rows="3" maxlength="500"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500"><?= e($dados['observacoes']) ?></textarea>
      </div>
    </section>

    <div class="flex justify-end gap-2 pt-2">
      <a href="pagamentos.php"
         class="px-4 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-sm font-medium">Cancelar</a>
      <button class="px-5 py-2.5 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-medium">
        <?= $editar ? 'Salvar alterações' : 'Cadastrar pagamento' ?>
      </button>
    </div>
  </form>
</div>

<script>
(function () {
  const pessoas = <?= json_encode($pessoas, JSON_UNESCAPED_UNICODE) ?>;
  const radios  = document.querySelectorAll('input[name="tipo_pessoa"]');
  const selEl   = document.getElementById('pessoaSelect');
  const hidNome = document.getElementById('pessoaNome');
  const avulsoEl= document.getElementById('pessoaAvulso');
  const blocoSel= document.getElementById('blocoSelect');
  const blocoAv = document.getElementById('blocoAvulso');
  const pessoaIdAtual = <?= (int)$dados['pessoa_id'] ?>;

  function renderizar() {
    const tipo = document.querySelector('input[name="tipo_pessoa"]:checked').value;
    if (tipo === 'avulso') {
      blocoSel.hidden = true;
      blocoAv.hidden  = false;
      hidNome.value   = avulsoEl.value;
    } else {
      blocoSel.hidden = false;
      blocoAv.hidden  = true;
      selEl.innerHTML = '<option value="0">— Selecione —</option>';
      (pessoas[tipo] || []).forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.nome + (p.doc ? ' — ' + p.doc : '');
        opt.dataset.nome = p.nome;
        if (parseInt(p.id, 10) === pessoaIdAtual) opt.selected = true;
        selEl.appendChild(opt);
      });
      const opt = selEl.options[selEl.selectedIndex];
      hidNome.value = opt && opt.dataset.nome ? opt.dataset.nome : '';
    }
  }

  selEl.addEventListener('change', () => {
    const opt = selEl.options[selEl.selectedIndex];
    hidNome.value = opt && opt.dataset.nome ? opt.dataset.nome : '';
  });
  avulsoEl.addEventListener('input', () => {
    if (document.querySelector('input[name="tipo_pessoa"]:checked').value === 'avulso') {
      hidNome.value = avulsoEl.value;
    }
  });

  radios.forEach(r => r.addEventListener('change', renderizar));
  renderizar();

  // Já foi pago?
  const chkPago  = document.getElementById('chkPago');
  const blocoPago= document.getElementById('blocoPago');
  function togglePago() {
    blocoPago.hidden = !chkPago.checked;
    if (!chkPago.checked) {
      document.querySelector('[name="data_pagamento"]').value = '';
      document.querySelector('[name="forma_pagamento"]').value = '';
    } else if (!document.querySelector('[name="data_pagamento"]').value) {
      document.querySelector('[name="data_pagamento"]').value = new Date().toISOString().slice(0, 10);
    }
  }
  chkPago.addEventListener('change', togglePago);
  togglePago();
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
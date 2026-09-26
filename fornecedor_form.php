<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_fornecedores();

$id     = (int)($_GET['id'] ?? 0);
$editar = $id > 0;
$fornecedor = $editar ? buscar_fornecedor($id) : null;

if ($editar && !$fornecedor) {
    flash('Fornecedor não encontrado.', 'erro');
    header('Location: fornecedores.php');
    exit;
}

$erros = [];

/* valores iniciais */
$dados = $fornecedor ?? [
    'tipo' => 'J', 'nome' => '', 'nome_fantasia' => '', 'cpf_cnpj' => '',
    'rg_ie' => '', 'contato' => '', 'email' => '', 'telefone' => '',
    'celular' => '', 'site' => '', 'cep' => '', 'endereco' => '',
    'numero' => '', 'complemento' => '', 'bairro' => '', 'cidade' => '',
    'uf' => '', 'banco' => '', 'agencia' => '', 'conta' => '', 'pix' => '',
    'observacoes' => '', 'ativo' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $dados = [
        'tipo'          => ($_POST['tipo'] ?? 'J') === 'F' ? 'F' : 'J',
        'nome'          => trim((string)($_POST['nome'] ?? '')),
        'nome_fantasia' => trim((string)($_POST['nome_fantasia'] ?? '')),
        'cpf_cnpj'      => preg_replace('/\D/', '', (string)($_POST['cpf_cnpj'] ?? '')),
        'rg_ie'         => trim((string)($_POST['rg_ie'] ?? '')),
        'contato'       => trim((string)($_POST['contato'] ?? '')),
        'email'         => trim((string)($_POST['email'] ?? '')),
        'telefone'      => trim((string)($_POST['telefone'] ?? '')),
        'celular'       => trim((string)($_POST['celular'] ?? '')),
        'site'          => trim((string)($_POST['site'] ?? '')),
        'cep'           => trim((string)($_POST['cep'] ?? '')),
        'endereco'      => trim((string)($_POST['endereco'] ?? '')),
        'numero'        => trim((string)($_POST['numero'] ?? '')),
        'complemento'   => trim((string)($_POST['complemento'] ?? '')),
        'bairro'        => trim((string)($_POST['bairro'] ?? '')),
        'cidade'        => trim((string)($_POST['cidade'] ?? '')),
        'uf'            => strtoupper(trim((string)($_POST['uf'] ?? ''))),
        'banco'         => trim((string)($_POST['banco'] ?? '')),
        'agencia'       => trim((string)($_POST['agencia'] ?? '')),
        'conta'         => trim((string)($_POST['conta'] ?? '')),
        'pix'           => trim((string)($_POST['pix'] ?? '')),
        'observacoes'   => trim((string)($_POST['observacoes'] ?? '')),
        'ativo'         => isset($_POST['ativo']) ? 1 : 0,
    ];

    /* ---------- Validações ---------- */
    if ($dados['nome'] === '') {
        $erros[] = 'Informe o nome / razão social.';
    }

    // CPF/CNPJ opcional: só valida se informado
    if ($dados['cpf_cnpj'] !== '' && !validar_cpf_cnpj($dados['cpf_cnpj'], $dados['tipo'])) {
        $erros[] = ($dados['tipo'] === 'J' ? 'CNPJ' : 'CPF') . ' informado é inválido.';
    }

    if ($dados['email'] !== '' && !validar_email($dados['email'])) {
        $erros[] = 'E-mail inválido.';
    }
    if ($dados['uf'] !== '' && !preg_match('/^[A-Z]{2}$/', $dados['uf'])) {
        $erros[] = 'UF inválida.';
    }

    /* Duplicidade: só verifica se informado */
    if (!$erros && $dados['cpf_cnpj'] !== '') {
        $sql = 'SELECT id FROM fornecedores WHERE cpf_cnpj = ?' . ($editar ? ' AND id <> ?' : '');
        $st  = db()->prepare($sql);
        $st->execute($editar ? [$dados['cpf_cnpj'], $id] : [$dados['cpf_cnpj']]);
        if ($st->fetch()) {
            $erros[] = 'Já existe um fornecedor cadastrado com esse '
                     . ($dados['tipo'] === 'J' ? 'CNPJ' : 'CPF') . '.';
        }
    }

    if (!$erros) {
        if ($editar) {
            $sql = 'UPDATE fornecedores SET
                tipo=:tipo, nome=:nome, nome_fantasia=:nome_fantasia,
                cpf_cnpj=:cpf_cnpj, rg_ie=:rg_ie, contato=:contato,
                email=:email, telefone=:telefone, celular=:celular, site=:site,
                cep=:cep, endereco=:endereco, numero=:numero, complemento=:complemento,
                bairro=:bairro, cidade=:cidade, uf=:uf,
                banco=:banco, agencia=:agencia, conta=:conta, pix=:pix,
                observacoes=:observacoes, ativo=:ativo,
                atualizado_em=datetime(\'now\',\'localtime\')
                WHERE id=:id';
            $dados['id'] = $id;
            db()->prepare($sql)->execute($dados);
            flash('Fornecedor atualizado com sucesso.', 'sucesso');
        } else {
            $sql = 'INSERT INTO fornecedores
                (tipo, nome, nome_fantasia, cpf_cnpj, rg_ie, contato,
                 email, telefone, celular, site,
                 cep, endereco, numero, complemento, bairro, cidade, uf,
                 banco, agencia, conta, pix, observacoes, ativo)
                VALUES
                (:tipo, :nome, :nome_fantasia, :cpf_cnpj, :rg_ie, :contato,
                 :email, :telefone, :celular, :site,
                 :cep, :endereco, :numero, :complemento, :bairro, :cidade, :uf,
                 :banco, :agencia, :conta, :pix, :observacoes, :ativo)';
            db()->prepare($sql)->execute($dados);
            flash('Fornecedor cadastrado com sucesso.', 'sucesso');
        }
        header('Location: fornecedores.php');
        exit;
    }
}

$titulo = $editar ? 'Editar fornecedor' : 'Novo fornecedor';
require __DIR__ . '/header.php';
?>

<!-- ============ MÁSCARAS ============ -->
<script>
(function () {
  const soDigitos = v => (v || '').replace(/\D+/g, '');

  const Mask = {
    cpf(v) {
      v = soDigitos(v).slice(0, 11);
      v = v.replace(/^(\d{3})(\d)/, '$1.$2');
      v = v.replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3');
      v = v.replace(/\.(\d{3})(\d)/, '.$1-$2');
      return v;
    },
    cnpj(v) {
      v = soDigitos(v).slice(0, 14);
      v = v.replace(/^(\d{2})(\d)/, '$1.$2');
      v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
      v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
      v = v.replace(/(\d{4})(\d)/, '$1-$2');
      return v;
    },
    cpf_cnpj(v) {
      const d = soDigitos(v);
      return d.length <= 11 ? Mask.cpf(v) : Mask.cnpj(v);
    },
    telefone(v) {
      v = soDigitos(v).slice(0, 10);
      v = v.replace(/^(\d{2})(\d)/, '($1) $2');
      v = v.replace(/(\d{4})(\d)/, '$1-$2');
      return v;
    },
    celular(v) {
      v = soDigitos(v).slice(0, 11);
      v = v.replace(/^(\d{2})(\d)/, '($1) $2');
      v = v.replace(/(\d{5})(\d)/, '$1-$2');
      return v;
    },
    cep(v) {
      v = soDigitos(v).slice(0, 8);
      return v.replace(/^(\d{5})(\d)/, '$1-$2');
    }
  };
  window.Mask = Mask;

  function aplicar(el) {
    const fn = Mask[el.dataset.mask];
    if (typeof fn !== 'function') return;
    const exec = () => { el.value = fn(el.value); };
    el.addEventListener('input', exec);
    el.addEventListener('blur',  exec);
    exec();
  }
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-mask]').forEach(aplicar);
  });
})();
</script>

<div class="max-w-4xl mx-auto">
  <form method="post" class="bg-white rounded-xl shadow p-6 sm:p-8 space-y-6" novalidate
        id="formFornecedor">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold"><?= $editar ? 'Editar fornecedor' : 'Novo fornecedor' ?></h1>
        <p class="text-sm text-slate-500">Preencha os dados do fornecedor.</p>
      </div>
      <a href="fornecedores.php" class="text-sm text-sky-600 hover:underline">← Voltar</a>
    </div>

    <?php if ($erros): ?>
      <div class="rounded-md border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm">
        <ul class="list-disc pl-5 space-y-1">
          <?php foreach ($erros as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- Tipo -->
    <section>
      <label class="block text-sm font-medium mb-2">Tipo *</label>
      <div class="flex gap-2">
        <label class="cursor-pointer">
          <input type="radio" name="tipo" value="J" class="peer sr-only"
                 <?= $dados['tipo'] === 'J' ? 'checked' : '' ?>>
          <span class="inline-block px-4 py-2 rounded-lg border border-slate-300 text-sm
                       peer-checked:bg-sky-600 peer-checked:text-white peer-checked:border-sky-600">
            Pessoa Jurídica
          </span>
        </label>
        <label class="cursor-pointer">
          <input type="radio" name="tipo" value="F" class="peer sr-only"
                 <?= $dados['tipo'] === 'F' ? 'checked' : '' ?>>
          <span class="inline-block px-4 py-2 rounded-lg border border-slate-300 text-sm
                       peer-checked:bg-sky-600 peer-checked:text-white peer-checked:border-sky-600">
            Pessoa Física
          </span>
        </label>
      </div>
    </section>

    <!-- Dados principais -->
    <section class="grid gap-4 sm:grid-cols-2">
      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">
          <span data-lbl-pf>Nome completo</span><span data-lbl-pj hidden>Razão social</span> *
        </label>
        <input name="nome" required maxlength="150" value="<?= e($dados['nome']) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">Nome fantasia</label>
        <input name="nome_fantasia" maxlength="150" value="<?= e($dados['nome_fantasia']) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">
          <span data-lbl-pf>CPF</span><span data-lbl-pj hidden>CNPJ</span>
          <span class="text-xs text-slate-400 font-normal">(opcional)</span>
        </label>
        <input name="cpf_cnpj" id="cpf_cnpj" inputmode="numeric"
               value="<?= e($dados['cpf_cnpj'] ? formatar_cpf_cnpj($dados['cpf_cnpj'], $dados['tipo']) : '') ?>"
               data-mask="cpf_cnpj"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">
          <span data-lbl-pf>RG</span><span data-lbl-pj hidden>Inscrição Estadual</span>
        </label>
        <input name="rg_ie" maxlength="30" value="<?= e($dados['rg_ie']) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">Pessoa de contato</label>
        <input name="contato" maxlength="120" value="<?= e($dados['contato']) ?>"
               placeholder="Ex.: João — setor de vendas"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">E-mail</label>
        <input type="email" name="email" maxlength="150" value="<?= e($dados['email']) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Telefone</label>
        <input name="telefone" data-mask="telefone" inputmode="numeric"
               value="<?= e($dados['telefone']) ?>" placeholder="(00) 0000-0000"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Celular</label>
        <input name="celular" data-mask="celular" inputmode="numeric"
               value="<?= e($dados['celular']) ?>" placeholder="(00) 00000-0000"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">Site</label>
        <input name="site" maxlength="150" value="<?= e($dados['site']) ?>"
               placeholder="https://..."
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>
    </section>

    <!-- Endereço -->
    <section class="grid gap-4 sm:grid-cols-6 pt-2 border-t border-slate-200">
      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">CEP</label>
        <input name="cep" id="cep" data-mask="cep" value="<?= e($dados['cep']) ?>"
               placeholder="00000-000" inputmode="numeric"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        <p id="cepStatus" class="text-xs text-slate-400 mt-1"></p>
      </div>

      <div class="sm:col-span-3">
        <label class="block text-sm font-medium mb-1">Endereço</label>
        <input name="endereco" id="endereco" maxlength="150" value="<?= e($dados['endereco']) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div class="sm:col-span-1">
        <label class="block text-sm font-medium mb-1">Número</label>
        <input name="numero" maxlength="10" value="<?= e($dados['numero']) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">Complemento</label>
        <input name="complemento" maxlength="80" value="<?= e($dados['complemento']) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">Bairro</label>
        <input name="bairro" id="bairro" maxlength="80" value="<?= e($dados['bairro']) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div class="sm:col-span-1">
        <label class="block text-sm font-medium mb-1">UF</label>
        <input name="uf" id="uf" maxlength="2" value="<?= e($dados['uf']) ?>" placeholder="SP"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 uppercase focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div class="sm:col-span-1"><!-- espaçador --></div>

      <div class="sm:col-span-3">
        <label class="block text-sm font-medium mb-1">Cidade</label>
        <input name="cidade" id="cidade" maxlength="80" value="<?= e($dados['cidade']) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>
    </section>

    <!-- Dados bancários -->
    <section class="grid gap-4 sm:grid-cols-2 pt-2 border-t border-slate-200">
      <h2 class="sm:col-span-2 text-sm font-semibold text-slate-700 mt-1">Dados bancários</h2>

      <div>
        <label class="block text-sm font-medium mb-1">Banco</label>
        <input name="banco" maxlength="80" value="<?= e($dados['banco']) ?>"
               placeholder="Ex.: Banco do Brasil"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Agência</label>
        <input name="agencia" maxlength="20" value="<?= e($dados['agencia']) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Conta</label>
        <input name="conta" maxlength="30" value="<?= e($dados['conta']) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">PIX</label>
        <input name="pix" maxlength="150" value="<?= e($dados['pix']) ?>"
               placeholder="Chave PIX"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>
    </section>

    <!-- Observações / Status -->
    <section class="grid gap-4 sm:grid-cols-2 pt-2 border-t border-slate-200">
      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">Observações</label>
        <textarea name="observacoes" rows="3" maxlength="1000"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500"><?= e($dados['observacoes']) ?></textarea>
      </div>
      <label class="flex items-center gap-2 text-sm sm:col-span-2">
        <input type="checkbox" name="ativo" value="1" <?= !empty($dados['ativo']) ? 'checked' : '' ?>
               class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
        Fornecedor ativo
      </label>
    </section>

    <div class="flex justify-end gap-2 pt-2">
      <a href="fornecedores.php"
         class="px-4 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-sm font-medium">Cancelar</a>
      <button class="px-5 py-2.5 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-medium">
        <?= $editar ? 'Salvar alterações' : 'Cadastrar fornecedor' ?>
      </button>
    </div>
  </form>
</div>

<script>
(function () {
  /* ---------- Alternar PF/PJ ---------- */
  const form = document.getElementById('formFornecedor');
  const radios = form.querySelectorAll('input[name="tipo"]');
  const lblPF  = form.querySelectorAll('[data-lbl-pf]');
  const lblPJ  = form.querySelectorAll('[data-lbl-pj]');

  function atualizarTipo() {
    const tipo = form.querySelector('input[name="tipo"]:checked').value;
    lblPF.forEach(el => el.hidden = (tipo !== 'F'));
    lblPJ.forEach(el => el.hidden = (tipo !== 'J'));

    const doc = document.getElementById('cpf_cnpj');
    doc.placeholder = tipo === 'J' ? '00.000.000/0000-00' : '000.000.000-00';
    if (window.Mask && doc.value) doc.value = Mask.cpf_cnpj(doc.value);
  }
  radios.forEach(r => r.addEventListener('change', atualizarTipo));
  atualizarTipo();

  /* ---------- ViaCEP ---------- */
  const cep      = document.getElementById('cep');
  const status   = document.getElementById('cepStatus');
  const endereco = document.getElementById('endereco');
  const bairro   = document.getElementById('bairro');
  const cidade   = document.getElementById('cidade');
  const uf       = document.getElementById('uf');

  cep.addEventListener('blur', async () => {
    const num = cep.value.replace(/\D/g, '');
    if (num.length !== 8) { status.textContent = ''; return; }
    status.textContent = 'Buscando...';
    try {
      const r = await fetch('https://viacep.com.br/ws/' + num + '/json/');
      const d = await r.json();
      if (d.erro) { status.textContent = 'CEP não encontrado.'; return; }
      endereco.value = d.logradouro || endereco.value;
      bairro.value   = d.bairro     || bairro.value;
      cidade.value   = d.localidade || cidade.value;
      uf.value       = d.uf         || uf.value;
      status.textContent = '';
      document.querySelector('[name="numero"]').focus();
    } catch (e) {
      status.textContent = 'Falha ao consultar CEP.';
    }
  });
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
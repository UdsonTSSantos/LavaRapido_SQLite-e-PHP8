<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_clientes();

$id     = (int)($_GET['id'] ?? 0);
$editar = $id > 0;
$cliente = $editar ? buscar_cliente($id) : null;

if ($editar && !$cliente) {
    flash('Cliente não encontrado.', 'erro');
    header('Location: clientes.php');
    exit;
}

$erros = [];

/* valores iniciais */
$dados = $cliente ?? [
    'tipo' => 'F', 'nome' => '', 'nome_fantasia' => '', 'cpf_cnpj' => '',
    'rg_ie' => '', 'data_nascimento' => '', 'email' => '',
    'telefone' => '', 'celular' => '', 'cep' => '', 'endereco' => '',
    'numero' => '', 'complemento' => '', 'bairro' => '', 'cidade' => '',
    'uf' => '', 'observacoes' => '', 'ativo' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $dados = [
        'tipo'             => ($_POST['tipo'] ?? 'F') === 'J' ? 'J' : 'F',
        'nome'             => trim((string)($_POST['nome'] ?? '')),
        'nome_fantasia'    => trim((string)($_POST['nome_fantasia'] ?? '')),
        'cpf_cnpj'         => preg_replace('/\D/', '', (string)($_POST['cpf_cnpj'] ?? '')),
        'rg_ie'            => trim((string)($_POST['rg_ie'] ?? '')),
        'data_nascimento'  => trim((string)($_POST['data_nascimento'] ?? '')),
        'email'            => trim((string)($_POST['email'] ?? '')),
        'telefone'         => trim((string)($_POST['telefone'] ?? '')),
        'celular'          => trim((string)($_POST['celular'] ?? '')),
        'cep'              => trim((string)($_POST['cep'] ?? '')),
        'endereco'         => trim((string)($_POST['endereco'] ?? '')),
        'numero'           => trim((string)($_POST['numero'] ?? '')),
        'complemento'      => trim((string)($_POST['complemento'] ?? '')),
        'bairro'           => trim((string)($_POST['bairro'] ?? '')),
        'cidade'           => trim((string)($_POST['cidade'] ?? '')),
        'uf'               => strtoupper(trim((string)($_POST['uf'] ?? ''))),
        'observacoes'      => trim((string)($_POST['observacoes'] ?? '')),
        'ativo'            => isset($_POST['ativo']) ? 1 : 0,
    ];

    /* ---------- Validações ---------- */
    if ($dados['nome'] === '') {
        $erros[] = 'Informe o nome / razão social.';
    }

    // CPF/CNPJ é OPCIONAL: só valida se foi informado
    if ($dados['cpf_cnpj'] !== '' && !validar_cpf_cnpj($dados['cpf_cnpj'], $dados['tipo'])) {
        $erros[] = ($dados['tipo'] === 'J' ? 'CNPJ' : 'CPF') . ' informado é inválido.';
    }

    if ($dados['email'] !== '' && !validar_email($dados['email'])) {
        $erros[] = 'E-mail inválido.';
    }
    if ($dados['uf'] !== '' && !preg_match('/^[A-Z]{2}$/', $dados['uf'])) {
        $erros[] = 'UF inválida.';
    }
    if ($dados['data_nascimento'] !== '' && $dados['tipo'] === 'F') {
        $dt = DateTime::createFromFormat('Y-m-d', $dados['data_nascimento']);
        if (!$dt || $dt->format('Y-m-d') !== $dados['data_nascimento']) {
            $erros[] = 'Data de nascimento inválida.';
        }
    }

    /* Duplicidade: só checa se CPF/CNPJ foi informado */
    if (!$erros && $dados['cpf_cnpj'] !== '') {
        $sql = 'SELECT id FROM clientes WHERE cpf_cnpj = ?' . ($editar ? ' AND id <> ?' : '');
        $st  = db()->prepare($sql);
        $st->execute($editar ? [$dados['cpf_cnpj'], $id] : [$dados['cpf_cnpj']]);
        if ($st->fetch()) {
            $erros[] = 'Já existe um cliente cadastrado com esse '
                     . ($dados['tipo'] === 'J' ? 'CNPJ' : 'CPF') . '.';
        }
    }

    if (!$erros) {
        if ($editar) {
            $sql = 'UPDATE clientes SET
                tipo=:tipo, nome=:nome, nome_fantasia=:nome_fantasia,
                cpf_cnpj=:cpf_cnpj, rg_ie=:rg_ie, data_nascimento=:data_nascimento,
                email=:email, telefone=:telefone, celular=:celular,
                cep=:cep, endereco=:endereco, numero=:numero, complemento=:complemento,
                bairro=:bairro, cidade=:cidade, uf=:uf,
                observacoes=:observacoes, ativo=:ativo,
                atualizado_em=datetime(\'now\',\'localtime\')
                WHERE id=:id';
            $dados['id'] = $id;
            db()->prepare($sql)->execute($dados);
            flash('Cliente atualizado com sucesso.', 'sucesso');
        } else {
            $sql = 'INSERT INTO clientes
                (tipo, nome, nome_fantasia, cpf_cnpj, rg_ie, data_nascimento,
                 email, telefone, celular, cep, endereco, numero, complemento,
                 bairro, cidade, uf, observacoes, ativo)
                VALUES
                (:tipo, :nome, :nome_fantasia, :cpf_cnpj, :rg_ie, :data_nascimento,
                 :email, :telefone, :celular, :cep, :endereco, :numero, :complemento,
                 :bairro, :cidade, :uf, :observacoes, :ativo)';
            db()->prepare($sql)->execute($dados);
            flash('Cliente cadastrado com sucesso.', 'sucesso');
        }
        header('Location: clientes.php');
        exit;
    }
}

$titulo = $editar ? 'Editar cliente' : 'Novo cliente';
require __DIR__ . '/header.php';
?>

<!-- ============ MÁSCARAS (inline, garantidas) ============ -->
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
    exec(); // aplica valor que já veio do PHP (edição)
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-mask]').forEach(aplicar);
  });
})();
</script>

<div class="max-w-4xl mx-auto">
  <form method="post" class="bg-white rounded-xl shadow p-6 sm:p-8 space-y-6" novalidate
        id="formCliente">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold"><?= $editar ? 'Editar cliente' : 'Novo cliente' ?></h1>
        <p class="text-sm text-slate-500">Preencha os dados do cliente.</p>
      </div>
      <a href="clientes.php" class="text-sm text-sky-600 hover:underline">← Voltar</a>
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
      <label class="block text-sm font-medium mb-2">Tipo de pessoa *</label>
      <div class="flex gap-2">
        <label class="cursor-pointer">
          <input type="radio" name="tipo" value="F" class="peer sr-only"
                 <?= $dados['tipo'] === 'F' ? 'checked' : '' ?>>
          <span class="inline-block px-4 py-2 rounded-lg border border-slate-300 text-sm
                       peer-checked:bg-sky-600 peer-checked:text-white peer-checked:border-sky-600">
            Pessoa Física
          </span>
        </label>
        <label class="cursor-pointer">
          <input type="radio" name="tipo" value="J" class="peer sr-only"
                 <?= $dados['tipo'] === 'J' ? 'checked' : '' ?>>
          <span class="inline-block px-4 py-2 rounded-lg border border-slate-300 text-sm
                       peer-checked:bg-sky-600 peer-checked:text-white peer-checked:border-sky-600">
            Pessoa Jurídica
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

      <div class="sm:col-span-2" data-campo-pj <?= $dados['tipo'] === 'J' ? '' : 'hidden' ?>>
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

      <div data-campo-pf <?= $dados['tipo'] === 'F' ? '' : 'hidden' ?>>
        <label class="block text-sm font-medium mb-1">Data de nascimento</label>
        <input type="date" name="data_nascimento" value="<?= e($dados['data_nascimento']) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">E-mail</label>
        <input type="email" name="email" maxlength="150" value="<?= e($dados['email']) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Telefone</label>
        <input name="telefone" id="telefone" data-mask="telefone" inputmode="numeric"
               value="<?= e($dados['telefone']) ?>" placeholder="(00) 0000-0000"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Celular</label>
        <input name="celular" id="celular" data-mask="celular" inputmode="numeric"
               value="<?= e($dados['celular']) ?>" placeholder="(00) 00000-0000"
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
        Cliente ativo
      </label>
    </section>

    <div class="flex justify-end gap-2 pt-2">
      <a href="clientes.php"
         class="px-4 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-sm font-medium">Cancelar</a>
      <button class="px-5 py-2.5 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-medium">
        <?= $editar ? 'Salvar alterações' : 'Cadastrar cliente' ?>
      </button>
    </div>
  </form>
</div>

<script>
(function () {
  /* ---------- Alternar PF/PJ ---------- */
  const form = document.getElementById('formCliente');
  const radios = form.querySelectorAll('input[name="tipo"]');
  const camposPJ = form.querySelectorAll('[data-campo-pj]');
  const camposPF = form.querySelectorAll('[data-campo-pf]');
  const lblPF    = form.querySelectorAll('[data-lbl-pf]');
  const lblPJ    = form.querySelectorAll('[data-lbl-pj]');

  function atualizarTipo() {
    const tipo = form.querySelector('input[name="tipo"]:checked').value;
    camposPJ.forEach(el => el.hidden = (tipo !== 'J'));
    camposPF.forEach(el => el.hidden = (tipo !== 'F'));
    lblPF.forEach(el => el.hidden = (tipo !== 'F'));
    lblPJ.forEach(el => el.hidden = (tipo !== 'J'));

    const doc = document.getElementById('cpf_cnpj');
    doc.placeholder = tipo === 'J' ? '00.000.000/0000-00' : '000.000.000-00';

    // Reaplica máscara ao valor atual quando troca PF ↔ PJ
    if (window.Mask && doc.value) {
      doc.value = Mask.cpf_cnpj(doc.value);
    }
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
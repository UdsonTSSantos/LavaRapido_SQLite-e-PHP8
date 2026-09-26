<?php
require_once __DIR__ . '/config.php';
$admin = exigir_admin();

$erros = [];
$emp   = empresa();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $dados = [
        'razao_social'       => trim((string)($_POST['razao_social'] ?? '')),
        'nome_fantasia'      => trim((string)($_POST['nome_fantasia'] ?? '')),
        'cnpj'               => preg_replace('/\D/', '', (string)($_POST['cnpj'] ?? '')),
        'inscricao_estadual' => trim((string)($_POST['inscricao_estadual'] ?? '')),
        'email'              => trim((string)($_POST['email'] ?? '')),
        'telefone'           => trim((string)($_POST['telefone'] ?? '')),
        'celular'            => trim((string)($_POST['celular'] ?? '')),
        'cep'                => trim((string)($_POST['cep'] ?? '')),
        'endereco'           => trim((string)($_POST['endereco'] ?? '')),
        'numero'             => trim((string)($_POST['numero'] ?? '')),
        'complemento'        => trim((string)($_POST['complemento'] ?? '')),
        'bairro'             => trim((string)($_POST['bairro'] ?? '')),
        'cidade'             => trim((string)($_POST['cidade'] ?? '')),
        'uf'                 => strtoupper(trim((string)($_POST['uf'] ?? ''))),
    ];

    if ($dados['razao_social'] === '') $erros[] = 'Informe a Razão Social.';
    if ($dados['cnpj'] === '')        $erros[] = 'Informe o CNPJ.';
    elseif (!validar_cnpj($dados['cnpj'])) $erros[] = 'CNPJ inválido.';
    if ($dados['email'] !== '' && !validar_email($dados['email'])) $erros[] = 'E-mail da empresa inválido.';
    if ($dados['uf'] !== '' && !preg_match('/^[A-Z]{2}$/', $dados['uf'])) $erros[] = 'UF inválida.';

    $novoLogoPath = null;
    if (!empty($_FILES['logo']['name'])) {
        $r = salvar_logo($_FILES['logo']);
        if ($r['ok']) {
            $novoLogoPath = $r['path'];
        } else {
            $erros[] = $r['erro'];
        }
    }

    if (!$erros) {
        $logoFinal = $novoLogoPath ?? ($emp['logo_path'] ?? '');

        $sql = 'UPDATE empresa SET
                    razao_social = :razao_social,
                    nome_fantasia = :nome_fantasia,
                    cnpj = :cnpj,
                    inscricao_estadual = :inscricao_estadual,
                    email = :email,
                    telefone = :telefone,
                    celular = :celular,
                    cep = :cep,
                    endereco = :endereco,
                    numero = :numero,
                    complemento = :complemento,
                    bairro = :bairro,
                    cidade = :cidade,
                    uf = :uf,
                    logo_path = :logo_path,
                    atualizado_em = datetime(\'now\',\'localtime\')
                WHERE id = 1';
        $dados['logo_path'] = $logoFinal;
        db()->prepare($sql)->execute($dados);

        flash('Dados da empresa salvos com sucesso.', 'sucesso');
        header('Location: empresa.php');
        exit;
    }
}

$titulo = 'Cadastro da Empresa';
require __DIR__ . '/header.php';
?>

<div class="max-w-4xl mx-auto">
  <div class="bg-white rounded-xl shadow p-6 sm:p-8">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
      <div>
        <h1 class="text-2xl font-semibold">Cadastro da Empresa</h1>
        <p class="text-sm text-slate-500">Dados usados no sistema, relatórios e favicon.</p>
      </div>
      <a href="dashboard.php" class="text-sm text-sky-600 hover:underline">← Voltar</a>
    </div>

    <?php if ($erros): ?>
      <div class="mb-5 rounded-md border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm">
        <ul class="list-disc pl-5 space-y-1">
          <?php foreach ($erros as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="space-y-6" novalidate>
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <!-- LOGO -->
      <section class="grid gap-5 sm:grid-cols-[160px_1fr] items-start">
        <div>
          <label class="block text-sm font-medium mb-2">Logo</label>
          <div class="w-40 h-40 rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 flex items-center justify-center overflow-hidden">
            <?php $logo = empresa_logo_url(); ?>
            <?php if ($logo): ?>
              <img id="previewLogo" src="<?= e($logo) ?>" alt="Logo" class="max-w-full max-h-full object-contain">
            <?php else: ?>
              <img id="previewLogo" src="" alt="" class="hidden max-w-full max-h-full object-contain">
              <span id="previewVazio" class="text-xs text-slate-400 text-center px-2">Sem logo<br>(PNG/JPG, máx 2 MB)</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="pt-1">
          <input type="file" name="logo" id="logo" accept="image/png,image/jpeg,image/gif,image/webp"
                 class="block w-full text-sm text-slate-600
                        file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0
                        file:bg-sky-600 file:text-white hover:file:bg-sky-500
                        file:cursor-pointer">
          <p class="mt-2 text-xs text-slate-500">
            Formatos aceitos: <strong>PNG, JPG, GIF, WEBP</strong>. Tamanho máximo: <strong>2 MB</strong>.<br>
            Recomendado: imagem quadrada (ex.: 512×512) com fundo transparente.
          </p>
        </div>
      </section>

      <hr class="border-slate-200">

      <!-- DADOS -->
      <section class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium mb-1">Razão Social *</label>
          <input name="razao_social" required maxlength="150" value="<?= e($emp['razao_social'] ?? '') ?>"
                 class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        </div>

        <div class="sm:col-span-2">
          <label class="block text-sm font-medium mb-1">Nome Fantasia</label>
          <input name="nome_fantasia" maxlength="150" value="<?= e($emp['nome_fantasia'] ?? '') ?>"
                 class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">CNPJ *</label>
          <input name="cnpj" id="cnpj" required inputmode="numeric"
                 value="<?= e($emp['cnpj'] ? formatar_cnpj($emp['cnpj']) : '') ?>"
                 placeholder="00.000.000/0000-00"
                 class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Inscrição Estadual</label>
          <input name="inscricao_estadual" maxlength="20" value="<?= e($emp['inscricao_estadual'] ?? '') ?>"
                 class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Telefone</label>
          <input name="telefone" id="telefone" inputmode="numeric"
                 value="<?= e($emp['telefone'] ?? '') ?>" placeholder="(00) 0000-0000"
                 class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Celular</label>
          <input name="celular" id="celular" inputmode="numeric"
                 value="<?= e($emp['celular'] ?? '') ?>" placeholder="(00) 00000-0000"
                 class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        </div>

        <div class="sm:col-span-2">
          <label class="block text-sm font-medium mb-1">E-mail</label>
          <input type="email" name="email" maxlength="150" value="<?= e($emp['email'] ?? '') ?>"
                 class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">CEP</label>
          <input name="cep" id="cep" inputmode="numeric" maxlength="9"
                 value="<?= e($emp['cep'] ?? '') ?>" placeholder="00000-000"
                 class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        </div>

        <div class="sm:col-span-2">
          <label class="block text-sm font-medium mb-1">Endereço</label>
          <input name="endereco" maxlength="150" value="<?= e($emp['endereco'] ?? '') ?>"
                 class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Número</label>
          <input name="numero" maxlength="10" value="<?= e($emp['numero'] ?? '') ?>"
                 class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Complemento</label>
          <input name="complemento" maxlength="80" value="<?= e($emp['complemento'] ?? '') ?>"
                 class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Bairro</label>
          <input name="bairro" maxlength="80" value="<?= e($emp['bairro'] ?? '') ?>"
                 class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Cidade</label>
          <input name="cidade" maxlength="80" value="<?= e($emp['cidade'] ?? '') ?>"
                 class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">UF</label>
          <input name="uf" maxlength="2" value="<?= e($emp['uf'] ?? '') ?>" placeholder="SP"
                 class="w-full rounded-lg border border-slate-300 px-3 py-2.5 uppercase focus:outline-none focus:ring-2 focus:ring-sky-500">
        </div>
      </section>

      <div class="flex justify-end gap-2 pt-2">
        <a href="dashboard.php" class="px-4 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-sm font-medium">Cancelar</a>
        <button class="px-5 py-2.5 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-medium">
          Salvar dados
        </button>
      </div>
    </form>
  </div>
</div>

<script>
/* ---------- Preview do logo ---------- */
document.getElementById('logo').addEventListener('change', function (ev) {
  const file = ev.target.files[0];
  if (!file) return;
  const img = document.getElementById('previewLogo');
  const vazio = document.getElementById('previewVazio');
  img.src = URL.createObjectURL(file);
  img.classList.remove('hidden');
  if (vazio) vazio.classList.add('hidden');
});

/* ---------- Máscaras ---------- */
function somenteDigitos(v) { return v.replace(/\D+/g, ''); }

function mascaraCNPJ(v) {
  v = somenteDigitos(v).slice(0, 14);
  v = v.replace(/^(\d{2})(\d)/, '$1.$2');
  v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
  v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
  v = v.replace(/(\d{4})(\d)/, '$1-$2');
  return v;
}
function mascaraTelefone(v) {          // (00) 0000-0000
  v = somenteDigitos(v).slice(0, 10);
  v = v.replace(/^(\d{2})(\d)/, '($1) $2');
  v = v.replace(/(\d{4})(\d)/, '$1-$2');
  return v;
}
function mascaraCelular(v) {           // (00) 00000-0000
  v = somenteDigitos(v).slice(0, 11);
  v = v.replace(/^(\d{2})(\d)/, '($1) $2');
  v = v.replace(/(\d{5})(\d)/, '$1-$2');
  return v;
}
function mascaraCEP(v) {
  v = somenteDigitos(v).slice(0, 8);
  return v.replace(/^(\d{5})(\d)/, '$1-$2');
}

function aplicarMascara(id, fn) {
  const el = document.getElementById(id);
  if (!el) return;
  const exec = () => { el.value = fn(el.value); };
  el.addEventListener('input', exec);
  el.addEventListener('blur',  exec);
}

aplicarMascara('cnpj',     mascaraCNPJ);
aplicarMascara('telefone', mascaraTelefone);
aplicarMascara('celular',  mascaraCelular);
aplicarMascara('cep',      mascaraCEP);
</script>

<?php require __DIR__ . '/footer.php'; ?>
<?php
require_once __DIR__ . '/config.php';
$admin = exigir_admin();
ensure_usuarios();

$id      = (int)($_GET['id'] ?? 0);
$editar  = $id > 0;
$usuario = $editar ? usuario_completo($id) : null;

if ($editar && !$usuario) {
    flash('Usuário não encontrado.', 'erro');
    header('Location: usuarios.php');
    exit;
}

$erros = [];

$dados = $usuario ?? [
    'nome'    => '',
    'email'   => '',
    'cpf'     => '',
    'celular' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $dados = [
        'nome'    => trim((string)($_POST['nome'] ?? '')),
        'email'   => trim((string)($_POST['email'] ?? '')),
        'cpf'     => preg_replace('/\D/', '', (string)($_POST['cpf'] ?? '')),
        'celular' => preg_replace('/\D/', '', (string)($_POST['celular'] ?? '')),
    ];

    /* ---------- Validações ---------- */
    if ($dados['nome'] === '')                             $erros[] = 'Informe o nome.';
    if (!validar_email($dados['email']))                   $erros[] = 'Informe um endereço de e-mail válido.';
    if ($dados['cpf'] !== '' && !validar_cpf($dados['cpf'])) $erros[] = 'CPF inválido.';
    if ($dados['celular'] !== '' && strlen($dados['celular']) < 10) $erros[] = 'Celular inválido.';

    /* ---------- E-mail duplicado ---------- */
    if (!$erros) {
        $sql = 'SELECT id FROM usuarios WHERE email = ?' . ($editar ? ' AND id <> ?' : '');
        $st  = db()->prepare($sql);
        $st->execute($editar ? [$dados['email'], $id] : [$dados['email']]);
        if ($st->fetch()) $erros[] = 'Já existe um usuário com esse e-mail.';
    }

    /* ---------- Salvar ---------- */
    if (!$erros) {
        if ($editar) {
            /* Atualização: NÃO altera senha */
            db()->prepare("
                UPDATE usuarios SET
                    nome = :nome,
                    email = :email,
                    cpf = :cpf,
                    celular = :celular
                WHERE id = :id
            ")->execute([
                ':nome'    => $dados['nome'],
                ':email'   => $dados['email'],
                ':cpf'     => $dados['cpf'],
                ':celular' => $dados['celular'],
                ':id'      => $id,
            ]);

            flash('Usuário atualizado com sucesso.', 'sucesso');
            header('Location: usuarios.php');
            exit;
        } else {
            /* Criação: gera senha temporária */
            $senhaTemp = gerar_senha_temporaria(10);

            db()->prepare("
                INSERT INTO usuarios
                    (email, senha_hash, is_admin, precisa_trocar_senha,
                     nome, cpf, celular)
                VALUES (?, ?, 0, 1, ?, ?, ?)
            ")->execute([
                $dados['email'],
                password_hash($senhaTemp, PASSWORD_DEFAULT),
                $dados['nome'],
                $dados['cpf'],
                $dados['celular'],
            ]);
            $novoId = (int)db()->lastInsertId();

            /* Guarda os dados na sessão para exibir na próxima página */
            $_SESSION['senha_gerada'] = [
                'id'      => $novoId,
                'nome'    => $dados['nome'],
                'email'   => $dados['email'],
                'celular' => $dados['celular'],
                'senha'   => $senhaTemp,
                'acao'    => 'criado',
            ];

            header('Location: usuario_senha_gerada.php');
            exit;
        }
    }
}

$titulo = $editar ? 'Editar usuário' : 'Novo usuário';
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
    celular(v) {
      v = soDigitos(v).slice(0, 11);
      v = v.replace(/^(\d{2})(\d)/, '($1) $2');
      v = v.replace(/(\d{5})(\d)/, '$1-$2');
      return v;
    }
  };
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

<div class="max-w-3xl mx-auto">
  <form method="post" class="bg-white rounded-xl shadow p-6 sm:p-8 space-y-6" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold"><?= $editar ? 'Editar usuário' : 'Novo usuário' ?></h1>
        <p class="text-sm text-slate-500">
          <?= $editar
                ? 'Altere os dados do usuário.'
                : 'A senha será gerada automaticamente após salvar.' ?>
        </p>
      </div>
      <a href="usuarios.php" class="text-sm text-sky-600 hover:underline">← Voltar</a>
    </div>

    <?php if (!$editar): ?>
      <div class="rounded-lg border border-sky-200 bg-sky-50 text-sky-800 px-4 py-3 text-sm flex items-start gap-3">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none"
             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
          <strong>Senha automática:</strong> ao salvar, o sistema irá gerar uma senha temporária
          segura. Você poderá copiá-la, enviá-la por WhatsApp ou imprimi-la para entregar ao usuário.
          No primeiro acesso ele será obrigado a trocá-la.
        </div>
      </div>
    <?php endif; ?>

    <?php if ($erros): ?>
      <div class="rounded-md border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm">
        <ul class="list-disc pl-5 space-y-1">
          <?php foreach ($erros as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <section class="grid gap-4 sm:grid-cols-2">
      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">Nome *</label>
        <input name="nome" required maxlength="120" value="<?= e($dados['nome']) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">E-mail *</label>
        <input type="email" name="email" required value="<?= e($dados['email']) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">CPF</label>
        <input name="cpf" data-mask="cpf" inputmode="numeric" maxlength="14"
               value="<?= e($dados['cpf'] ? formatar_cpf($dados['cpf']) : '') ?>"
               placeholder="000.000.000-00"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Celular</label>
        <input name="celular" data-mask="celular" inputmode="numeric" maxlength="16"
               value="<?= e($dados['celular'] ? formatar_fone_usuario($dados['celular']) : '') ?>"
               placeholder="(00) 00000-0000"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        <p class="mt-1 text-xs text-slate-500">Usado para o envio da senha por WhatsApp.</p>
      </div>
    </section>

    <div class="flex justify-end gap-2 pt-2">
      <a href="usuarios.php"
         class="px-4 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-sm font-medium">Cancelar</a>
      <button class="px-5 py-2.5 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-medium">
        <?= $editar ? 'Salvar alterações' : 'Criar e gerar senha' ?>
      </button>
    </div>
  </form>
</div>

<?php require __DIR__ . '/footer.php'; ?>
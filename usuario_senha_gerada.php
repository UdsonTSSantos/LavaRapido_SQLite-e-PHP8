<?php
require_once __DIR__ . '/config.php';
exigir_admin();

/* Dados vindos da sessão (após criar/redefinir senha) */
$info = $_SESSION['senha_gerada'] ?? null;

if (!$info) {
    flash('Nenhuma senha gerada recentemente.', 'info');
    header('Location: usuarios.php');
    exit;
}

/* Limpa da sessão para não exibir de novo ao recarregar */
unset($_SESSION['senha_gerada']);

$acao = $info['acao'] ?? 'criado';
$email = $info['email'];
$nome  = $info['nome'] ?: $email;
$senha = $info['senha'];

/* Login URL */
$loginUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
          . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
          . dirname($_SERVER['PHP_SELF']) . '/index.php';
$loginUrl = rtrim($loginUrl, '/');

/* Telefone WhatsApp */
$telWhats = '';
if (!empty($info['celular'])) {
    $telWhats = preg_replace('/\D/', '', $info['celular']);
    if ($telWhats && !str_starts_with($telWhats, '55')) $telWhats = '55' . $telWhats;
}

/* Mensagem para WhatsApp */
$msg = "Ola, {$nome}!\n\n"
     . "Seu acesso ao sistema foi " . ($acao === 'redefinida' ? 'atualizado' : 'criado') . ".\n\n"
     . "Login: {$email}\n"
     . "Senha provisoria: {$senha}\n\n"
     . "Acesse: {$loginUrl}\n"
     . "No primeiro acesso, o sistema pedira para voce criar uma nova senha.\n\n"
     . "Qualquer duvida, estamos a disposicao!";

$whatsUrl = 'https://api.whatsapp.com/send?'
          . ($telWhats ? 'phone=' . $telWhats . '&' : '')
          . 'text=' . rawurlencode($msg);

$titulo = 'Senha gerada';
require __DIR__ . '/header.php';
?>

<div class="max-w-2xl mx-auto">

  <div class="bg-white rounded-xl shadow p-6 sm:p-8 space-y-6">

    <!-- Ícone + título -->
    <div class="text-center">
      <div class="mx-auto w-14 h-14 rounded-full bg-emerald-100 flex items-center justify-center mb-3">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-emerald-600" fill="none"
             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
        </svg>
      </div>
      <h1 class="text-2xl font-semibold">
        <?= $acao === 'redefinida' ? 'Senha redefinida!' : 'Usuário criado!' ?>
      </h1>
      <p class="text-sm text-slate-500 mt-1">
        Envie os dados abaixo para o usuário. Guarde esta senha em local seguro — ela
        <strong>não</strong> será mostrada novamente.
      </p>
    </div>

    <!-- Aviso -->
    <div class="rounded-md border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3 text-sm">
      <strong>Importante:</strong> esta é a única vez que a senha aparece.
      Se você sair desta página sem copiá-la, será necessário gerar uma nova.
    </div>

    <!-- Cartão de credenciais -->
    <div class="rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 p-5">
      <div class="grid gap-4">
        <div>
          <div class="text-xs uppercase text-slate-500 font-semibold mb-1">Nome</div>
          <div class="text-slate-800 font-medium"><?= e($nome) ?></div>
        </div>

        <div>
          <div class="text-xs uppercase text-slate-500 font-semibold mb-1">Login (e-mail)</div>
          <div class="flex items-center gap-2">
            <input type="text" readonly value="<?= e($email) ?>" id="campoEmail"
                   class="flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm font-mono">
            <button type="button" data-copy="#campoEmail"
                    class="btn-copy px-3 py-2.5 rounded-lg bg-slate-200 hover:bg-slate-300 text-sm whitespace-nowrap">
              Copiar
            </button>
          </div>
        </div>

        <div>
          <div class="text-xs uppercase text-slate-500 font-semibold mb-1">Senha provisória</div>
          <div class="flex items-center gap-2">
            <input type="text" readonly value="<?= e($senha) ?>" id="campoSenha"
                   class="flex-1 rounded-lg border border-emerald-300 bg-white px-3 py-2.5 text-lg font-mono tracking-wider text-emerald-800 font-semibold">
            <button type="button" data-copy="#campoSenha"
                    class="btn-copy px-3 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm whitespace-nowrap">
              Copiar
            </button>
          </div>
        </div>

        <div>
          <div class="text-xs uppercase text-slate-500 font-semibold mb-1">Endereço de acesso</div>
          <div class="text-slate-700 text-sm break-all font-mono"><?= e($loginUrl) ?></div>
        </div>
      </div>
    </div>

    <!-- Ações -->
    <div class="grid gap-2 sm:grid-cols-2 pt-2">
      <a href="<?= e($whatsUrl) ?>" target="_blank" rel="noopener"
         class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-medium">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
          <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.573-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
        </svg>
        Enviar por WhatsApp
      </a>

      <button onclick="window.print()"
              class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-slate-800 hover:bg-slate-700 text-white font-medium">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round"
                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
        </svg>
        Imprimir
      </button>
    </div>

    <div class="pt-2 text-center">
      <a href="usuarios.php" class="text-sm text-sky-600 hover:underline">← Voltar para a lista de usuários</a>
    </div>

  </div>
</div>

<script>
/* Copiar para a área de transferência */
document.querySelectorAll('.btn-copy').forEach(btn => {
  btn.addEventListener('click', async () => {
    const alvo = document.querySelector(btn.dataset.copy);
    if (!alvo) return;
    try {
      await navigator.clipboard.writeText(alvo.value);
      const original = btn.textContent;
      btn.textContent = '✓ Copiado';
      btn.classList.add('bg-emerald-600', 'text-white');
      setTimeout(() => {
        btn.textContent = original;
        btn.classList.remove('bg-emerald-600', 'text-white');
      }, 1500);
    } catch (e) {
      alvo.select();
      document.execCommand('copy');
    }
  });
});

/* Impressão em formato amigável */
const style = document.createElement('style');
style.textContent = `
  @media print {
    nav, .btn-copy, a[href^="https://api.whatsapp"], button {
      display: none !important;
    }
    body { background: #fff !important; }
    .bg-white { box-shadow: none !important; border: 1px solid #cbd5e1; }
  }
`;
document.head.appendChild(style);
</script>

<?php require __DIR__ . '/footer.php'; ?>
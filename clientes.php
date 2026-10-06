<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_clientes();

/* ---------- Excluir (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    csrf_validar();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('DELETE FROM clientes WHERE id = ?')->execute([$id]);
        flash('Cliente excluído.', 'sucesso');
    }
    header('Location: clientes.php');
    exit;
}

/* ---------- Busca ---------- */
$busca  = trim((string)($_GET['q'] ?? ''));
$where  = '';
$params = [];
if ($busca !== '') {
    $where = "WHERE nome LIKE :q2 OR nome_fantasia LIKE :q2
                 OR email LIKE :q2 OR cpf_cnpj LIKE :q";
    $params[':q']  = '%' . preg_replace('/\D/', '', $busca) . '%';
    $params[':q2'] = '%' . $busca . '%';
}

$stmt = db()->prepare("SELECT * FROM clientes $where ORDER BY nome COLLATE NOCASE");
$stmt->execute($params);
$clientes = $stmt->fetchAll();

$titulo = 'Clientes';
require __DIR__ . '/header.php';
?>

<div class="bg-white rounded-2xl shadow-card border border-slate-200 overflow-hidden">
  <div class="px-5 py-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-xl font-bold text-slate-900">Clientes</h1>
      <p class="text-xs text-slate-500 mt-0.5"><?= count($clientes) ?> registro(s)</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <form method="get" class="flex">
        <input type="text" name="q" value="<?= e($busca) ?>"
               placeholder="Buscar por nome, documento ou e-mail"
               class="w-56 sm:w-72 rounded-l-lg border border-slate-300 px-3 py-2 text-sm font-semibold
                      focus:outline-none focus:ring-2 focus:ring-brand-400">
        <button class="rounded-r-lg bg-slate-800 hover:bg-slate-700 text-white px-4 text-sm font-semibold">Buscar</button>
      </form>
      <a href="cliente_form.php"
         class="rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 text-sm font-semibold
                whitespace-nowrap shadow-sm">
        + Novo
      </a>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-slate-600">
        <tr>
          <th class="text-left px-4 py-2.5 font-bold">Nome</th>
          <th class="text-left px-4 py-2.5 font-bold">CPF/CNPJ</th>
          <th class="text-left px-4 py-2.5 font-bold">Contato</th>
          <th class="text-left px-4 py-2.5 font-bold">Cidade/UF</th>
          <th class="text-left px-4 py-2.5 font-bold">Status</th>
          <th class="px-4 py-2.5"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php if (!$clientes): ?>
        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">
          Nenhum cliente cadastrado. Clique em <strong>+ Novo</strong> para começar.
        </td></tr>
      <?php else: foreach ($clientes as $c): ?>
        <tr class="hover:bg-slate-50 transition">
          <td class="px-4 py-3">
            <div class="font-semibold text-slate-800"><?= e($c['nome']) ?></div>
            <?php if ($c['tipo'] === 'J' && $c['nome_fantasia']): ?>
              <div class="text-xs text-slate-500"><?= e($c['nome_fantasia']) ?></div>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 whitespace-nowrap">
            <?php if ($c['cpf_cnpj']): ?>
              <div class="text-xs text-slate-400 font-bold"><?= $c['tipo'] === 'J' ? 'CNPJ' : 'CPF' ?></div>
              <?= e(formatar_cpf_cnpj($c['cpf_cnpj'], $c['tipo'])) ?>
            <?php else: ?>
              <span class="text-slate-400">—</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3">
            <?php if ($c['celular']): ?><div class="font-medium text-slate-700"><?= e($c['celular']) ?></div><?php endif; ?>
            <?php if ($c['telefone']): ?><div class="text-xs text-slate-500"><?= e($c['telefone']) ?></div><?php endif; ?>
            <?php if ($c['email']): ?><div class="text-xs text-slate-500 break-all"><?= e($c['email']) ?></div><?php endif; ?>
          </td>
          <td class="px-4 py-3">
            <?= e($c['cidade']) ?><?= $c['uf'] ? '/' . e($c['uf']) : '' ?>
          </td>
          <td class="px-4 py-3">
            <?php if ($c['ativo']): ?>
              <span class="inline-block text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-bold">Ativo</span>
            <?php else: ?>
              <span class="inline-block text-xs px-2 py-0.5 rounded-full bg-slate-200 text-slate-700 font-bold">Inativo</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 text-right whitespace-nowrap">
            <button type="button"
                    class="btn-historico text-brand-600 hover:underline text-xs font-semibold mr-3"
                    data-id="<?= (int)$c['id'] ?>"
                    data-nome="<?= e($c['nome']) ?>">
              Histórico
            </button>
            <a href="cliente_form.php?id=<?= (int)$c['id'] ?>"
               class="text-sky-600 hover:underline text-xs font-semibold mr-3">Editar</a>
            <form method="post" class="inline"
                  onsubmit="return confirm('Excluir o cliente <?= e(addslashes($c['nome'])) ?>?');">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="acao" value="excluir">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button class="text-rose-600 hover:underline text-xs font-semibold">Excluir</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- =========================================================
     MODAL: HISTÓRICO DE LAVAGENS DO CLIENTE
     ========================================================= -->
<div id="modalHistorico" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl max-h-[92vh] flex flex-col overflow-hidden">

    <!-- Cabeçalho -->
    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between flex-shrink-0 bg-white no-print">
      <div>
        <h3 class="font-bold text-slate-800">Histórico de lavagens</h3>
        <p class="text-xs text-slate-500" id="histClienteNome"></p>
      </div>
      <button type="button" id="fecharHistorico" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
    </div>

    <!-- Filtros -->
    <div class="px-6 py-3 border-b border-slate-200 bg-slate-50 flex flex-wrap items-end gap-3 flex-shrink-0 no-print">
      <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">De</label>
        <input type="date" id="histDe"
               class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-black
                      focus:outline-none focus:ring-2 focus:ring-brand-400">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">Até</label>
        <input type="date" id="histAte"
               class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-black
                      focus:outline-none focus:ring-2 focus:ring-brand-400">
      </div>
      <button type="button" id="histFiltrar"
              class="rounded-lg bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 text-sm font-semibold">
        Filtrar
      </button>
      <button type="button" id="histMesAtual"
              class="rounded-lg bg-brand-500 hover:bg-brand-700 text-white px-4 py-2 text-sm font-semibold">
        Mês atual
      </button>
      <button type="button" id="histLimpar"
              class="text-xs text-slate-500 hover:underline font-medium ml-1">
        Limpar filtro
      </button>

      <div class="ml-auto flex items-center gap-3">
        <div class="text-right">
          <div class="text-xs text-slate-500">Lavagens</div>
          <div class="font-bold text-slate-800" id="histQtd">0</div>
        </div>
        <div class="text-right">
          <div class="text-xs text-slate-500">Total</div>
          <div class="font-bold text-emerald-700" id="histTotal">R$ 0,00</div>
        </div>
      </div>
    </div>

    <!-- Corpo (rola) -->
    <div class="overflow-y-auto flex-1" id="histCorpo">
      <div class="px-6 py-10 text-center text-slate-400 text-sm" id="histLoading">
        Carregando histórico...
      </div>

      <div id="histResultado" class="hidden">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-100 text-slate-700 sticky top-0 z-10">
            <tr>
              <th class="text-left px-4 py-2 font-bold whitespace-nowrap">Data</th>
              <th class="text-left px-4 py-2 font-bold">Veículo</th>
              <th class="text-left px-4 py-2 font-bold">Placa</th>
              <th class="text-left px-4 py-2 font-bold">Serviços</th>
              <th class="text-right px-4 py-2 font-bold whitespace-nowrap">Valor</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100" id="histTbody"></tbody>
          <tfoot>
            <tr class="bg-slate-50 border-t-2 border-slate-300">
              <td colspan="4" class="px-4 py-3 text-right font-bold text-slate-700">TOTAL</td>
              <td class="px-4 py-3 text-right font-extrabold text-emerald-700 whitespace-nowrap" id="histRodapeTotal">
                R$ 0,00
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div id="histVazio" class="hidden px-6 py-12 text-center text-slate-400 text-sm">
        Nenhuma lavagem encontrada no período.
      </div>
    </div>

    <!-- Rodapé -->
    <div class="px-6 py-4 border-t border-slate-200 flex justify-end gap-2 flex-shrink-0 no-print bg-white">
      <button type="button" id="histImprimir"
              class="rounded-lg bg-brand-500 hover:bg-brand-700 text-white px-5 py-2.5 text-sm font-semibold shadow-sm">
        🖨️ Imprimir
      </button>
      <button type="button" id="histFechar2"
              class="rounded-lg bg-slate-100 hover:bg-slate-200 px-5 py-2.5 text-sm font-semibold">
        Fechar
      </button>
    </div>

  </div>
</div>

<style>
  /* =========================================================
     IMPRESSÃO DO HISTÓRICO — 80mm (cupom) ou A4, o que o usuário escolher
     ========================================================= */
  @media print {
    /* Esconde tudo na página */
    body * { visibility: hidden !important; }

    /* Só o modal do histórico fica visível */
    #modalHistorico, #modalHistorico * { visibility: visible !important; }

    /* Posiciona o modal no topo da folha */
    #modalHistorico {
      position: absolute !important;
      inset: 0 !important;
      background: #fff !important;
      padding: 0 !important;
      display: block !important;
    }

    #modalHistorico > div {
      box-shadow: none !important;
      border-radius: 0 !important;
      max-width: 100% !important;
      max-height: none !important;
      width: 100% !important;
      overflow: visible !important;
    }

    #histCorpo {
      overflow: visible !important;
      max-height: none !important;
    }

    .no-print { display: none !important; }

    /* Cabeçalho amigável na impressão */
    #modalHistorico::before {
      content: "Histórico de Lavagens";
      display: block;
      font-size: 16px;
      font-weight: 700;
      padding: 8px 16px;
      border-bottom: 2px solid #004173;
      color: #004173;
    }

    /* Ajustes de tabela */
    table { font-size: 11px !important; }
    thead { background: #e2e8f0 !important; }
    th, td { padding: 5px 8px !important; }
  }
</style>

<script>
(function () {
  const modal      = document.getElementById('modalHistorico');
  const nomeEl     = document.getElementById('histClienteNome');
  const deEl       = document.getElementById('histDe');
  const ateEl      = document.getElementById('histAte');
  const tbody      = document.getElementById('histTbody');
  const loading    = document.getElementById('histLoading');
  const resultado  = document.getElementById('histResultado');
  const vazio      = document.getElementById('histVazio');
  const qtdEl      = document.getElementById('histQtd');
  const totalEl    = document.getElementById('histTotal');
  const rodapeTotal= document.getElementById('histRodapeTotal');

  let clienteAtualId = 0;

  /* ---------- Utilitários de data ---------- */
  function hoje() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }
  function primeiroDiaMes() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-01';
  }
  function ultimoDiaMes() {
    const d = new Date();
    const ultimo = new Date(d.getFullYear(), d.getMonth() + 1, 0);
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(ultimo.getDate()).padStart(2, '0');
  }

  /* ---------- Abrir modal ---------- */
  function abrir(id, nome) {
    clienteAtualId = id;
    nomeEl.textContent = nome;
    modal.classList.remove('hidden');
    // Por padrão abre mostrando o mês atual
    deEl.value  = primeiroDiaMes();
    ateEl.value = ultimoDiaMes();
    carregar();
  }

  /* ---------- Fechar ---------- */
  function fechar() {
    modal.classList.add('hidden');
  }

  document.querySelectorAll('.btn-historico').forEach(btn => {
    btn.addEventListener('click', () => abrir(btn.dataset.id, btn.dataset.nome));
  });
  document.getElementById('fecharHistorico').addEventListener('click', fechar);
  document.getElementById('histFechar2').addEventListener('click', fechar);
  modal.addEventListener('click', e => { if (e.target === modal) fechar(); });

  /* ---------- Carregar dados ---------- */
  async function carregar() {
    if (!clienteAtualId) return;

    loading.classList.remove('hidden');
    resultado.classList.add('hidden');
    vazio.classList.add('hidden');
    tbody.innerHTML = '';

    const url = 'cliente_historico.php?cliente_id=' + clienteAtualId
              + '&de='  + encodeURIComponent(deEl.value || '')
              + '&ate=' + encodeURIComponent(ateEl.value || '');

    try {
      const r = await fetch(url);
      const d = await r.json();

      loading.classList.add('hidden');

      if (!d.ok) {
        vazio.textContent = d.erro || 'Erro ao carregar.';
        vazio.classList.remove('hidden');
        return;
      }

      qtdEl.textContent    = d.qtd;
      totalEl.textContent  = d.total_fmt;
      rodapeTotal.textContent = d.total_fmt;

      if (!d.linhas.length) {
        vazio.classList.remove('hidden');
        return;
      }

      d.linhas.forEach(li => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition';

        /* Data */
        const tdData = document.createElement('td');
        tdData.className = 'px-4 py-2.5 whitespace-nowrap';
        const dataDiv = document.createElement('div');
        dataDiv.className = 'font-semibold text-slate-800';
        dataDiv.textContent = li.data_fmt;
        const horaDiv = document.createElement('div');
        horaDiv.className = 'text-xs text-slate-500';
        horaDiv.textContent = li.hora;
        tdData.appendChild(dataDiv);
        tdData.appendChild(horaDiv);

        /* Veículo */
        const tdVeic = document.createElement('td');
        tdVeic.className = 'px-4 py-2.5 text-slate-700';
        tdVeic.textContent = li.veiculo;

        /* Placa */
        const tdPlaca = document.createElement('td');
        tdPlaca.className = 'px-4 py-2.5';
        const span = document.createElement('span');
        span.className = 'font-mono font-semibold text-slate-800';
        span.textContent = li.placa;
        tdPlaca.appendChild(span);

        /* Serviços */
        const tdServ = document.createElement('td');
        tdServ.className = 'px-4 py-2.5 text-xs text-slate-600';
        tdServ.textContent = li.servicos;

        /* Valor */
        const tdValor = document.createElement('td');
        tdValor.className = 'px-4 py-2.5 text-right whitespace-nowrap';
        const vDiv = document.createElement('div');
        vDiv.className = 'font-bold text-slate-800';
        vDiv.textContent = li.valor_fmt;
        tdValor.appendChild(vDiv);
        if (li.desconto_fmt) {
          const dDiv = document.createElement('div');
          dDiv.className = 'text-xs text-rose-600';
          dDiv.textContent = '- ' + li.desconto_fmt;
          tdValor.appendChild(dDiv);
        }

        tr.appendChild(tdData);
        tr.appendChild(tdVeic);
        tr.appendChild(tdPlaca);
        tr.appendChild(tdServ);
        tr.appendChild(tdValor);
        tbody.appendChild(tr);
      });

      resultado.classList.remove('hidden');

    } catch (e) {
      loading.classList.add('hidden');
      vazio.textContent = 'Falha de conexão.';
      vazio.classList.remove('hidden');
    }
  }

  /* ---------- Botões de filtro ---------- */
  document.getElementById('histFiltrar').addEventListener('click', carregar);

  document.getElementById('histMesAtual').addEventListener('click', () => {
    deEl.value  = primeiroDiaMes();
    ateEl.value = ultimoDiaMes();
    carregar();
  });

  document.getElementById('histLimpar').addEventListener('click', () => {
    deEl.value  = '';
    ateEl.value = '';
    carregar();
  });

  document.getElementById('histImprimir').addEventListener('click', () => {
    window.print();
  });

  /* Fecha modal com ESC */
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) fechar();
  });
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
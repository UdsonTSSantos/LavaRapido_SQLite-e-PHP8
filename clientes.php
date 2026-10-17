<?php
require_once __DIR__ . '/config.php';
exigir_login();
exigir_permissao('clientes');
ensure_clientes();

/* ---------- Dados da empresa para o cabeçalho de impressão ---------- */
$__emp  = [];
$__logo = null;
try {
    $__emp  = empresa();
    $__logo = empresa_logo_url();
} catch (Throwable $e) {
    $__emp = []; $__logo = null;
}
$__nomeEmpresa = $__emp['nome_fantasia'] ?: ($__emp['razao_social'] ?: 'Lava Rápido');

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
     MODAL: HISTÓRICO DE LAVAGENS
     ========================================================= -->
<div id="modalHistorico"
     class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
     role="dialog" aria-modal="true">

  <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl max-h-[92vh] flex flex-col overflow-hidden">

    <!-- Cabeçalho (só tela) -->
    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between flex-shrink-0 bg-white no-print">
      <div>
        <h3 class="font-bold text-slate-800">Histórico de lavagens</h3>
        <p class="text-xs text-slate-500" id="histClienteNome"></p>
      </div>
      <button type="button" id="fecharHistorico"
              class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
    </div>

    <!-- Cabeçalho de impressão (só imprime) -->
    <div class="print-only print-header">
      <div style="display: flex; align-items: flex-start; gap: 16px;">
        <?php if ($__logo): ?>
          <img src="<?= e($__logo) ?>" alt="Logo" class="logo-img">
        <?php endif; ?>
        <div style="flex: 1;">
          <div class="empresa-nome"><?= e($__nomeEmpresa) ?></div>
          <div class="empresa-info">
            <?php if (!empty($__emp['razao_social']) && $__emp['razao_social'] !== $__nomeEmpresa): ?>
              <?= e($__emp['razao_social']) ?><br>
            <?php endif; ?>
            <?php if (!empty($__emp['cnpj'])): ?>
              CNPJ: <?= e(formatar_cnpj($__emp['cnpj'])) ?><br>
            <?php endif; ?>
            <?php if (!empty($__emp['endereco'])): ?>
              <?= e($__emp['endereco']) ?><?= $__emp['numero'] ? ', ' . e($__emp['numero']) : '' ?>
              <?= $__emp['bairro'] ? ' — ' . e($__emp['bairro']) : '' ?>
              <?= $__emp['cidade'] ? ' — ' . e($__emp['cidade']) : '' ?><?= $__emp['uf'] ? '/' . e($__emp['uf']) : '' ?><br>
            <?php endif; ?>
            <?php if (!empty($__emp['celular']) || !empty($__emp['telefone'])): ?>
              Tel: <?= e($__emp['celular'] ?: $__emp['telefone']) ?><br>
            <?php endif; ?>
            <?php if (!empty($__emp['email'])): ?>
              <?= e($__emp['email']) ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="titulo-relatorio">
          EXTRATO DE LAVAGENS<br>
          <span style="font-weight: normal; font-size: 10px; color: #64748b;">Documento não fiscal</span>
        </div>
      </div>
    </div>

    <!-- Dados do cliente + período (só imprime) -->
    <div class="print-only print-cliente">
      <div class="linha">
        <div><span class="label">Cliente:</span> <strong id="printCliNome">—</strong></div>
        <div><span class="label">CPF/CNPJ:</span> <span id="printCliDoc">—</span></div>
      </div>
      <div class="linha" style="margin-top: 4px;">
        <div><span class="label">Telefone:</span> <span id="printCliTel">—</span></div>
        <div><span class="label">E-mail:</span> <span id="printCliEmail">—</span></div>
      </div>
      <div class="linha" style="margin-top: 4px;">
        <div><span class="label">Período:</span> <span id="printPeriodo">—</span></div>
        <div><span class="label">Emitido em:</span> <?= e(date('d/m/Y H:i')) ?></div>
      </div>
    </div>

    <!-- Filtros (só tela) -->
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

    <!-- Corpo -->
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

      <!-- Rodapé (só impressão) -->
      <div class="print-only print-footer">
        <?= e($__nomeEmpresa) ?> · Extrato gerado em <?= e(date('d/m/Y H:i')) ?> · Documento não fiscal
      </div>
    </div>

    <!-- Rodapé (só tela) -->
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
  .print-only { display: none; }

  @media print {
    @page { size: A4; margin: 12mm 10mm; }

    body * { visibility: hidden !important; }

    #modalHistorico,
    #modalHistorico * { visibility: visible !important; }

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

    .print-only {
      display: block !important;
      visibility: visible !important;
    }

    .print-header {
      border-bottom: 2px solid #004173;
      padding-bottom: 10px;
      margin-bottom: 12px;
    }
    .print-header .logo-img {
      height: 60px;
      width: 60px;
      object-fit: contain;
    }
    .print-header .empresa-nome {
      font-size: 16px;
      font-weight: 700;
      color: #004173;
    }
    .print-header .empresa-info {
      font-size: 10px;
      color: #475569;
      line-height: 1.35;
    }
    .print-header .titulo-relatorio {
      font-size: 13px;
      font-weight: 700;
      color: #004173;
      text-align: right;
    }

    .print-cliente {
      font-size: 11px;
      border: 1px solid #cbd5e1;
      border-radius: 4px;
      padding: 8px 10px;
      margin-bottom: 10px;
      background: #f8fafc;
    }
    .print-cliente .linha {
      display: flex;
      justify-content: space-between;
      gap: 12px;
    }
    .print-cliente .label {
      color: #64748b;
      font-weight: 600;
    }

    table { font-size: 10.5px !important; }
    thead { background: #e2e8f0 !important; }
    th, td { padding: 5px 8px !important; }
    tbody tr:nth-child(even) { background: #f8fafc !important; }

    .print-footer {
      font-size: 9px;
      color: #94a3b8;
      text-align: center;
      margin-top: 12px;
      padding-top: 6px;
      border-top: 1px solid #cbd5e1;
    }
  }
</style>

<script>
(function () {
  'use strict';

  /* =========================================================
   *  REFERÊNCIAS
   * ========================================================= */
  function $(id) { return document.getElementById(id); }

  var modal       = $('modalHistorico');
  var nomeEl      = $('histClienteNome');
  var deEl        = $('histDe');
  var ateEl       = $('histAte');
  var tbody       = $('histTbody');
  var loading     = $('histLoading');
  var resultado   = $('histResultado');
  var vazio       = $('histVazio');
  var qtdEl       = $('histQtd');
  var totalEl     = $('histTotal');
  var rodapeTotal = $('histRodapeTotal');

  if (!modal) {
    console.error('[Histórico] Modal #modalHistorico não encontrado.');
    return;
  }

  var clienteAtualId = 0;

  /* =========================================================
   *  UTILITÁRIOS DE DATA
   * ========================================================= */
  function pad(n) { return String(n).padStart(2, '0'); }
  function primeiroDiaMes() {
    var d = new Date();
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-01';
  }
  function ultimoDiaMes() {
    var d = new Date();
    var ultimo = new Date(d.getFullYear(), d.getMonth() + 1, 0);
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(ultimo.getDate());
  }

  /* =========================================================
   *  ABRIR / FECHAR MODAL
   * ========================================================= */
  function abrir(id, nome) {
    clienteAtualId = parseInt(id, 10) || 0;
    nomeEl.textContent = nome || '';
    modal.classList.remove('hidden');
    deEl.value  = primeiroDiaMes();
    ateEl.value = ultimoDiaMes();
    carregar();
  }

  function fechar() {
    modal.classList.add('hidden');
  }

  /* =========================================================
   *  CARREGAR
   * ========================================================= */
  function carregar() {
    if (!clienteAtualId) return;

    loading.classList.remove('hidden');
    resultado.classList.add('hidden');
    vazio.classList.add('hidden');
    tbody.innerHTML = '';

    var url = 'cliente_historico.php?cliente_id=' + clienteAtualId
            + '&de='  + encodeURIComponent(deEl.value || '')
            + '&ate=' + encodeURIComponent(ateEl.value || '');

    fetch(url)
      .then(function (r) { return r.text(); })
      .then(function (texto) {
        var d;
        try {
          d = JSON.parse(texto);
        } catch (e) {
          loading.classList.add('hidden');
          vazio.textContent = 'Resposta inválida do servidor: ' + texto.substring(0, 300);
          vazio.classList.remove('hidden');
          console.error('[Histórico] Resposta bruta:', texto);
          return;
        }

        loading.classList.add('hidden');

        if (!d.ok) {
          vazio.textContent = d.erro || 'Erro ao carregar.';
          vazio.classList.remove('hidden');
          return;
        }

        qtdEl.textContent       = d.qtd;
        totalEl.textContent     = d.total_fmt;
        rodapeTotal.textContent = d.total_fmt;

        /* ---------- Preenche cabeçalho de impressão ---------- */
        var pc = d.cliente || {};
        var pcn = $('printCliNome');
        var pcd = $('printCliDoc');
        var pct = $('printCliTel');
        var pce = $('printCliEmail');
        if (pcn) pcn.textContent = pc.nome || '—';
        if (pcd) pcd.textContent = pc.doc  || '—';
        if (pct) pct.textContent = pc.celular || '—';
        if (pce) pce.textContent = pc.email || '—';

        var fd = deEl.value, fa = ateEl.value;
        var periodo = 'Todo o histórico';
        if (fd && fa) {
          var pf = fd.split('-'), pa = fa.split('-');
          periodo = pf[2] + '/' + pf[1] + '/' + pf[0] + ' a ' + pa[2] + '/' + pa[1] + '/' + pa[0];
        } else if (fd) {
          var pf2 = fd.split('-');
          periodo = 'A partir de ' + pf2[2] + '/' + pf2[1] + '/' + pf2[0];
        } else if (fa) {
          var pa2 = fa.split('-');
          periodo = 'Até ' + pa2[2] + '/' + pa2[1] + '/' + pa2[0];
        }
        var pPer = $('printPeriodo');
        if (pPer) pPer.textContent = periodo;

        if (!d.linhas || !d.linhas.length) {
          vazio.classList.remove('hidden');
          return;
        }

        d.linhas.forEach(function (li) {
          var tr = document.createElement('tr');
          tr.className = 'hover:bg-slate-50 transition';

          var tdData = document.createElement('td');
          tdData.className = 'px-4 py-2.5 whitespace-nowrap';
          var dData = document.createElement('div');
          dData.className = 'font-semibold text-slate-800';
          dData.textContent = li.data_fmt;
          var dHora = document.createElement('div');
          dHora.className = 'text-xs text-slate-500';
          dHora.textContent = li.hora;
          tdData.appendChild(dData);
          tdData.appendChild(dHora);

          var tdVeic = document.createElement('td');
          tdVeic.className = 'px-4 py-2.5 text-slate-700';
          tdVeic.textContent = li.veiculo;
          if (li.ano_cor) {
            var sub = document.createElement('div');
            sub.className = 'text-xs text-slate-400';
            sub.textContent = li.ano_cor;
            tdVeic.appendChild(sub);
          }

          var tdPlaca = document.createElement('td');
          tdPlaca.className = 'px-4 py-2.5';
          var spanP = document.createElement('span');
          spanP.className = 'font-mono font-semibold text-slate-800';
          spanP.textContent = li.placa;
          tdPlaca.appendChild(spanP);

          var tdServ = document.createElement('td');
          tdServ.className = 'px-4 py-2.5 text-xs text-slate-600';
          tdServ.textContent = li.servicos;

          var tdValor = document.createElement('td');
          tdValor.className = 'px-4 py-2.5 text-right whitespace-nowrap';
          var vDiv = document.createElement('div');
          vDiv.className = 'font-bold text-slate-800';
          vDiv.textContent = li.valor_fmt;
          tdValor.appendChild(vDiv);

          tr.appendChild(tdData);
          tr.appendChild(tdVeic);
          tr.appendChild(tdPlaca);
          tr.appendChild(tdServ);
          tr.appendChild(tdValor);
          tbody.appendChild(tr);
        });

        resultado.classList.remove('hidden');
      })
      .catch(function (e) {
        loading.classList.add('hidden');
        vazio.textContent = 'Erro de rede: ' + e.message;
        vazio.classList.remove('hidden');
        console.error('[Histórico] Erro de fetch:', e);
      });
  }

  /* =========================================================
   *  DELEGAÇÃO DE EVENTOS (à prova de erros)
   * ========================================================= */

  /* Clique no botão "Histórico" (delegação global) */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-historico');
    if (btn) {
      e.preventDefault();
      abrir(btn.dataset.id, btn.dataset.nome);
      return;
    }

    /* Fechar modal */
    if (e.target.id === 'fecharHistorico' || e.target.id === 'histFechar2') {
      e.preventDefault();
      fechar();
      return;
    }

    /* Clicar no backdrop */
    if (e.target === modal) {
      fechar();
      return;
    }

    /* Filtrar */
    if (e.target.id === 'histFiltrar') {
      e.preventDefault();
      carregar();
      return;
    }

    /* Mês atual */
    if (e.target.id === 'histMesAtual') {
      e.preventDefault();
      deEl.value  = primeiroDiaMes();
      ateEl.value = ultimoDiaMes();
      carregar();
      return;
    }

    /* Limpar filtro */
    if (e.target.id === 'histLimpar') {
      e.preventDefault();
      deEl.value  = '';
      ateEl.value = '';
      carregar();
      return;
    }

    /* Imprimir */
    if (e.target.id === 'histImprimir') {
      e.preventDefault();
      window.print();
      return;
    }
  });

  /* ESC fecha o modal */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
      fechar();
    }
  });

  /* Log de diagnóstico */
  console.log('[Histórico] Módulo carregado. Botões encontrados:',
              document.querySelectorAll('.btn-historico').length);

})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
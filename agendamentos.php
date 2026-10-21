<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_agendamentos();
ensure_lavagens();

$mesRef = $_GET['mes'] ?? date('Y-m');
$inicio = $mesRef . '-01';
$fim    = date('Y-m-t', strtotime($inicio));

// Busca agendamentos do mês
$stmt = db()->prepare("
    SELECT a.*, l.nome AS servico_nome
    FROM agendamentos a
    INNER JOIN lavagens l ON l.id = a.lavagem_id
    WHERE a.data_agendamento BETWEEN ? AND ?
    ORDER BY a.hora_agendamento ASC
");
$stmt->execute([$inicio, $fim]);
$rows = $stmt->fetchAll();

// Agrupa por dia
$agendamentosPorDia = [];
foreach ($rows as $r) {
    $dia = (int)date('d', strtotime($r['data_agendamento']));
    $agendamentosPorDia[$dia][] = $r;
}

// Monta os dias do mês
$totalDias = (int)date('t', strtotime($inicio));
$primeiroDiaSemana = (int)date('w', strtotime($inicio)); // 0=Dom, 6=Sáb

$titulo = 'Agendamentos';
require __DIR__ . '/header.php';
?>

<div class="bg-white rounded-2xl shadow-card border border-slate-200 p-5 sm:p-6 mb-6">
  <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Painel de Agendamentos</h1>
      <p class="text-sm text-slate-500">Clique em um agendamento para ver os detalhes e dar entrada.</p>
    </div>
    <div class="flex items-center gap-3">
      <form method="get" class="flex items-center gap-2">
        <input type="month" name="mes" value="<?= e($mesRef) ?>" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-400">
      </form>
      <a href="agendamento_form.php" class="rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 text-sm font-semibold shadow-sm">+ Novo Agendamento</a>
    </div>
  </div>

  <!-- Calendário -->
  <div class="grid grid-cols-7 gap-px bg-slate-200 border border-slate-200 rounded-lg overflow-hidden">
    <?php foreach(['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'] as $dsm): ?>
      <div class="bg-slate-50 p-2 text-center text-xs font-bold text-slate-500 uppercase tracking-wider"><?= $dsm ?></div>
    <?php endforeach; ?>

    <?php
      // Preenche dias vazios no início
      for ($i = 0; $i < $primeiroDiaSemana; $i++) {
          echo '<div class="bg-white min-h-[120px] p-2 opacity-50"></div>';
      }

      // Preenche os dias do mês
      for ($dia = 1; $dia <= $totalDias; $dia++) {
          $isHoje = ($mesRef === date('Y-m') && $dia === (int)date('d'));
          $bgHoje = $isHoje ? 'bg-sky-50' : 'bg-white';
          echo '<div class="'.$bgHoje.' min-h-[120px] p-2 border-t border-slate-100 flex flex-col gap-1">';
          echo '<div class="text-xs font-bold '.($isHoje ? 'text-brand-600' : 'text-slate-400').' text-right mb-1">'.$dia.'</div>';
          
          if (isset($agendamentosPorDia[$dia])) {
              foreach ($agendamentosPorDia[$dia] as $ag) {
                  $classeStatus = $ag['status'] === 'concluido' ? 'bg-slate-100 text-slate-500 line-through' : 'bg-emerald-50 text-emerald-800 border-emerald-200 hover:bg-emerald-100 cursor-pointer';
                  $dadosJson = htmlspecialchars(json_encode($ag), ENT_QUOTES, 'UTF-8');
                  
                  echo "<div onclick='abrirModalAgendamento($dadosJson)' class='text-[11px] p-1.5 rounded border {$classeStatus} transition leading-tight'>";
                  echo "<div class='font-bold truncate'>".substr($ag['hora_agendamento'],0,5)." - ".e($ag['cliente_nome'])."</div>";
                  echo "<div class='truncate font-mono'>".e(formatar_placa($ag['placa']))."</div>";
                  echo "</div>";
              }
          }
          echo '</div>';
      }

      // Preenche dias vazios no final
      $ultimoDiaSemana = (int)date('w', strtotime($fim));
      $diasRestantes = 6 - $ultimoDiaSemana;
      for ($i = 0; $i < $diasRestantes; $i++) {
          echo '<div class="bg-white min-h-[120px] p-2 opacity-50"></div>';
      }
    ?>
  </div>
</div>

<!-- MODAL DE DETALHES DO AGENDAMENTO -->
<div id="modalAgendamento" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm">
    <div class="px-5 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50 rounded-t-2xl">
      <h3 class="font-bold text-slate-800">Detalhes do Agendamento</h3>
      <button type="button" onclick="document.getElementById('modalAgendamento').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 text-xl leading-none">&times;</button>
    </div>
    
    <div class="px-5 py-4 space-y-3">
      <div>
        <div class="text-xs text-slate-500 font-bold uppercase">Cliente</div>
        <div class="font-semibold text-slate-800 text-lg" id="m_nome"></div>
        <div class="text-sm text-slate-600" id="m_celular"></div>
      </div>
      <div class="grid grid-cols-2 gap-2">
        <div>
          <div class="text-xs text-slate-500 font-bold uppercase">Placa</div>
          <div class="font-mono font-bold text-slate-800" id="m_placa"></div>
        </div>
        <div>
          <div class="text-xs text-slate-500 font-bold uppercase">Data / Hora</div>
          <div class="font-semibold text-slate-800" id="m_datahora"></div>
        </div>
      </div>
      <div>
        <div class="text-xs text-slate-500 font-bold uppercase">Serviço</div>
        <div class="font-semibold text-brand-700 bg-brand-50 px-2 py-1 rounded inline-block mt-1" id="m_servico"></div>
      </div>
    </div>

    <div class="px-5 py-4 border-t border-slate-200 flex justify-end gap-2 bg-slate-50 rounded-b-2xl">
      <button type="button" onclick="document.getElementById('modalAgendamento').classList.add('hidden')" class="px-4 py-2 rounded-lg bg-white border border-slate-300 text-slate-700 font-semibold text-sm">Fechar</button>
      <a href="#" id="btnEntrada" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm shadow-sm flex items-center gap-2">
        Dar Entrada
      </a>
    </div>
  </div>
</div>

<script>
function abrirModalAgendamento(ag) {
    document.getElementById('m_nome').textContent = ag.cliente_nome;
    document.getElementById('m_celular').textContent = ag.cliente_celular || 'Não informado';
    
    // Formata a placa
    let p = ag.placa.replace(/[^A-Z0-9]/gi, '').toUpperCase();
    if(p.length === 7) p = p.slice(0,3) + '-' + p.slice(3);
    document.getElementById('m_placa').textContent = p;
    
    // Formata a data
    let dt = ag.data_agendamento.split('-');
    document.getElementById('m_datahora').textContent = dt[2] + '/' + dt[1] + ' às ' + ag.hora_agendamento.slice(0,5);
    
    document.getElementById('m_servico').textContent = ag.servico_nome;
    
    // Configura o botão de entrada
    let btn = document.getElementById('btnEntrada');
    if (ag.status === 'concluido') {
        btn.style.display = 'none';
    } else {
        btn.style.display = 'flex';
        btn.href = 'entrada_lavagem.php?agendamento_id=' + ag.id;
    }

    document.getElementById('modalAgendamento').classList.remove('hidden');
}
</script>

<?php require __DIR__ . '/footer.php'; ?>
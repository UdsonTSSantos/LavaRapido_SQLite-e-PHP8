<?php
require_once __DIR__ . '/config.php';
exigir_login();
$u = usuario_logado();

$hoje = date('Y-m-d');
$ontem = date('Y-m-d', strtotime('-1 day'));
$amanha = date('Y-m-d', strtotime('+1 day'));
$mesAtual = date('Y-m'); // Usado para buscar todo o mês no SQLite

$pdo = db();

// ==========================================
// 1. SITUAÇÃO DO PÁTIO
// ==========================================
// Conta veículos por status hoje (Ajuste os nomes dos status se necessário)
$patio = $pdo->prepare("SELECT status, COUNT(*) as total FROM lavagem_entradas WHERE data_entrada = ? GROUP BY status");
$patio->execute([$hoje]);
$contagemPatio = ['lavando' => 0, 'pronto' => 0, 'entregue' => 0];
foreach ($patio->fetchAll() as $row) {
    $st = strtolower($row['status']);
    if (in_array($st, ['lavando', 'aberta', 'em_andamento'])) $contagemPatio['lavando'] += $row['total'];
    elseif (in_array($st, ['pronto', 'concluido', 'concluida'])) $contagemPatio['pronto'] += $row['total'];
    elseif (in_array($st, ['entregue', 'finalizado'])) $contagemPatio['entregue'] += $row['total'];
}
// Não pagos hoje
$naoPagos = $pdo->prepare("SELECT COUNT(*) FROM lavagem_entradas WHERE data_entrada = ? AND pago = 0 AND status NOT IN ('cancelado')");
$naoPagos->execute([$hoje]);
$totalNaoPagos = $naoPagos->fetchColumn();

// ==========================================
// 2. AGENDA DE VEÍCULOS (CORRIGIDO PARA SQLITE)
// ==========================================
$agHoje = $pdo->prepare("SELECT COUNT(*) FROM agendamentos WHERE data_agendamento = ?"); 
$agHoje->execute([$hoje]); 
$totalAgHoje = $agHoje->fetchColumn();

$agAmanha = $pdo->prepare("SELECT COUNT(*) FROM agendamentos WHERE data_agendamento = ?"); 
$agAmanha->execute([$amanha]); 
$totalAgAmanha = $agAmanha->fetchColumn();

// Aqui está a correção: Usando substr para pegar os 7 primeiros caracteres (YYYY-MM)
$agMes = $pdo->prepare("SELECT COUNT(*) FROM agendamentos WHERE substr(data_agendamento, 1, 7) = ?"); 
$agMes->execute([$mesAtual]); 
$totalAgMes = $agMes->fetchColumn();

// ==========================================
// 3. FUNÇÃO PARA GRÁFICOS (HOJE E ONTEM)
// ==========================================
function gerarDadosGrafico($data, $pdo) {
    // Busca todos os horários das entradas do dia
    $stmt = $pdo->prepare("SELECT hora_entrada FROM lavagem_entradas WHERE data_entrada = ?");
    $stmt->execute([$data]);
    $horarios = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Agrupa em períodos: Manhã (06-11), Tarde (12-17), Noite (18-23)
    $periodos = [
        'manha' => 0, 'tarde' => 0, 'noite' => 0
    ];

    // Para fazer as barrinhas fictícias do gráfico, vamos contar hora a hora
    $horasBarras = array_fill(6, 18, 0); // Das 06h às 23h

    foreach ($horarios as $hora) {
        $h = (int)substr($hora, 0, 2);
        if ($h >= 6 && $h <= 23) $horasBarras[$h]++;
        
        if ($h >= 6 && $h <= 11) $periodos['manha']++;
        elseif ($h >= 12 && $h <= 17) $periodos['tarde']++;
        elseif ($h >= 18 && $h <= 23) $periodos['noite']++;
    }

    $total = array_sum($periodos);
    
    // Descobre o menor período (> 0, se houver) para pintar de vermelho
    $minValor = PHP_INT_MAX;
    $menorPeriodo = '';
    foreach ($periodos as $key => $val) {
        if ($val < $minValor && $val > 0) {
            $minValor = $val;
            $menorPeriodo = $key;
        }
    }

    // Calcula a altura das barras para o CSS (máximo de 100%)
    $maxHora = max(1, max($horasBarras)); // Evita divisão por zero
    $alturas = [];
    foreach ($horasBarras as $h => $qtd) {
        $alturas[$h] = ($qtd / $maxHora) * 100;
    }

    return [
        'total' => str_pad($total, 3, '0', STR_PAD_LEFT),
        'periodos' => $periodos,
        'menor' => $menorPeriodo,
        'alturas' => $alturas
    ];
}

$graficoHoje = gerarDadosGrafico($hoje, $pdo);
$graficoOntem = gerarDadosGrafico($ontem, $pdo);

$titulo = 'Dashboard';
require __DIR__ . '/header.php';
?>

<div class="space-y-6">
    
    <!-- CARDS DE CIMA (PATIO E AGENDA) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- CARD PÁTIO -->
        <div class="bg-slate-100 rounded-3xl p-6 relative shadow-inner border border-slate-200/60">
            <div class="flex items-center gap-3 mb-4">
                <div class="bg-blue-600 text-white p-2 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                </div>
                <h2 class="text-xl font-bold text-blue-800">Situação do dia no pátio</h2>
            </div>
            
            <div class="grid grid-cols-4 gap-2 mb-6">
                <div>
                    <p class="text-blue-600 text-sm font-semibold mb-1">Lavando</p>
                    <p class="text-4xl text-blue-600 font-light"><?= str_pad($contagemPatio['lavando'], 3, '0', STR_PAD_LEFT) ?></p>
                </div>
                <div>
                    <p class="text-blue-600 text-sm font-semibold mb-1">Pronto(s)</p>
                    <p class="text-4xl text-blue-600 font-light"><?= str_pad($contagemPatio['pronto'], 3, '0', STR_PAD_LEFT) ?></p>
                </div>
                <div>
                    <p class="text-blue-600 text-sm font-semibold mb-1">Entregue(s)</p>
                    <p class="text-4xl text-blue-600 font-light"><?= str_pad($contagemPatio['entregue'], 3, '0', STR_PAD_LEFT) ?></p>
                </div>
                <div>
                    <p class="text-blue-600 text-sm font-semibold mb-1">Não Pagos</p>
                    <p class="text-4xl text-rose-500 font-light"><?= str_pad($totalNaoPagos, 3, '0', STR_PAD_LEFT) ?></p>
                </div>
            </div>
            
            <div class="text-right">
                <a href="entradas.php" class="text-orange-500 font-semibold hover:underline">Acessar -></a>
            </div>
        </div>

        <!-- CARD AGENDA -->
        <div class="bg-slate-100 rounded-3xl p-6 relative shadow-inner border border-slate-200/60">
            <div class="flex items-center gap-3 mb-4">
                <div class="bg-blue-600 text-white p-2 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <h2 class="text-xl font-bold text-blue-800">Agenda de veículos</h2>
            </div>
            
            <div class="grid grid-cols-3 gap-2 mb-6">
                <div>
                    <p class="text-blue-600 text-sm font-semibold mb-1">Hoje</p>
                    <p class="text-4xl text-blue-600 font-light"><?= str_pad($totalAgHoje, 3, '0', STR_PAD_LEFT) ?></p>
                </div>
                <div>
                    <p class="text-blue-600 text-sm font-semibold mb-1">Amanhã</p>
                    <p class="text-4xl text-blue-600 font-light"><?= str_pad($totalAgAmanha, 3, '0', STR_PAD_LEFT) ?></p>
                </div>
                <div>
                    <p class="text-blue-600 text-sm font-semibold mb-1">Mês</p>
                    <p class="text-4xl text-blue-600 font-light"><?= str_pad($totalAgMes, 3, '0', STR_PAD_LEFT) ?></p>
                </div>
            </div>
            
            <div class="text-right">
                <a href="agendamentos.php" class="text-orange-500 font-semibold hover:underline">Acessar -></a>
            </div>
        </div>
    </div>

    <!-- GRÁFICOS -->
    <div class="bg-white rounded-3xl p-6 shadow-card border border-slate-200">
        
        <!-- HOJE -->
        <div class="mb-8">
            <h3 class="text-slate-500 font-semibold mb-4">Veículos atendidos hoje</h3>
            <div class="flex items-end gap-4">
                
                <!-- BARRAS DO GRÁFICO -->
                <div class="flex items-end gap-4 flex-1 h-32">
                    <!-- Manhã -->
                    <div class="flex-1 flex items-end justify-center gap-1 h-full border-b-2 border-slate-200 pb-1 relative group">
                        <?php for($h=6;$h<=11;$h++): ?>
                            <div class="w-3 bg-indigo-400 rounded-t-sm transition-all" style="height: <?= max(5, $graficoHoje['alturas'][$h]) ?>%;" title="<?= $h ?>h: <?= $graficoHoje['alturas'][$h] ?> veículos"></div>
                        <?php endfor; ?>
                        <span class="absolute -bottom-6 text-xs text-slate-500 font-semibold <?= $graficoHoje['menor'] === 'manha' ? 'text-red-500' : '' ?>">Manhã</span>
                    </div>
                    <!-- Tarde -->
                    <div class="flex-1 flex items-end justify-center gap-1 h-full border-b-2 border-slate-200 pb-1 relative group">
                        <?php for($h=12;$h<=17;$h++): ?>
                            <div class="w-3 bg-indigo-400 rounded-t-sm transition-all" style="height: <?= max(5, $graficoHoje['alturas'][$h]) ?>%;" title="<?= $h ?>h: <?= $graficoHoje['alturas'][$h] ?> veículos"></div>
                        <?php endfor; ?>
                        <span class="absolute -bottom-6 text-xs text-slate-500 font-semibold <?= $graficoHoje['menor'] === 'tarde' ? 'text-red-500' : '' ?>">Tarde</span>
                    </div>
                    <!-- Noite -->
                    <div class="flex-1 flex items-end justify-center gap-1 h-full border-b-2 border-slate-200 pb-1 relative group">
                        <?php for($h=18;$h<=23;$h++): ?>
                            <div class="w-3 bg-indigo-400 rounded-t-sm transition-all" style="height: <?= max(5, $graficoHoje['alturas'][$h]) ?>%;" title="<?= $h ?>h: <?= $graficoHoje['alturas'][$h] ?> veículos"></div>
                        <?php endfor; ?>
                        <span class="absolute -bottom-6 text-xs text-slate-500 font-semibold <?= $graficoHoje['menor'] === 'noite' ? 'text-red-500' : '' ?>">Noite</span>
                    </div>
                </div>

                <!-- TOTAL -->
                <div class="w-32 text-right pb-4">
                    <span class="text-5xl font-light text-blue-600"><?= $graficoHoje['total'] ?></span>
                </div>
            </div>
        </div>

        <hr class="border-slate-200 mb-8">

        <!-- ONTEM -->
        <div>
            <h3 class="text-slate-500 font-semibold mb-4">Veículos atendidos ontem</h3>
            <div class="flex items-end gap-4">
                <!-- BARRAS DO GRÁFICO -->
                <div class="flex items-end gap-4 flex-1 h-32">
                    <!-- Manhã -->
                    <div class="flex-1 flex items-end justify-center gap-1 h-full border-b-2 border-slate-200 pb-1 relative group">
                        <?php for($h=6;$h<=11;$h++): ?>
                            <div class="w-3 bg-indigo-400 rounded-t-sm transition-all" style="height: <?= max(5, $graficoOntem['alturas'][$h]) ?>%;" title="<?= $h ?>h: <?= $graficoOntem['alturas'][$h] ?> veículos"></div>
                        <?php endfor; ?>
                        <span class="absolute -bottom-6 text-xs text-slate-500 font-semibold <?= $graficoOntem['menor'] === 'manha' ? 'text-red-500' : '' ?>">Manhã</span>
                    </div>
                    <!-- Tarde -->
                    <div class="flex-1 flex items-end justify-center gap-1 h-full border-b-2 border-slate-200 pb-1 relative group">
                        <?php for($h=12;$h<=17;$h++): ?>
                            <div class="w-3 bg-indigo-400 rounded-t-sm transition-all" style="height: <?= max(5, $graficoOntem['alturas'][$h]) ?>%;" title="<?= $h ?>h: <?= $graficoOntem['alturas'][$h] ?> veículos"></div>
                        <?php endfor; ?>
                        <span class="absolute -bottom-6 text-xs text-slate-500 font-semibold <?= $graficoOntem['menor'] === 'tarde' ? 'text-red-500' : '' ?>">Tarde</span>
                    </div>
                    <!-- Noite -->
                    <div class="flex-1 flex items-end justify-center gap-1 h-full border-b-2 border-slate-200 pb-1 relative group">
                        <?php for($h=18;$h<=23;$h++): ?>
                            <div class="w-3 bg-indigo-400 rounded-t-sm transition-all" style="height: <?= max(5, $graficoOntem['alturas'][$h]) ?>%;" title="<?= $h ?>h: <?= $graficoOntem['alturas'][$h] ?> veículos"></div>
                        <?php endfor; ?>
                        <span class="absolute -bottom-6 text-xs text-slate-500 font-semibold <?= $graficoOntem['menor'] === 'noite' ? 'text-red-500' : '' ?>">Noite</span>
                    </div>
                </div>
                <!-- TOTAL -->
                <div class="w-32 text-right pb-4">
                    <span class="text-5xl font-light text-blue-600"><?= $graficoOntem['total'] ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ÁREA DE ADMINISTRAÇÃO (Só aparece para admins) -->
    <?php if (isset($u['nivel']) && $u['nivel'] === 'admin' || isset($u['admin']) && $u['admin'] == 1): ?>
    <div class="mt-8">
        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 ml-2">Administração</h4>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="usuarios.php" class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 hover:border-blue-400 transition flex items-center gap-4">
                <div class="bg-indigo-100 p-2 rounded-lg text-indigo-600"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg></div>
                <div>
                    <h5 class="font-bold text-slate-800">Usuários</h5>
                    <p class="text-xs text-slate-500">Criar, editar e gerenciar senhas.</p>
                </div>
            </a>
            <a href="empresa.php" class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 hover:border-blue-400 transition flex items-center gap-4">
                <div class="bg-blue-100 p-2 rounded-lg text-blue-600"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg></div>
                <div>
                    <h5 class="font-bold text-slate-800">Empresa</h5>
                    <p class="text-xs text-slate-500">Logo, CNPJ, endereço e contatos.</p>
                </div>
            </a>
            <a href="financeiro.php" class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 hover:border-blue-400 transition flex items-center gap-4">
                <div class="bg-emerald-100 p-2 rounded-lg text-emerald-600"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg></div>
                <div>
                    <h5 class="font-bold text-slate-800">Dashboard Financeiro</h5>
                    <p class="text-xs text-slate-500">Gráficos e indicadores de caixa.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require __DIR__ . '/footer.php'; ?>
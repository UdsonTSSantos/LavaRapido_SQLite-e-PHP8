<?php
require_once __DIR__ . '/config.php';
exigir_login();
$u = usuario_logado();

// ==========================================
// PROCESSA O PAGAMENTO VIA MODAL
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_pagar'])) {
    csrf_validar();
    
    $id_entrada = (int)($_POST['id_entrada'] ?? 0);
    $tipo = $_POST['tipo'] ?? 'lavagem';
    $data_pgto = trim($_POST['data_pagamento'] ?? date('Y-m-d'));
    $valor_pago = moeda_para_centavos($_POST['valor_pago'] ?? '0');
    $forma_pgto = trim($_POST['forma_pagamento'] ?? 'Dinheiro');
    $obs = trim($_POST['observacao'] ?? '');
    $gerar_recibo = isset($_POST['acao_imprimir']);

    if ($id_entrada > 0) {
        if ($tipo === 'lavagem') {
            try {
                $stmt = db()->prepare("UPDATE lavagem_entradas SET pago = 1, data_pagamento = ?, forma_pagamento = ?, observacao_pagamento = ? WHERE id = ?");
                $stmt->execute([$data_pgto, $forma_pgto, $obs, $id_entrada]);
            } catch (Throwable $e) {
                db()->prepare("UPDATE lavagem_entradas SET pago = 1 WHERE id = ?")->execute([$id_entrada]);
            }
        } elseif ($tipo === 'conta') {
            try {
                db()->prepare("UPDATE contas_pagar SET status = 'pago', data_pagamento = ? WHERE id = ?")->execute([$data_pgto, $id_entrada]);
            } catch (Throwable $e) {}
        }
        
        flash('Pagamento registrado com sucesso!', 'sucesso');
        
        if ($gerar_recibo && $tipo === 'lavagem') {
            header('Location: cupom_lavagem.php?entrada=' . $id_entrada . '&auto=1');
            exit;
        }
    }
    header('Location: dashboard.php');
    exit;
}

$hoje = date('Y-m-d');
$inicioSemana = date('Y-m-d', strtotime('monday this week'));
$fimSemana = date('Y-m-d', strtotime('sunday this week'));
$pdo = db();

// ==========================================
// CONTROLE DE PAGINAÇÃO
// ==========================================
$limite = 6;
$pag1 = max(1, (int)($_GET['p1'] ?? 1));
$pag2 = max(1, (int)($_GET['p2'] ?? 1));
$pag3 = max(1, (int)($_GET['p3'] ?? 1));
$pag4 = max(1, (int)($_GET['p4'] ?? 1));
$off1 = ($pag1 - 1) * $limite;
$off2 = ($pag2 - 1) * $limite;
$off3 = ($pag3 - 1) * $limite;
$off4 = ($pag4 - 1) * $limite;

// ==========================================
// 1. LAVAGENS DE HOJE
// ==========================================
$total1 = $pdo->prepare("SELECT COUNT(*) FROM lavagem_entradas WHERE data_entrada = ? AND status != 'cancelado'");
$total1->execute([$hoje]);
$total1 = $total1->fetchColumn();

$soma1 = $pdo->prepare("SELECT SUM(total_centavos) FROM lavagem_entradas WHERE data_entrada = ? AND status != 'cancelado'");
$soma1->execute([$hoje]);
$valorTotalHoje = $soma1->fetchColumn() ?: 0;

$lista1 = $pdo->prepare("SELECT id, cliente_nome_avulso, placa, total_centavos, hora_entrada, status, pago FROM lavagem_entradas WHERE data_entrada = ? AND status != 'cancelado' ORDER BY hora_entrada DESC LIMIT $limite OFFSET $off1");
$lista1->execute([$hoje]);
$lista1 = $lista1->fetchAll();

// ==========================================
// 2. ENTREGUES SEM PAGAMENTO
// ==========================================
$total2 = $pdo->prepare("SELECT COUNT(*) FROM lavagem_entradas WHERE pago = 0 AND status != 'cancelado' AND data_entrada < ?");
$total2->execute([$hoje]);
$total2 = $total2->fetchColumn();

// NOVA CONSULTA: Somatória de valores anteriores
$soma2 = $pdo->prepare("SELECT SUM(total_centavos) FROM lavagem_entradas WHERE pago = 0 AND status != 'cancelado' AND data_entrada < ?");
$soma2->execute([$hoje]);
$valorTotalAnteriores = $soma2->fetchColumn() ?: 0;

$lista2 = $pdo->prepare("SELECT id, cliente_nome_avulso, placa, total_centavos, data_entrada, hora_entrada FROM lavagem_entradas WHERE pago = 0 AND status != 'cancelado' AND data_entrada < ? ORDER BY data_entrada DESC LIMIT $limite OFFSET $off2");
$lista2->execute([$hoje]);
$lista2 = $lista2->fetchAll();

// ==========================================
// 3. PAGAMENTOS VENCIDOS
// ==========================================
$total3 = 0; $lista3 = []; $erro_financeiro = false;
try {
    $total3 = $pdo->prepare("SELECT COUNT(*) FROM contas_pagar WHERE data_vencimento < ? AND status != 'pago'");
    $total3->execute([$hoje]);
    $total3 = $total3->fetchColumn();

    $lista3 = $pdo->prepare("SELECT id, descricao, valor_centavos, data_vencimento FROM contas_pagar WHERE data_vencimento < ? AND status != 'pago' ORDER BY data_vencimento ASC LIMIT $limite OFFSET $off3");
    $lista3->execute([$hoje]);
    $lista3 = $lista3->fetchAll();
} catch (Throwable $e) { $erro_financeiro = true; }

// ==========================================
// 4. AGENDA DA SEMANA
// ==========================================
$total4 = $pdo->prepare("SELECT COUNT(*) FROM agendamentos WHERE data_agendamento BETWEEN ? AND ?");
$total4->execute([$inicioSemana, $fimSemana]);
$total4 = $total4->fetchColumn();

$lista4 = $pdo->prepare("SELECT id, cliente_nome, placa, data_agendamento, hora_agendamento FROM agendamentos WHERE data_agendamento BETWEEN ? AND ? ORDER BY data_agendamento ASC, hora_agendamento ASC LIMIT $limite OFFSET $off4");
$lista4->execute([$inicioSemana, $fimSemana]);
$lista4 = $lista4->fetchAll();

$titulo = 'Dashboard';
require __DIR__ . '/header.php';
?>

<div class="max-w-6xl mx-auto space-y-6">
    
    <div class="flex flex-wrap justify-between items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Visão Geral</h1>
            <p class="text-sm text-slate-500">Acompanhe o movimento e as pendências do seu lava rápido.</p>
        </div>
        <a href="entrada_lavagem.php" class="rounded-lg bg-brand-500 hover:bg-brand-700 text-white px-5 py-2.5 text-sm font-bold shadow-sm transition flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Nova Entrada
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- 1. TODAS AS LAVAGENS DE HOJE -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 flex flex-col">
            <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-xl">
                <h3 class="font-bold text-slate-800"><span class="text-emerald-500 mr-1">●</span> Lavagens de Hoje</h3>
                <div class="text-right">
                    <span class="text-xs font-bold text-slate-500 bg-slate-200 px-2 py-1 rounded"><?= $total1 ?> registros</span>
                    <span class="block text-sm font-black text-emerald-600 mt-1"><?= centavos_para_moeda($valorTotalHoje) ?></span>
                </div>
            </div>
            <div class="p-0 flex-1">
                <?php if (empty($lista1)): ?>
                    <p class="p-6 text-center text-sm text-slate-500">Nenhuma lavagem registrada hoje.</p>
                <?php else: ?>
                    <ul class="divide-y divide-slate-100">
                        <?php foreach ($lista1 as $item): ?>
                            <li class="p-3 hover:bg-slate-50 flex items-center justify-between">
                                <div>
                                    <div class="font-bold text-sm text-slate-800 flex items-center gap-2">
                                        <?= e($item['cliente_nome_avulso']) ?>
                                        <?php if($item['pago']): ?>
                                            <span class="text-[10px] uppercase font-bold bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded">Pago</span>
                                        <?php else: ?>
                                            <span class="text-[10px] uppercase font-bold bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded">Pendente</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-slate-500 font-mono">
                                        <?= e(formatar_placa($item['placa'])) ?> • 
                                        <span class="capitalize"><?= str_replace('_', ' ', $item['status']) ?></span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-bold text-slate-700 text-sm"><?= centavos_para_moeda($item['total_centavos']) ?></span>
                                    
                                    <?php if (!$item['pago']): ?>
                                        <button onclick="abrirModal(<?= $item['id'] ?>, '<?= addslashes($item['cliente_nome_avulso']) ?>', '<?= formatar_placa($item['placa']) ?>', '<?= number_format($item['total_centavos']/100, 2, ',', '.') ?>')" class="bg-emerald-100 hover:bg-emerald-200 text-emerald-700 px-3 py-1 rounded text-xs font-bold transition">Dar Baixa</button>
                                    <?php else: ?>
                                        <a href="entrada_lavagem.php?id=<?= $item['id'] ?>" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-1 rounded text-xs font-bold transition">Ver</a>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php if ($total1 > $limite): ?>
                <div class="p-3 border-t border-slate-100 bg-slate-50 flex justify-between items-center text-xs rounded-b-xl">
                    <span class="text-slate-500">Pág <?= $pag1 ?> de <?= ceil($total1/$limite) ?></span>
                    <div class="flex gap-2">
                        <a href="?p1=<?= $pag1-1 ?>" class="<?= $pag1 <= 1 ? 'opacity-50 pointer-events-none' : '' ?> text-brand-600 font-bold hover:underline">Ant</a>
                        <a href="?p1=<?= $pag1+1 ?>" class="<?= $pag1 >= ceil($total1/$limite) ? 'opacity-50 pointer-events-none' : '' ?> text-brand-600 font-bold hover:underline">Próx</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- 2. ENTREGUES SEM PAGAMENTO -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 flex flex-col">
            <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-xl">
                <h3 class="font-bold text-slate-800"><span class="text-rose-500 mr-1">●</span> Entregues / Anteriores</h3>
                <!-- EXIBIÇÃO DA SOMATÓRIA AQUI -->
                <div class="text-right">
                    <span class="text-xs font-bold text-slate-500 bg-slate-200 px-2 py-1 rounded"><?= $total2 ?> registros</span>
                    <span class="block text-sm font-black text-rose-600 mt-1"><?= centavos_para_moeda($valorTotalAnteriores) ?></span>
                </div>
            </div>
            <div class="p-0 flex-1">
                <?php if (empty($lista2)): ?>
                    <p class="p-6 text-center text-sm text-slate-500">Nenhuma pendência anterior.</p>
                <?php else: ?>
                    <ul class="divide-y divide-slate-100">
                        <?php foreach ($lista2 as $item): ?>
                            <li class="p-3 hover:bg-slate-50 flex items-center justify-between">
                                <div>
                                    <div class="font-bold text-sm text-slate-800"><?= e($item['cliente_nome_avulso']) ?></div>
                                    <div class="text-xs text-slate-500">
                                        <span class="font-mono"><?= e(formatar_placa($item['placa'])) ?></span> 
                                        • <?= date('d/m/Y', strtotime($item['data_entrada'])) ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-bold text-rose-600 text-sm"><?= centavos_para_moeda($item['total_centavos']) ?></span>
                                    <button onclick="abrirModal(<?= $item['id'] ?>, '<?= addslashes($item['cliente_nome_avulso']) ?>', '<?= formatar_placa($item['placa']) ?>', '<?= number_format($item['total_centavos']/100, 2, ',', '.') ?>')" class="bg-rose-100 hover:bg-rose-200 text-rose-700 px-3 py-1 rounded text-xs font-bold transition">Dar Baixa</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php if ($total2 > $limite): ?>
                <div class="p-3 border-t border-slate-100 bg-slate-50 flex justify-between items-center text-xs rounded-b-xl">
                    <span class="text-slate-500">Pág <?= $pag2 ?> de <?= ceil($total2/$limite) ?></span>
                    <div class="flex gap-2">
                        <a href="?p2=<?= $pag2-1 ?>" class="<?= $pag2 <= 1 ? 'opacity-50 pointer-events-none' : '' ?> text-brand-600 font-bold hover:underline">Ant</a>
                        <a href="?p2=<?= $pag2+1 ?>" class="<?= $pag2 >= ceil($total2/$limite) ? 'opacity-50 pointer-events-none' : '' ?> text-brand-600 font-bold hover:underline">Próx</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- 3. PAGAMENTOS VENCIDOS -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 flex flex-col">
            <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-xl">
                <h3 class="font-bold text-slate-800"><span class="text-amber-500 mr-1">●</span> Pagamentos Vencidos</h3>
                <span class="text-xs font-bold text-slate-500 bg-slate-200 px-2 py-1 rounded"><?= $total3 ?> total</span>
            </div>
            <div class="p-0 flex-1">
                <?php if ($erro_financeiro): ?>
                    <div class="p-6 text-center">
                        <p class="text-sm font-semibold text-amber-600 mb-1">Módulo financeiro não instalado.</p>
                    </div>
                <?php elseif (empty($lista3)): ?>
                    <p class="p-6 text-center text-sm text-slate-500">Nenhuma conta vencida.</p>
                <?php else: ?>
                    <ul class="divide-y divide-slate-100">
                        <?php foreach ($lista3 as $item): ?>
                            <li class="p-3 hover:bg-slate-50 flex items-center justify-between">
                                <div>
                                    <div class="font-bold text-sm text-slate-800 truncate w-40"><?= e($item['descricao']) ?></div>
                                    <div class="text-xs text-rose-500 font-semibold">Venceu em <?= date('d/m/Y', strtotime($item['data_vencimento'])) ?></div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-bold text-slate-700 text-sm"><?= centavos_para_moeda($item['valor_centavos']) ?></span>
                                    <button onclick="abrirModal(<?= $item['id'] ?>, '<?= addslashes($item['descricao']) ?>', 'Conta a Pagar', '<?= number_format($item['valor_centavos']/100, 2, ',', '.') ?>', 'conta')" class="bg-amber-100 hover:bg-amber-200 text-amber-700 px-3 py-1 rounded text-xs font-bold transition">Pagar</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- 4. AGENDA DA SEMANA -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 flex flex-col">
            <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-xl">
                <h3 class="font-bold text-slate-800"><span class="text-blue-500 mr-1">●</span> Agenda da Semana</h3>
                <span class="text-xs font-bold text-slate-500 bg-slate-200 px-2 py-1 rounded"><?= $total4 ?> total</span>
            </div>
            <div class="p-0 flex-1">
                <?php if (empty($lista4)): ?>
                    <p class="p-6 text-center text-sm text-slate-500">Nenhum agendamento nesta semana.</p>
                <?php else: ?>
                    <ul class="divide-y divide-slate-100">
                        <?php foreach ($lista4 as $item): ?>
                            <li class="p-3 hover:bg-slate-50 flex items-center justify-between group">
                                <div>
                                    <div class="font-bold text-sm text-slate-800"><?= e($item['cliente_nome']) ?></div>
                                    <div class="text-xs text-slate-500 font-mono">
                                        <?= e(formatar_placa($item['placa'])) ?> • 
                                        <?= date('d/m', strtotime($item['data_agendamento'])) ?> às <?= substr($item['hora_agendamento'],0,5) ?>
                                    </div>
                                </div>
                                <a href="entrada_lavagem.php?agendamento_id=<?= $item['id'] ?>" class="bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white px-3 py-1 rounded text-xs font-bold transition flex items-center gap-1">
                                    Abrir <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL DE REGISTRAR PAGAMENTO -->
<!-- ========================================== -->
<div id="modalPagamento" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col">
        
        <div class="px-5 py-4 border-b border-slate-200 flex justify-between items-center bg-white">
            <h3 class="font-bold text-lg text-slate-800">Registrar pagamento</h3>
            <button type="button" onclick="document.getElementById('modalPagamento').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
        </div>
        
        <form method="post" class="flex flex-col flex-1 overflow-y-auto">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao_pagar" value="1">
            <input type="hidden" name="id_entrada" id="m_id" value="">
            <input type="hidden" name="tipo" id="m_tipo" value="lavagem">
            <input type="hidden" name="forma_pagamento" id="m_forma_pgto" value="Dinheiro">

            <div class="p-5 space-y-5">
                <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 flex justify-between items-center text-sm">
                    <div>
                        <span class="text-slate-500 text-xs block">Cliente:</span>
                        <span class="font-bold text-slate-800" id="m_cliente">---</span>
                    </div>
                    <div class="text-right">
                        <span class="text-slate-500 text-xs block">Placa:</span>
                        <span class="font-bold text-slate-800 font-mono" id="m_placa">---</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1.5">Data do pagamento *</label>
                        <input type="date" name="data_pagamento" required value="<?= date('Y-m-d') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-black focus:ring-2 focus:ring-blue-400 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1.5">Valor pago (R$) *</label>
                        <input type="text" name="valor_pago" id="m_valor" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-black focus:ring-2 focus:ring-blue-400 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Forma de pagamento *</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" onclick="selectPgto(this, 'Crédito')" class="btn-pgto border border-slate-300 text-slate-600 rounded-lg py-1.5 text-sm hover:bg-slate-50 transition">Crédito</button>
                        <button type="button" onclick="selectPgto(this, 'Débito')" class="btn-pgto border border-slate-300 text-slate-600 rounded-lg py-1.5 text-sm hover:bg-slate-50 transition">Débito</button>
                        <button type="button" onclick="selectPgto(this, 'Dinheiro')" class="btn-pgto border border-blue-500 bg-blue-50 text-blue-700 font-bold rounded-lg py-1.5 text-sm transition">Dinheiro</button>
                        <button type="button" onclick="selectPgto(this, 'PIX')" class="btn-pgto border border-slate-300 text-slate-600 rounded-lg py-1.5 text-sm hover:bg-slate-50 transition">PIX</button>
                        <button type="button" onclick="selectPgto(this, 'Cheque')" class="btn-pgto border border-slate-300 text-slate-600 rounded-lg py-1.5 text-sm hover:bg-slate-50 transition">Cheque</button>
                        <button type="button" onclick="selectPgto(this, 'Promissória')" class="btn-pgto border border-slate-300 text-slate-600 rounded-lg py-1.5 text-sm hover:bg-slate-50 transition">Promissória</button>
                        <button type="button" onclick="selectPgto(this, 'Outro')" class="btn-pgto border border-slate-300 text-slate-600 rounded-lg py-1.5 text-sm hover:bg-slate-50 transition">Outro</button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1.5">Observação</label>
                    <textarea name="observacao" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-black focus:ring-2 focus:ring-blue-400 focus:outline-none"></textarea>
                </div>
            </div>

            <div class="px-5 py-4 border-t border-slate-200 bg-slate-50 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modalPagamento').classList.add('hidden')" class="px-4 py-2 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-sm transition">Cancelar</button>
                <button type="submit" name="acao_salvar" value="1" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-bold text-sm shadow-sm transition">Salvar</button>
                <button type="submit" name="acao_imprimir" value="1" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm shadow-sm transition flex items-center gap-2">
                    Salvar e gerar recibo
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function abrirModal(id, cliente, placa, valor, tipo = 'lavagem') {
        document.getElementById('m_id').value = id;
        document.getElementById('m_tipo').value = tipo;
        document.getElementById('m_cliente').innerText = cliente;
        document.getElementById('m_placa').innerText = placa;
        document.getElementById('m_valor').value = valor;
        
        let botoes = document.querySelectorAll('.btn-pgto');
        botoes.forEach(b => {
            b.classList.remove('border-blue-500', 'bg-blue-50', 'text-blue-700', 'font-bold');
            b.classList.add('border-slate-300', 'text-slate-600');
        });
        let btnDinheiro = Array.from(botoes).find(b => b.innerText === 'Dinheiro');
        if(btnDinheiro) selectPgto(btnDinheiro, 'Dinheiro');

        document.getElementById('modalPagamento').classList.remove('hidden');
    }

    function selectPgto(btn, forma) {
        document.querySelectorAll('.btn-pgto').forEach(b => {
            b.classList.remove('border-blue-500', 'bg-blue-50', 'text-blue-700', 'font-bold');
            b.classList.add('border-slate-300', 'text-slate-600');
        });
        btn.classList.remove('border-slate-300', 'text-slate-600');
        btn.classList.add('border-blue-500', 'bg-blue-50', 'text-blue-700', 'font-bold');
        document.getElementById('m_forma_pgto').value = forma;
    }
</script>

<?php require __DIR__ . '/footer.php'; ?>
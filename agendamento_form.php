<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_agendamentos();
ensure_lavagens();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$editar = $id > 0;
$erros = [];

// Busca o agendamento se for edição
$agendamento = [
    'cliente_nome' => '',
    'cliente_celular' => '',
    'placa' => '',
    'lavagem_id' => '',
    'data_agendamento' => date('Y-m-d'),
    'hora_agendamento' => date('H:i'),
    'status' => 'pendente'
];

if ($editar) {
    $st = db()->prepare('SELECT * FROM agendamentos WHERE id = ?');
    $st->execute([$id]);
    $agendamento = $st->fetch();
    
    if (!$agendamento) {
        flash('Agendamento não encontrado.', 'erro');
        header('Location: agendamentos.php');
        exit;
    }
}

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $agendamento['cliente_nome'] = trim($_POST['cliente_nome'] ?? '');
    $agendamento['cliente_celular'] = trim($_POST['cliente_celular'] ?? '');
    $agendamento['placa'] = normalizar_placa($_POST['placa'] ?? '');
    $agendamento['lavagem_id'] = (int)($_POST['lavagem_id'] ?? 0);
    $agendamento['data_agendamento'] = trim($_POST['data_agendamento'] ?? '');
    $agendamento['hora_agendamento'] = trim($_POST['hora_agendamento'] ?? '');

    // Validações
    if (empty($agendamento['cliente_nome'])) $erros[] = 'O nome do cliente é obrigatório.';
    if (empty($agendamento['placa']) || !validar_placa($agendamento['placa'])) $erros[] = 'Informe uma placa válida.';
    if (empty($agendamento['lavagem_id'])) $erros[] = 'Selecione um serviço.';
    if (empty($agendamento['data_agendamento'])) $erros[] = 'A data é obrigatória.';
    if (empty($agendamento['hora_agendamento'])) $erros[] = 'A hora é obrigatória.';

    if (empty($erros)) {
        try {
            if ($editar) {
                $stmt = db()->prepare("
                    UPDATE agendamentos SET 
                        cliente_nome = ?, cliente_celular = ?, placa = ?, lavagem_id = ?, data_agendamento = ?, hora_agendamento = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $agendamento['cliente_nome'], $agendamento['cliente_celular'], $agendamento['placa'], 
                    $agendamento['lavagem_id'], $agendamento['data_agendamento'], $agendamento['hora_agendamento'], $id
                ]);
                flash('Agendamento atualizado com sucesso.', 'sucesso');
            } else {
                $stmt = db()->prepare("
                    INSERT INTO agendamentos (cliente_nome, cliente_celular, placa, lavagem_id, data_agendamento, hora_agendamento, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pendente')
                ");
                $stmt->execute([
                    $agendamento['cliente_nome'], $agendamento['cliente_celular'], $agendamento['placa'], 
                    $agendamento['lavagem_id'], $agendamento['data_agendamento'], $agendamento['hora_agendamento']
                ]);
                flash('Agendamento criado com sucesso.', 'sucesso');
            }
            header('Location: agendamentos.php');
            exit;
        } catch (Throwable $e) {
            $erros[] = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}

// Busca os serviços disponíveis
$servicos = db()->query("SELECT id, nome, preco_centavos FROM lavagens WHERE ativo = 1 ORDER BY nome")->fetchAll();

$titulo = $editar ? 'Editar Agendamento' : 'Novo Agendamento';
require __DIR__ . '/header.php';
?>

<div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-2xl shadow-card border border-slate-200 p-6 sm:p-8">
        
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-slate-900"><?= $titulo ?></h1>
            <a href="agendamentos.php" class="text-sm text-brand-600 hover:underline font-semibold flex items-center gap-1">
                ← Voltar
            </a>
        </div>

        <?php if ($erros): ?>
            <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm font-semibold">
                <ul class="list-disc pl-5 space-y-1">
                    <?php foreach ($erros as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-5">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <?php if ($editar): ?>
                <input type="hidden" name="id" value="<?= $id ?>">
            <?php endif; ?>

            <!-- Cliente -->
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1.5">Nome do cliente *</label>
                <input type="text" name="cliente_nome" required value="<?= e($agendamento['cliente_nome']) ?>" placeholder="Ex: João da Silva" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-400 font-semibold text-black">
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <!-- Celular -->
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1.5">Celular</label>
                    <input type="text" name="cliente_celular" id="celular" value="<?= e($agendamento['cliente_celular']) ?>" placeholder="(00) 00000-0000" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-400 font-semibold text-black">
                </div>

                <!-- Placa -->
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1.5">Placa *</label>
                    <input type="text" name="placa" required maxlength="8" value="<?= e(formatar_placa($agendamento['placa'])) ?>" placeholder="ABC-1234" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-400 font-semibold text-black uppercase">
                </div>
            </div>

            <!-- Serviço -->
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1.5">Serviço Desejado *</label>
                <select name="lavagem_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-400 font-semibold text-black bg-white">
                    <option value="">Selecione um serviço...</option>
                    <?php foreach ($servicos as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $agendamento['lavagem_id'] == $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['nome']) ?> — <?= centavos_para_moeda($s['preco_centavos']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Data e Hora -->
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1.5">Data *</label>
                    <input type="date" name="data_agendamento" required value="<?= e($agendamento['data_agendamento']) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-400 font-semibold text-black">
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1.5">Hora *</label>
                    <input type="time" name="hora_agendamento" required value="<?= e($agendamento['hora_agendamento']) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-400 font-semibold text-black">
                </div>
            </div>

            <hr class="border-slate-200 my-6">

            <!-- BOTÕES DE AÇÃO -->
            <div class="flex justify-end items-center gap-2 pt-2">
                <a href="agendamentos.php" class="px-5 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-sm transition">
                    Cancelar
                </a>
                
                <button type="submit" class="px-6 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold shadow-sm transition">
                    <?= $editar ? 'Salvar Alterações' : 'Criar Agendamento' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Máscara simples para o celular (caso não tenha no sistema)
    document.getElementById('celular').addEventListener('input', function (e) {
        let v = e.target.value.replace(/\D/g, '').slice(0, 11);
        if (v.length > 10) {
            v = v.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
        } else if (v.length > 6) {
            v = v.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
        } else if (v.length > 2) {
            v = v.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
        } else if (v.length > 0) {
            v = v.replace(/^(\d{0,2})/, '($1');
        }
        e.target.value = v;
    });
</script>

<?php require __DIR__ . '/footer.php'; ?>
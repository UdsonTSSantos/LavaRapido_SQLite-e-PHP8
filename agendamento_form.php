<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_agendamentos();
ensure_lavagens();

$erros = [];
$dados = [
    'cliente_nome' => '', 'cliente_celular' => '', 'placa' => '', 
    'lavagem_id' => 0, 'data_agendamento' => date('Y-m-d'), 'hora_agendamento' => date('H:i')
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    
    $dados = [
        'cliente_nome'     => trim((string)($_POST['cliente_nome'] ?? '')),
        'cliente_celular'  => trim((string)($_POST['cliente_celular'] ?? '')),
        'placa'            => normalizar_placa((string)($_POST['placa'] ?? '')),
        'lavagem_id'       => (int)($_POST['lavagem_id'] ?? 0),
        'data_agendamento' => trim((string)($_POST['data_agendamento'] ?? '')),
        'hora_agendamento' => trim((string)($_POST['hora_agendamento'] ?? '')),
    ];

    if ($dados['cliente_nome'] === '') $erros[] = 'Informe o nome do cliente.';
    if (!validar_placa($dados['placa'])) $erros[] = 'Informe uma placa válida.';
    if ($dados['lavagem_id'] <= 0) $erros[] = 'Selecione um serviço.';
    if ($dados['data_agendamento'] === '') $erros[] = 'Informe a data.';
    if ($dados['hora_agendamento'] === '') $erros[] = 'Informe a hora.';

    if (!$erros) {
        $u = usuario_logado();
        db()->prepare("
            INSERT INTO agendamentos (cliente_nome, cliente_celular, placa, lavagem_id, data_agendamento, hora_agendamento, criado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $dados['cliente_nome'], $dados['cliente_celular'], $dados['placa'],
            $dados['lavagem_id'], $dados['data_agendamento'], $dados['hora_agendamento'], (int)$u['id']
        ]);
        flash('Agendamento criado com sucesso!', 'sucesso');
        header('Location: agendamentos.php');
        exit;
    }
}

$servicos = db()->query('SELECT id, nome, preco_centavos FROM lavagens WHERE ativo = 1 ORDER BY nome COLLATE NOCASE')->fetchAll();

$titulo = 'Novo Agendamento';
require __DIR__ . '/header.php';
?>

<div class="max-w-2xl mx-auto">
  <form method="post" class="bg-white rounded-2xl shadow-card border border-slate-200 p-6 sm:p-8 space-y-6" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-2xl font-bold text-slate-900">Novo Agendamento</h1>
      <a href="agendamentos.php" class="text-sm text-brand-600 hover:underline font-semibold">← Voltar</a>
    </div>

    <?php if ($erros): ?>
      <div class="rounded-lg border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm font-semibold">
        <ul class="list-disc pl-5"><?php foreach ($erros as $e) echo "<li>$e</li>"; ?></ul>
      </div>
    <?php endif; ?>

    <div class="grid gap-4 sm:grid-cols-2">
      <div class="sm:col-span-2">
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Nome do cliente *</label>
        <input name="cliente_nome" required value="<?= e($dados['cliente_nome']) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black focus:outline-none focus:ring-2 focus:ring-brand-400">
      </div>
      <div>
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Celular</label>
        <input name="cliente_celular" inputmode="numeric" maxlength="16" value="<?= e($dados['cliente_celular']) ?>" placeholder="(00) 00000-0000" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black focus:outline-none focus:ring-2 focus:ring-brand-400">
      </div>
      <div>
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Placa *</label>
        <input name="placa" required maxlength="8" value="<?= e($dados['placa']) ?>" placeholder="ABC1D23" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 uppercase font-semibold text-black focus:outline-none focus:ring-2 focus:ring-brand-400">
      </div>
      <div class="sm:col-span-2">
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Serviço Desejado *</label>
        <select name="lavagem_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black focus:outline-none focus:ring-2 focus:ring-brand-400">
          <option value="">Selecione...</option>
          <?php foreach ($servicos as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $dados['lavagem_id'] == $s['id'] ? 'selected' : '' ?>><?= e($s['nome']) ?> — <?= e(centavos_para_moeda_brl($s['preco_centavos'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Data *</label>
        <input type="date" name="data_agendamento" required value="<?= e($dados['data_agendamento']) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black focus:outline-none focus:ring-2 focus:ring-brand-400">
      </div>
      <div>
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Hora *</label>
        <input type="time" name="hora_agendamento" required value="<?= e($dados['hora_agendamento']) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black focus:outline-none focus:ring-2 focus:ring-brand-400">
      </div>
    </div>

    <div class="flex justify-end pt-4 border-t border-slate-200">
      <button class="px-6 py-2.5 rounded-lg bg-brand-500 hover:bg-brand-700 text-white font-bold shadow-sm">Salvar Agendamento</button>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var inputCel = document.querySelector('[name="cliente_celular"]');
    inputCel.addEventListener('input', function() {
        var v = this.value.replace(/\D/g,'').slice(0,11);
        if(v.length > 2) v = '(' + v.slice(0,2) + ') ' + v.slice(2);
        this.value = v;
    });
});
</script>
<?php require __DIR__ . '/footer.php'; ?>
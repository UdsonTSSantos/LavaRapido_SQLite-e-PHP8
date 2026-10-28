<?php
require_once __DIR__ . '/config.php';
exigir_login();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    flash('ID do cupom inválido.', 'erro');
    header('Location: pagamentos_pendentes.php');
    exit;
}

// Busca a entrada no banco
$stmt = db()->prepare("SELECT * FROM lavagem_entradas WHERE id = ?");
$stmt->execute([$id]);
$entrada = $stmt->fetch();

if (!$entrada) {
    flash('Cupom não encontrado.', 'erro');
    header('Location: pagamentos_pendentes.php');
    exit;
}

// Se já estiver pago, não deixa pagar de novo
if ($entrada['pago'] == 1) {
    flash('Este cupom já foi pago anteriormente.', 'erro');
    header('Location: pagamentos_pendentes.php');
    exit;
}

// Processa o pagamento quando o formulário for enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    
    $forma_pagamento = trim((string)($_POST['forma_pagamento'] ?? 'Dinheiro'));
    
    $pdo = db();
    $pdo->beginTransaction();
    
    try {
        // Atualiza o status para pago
        $pdo->prepare("UPDATE lavagem_entradas SET pago = 1, atualizado_em = datetime('now','localtime') WHERE id = ?")->execute([$id]);
        
        // (Opcional) Se você tiver uma tabela de caixa ou financeiro, insira o lançamento aqui.
        
        $pdo->commit();
        flash('Pagamento recebido com sucesso!', 'sucesso');
        header('Location: pagamentos_pendentes.php');
        exit;
    } catch (Throwable $e) {
        $pdo->rollBack();
        $erros[] = 'Erro ao registrar pagamento: ' . $e->getMessage();
    }
}

$titulo = 'Receber Pagamento';
require __DIR__ . '/header.php';
?>

<div class="max-w-lg mx-auto mt-6">
  <div class="bg-white rounded-2xl shadow-card border border-slate-200 overflow-hidden">
    
    <!-- Topo do Modal -->
    <div class="bg-emerald-600 px-6 py-4 text-white">
      <h1 class="text-xl font-bold flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        Finalizar Pagamento
      </h1>
      <p class="text-emerald-100 text-sm mt-1">Confirme os dados abaixo para dar baixa no cupom.</p>
    </div>

    <!-- Detalhes do Cupom -->
    <div class="p-6 space-y-4">
      
      <?php if (!empty($erros)): ?>
        <div class="bg-rose-50 text-rose-700 p-3 rounded-lg text-sm font-semibold border border-rose-200">
            <?php foreach ($erros as $e): ?><p><?= e($e) ?></p><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="flex justify-between items-center pb-4 border-b border-slate-100">
        <div>
          <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Cupom</p>
          <p class="text-lg font-mono font-bold text-slate-800">#<?= str_pad($entrada['id'], 5, '0', STR_PAD_LEFT) ?></p>
        </div>
        <div class="text-right">
          <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Data</p>
          <p class="text-sm font-semibold text-slate-800"><?= e(date('d/m/Y', strtotime($entrada['data_entrada']))) ?></p>
        </div>
      </div>

      <div>
        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Cliente</p>
        <p class="text-base font-semibold text-slate-800"><?= e($entrada['cliente_nome_avulso']) ?></p>
        <p class="text-sm font-mono font-semibold text-slate-600 mt-1 bg-slate-100 inline-block px-2 py-0.5 rounded"><?= e(formatar_placa($entrada['placa'])) ?></p>
      </div>

      <div class="bg-slate-50 rounded-xl p-4 border border-slate-200 mt-2">
        <div class="flex justify-between text-sm mb-1">
          <span class="text-slate-500">Subtotal:</span>
          <span class="font-semibold text-slate-700"><?= centavos_para_moeda($entrada['subtotal_centavos']) ?></span>
        </div>
        <div class="flex justify-between text-sm mb-3">
          <span class="text-slate-500">Desconto:</span>
          <span class="font-semibold text-rose-600">- <?= centavos_para_moeda($entrada['desconto_centavos']) ?></span>
        </div>
        <div class="flex justify-between items-center pt-3 border-t border-slate-200">
          <span class="text-base font-bold text-slate-800">Total a Receber:</span>
          <span class="text-2xl font-black text-emerald-600"><?= centavos_para_moeda($entrada['total_centavos']) ?></span>
        </div>
      </div>

      <!-- Formulário de Confirmação -->
      <form method="post" action="receber_pagamento.php?id=<?= $id ?>" class="pt-2">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="mb-5">
          <label class="block text-sm font-bold text-slate-700 mb-1.5">Forma de Pagamento</label>
          <select name="forma_pagamento" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-400">
            <option value="Dinheiro">Dinheiro</option>
            <option value="Pix">Pix</option>
            <option value="Cartão de Débito">Cartão de Débito</option>
            <option value="Cartão de Crédito">Cartão de Crédito</option>
          </select>
        </div>

        <div class="flex gap-3">
          <a href="pagamentos_pendentes.php" class="flex-1 text-center px-4 py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold transition">
            Cancelar
          </a>
          <button type="submit" class="flex-1 px-4 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold shadow-sm transition flex items-center justify-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            Confirmar Recebimento
          </button>
        </div>
      </form>

    </div>
  </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
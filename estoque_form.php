<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_estoque();

$id = (int)($_GET['id'] ?? 0);
$item = $id > 0 ? buscar_item_estoque($id) : null;

$dados = $item ?? [
    'codigo' => '', 'nome' => '', 'unidade' => 'UN', 'quantidade' => 0,
    'estoque_minimo' => 0, 'preco_custo_centavos' => 0, 'preco_venda_centavos' => 0, 'ativo' => 1
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    
    $dados = [
        'codigo' => trim((string)($_POST['codigo'] ?? '')),
        'nome' => trim((string)($_POST['nome'] ?? '')),
        'unidade' => trim((string)($_POST['unidade'] ?? 'UN')),
        'estoque_minimo' => (float)str_replace(',', '.', $_POST['estoque_minimo'] ?? '0'),
        'preco_custo_centavos' => moeda_para_centavos((string)($_POST['preco_custo'] ?? '0')),
        'preco_venda_centavos' => moeda_para_centavos((string)($_POST['preco_venda'] ?? '0')),
        'ativo' => isset($_POST['ativo']) ? 1 : 0,
    ];

    $erros = [];
    if ($dados['codigo'] === '') $erros[] = 'Informe o código.';
    if ($dados['nome'] === '') $erros[] = 'Informe o nome.';

    if (!$erros) {
        $sql = 'SELECT id FROM estoque_itens WHERE codigo = ?' . ($id ? ' AND id <> ?' : '');
        $st = db()->prepare($sql);
        $st->execute($id ? [$dados['codigo'], $id] : [$dados['codigo']]);
        if ($st->fetch()) $erros[] = 'Já existe um item com este código.';
    }

    if (!$erros) {
        if ($id) {
            // Atualiza dados cadastrais (quantidade inicial não é editada aqui, só via movimentação)
            db()->prepare("
                UPDATE estoque_itens SET codigo=?, nome=?, unidade=?, estoque_minimo=?, preco_custo_centavos=?, preco_venda_centavos=?, ativo=?, atualizado_em=datetime('now','localtime')
                WHERE id=?
            ")->execute([$dados['codigo'], $dados['nome'], $dados['unidade'], $dados['estoque_minimo'], $dados['preco_custo_centavos'], $dados['preco_venda_centavos'], $dados['ativo'], $id]);
            flash('Item atualizado.', 'sucesso');
        } else {
            db()->prepare("
                INSERT INTO estoque_itens (codigo, nome, unidade, quantidade, estoque_minimo, preco_custo_centavos, preco_venda_centavos, ativo)
                VALUES (?, ?, ?, 0, ?, ?, ?, ?)
            ")->execute([$dados['codigo'], $dados['nome'], $dados['unidade'], $dados['estoque_minimo'], $dados['preco_custo_centavos'], $dados['preco_venda_centavos'], $dados['ativo']]);
            $novoId = (int)db()->lastInsertId();
            flash('Item cadastrado. Use o botão de movimentação para adicionar saldo.', 'sucesso');
        }
        header('Location: estoque.php');
        exit;
    }
}

$titulo = $id ? 'Editar Item' : 'Novo Item no Estoque';
require __DIR__ . '/header.php';
?>

<div class="max-w-3xl mx-auto">
  <form method="post" class="bg-white rounded-2xl shadow-card border border-slate-200 p-6 sm:p-8 space-y-6" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-2xl font-bold text-slate-900"><?= $titulo ?></h1>
      <a href="estoque.php" class="text-sm text-brand-600 hover:underline font-semibold">← Voltar</a>
    </div>

    <?php if (!empty($erros)): ?>
      <div class="rounded-lg border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm font-semibold">
        <ul class="list-disc pl-5"><?php foreach($erros as $e) echo "<li>$e</li>"; ?></ul>
      </div>
    <?php endif; ?>

    <div class="grid gap-4 sm:grid-cols-6">
      <div class="sm:col-span-2">
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Código / SKU *</label>
        <input name="codigo" required value="<?= e($dados['codigo']) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black">
      </div>
      <div class="sm:col-span-4">
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Nome da Peça *</label>
        <input name="nome" required value="<?= e($dados['nome']) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black">
      </div>

      <div class="sm:col-span-2">
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Unidade</label>
        <input name="unidade" value="<?= e($dados['unidade']) ?>" placeholder="UN, CX, L" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black">
      </div>
      <div class="sm:col-span-2">
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Estoque Mínimo</label>
        <input name="estoque_minimo" type="number" step="0.01" value="<?= e((string)$dados['estoque_minimo']) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black">
      </div>

      <div class="sm:col-span-3">
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Preço de Custo (R$)</label>
        <input name="preco_custo" data-mask="money" value="<?= e($dados['preco_custo_centavos'] ? centavos_para_moeda((int)$dados['preco_custo_centavos']) : '') ?>" placeholder="0,00" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black">
      </div>
      <div class="sm:col-span-3">
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Preço de Venda (R$)</label>
        <input name="preco_venda" data-mask="money" value="<?= e($dados['preco_venda_centavos'] ? centavos_para_moeda((int)$dados['preco_venda_centavos']) : '') ?>" placeholder="0,00" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black">
      </div>

      <label class="flex items-center gap-2 text-sm font-semibold text-slate-800 sm:col-span-6 cursor-pointer mt-2">
        <input type="checkbox" name="ativo" value="1" <?= $dados['ativo'] ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-500 focus:ring-brand-400">
        Item ativo no sistema
      </label>
    </div>

    <div class="flex justify-end gap-2 pt-4 border-t border-slate-200">
      <a href="estoque.php" class="px-4 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-sm font-semibold text-slate-700">Cancelar</a>
      <button class="px-5 py-2.5 rounded-lg bg-brand-500 hover:bg-brand-700 text-white font-semibold shadow-sm">Salvar Item</button>
    </div>
  </form>
</div>
<script>
// Reaproveitando a máscara de moeda
document.addEventListener('DOMContentLoaded', () => {
  const soDigitos = v => (v || '').replace(/\D+/g, '');
  document.querySelectorAll('[data-mask="money"]').forEach(el => {
    el.addEventListener('input', () => {
      let v = soDigitos(el.value);
      if (v === '') { el.value = ''; return; }
      v = v.padStart(3, '0');
      const int = v.slice(0, -2), dec = v.slice(-2);
      el.value = int.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + dec;
    });
  });
});
</script>
<?php require __DIR__ . '/footer.php'; ?>
<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_lavagens();

$id     = (int)($_GET['id'] ?? 0);
$editar = $id > 0;
$lavagem = $editar ? buscar_lavagem($id) : null;

if ($editar && !$lavagem) {
    flash('Lavagem não encontrada.', 'erro');
    header('Location: lavagens.php');
    exit;
}

$erros = [];

$dados = $lavagem ?? [
    'nome' => '', 'descricao' => '', 'preco_centavos' => 0, 
    'comissao_centavos' => 0, 'duracao_min' => 0, 'ativo' => 1, 'ordem' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $dados = [
        'nome'             => trim((string)($_POST['nome'] ?? '')),
        'descricao'        => trim((string)($_POST['descricao'] ?? '')),
        'preco_centavos'   => moeda_para_centavos((string)($_POST['preco'] ?? '0')),
        'comissao_centavos'=> moeda_para_centavos((string)($_POST['comissao'] ?? '0')),
        'duracao_min'      => max(0, (int)($_POST['duracao_min'] ?? 0)),
        'ordem'            => max(0, (int)($_POST['ordem'] ?? 0)),
        'ativo'            => isset($_POST['ativo']) ? 1 : 0,
    ];

    if ($dados['nome'] === '') {
        $erros[] = 'Informe o nome do tipo de lavagem.';
    }
    if ($dados['preco_centavos'] < 0) {
        $erros[] = 'Preço inválido.';
    }
    if ($dados['comissao_centavos'] < 0) {
        $erros[] = 'Comissão inválida.';
    }

    /* Duplicidade de nome */
    if (!$erros) {
        $sql = 'SELECT id FROM lavagens WHERE nome = :n COLLATE NOCASE' . ($editar ? ' AND id <> :id' : '');
        $st = db()->prepare($sql);
        $st->bindValue(':n', $dados['nome']);
        if ($editar) $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        if ($st->fetch()) $erros[] = 'Já existe um tipo de lavagem com esse nome.';
    }

    if (!$erros) {
        if ($editar) {
            $sql = 'UPDATE lavagens SET
                        nome = :nome,
                        descricao = :descricao,
                        preco_centavos = :preco_centavos,
                        comissao_centavos = :comissao_centavos,
                        duracao_min = :duracao_min,
                        ordem = :ordem,
                        ativo = :ativo,
                        atualizado_em = datetime(\'now\',\'localtime\')
                    WHERE id = :id';
            $dados['id'] = $id;
            db()->prepare($sql)->execute($dados);
            flash('Lavagem atualizada com sucesso.', 'sucesso');
        } else {
            $sql = 'INSERT INTO lavagens
                        (nome, descricao, preco_centavos, comissao_centavos, duracao_min, ordem, ativo)
                    VALUES
                        (:nome, :descricao, :preco_centavos, :comissao_centavos, :duracao_min, :ordem, :ativo)';
            db()->prepare($sql)->execute($dados);
            flash('Lavagem cadastrada com sucesso.', 'sucesso');
        }
        header('Location: lavagens.php');
        exit;
    }
}

$titulo = $editar ? 'Editar lavagem' : 'Nova lavagem';
require __DIR__ . '/header.php';
?>

<!-- ============ MÁSCARA DE MOEDA ============ -->
<script>
(function () {
  const soDigitos = v => (v || '').replace(/\D+/g, '');

  const Mask = {
    money(v) {
      v = soDigitos(v);
      if (v === '') return '';
      v = v.padStart(3, '0');
      const int = v.slice(0, -2), dec = v.slice(-2);
      return int.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + dec;
    }
  };
  window.Mask = Mask;

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-mask="money"]').forEach(el => {
      const exec = () => { el.value = Mask.money(el.value); };
      el.addEventListener('input', exec);
      el.addEventListener('blur',  exec);
      exec();
    });
  });
})();
</script>

<div class="max-w-3xl mx-auto">
  <form method="post" class="bg-white rounded-2xl shadow-card border border-slate-200 p-6 sm:p-8 space-y-6" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-slate-900"><?= $editar ? 'Editar lavagem' : 'Nova lavagem' ?></h1>
        <p class="text-sm text-slate-500">Tipo de serviço que poderá ser escolhido ao registrar uma lavagem.</p>
      </div>
      <a href="lavagens.php" class="text-sm text-brand-600 hover:underline font-semibold">← Voltar</a>
    </div>

    <?php if ($erros): ?>
      <div class="rounded-lg border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm font-semibold">
        <ul class="list-disc pl-5 space-y-1">
          <?php foreach ($erros as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <section class="grid gap-4 sm:grid-cols-6">
      
      <!-- Nome -->
      <div class="sm:col-span-6">
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Nome do serviço *</label>
        <input name="nome" required maxlength="120" value="<?= e($dados['nome']) ?>"
               placeholder="Ex.: Lavagem simples, Lavagem completa, Enceramento..."
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-400 bg-white text-black font-semibold">
      </div>

      <!-- Descrição -->
      <div class="sm:col-span-6">
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Descrição</label>
        <textarea name="descricao" rows="3" maxlength="500"
                  placeholder="O que está incluso nesse tipo de lavagem?"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-400 bg-white text-black font-semibold"><?= e($dados['descricao']) ?></textarea>
      </div>

      <!-- Preço -->
      <div class="sm:col-span-3">
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Preço (R$)</label>
        <div class="relative flex items-center">
          <span class="absolute left-4 text-slate-500 font-bold pointer-events-none z-10">R$</span>
          <input name="preco" id="preco" data-mask="money" inputmode="numeric"
                 value="<?= e($dados['preco_centavos'] ? centavos_para_moeda((int)$dados['preco_centavos']) : '') ?>"
                 placeholder="0,00"
                 class="w-full rounded-lg border border-slate-300 !pl-12 pr-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-400 bg-white text-black font-semibold">
        </div>
        <p class="mt-1 text-xs text-slate-500">Valor cobrado do cliente.</p>
      </div>

      <!-- Comissão -->
      <div class="sm:col-span-3">
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Comissão (R$)</label>
        <div class="relative flex items-center">
          <span class="absolute left-4 text-slate-500 font-bold pointer-events-none z-10">R$</span>
          <input name="comissao" id="comissao" data-mask="money" inputmode="numeric"
                 value="<?= e(isset($dados['comissao_centavos']) && $dados['comissao_centavos'] ? centavos_para_moeda((int)$dados['comissao_centavos']) : '') ?>"
                 placeholder="0,00"
                 class="w-full rounded-lg border border-slate-300 !pl-12 pr-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-400 bg-white text-black font-semibold">
        </div>
        <p class="mt-1 text-xs text-slate-500">Valor pago ao funcionário.</p>
      </div>

      <!-- Duração -->
      <div class="sm:col-span-3">
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Duração estimada (min)</label>
        <input name="duracao_min" type="number" min="0" max="1440" step="5"
               value="<?= (int)$dados['duracao_min'] ?>"
               placeholder="0"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-400 bg-white text-black font-semibold">
        <p class="mt-1 text-xs text-slate-500">Use 0 para “não definida”.</p>
      </div>

      <!-- Ordem -->
      <div class="sm:col-span-3">
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Ordem de exibição</label>
        <input name="ordem" type="number" min="0" max="9999" step="1"
               value="<?= (int)$dados['ordem'] ?>"
               placeholder="0"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-400 bg-white text-black font-semibold">
        <p class="mt-1 text-xs text-slate-500">Números menores aparecem primeiro.</p>
      </div>

      <!-- Ativo -->
      <label class="flex items-center gap-2 text-sm font-semibold text-slate-800 sm:col-span-6 cursor-pointer">
        <input type="checkbox" name="ativo" value="1" <?= !empty($dados['ativo']) ? 'checked' : '' ?>
               class="rounded border-slate-300 text-brand-500 focus:ring-brand-400">
        Serviço ativo (disponível para escolha ao registrar lavagens)
      </label>
    </section>

    <div class="flex justify-end gap-2 pt-2">
      <a href="lavagens.php"
         class="px-4 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-sm font-semibold text-slate-700">Cancelar</a>
      <button class="px-5 py-2.5 rounded-lg bg-brand-500 hover:bg-brand-700 text-white font-semibold shadow-sm">
        <?= $editar ? 'Salvar alterações' : 'Cadastrar lavagem' ?>
      </button>
    </div>
  </form>
</div>

<?php require __DIR__ . '/footer.php'; ?>
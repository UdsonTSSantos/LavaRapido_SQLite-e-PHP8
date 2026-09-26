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
    'duracao_min' => 0, 'ativo' => 1, 'ordem' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $dados = [
        'nome'           => trim((string)($_POST['nome'] ?? '')),
        'descricao'      => trim((string)($_POST['descricao'] ?? '')),
        'preco_centavos' => moeda_para_centavos((string)($_POST['preco'] ?? '0')),
        'duracao_min'    => max(0, (int)($_POST['duracao_min'] ?? 0)),
        'ordem'          => max(0, (int)($_POST['ordem'] ?? 0)),
        'ativo'          => isset($_POST['ativo']) ? 1 : 0,
    ];

    if ($dados['nome'] === '') {
        $erros[] = 'Informe o nome do tipo de lavagem.';
    }
    if ($dados['preco_centavos'] < 0) {
        $erros[] = 'Preço inválido.';
    }

    /* Duplicidade de nome */
    if (!$erros) {
        $sql = 'SELECT id FROM lavagens WHERE nome = :n COLLATE NOCASE'
             . ($editar ? ' AND id <> :id' : '');
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
                        (nome, descricao, preco_centavos, duracao_min, ordem, ativo)
                    VALUES
                        (:nome, :descricao, :preco_centavos, :duracao_min, :ordem, :ativo)';
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
      const int = v.slice(0, -2);
      const dec = v.slice(-2);
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
  <form method="post" class="bg-white rounded-xl shadow p-6 sm:p-8 space-y-6" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold"><?= $editar ? 'Editar lavagem' : 'Nova lavagem' ?></h1>
        <p class="text-sm text-slate-500">Tipo de serviço que poderá ser escolhido ao registrar uma lavagem.</p>
      </div>
      <a href="lavagens.php" class="text-sm text-sky-600 hover:underline">← Voltar</a>
    </div>

    <?php if ($erros): ?>
      <div class="rounded-md border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm">
        <ul class="list-disc pl-5 space-y-1">
          <?php foreach ($erros as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <section class="grid gap-4 sm:grid-cols-6">
      <div class="sm:col-span-6">
        <label class="block text-sm font-medium mb-1">Nome do serviço *</label>
        <input name="nome" required maxlength="120" value="<?= e($dados['nome']) ?>"
               placeholder="Ex.: Lavagem simples, Lavagem completa, Enceramento..."
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>

      <div class="sm:col-span-6">
        <label class="block text-sm font-medium mb-1">Descrição</label>
        <textarea name="descricao" rows="3" maxlength="500"
                  placeholder="O que está incluso nesse tipo de lavagem?"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500"><?= e($dados['descricao']) ?></textarea>
      </div>

      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">Preço (R$)</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 text-sm pointer-events-none">R$</span>
          <input name="preco" id="preco" data-mask="money" inputmode="numeric"
                 value="<?= e($dados['preco_centavos'] ? centavos_para_moeda((int)$dados['preco_centavos']) : '') ?>"
                 placeholder="0,00"
                 class="w-full rounded-lg border border-slate-300 pl-9 pr-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        </div>
        <p class="mt-1 text-xs text-slate-500">Digite apenas números. Ex.: 2590 → 25,90</p>
      </div>

      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">Duração estimada (min)</label>
        <input name="duracao_min" type="number" min="0" max="1440" step="5"
               value="<?= (int)$dados['duracao_min'] ?>"
               placeholder="0"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        <p class="mt-1 text-xs text-slate-500">Use 0 para “não definida”.</p>
      </div>

      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">Ordem de exibição</label>
        <input name="ordem" type="number" min="0" max="9999" step="1"
               value="<?= (int)$dados['ordem'] ?>"
               placeholder="0"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
        <p class="mt-1 text-xs text-slate-500">Números menores aparecem primeiro.</p>
      </div>

      <label class="flex items-center gap-2 text-sm sm:col-span-6">
        <input type="checkbox" name="ativo" value="1" <?= !empty($dados['ativo']) ? 'checked' : '' ?>
               class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
        Serviço ativo (disponível para escolha ao registrar lavagens)
      </label>
    </section>

    <div class="flex justify-end gap-2 pt-2">
      <a href="lavagens.php"
         class="px-4 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-sm font-medium">Cancelar</a>
      <button class="px-5 py-2.5 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-medium">
        <?= $editar ? 'Salvar alterações' : 'Cadastrar lavagem' ?>
      </button>
    </div>
  </form>
</div>

<?php require __DIR__ . '/footer.php'; ?>
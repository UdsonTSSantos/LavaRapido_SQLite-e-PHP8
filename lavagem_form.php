<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_lavagens();
ensure_comissoes();

$id     = (int)($_GET['id'] ?? 0);
$editar = $id > 0;
$lavagem = $editar ? buscar_lavagem($id) : null;

if ($editar && !$lavagem) { flash('Lavagem não encontrada.', 'erro'); header('Location: lavagens.php'); exit; }

$erros = [];
$dados = $lavagem ?? [
    'nome' => '', 'descricao' => '', 'preco_centavos' => 0, 
    'comissao_centavos' => 0, 'duracao_min' => 0, 'ativo' => 1, 'ordem' => 0, 'paga_comissao' => 1
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
        'paga_comissao'    => isset($_POST['paga_comissao']) ? 1 : 0,
    ];

    if ($dados['nome'] === '') $erros[] = 'Informe o nome do tipo de lavagem.';
    if ($dados['paga_comissao'] && $dados['comissao_centavos'] <= 0) $erros[] = 'Como o serviço paga comissão, informe um valor maior que zero.';

    if (!$erros) {
        $sql = 'SELECT id FROM lavagens WHERE nome = :n COLLATE NOCASE' . ($editar ? ' AND id <> :id' : '');
        $st = db()->prepare($sql); $st->bindValue(':n', $dados['nome']);
        if ($editar) $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        if ($st->fetch()) $erros[] = 'Já existe um tipo de lavagem com esse nome.';
    }

    if (!$erros) {
        if ($editar) {
            db()->prepare('UPDATE lavagens SET nome=:nome, descricao=:descricao, preco_centavos=:preco_centavos, comissao_centavos=:comissao_centavos, paga_comissao=:paga_comissao, duracao_min=:duracao_min, ordem=:ordem, ativo=:ativo, atualizado_em=datetime(\'now\',\'localtime\') WHERE id=:id')
                ->execute(array_merge($dados, ['id' => $id]));
            flash('Lavagem atualizada.', 'sucesso');
        } else {
            db()->prepare('INSERT INTO lavagens (nome, descricao, preco_centavos, comissao_centavos, paga_comissao, duracao_min, ordem, ativo) VALUES (:nome, :descricao, :preco_centavos, :comissao_centavos, :paga_comissao, :duracao_min, :ordem, :ativo)')
                ->execute($dados);
            flash('Lavagem cadastrada.', 'sucesso');
        }
        header('Location: lavagens.php');
        exit;
    }
}

$titulo = $editar ? 'Editar lavagem' : 'Nova lavagem';
require __DIR__ . '/header.php';
?>

<div class="max-w-3xl mx-auto">
  <form method="post" class="bg-white rounded-2xl shadow-card border border-slate-200 p-6 sm:p-8 space-y-6" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-2xl font-bold text-slate-900"><?= $titulo ?></h1>
      <a href="lavagens.php" class="text-sm text-brand-600 hover:underline font-semibold">← Voltar</a>
    </div>

    <?php if ($erros): ?><div class="rounded-lg border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm font-semibold"><ul class="list-disc pl-5"><?php foreach ($erros as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <section class="grid gap-4 sm:grid-cols-6">
      <div class="sm:col-span-6">
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Nome do serviço *</label>
        <input name="nome" required maxlength="120" value="<?= e($dados['nome']) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black focus:outline-none focus:ring-2 focus:ring-brand-400">
      </div>
      <div class="sm:col-span-6">
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Descrição</label>
        <textarea name="descricao" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black focus:outline-none focus:ring-2 focus:ring-brand-400"><?= e($dados['descricao']) ?></textarea>
      </div>
      <div class="sm:col-span-3">
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Preço (R$)</label>
        <div class="relative flex items-center"><span class="absolute left-4 text-slate-500 font-bold z-10">R$</span><input name="preco" data-mask="money" inputmode="numeric" value="<?= e($dados['preco_centavos'] ? centavos_para_moeda((int)$dados['preco_centavos']) : '') ?>" class="w-full rounded-lg border border-slate-300 !pl-12 pr-3 py-2.5 font-semibold text-black focus:outline-none focus:ring-2 focus:ring-brand-400"></div>
      </div>
      
      <div class="sm:col-span-3">
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Duração estimada (min)</label>
        <input name="duracao_min" type="number" min="0" value="<?= (int)$dados['duracao_min'] ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black focus:outline-none focus:ring-2 focus:ring-brand-400">
      </div>

      <div class="sm:col-span-6 rounded-xl border border-sky-200 bg-sky-50 p-4">
        <label class="flex items-center gap-2 text-sm font-bold text-sky-900 cursor-pointer">
            <input type="checkbox" name="paga_comissao" value="1" <?= $dados['paga_comissao'] ? 'checked' : '' ?> class="w-4 h-4 rounded border-sky-300 text-sky-600 focus:ring-sky-500" onchange="document.getElementById('box_comissao').style.display = this.checked ? 'block' : 'none'">
            Este serviço paga comissão ao funcionário
        </label>
        
        <div id="box_comissao" class="mt-4 sm:w-1/2" style="<?= $dados['paga_comissao'] ? '' : 'display:none;' ?>">
            <label class="block text-xs font-bold text-sky-800 mb-1">Valor da Comissão (R$)</label>
            <div class="relative flex items-center"><span class="absolute left-4 text-slate-500 font-bold z-10">R$</span><input name="comissao" data-mask="money" inputmode="numeric" value="<?= e(isset($dados['comissao_centavos']) && $dados['comissao_centavos'] ? centavos_para_moeda((int)$dados['comissao_centavos']) : '') ?>" class="w-full rounded-lg border border-slate-300 !pl-12 pr-3 py-2.5 font-semibold text-black focus:outline-none focus:ring-2 focus:ring-brand-400"></div>
            <p class="text-[11px] text-sky-700 mt-1">Este valor será dividido se mais de um funcionário realizar o serviço.</p>
        </div>
      </div>

      <div class="sm:col-span-3">
        <label class="block text-sm font-bold text-slate-700 mb-1.5">Ordem de exibição</label>
        <input name="ordem" type="number" min="0" value="<?= (int)$dados['ordem'] ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 font-semibold text-black focus:outline-none focus:ring-2 focus:ring-brand-400">
      </div>

      <label class="flex items-center gap-2 text-sm font-semibold text-slate-800 sm:col-span-6 cursor-pointer mt-2">
        <input type="checkbox" name="ativo" value="1" <?= $dados['ativo'] ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-500 focus:ring-brand-400"> Serviço ativo
      </label>
    </section>

    <div class="flex justify-end gap-2 pt-4 border-t border-slate-200">
      <a href="lavagens.php" class="px-4 py-2.5 rounded-lg bg-slate-100 text-sm font-semibold text-slate-700">Cancelar</a>
      <button class="px-5 py-2.5 rounded-lg bg-brand-500 text-white font-semibold shadow-sm">Salvar</button>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  function formatarMoedaBR(v) { v=String(v||'').trim(); if(v==='')return''; if(v.includes(',')){let p=v.split(',');let i=p[0].replace(/\D/g,'')||'0';let d=(p[1].replace(/\D/g,'')+'00').slice(0,2);return i.replace(/\B(?=(\d{3})+(?!\d))/g,'.')+','+d;} const d=v.replace(/\D/g,''); if(d==='')return''; return d.replace(/\B(?=(\d{3})+(?!\d))/g,'.')+',00'; }
  document.querySelectorAll('[data-mask="money"]').forEach(el => {
    el.addEventListener('input', function() { this.value = this.value.replace(/[^\d.,]/g, ''); });
    el.addEventListener('blur', function() { this.value = formatarMoedaBR(this.value); });
    el.addEventListener('focus', function() { if (this.value === '0,00') this.value = ''; });
    if (el.value) el.value = formatarMoedaBR(el.value);
  });
});
</script>
<?php require __DIR__ . '/footer.php'; ?>
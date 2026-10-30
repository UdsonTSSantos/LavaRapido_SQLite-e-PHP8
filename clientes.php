<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_clientes();

// ==========================================
// LÓGICA DE BUSCA E PAGINAÇÃO
// ==========================================
$busca = trim($_GET['busca'] ?? '');
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$limite = 10;
$offset = ($pagina - 1) * $limite;

$where = "WHERE ativo = 1";
$params = [];

if ($busca !== '') {
    $where .= " AND (nome LIKE ? OR cpf_cnpj LIKE ? OR email LIKE ?)";
    $termo = "%{$busca}%";
    $params = [$termo, $termo, $termo];
}

// Conta o total de registros para a paginação
$stmtCount = db()->prepare("SELECT COUNT(*) FROM clientes $where");
$stmtCount->execute($params);
$totalRegistros = $stmtCount->fetchColumn();
$totalPaginas = ceil($totalRegistros / $limite);

// Busca os registros da página atual
$sql = "SELECT * FROM clientes $where ORDER BY nome ASC LIMIT $limite OFFSET $offset";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll();

$titulo = 'Clientes';
require __DIR__ . '/header.php';
?>

<div class="max-w-6xl mx-auto">
    
    <!-- CARD PRINCIPAL -->
    <div class="bg-white rounded-2xl shadow-card border border-slate-200 overflow-hidden">
        
        <!-- CABEÇALHO DO CARD (Título e Busca) -->
        <div class="p-5 sm:p-6 border-b border-slate-200 flex flex-wrap justify-between items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Clientes</h1>
                <p class="text-sm text-slate-500 mt-1"><?= $totalRegistros ?> registro(s) encontrado(s)</p>
            </div>
            
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <form method="get" class="flex flex-1 sm:flex-initial items-center gap-2">
                    <input type="text" name="busca" value="<?= e($busca) ?>" placeholder="Buscar por nome, documento ou e-mail" class="w-full sm:w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                    <button type="submit" class="rounded-lg bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 text-sm font-semibold transition">Buscar</button>
                    <?php if ($busca !== ''): ?>
                        <a href="clientes.php" class="text-sm text-slate-500 hover:text-rose-500 font-semibold ml-1" title="Limpar busca">X</a>
                    <?php endif; ?>
                </form>
                
                <a href="cliente_form.php" class="rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 text-sm font-bold shadow-sm transition whitespace-nowrap">
                    + Novo
                </a>
            </div>
        </div>

        <!-- TABELA -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-4">Nome</th>
                        <th class="px-6 py-4">CPF/CNPJ</th>
                        <th class="px-6 py-4">Contato</th>
                        <th class="px-6 py-4">Cidade/UF</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($clientes)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-slate-500">
                                Nenhum cliente encontrado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clientes as $c): ?>
                            <?php 
                                // Lógica de formatação do Celular
                                $celNumeros = preg_replace('/\D/', '', $c['celular'] ?? '');
                                if (strlen($celNumeros) == 11) {
                                    $celFormatado = '(' . substr($celNumeros, 0, 2) . ') ' . substr($celNumeros, 2, 5) . '-' . substr($celNumeros, 7);
                                } elseif (strlen($celNumeros) == 10) {
                                    $celFormatado = '(' . substr($celNumeros, 0, 2) . ') ' . substr($celNumeros, 2, 4) . '-' . substr($celNumeros, 6);
                                } else {
                                    $celFormatado = $c['celular'];
                                }

                                // Lógica de formatação do Telefone Fixo
                                $telNumeros = preg_replace('/\D/', '', $c['telefone'] ?? '');
                                if (strlen($telNumeros) == 11) {
                                    $telFormatado = '(' . substr($telNumeros, 0, 2) . ') ' . substr($telNumeros, 2, 5) . '-' . substr($telNumeros, 7);
                                } elseif (strlen($telNumeros) == 10) {
                                    $telFormatado = '(' . substr($telNumeros, 0, 2) . ') ' . substr($telNumeros, 2, 4) . '-' . substr($telNumeros, 6);
                                } else {
                                    $telFormatado = $c['telefone'];
                                }
                            ?>
                            <tr class="hover:bg-slate-50 transition group">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-sm text-slate-800"><?= e($c['nome']) ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    <?= !empty($c['cpf_cnpj']) ? e($c['cpf_cnpj']) : '<span class="text-slate-300">—</span>' ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    <div class="flex flex-col gap-0.5">
                                        <?php if (!empty($c['celular'])): ?>
                                            <span class="font-semibold text-slate-700"><?= $celFormatado ?></span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($c['telefone'])): ?>
                                            <span class="text-xs text-slate-500"><?= $telFormatado ?></span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($c['email'])): ?>
                                            <span class="text-xs text-slate-400"><?= e($c['email']) ?></span>
                                        <?php endif; ?>
                                        
                                        <?php if (empty($c['celular']) && empty($c['telefone']) && empty($c['email'])): ?>
                                            <span class="text-slate-300">—</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    <?php if (!empty($c['cidade'])): ?>
                                        <?= e($c['cidade']) ?><?= !empty($c['uf']) ? '/' . e($c['uf']) : '' ?>
                                    <?php else: ?>
                                        <span class="text-slate-300">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if ($c['ativo']): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">Ativo</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-3 text-xs font-semibold">
                                        <a href="cliente_historico.php?id=<?= $c['id'] ?>" class="text-slate-600 hover:text-brand-600">Histórico</a>
                                        <a href="cliente_form.php?id=<?= $c['id'] ?>" class="text-blue-600 hover:text-blue-800">Editar</a>
                                        <a href="cliente_excluir.php?id=<?= $c['id'] ?>" onclick="return confirm('Tem certeza que deseja excluir este cliente?')" class="text-rose-600 hover:text-rose-800">Excluir</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- RODAPÉ COM PAGINAÇÃO -->
        <?php if ($totalPaginas > 1): ?>
            <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between">
                <span class="text-sm text-slate-500">
                    Página <strong class="text-slate-700"><?= $pagina ?></strong> de <strong class="text-slate-700"><?= $totalPaginas ?></strong>
                </span>
                
                <div class="flex items-center gap-1">
                    <?php if ($pagina > 1): ?>
                        <a href="?busca=<?= urlencode($busca) ?>&pagina=<?= $pagina - 1 ?>" class="px-3 py-1.5 rounded border border-slate-300 bg-white hover:bg-slate-50 text-sm font-semibold text-slate-700 transition">Anterior</a>
                    <?php endif; ?>

                    <?php 
                    // Exibe apenas 5 botões de páginas ao redor da atual
                    $inicio = max(1, $pagina - 2);
                    $fim = min($totalPaginas, $pagina + 2);
                    for ($i = $inicio; $i <= $fim; $i++): 
                    ?>
                        <a href="?busca=<?= urlencode($busca) ?>&pagina=<?= $i ?>" class="px-3 py-1.5 rounded border text-sm font-semibold transition <?= $i === $pagina ? 'bg-brand-500 border-brand-500 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($pagina < $totalPaginas): ?>
                        <a href="?busca=<?= urlencode($busca) ?>&pagina=<?= $pagina + 1 ?>" class="px-3 py-1.5 rounded border border-slate-300 bg-white hover:bg-slate-50 text-sm font-semibold text-slate-700 transition">Próxima</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
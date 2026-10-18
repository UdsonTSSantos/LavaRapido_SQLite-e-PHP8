<?php
require_once __DIR__ . '/config.php';
exigir_login();
ensure_estoque();

$mensagens = [];
$erros = [];

// --- GERADOR DO ARQUIVO MODELO ---
if (isset($_GET['modelo'])) {
    header('Content-Type: application/xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="modelo_estoque.xml"');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<estoque>' . "\n";
    echo '  <item>' . "\n";
    echo '    <codigo>7891234567890</codigo>' . "\n";
    echo '    <nome>Shampoo Automotivo</nome>' . "\n";
    echo '    <quantidade>5</quantidade>' . "\n";
    echo '    <unidade>UN</unidade>' . "\n";
    echo '    <preco_custo>25.50</preco_custo>' . "\n";
    echo '    <preco_venda>45.00</preco_venda>' . "\n";
    echo '    <documento>NF-e 12345</documento>' . "\n";
    echo '  </item>' . "\n";
    echo '</estoque>';
    exit;
}

// --- PROCESSAMENTO DA IMPORTAÇÃO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xml_file'])) {
    csrf_validar();
    
    $file = $_FILES['xml_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $erros[] = 'Falha no upload do arquivo.';
    } elseif ($file['size'] > 2 * 1024 * 1024) {
        $erros[] = 'O arquivo excede o limite de 2MB.';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mime = mime_content_type($file['tmp_name']);
        
        if ($ext !== 'xml' || !in_array($mime, ['text/xml', 'application/xml', 'text/plain'])) {
            $erros[] = 'O arquivo deve ser um XML válido.';
        }
    }

    if (!$erros) {
        $xmlString = file_get_contents($file['tmp_name']);
        libxml_use_internal_errors(true);
        
        $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        
        if ($xml === false) {
            $erros[] = 'Não foi possível interpretar o XML. Verifique se o formato é válido.';
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            
            try {
                $importados = 0;
                $atualizados = 0;
                
                foreach ($xml->item as $node) {
                    $codigo  = trim((string)$node->codigo);
                    $nome    = trim((string)$node->nome);
                    $qtd     = (float)str_replace(',', '.', (string)$node->quantidade);
                    $unidade = trim((string)$node->unidade) ?: 'UN';
                    $custo   = (float)str_replace(',', '.', (string)$node->preco_custo);
                    $venda   = (float)str_replace(',', '.', (string)$node->preco_venda);
                    $doc     = trim((string)$node->documento) ?: 'XML Import';
                    
                    if ($codigo === '' || $nome === '') continue;
                    
                    $st = $pdo->prepare('SELECT id FROM estoque_itens WHERE codigo = ?');
                    $st->execute([$codigo]);
                    $itemId = $st->fetchColumn();
                    
                    if ($itemId) {
                        $pdo->prepare("
                            UPDATE estoque_itens 
                            SET nome = ?, preco_custo_centavos = ?, preco_venda_centavos = ?, atualizado_em = datetime('now','localtime')
                            WHERE id = ?
                        ")->execute([$nome, (int)round($custo * 100), (int)round($venda * 100), $itemId]);
                        $atualizados++;
                    } else {
                        $pdo->prepare("
                            INSERT INTO estoque_itens (codigo, nome, unidade, quantidade, preco_custo_centavos, preco_venda_centavos)
                            VALUES (?, ?, ?, 0, ?, ?)
                        ")->execute([$codigo, $nome, $unidade, (int)round($custo * 100), (int)round($venda * 100)]);
                        $itemId = (int)$pdo->lastInsertId();
                        $importados++;
                    }
                    
                    if ($qtd > 0) {
                        $u = usuario_logado();
                        $pdo->prepare("
                            INSERT INTO estoque_movimentacoes (item_id, tipo, quantidade, documento, observacao, criado_por)
                            VALUES (?, 'entrada', ?, ?, 'Importação via XML', ?)
                        ")->execute([$itemId, $qtd, $doc, (int)$u['id']]);
                        
                        $pdo->prepare("UPDATE estoque_itens SET quantidade = quantidade + ? WHERE id = ?")
                            ->execute([$qtd, $itemId]);
                    }
                }
                
                $pdo->commit();
                flash("Importação concluída! {$importados} itens criados e {$atualizados} itens atualizados.", 'sucesso');
                header('Location: estoque.php');
                exit;
                
            } catch (Throwable $e) {
                $pdo->rollBack();
                $erros[] = 'Erro ao salvar no banco: ' . $e->getMessage();
            }
        }
    }
}

$titulo = 'Importar Estoque via XML';
require __DIR__ . '/header.php';
?>

<div class="max-w-2xl mx-auto">
  <div class="bg-white rounded-2xl shadow-card border border-slate-200 p-6 sm:p-8">
    
    <div class="flex justify-between items-start mb-6">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Importar XML</h1>
        <p class="text-sm text-slate-500 mt-1">Faça a entrada de peças em lote de forma segura.</p>
      </div>
      <a href="estoque.php" class="text-sm text-brand-600 hover:underline font-semibold mt-1">← Voltar</a>
    </div>

    <?php if ($erros): ?>
      <div class="rounded-lg border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm font-semibold mb-6">
        <ul class="list-disc pl-5 space-y-1"><?php foreach ($erros as $e) echo "<li>$e</li>"; ?></ul>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="space-y-6">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      
      <div class="border-2 border-dashed border-slate-300 rounded-xl p-8 text-center hover:bg-slate-50 transition relative">
        <input type="file" name="xml_file" accept=".xml" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" required onchange="document.getElementById('file-name').textContent = this.files[0].name; document.getElementById('file-name').classList.add('text-brand-700', 'font-bold');">
        
        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-slate-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
        </svg>
        <span class="font-semibold text-brand-600 text-lg">Clique para selecionar o arquivo</span>
        <p id="file-name" class="text-sm text-slate-500 mt-2">Nenhum arquivo selecionado</p>
        <p class="text-xs text-slate-400 mt-4">Tamanho máximo: 2MB</p>
      </div>

      <!-- Ajuda amigável -->
      <div class="bg-brand-50 rounded-lg p-5 border border-brand-100 flex items-start gap-4">
        <div class="text-brand-500 mt-0.5">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-brand-800">Precisa do formato correto?</h3>
          <p class="text-sm text-brand-600 mt-1 mb-3">
            O arquivo precisa seguir o padrão de tags exigido pelo sistema para funcionar. Não sabe como montar?
          </p>
          <a href="estoque_xml.php?modelo=1" class="inline-flex items-center gap-2 text-sm font-bold text-brand-700 hover:text-brand-900 bg-white px-4 py-2 rounded-lg shadow-sm border border-brand-200 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
            Baixar Arquivo Modelo
          </a>
        </div>
      </div>

      <div class="pt-2">
        <button class="px-6 py-3.5 rounded-lg bg-brand-500 hover:bg-brand-700 text-white font-bold shadow-md w-full transition">
          Processar e Importar
        </button>
      </div>
    </form>
    
  </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
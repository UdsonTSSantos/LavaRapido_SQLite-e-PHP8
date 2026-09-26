<?php
require_once __DIR__ . '/config.php';

/* ---------- Conexão ---------- */
$pdo = db();

/* =========================================================
 *  TABELA: usuarios
 * ========================================================= */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        senha_hash TEXT NOT NULL,
        is_admin INTEGER NOT NULL DEFAULT 0,
        precisa_trocar_senha INTEGER NOT NULL DEFAULT 1,
        ativo INTEGER NOT NULL DEFAULT 1,
        criado_em TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    )
");
echo "✅ Tabela usuarios pronta.\n";

/* =========================================================
 *  TABELA: empresa (linha única, id=1)
 * ========================================================= */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS empresa (
        id INTEGER PRIMARY KEY CHECK (id = 1),
        razao_social  TEXT NOT NULL DEFAULT '',
        nome_fantasia TEXT NOT NULL DEFAULT '',
        cnpj          TEXT NOT NULL DEFAULT '',
        inscricao_estadual TEXT NOT NULL DEFAULT '',
        email         TEXT NOT NULL DEFAULT '',
        telefone      TEXT NOT NULL DEFAULT '',
        celular       TEXT NOT NULL DEFAULT '',
        cep           TEXT NOT NULL DEFAULT '',
        endereco      TEXT NOT NULL DEFAULT '',
        numero        TEXT NOT NULL DEFAULT '',
        complemento   TEXT NOT NULL DEFAULT '',
        bairro        TEXT NOT NULL DEFAULT '',
        cidade        TEXT NOT NULL DEFAULT '',
        uf            TEXT NOT NULL DEFAULT '',
        logo_path     TEXT NOT NULL DEFAULT '',
        atualizado_em TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    )
");
$pdo->exec("INSERT OR IGNORE INTO empresa (id) VALUES (1)");
echo "✅ Tabela empresa pronta.\n";

/* =========================================================
 *  TABELA: clientes
 * ========================================================= */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS clientes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tipo TEXT NOT NULL DEFAULT 'F',
        nome TEXT NOT NULL DEFAULT '',
        nome_fantasia TEXT NOT NULL DEFAULT '',
        cpf_cnpj TEXT NOT NULL DEFAULT '',
        rg_ie TEXT NOT NULL DEFAULT '',
        data_nascimento TEXT NOT NULL DEFAULT '',
        email TEXT NOT NULL DEFAULT '',
        telefone TEXT NOT NULL DEFAULT '',
        celular TEXT NOT NULL DEFAULT '',
        cep TEXT NOT NULL DEFAULT '',
        endereco TEXT NOT NULL DEFAULT '',
        numero TEXT NOT NULL DEFAULT '',
        complemento TEXT NOT NULL DEFAULT '',
        bairro TEXT NOT NULL DEFAULT '',
        cidade TEXT NOT NULL DEFAULT '',
        uf TEXT NOT NULL DEFAULT '',
        observacoes TEXT NOT NULL DEFAULT '',
        ativo INTEGER NOT NULL DEFAULT 1,
        criado_em TEXT NOT NULL DEFAULT (datetime('now','localtime')),
        atualizado_em TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    )
");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_clientes_nome ON clientes(nome)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_clientes_doc  ON clientes(cpf_cnpj)");
echo "✅ Tabela clientes pronta.\n";


/* =========================================================
 *  TABELA: fornecedores
 * ========================================================= */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS fornecedores (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tipo TEXT NOT NULL DEFAULT 'J',
        nome TEXT NOT NULL DEFAULT '',
        nome_fantasia TEXT NOT NULL DEFAULT '',
        cpf_cnpj TEXT NOT NULL DEFAULT '',
        rg_ie TEXT NOT NULL DEFAULT '',
        contato TEXT NOT NULL DEFAULT '',
        email TEXT NOT NULL DEFAULT '',
        telefone TEXT NOT NULL DEFAULT '',
        celular TEXT NOT NULL DEFAULT '',
        site TEXT NOT NULL DEFAULT '',
        cep TEXT NOT NULL DEFAULT '',
        endereco TEXT NOT NULL DEFAULT '',
        numero TEXT NOT NULL DEFAULT '',
        complemento TEXT NOT NULL DEFAULT '',
        bairro TEXT NOT NULL DEFAULT '',
        cidade TEXT NOT NULL DEFAULT '',
        uf TEXT NOT NULL DEFAULT '',
        banco TEXT NOT NULL DEFAULT '',
        agencia TEXT NOT NULL DEFAULT '',
        conta TEXT NOT NULL DEFAULT '',
        pix TEXT NOT NULL DEFAULT '',
        observacoes TEXT NOT NULL DEFAULT '',
        ativo INTEGER NOT NULL DEFAULT 1,
        criado_em TEXT NOT NULL DEFAULT (datetime('now','localtime')),
        atualizado_em TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    )
");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_fornecedores_nome ON fornecedores(nome)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_fornecedores_doc  ON fornecedores(cpf_cnpj)");
echo "✅ Tabela fornecedores pronta.\n";


/* =========================================================
 *  TABELA: lavagens (tipos de serviço)
 * ========================================================= */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS lavagens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT NOT NULL DEFAULT '',
        descricao TEXT NOT NULL DEFAULT '',
        preco_centavos INTEGER NOT NULL DEFAULT 0,
        duracao_min INTEGER NOT NULL DEFAULT 0,
        ativo INTEGER NOT NULL DEFAULT 1,
        ordem INTEGER NOT NULL DEFAULT 0,
        criado_em TEXT NOT NULL DEFAULT (datetime('now','localtime')),
        atualizado_em TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    )
");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_lavagens_nome  ON lavagens(nome)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_lavagens_ativo ON lavagens(ativo)");
echo "✅ Tabela lavagens pronta.\n";


/* =========================================================
 *  TABELA: veiculos (cache local do retorno da APIBrasil)
 * ========================================================= */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS veiculos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        placa TEXT NOT NULL UNIQUE,
        marca TEXT NOT NULL DEFAULT '',
        modelo TEXT NOT NULL DEFAULT '',
        ano_fabricacao TEXT NOT NULL DEFAULT '',
        ano_modelo TEXT NOT NULL DEFAULT '',
        cor TEXT NOT NULL DEFAULT '',
        combustivel TEXT NOT NULL DEFAULT '',
        chassi TEXT NOT NULL DEFAULT '',
        renavam TEXT NOT NULL DEFAULT '',
        origem TEXT NOT NULL DEFAULT 'manual',
        dados_json TEXT NOT NULL DEFAULT '',
        atualizado_em TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    )
");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_veiculos_placa ON veiculos(placa)");

/* =========================================================
 *  TABELA: lavagem_entradas
 * ========================================================= */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS lavagem_entradas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cliente_id INTEGER,
        placa TEXT NOT NULL DEFAULT '',
        veiculo_id INTEGER,
        veiculo_manual_marca TEXT NOT NULL DEFAULT '',
        veiculo_manual_modelo TEXT NOT NULL DEFAULT '',
        veiculo_manual_cor TEXT NOT NULL DEFAULT '',
        veiculo_manual_ano TEXT NOT NULL DEFAULT '',
        data_entrada TEXT NOT NULL,
        hora_entrada TEXT NOT NULL,
        previsao_saida_horas INTEGER NOT NULL DEFAULT 0,
        previsao_saida_datetime TEXT NOT NULL DEFAULT '',
        observacoes TEXT NOT NULL DEFAULT '',
        avarias TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'aberta',
        subtotal_centavos INTEGER NOT NULL DEFAULT 0,
        desconto_centavos INTEGER NOT NULL DEFAULT 0,
        desconto_percentual INTEGER NOT NULL DEFAULT 0,
        total_centavos INTEGER NOT NULL DEFAULT 0,
        pago INTEGER NOT NULL DEFAULT 0,
        criado_por INTEGER,
        criado_em TEXT NOT NULL DEFAULT (datetime('now','localtime')),
        atualizado_em TEXT NOT NULL DEFAULT (datetime('now','localtime')),
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
        FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE SET NULL
    )
");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_entradas_placa   ON lavagem_entradas(placa)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_entradas_cliente ON lavagem_entradas(cliente_id)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_entradas_data    ON lavagem_entradas(data_entrada)");

/* =========================================================
 *  TABELA: lavagem_itens (serviços escolhidos por entrada)
 * ========================================================= */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS lavagem_itens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        entrada_id INTEGER NOT NULL,
        lavagem_id INTEGER NOT NULL,
        nome TEXT NOT NULL DEFAULT '',
        preco_centavos INTEGER NOT NULL DEFAULT 0,
        FOREIGN KEY (entrada_id) REFERENCES lavagem_entradas(id) ON DELETE CASCADE,
        FOREIGN KEY (lavagem_id) REFERENCES lavagens(id)
    )
");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_itens_entrada ON lavagem_itens(entrada_id)");

/* =========================================================
 *  TABELA: pagamentos
 * ========================================================= */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS pagamentos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        entrada_id INTEGER NOT NULL,
        cliente_id INTEGER,
        placa TEXT NOT NULL DEFAULT '',
        data_pagamento TEXT NOT NULL,
        valor_centavos INTEGER NOT NULL DEFAULT 0,
        forma_pagamento TEXT NOT NULL DEFAULT '',
        observacao TEXT NOT NULL DEFAULT '',
        recibo_numero TEXT NOT NULL DEFAULT '',
        criado_por INTEGER,
        criado_em TEXT NOT NULL DEFAULT (datetime('now','localtime')),
        FOREIGN KEY (entrada_id) REFERENCES lavagem_entradas(id) ON DELETE CASCADE,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
    )
");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_pagamentos_entrada ON pagamentos(entrada_id)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_pagamentos_data    ON pagamentos(data_pagamento)");

echo "✅ Tabelas veiculos, lavagem_entradas, lavagem_itens e pagamentos prontas.\n";


/* =========================================================
 *  ADMIN PADRÃO
 * ========================================================= */
$email = 'suporte@ast7.com.br';
$senha = 'P4v@1H:3n#9r2B';

$stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
$stmt->execute([$email]);

if (!$stmt->fetch()) {
    $pdo->prepare(
        'INSERT INTO usuarios (email, senha_hash, is_admin, precisa_trocar_senha, ativo)
         VALUES (?, ?, 1, 0, 1)'
    )->execute([$email, password_hash($senha, PASSWORD_DEFAULT)]);
    echo "✅ Administrador criado: {$email}\n";
} else {
    echo "ℹ️  Administrador já existe.\n";
}

/* =========================================================
 *  PASTA DE UPLOADS
 * ========================================================= */
$dir = __DIR__ . '/uploads/logos';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
    echo "✅ Pasta uploads/logos criada.\n";
} else {
    echo "ℹ️  Pasta uploads/logos já existe.\n";
}

/* =========================================================
 *  FIM
 * ========================================================= */
echo "\n🎉 Instalação concluída. Acesse o sistema.\n";
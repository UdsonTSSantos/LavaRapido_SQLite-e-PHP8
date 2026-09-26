/** Garante as tabelas necessárias e migra colunas novas. */
function ensure_entradas(): void {
    static $ok = false;
    if ($ok) return;

    $pdo = db();

    /* ---------- Cria tabelas se não existirem ---------- */
    $tabelas = [
        'veiculos' => "
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
            )",
        'lavagem_entradas' => "
            CREATE TABLE IF NOT EXISTS lavagem_entradas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cliente_id INTEGER,
                cliente_nome_avulso TEXT NOT NULL DEFAULT '',
                cliente_celular TEXT NOT NULL DEFAULT '',
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
                sms_enviado INTEGER NOT NULL DEFAULT 0,
                sms_enviado_em TEXT NOT NULL DEFAULT '',
                criado_por INTEGER,
                criado_em TEXT NOT NULL DEFAULT (datetime('now','localtime')),
                atualizado_em TEXT NOT NULL DEFAULT (datetime('now','localtime'))
            )",
        'lavagem_itens' => "
            CREATE TABLE IF NOT EXISTS lavagem_itens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entrada_id INTEGER NOT NULL,
                lavagem_id INTEGER NOT NULL,
                nome TEXT NOT NULL DEFAULT '',
                preco_centavos INTEGER NOT NULL DEFAULT 0
            )",
        'pagamentos' => "
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
                criado_em TEXT NOT NULL DEFAULT (datetime('now','localtime'))
            )",
    ];

    foreach ($tabelas as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $ex) {
            if (!str_contains($ex->getMessage(), 'locked')) throw $ex;
            usleep(300000);
            try { $pdo->exec($sql); } catch (PDOException $e2) { throw $e2; }
        }
    }

    /* ---------- Migração: garante as colunas novas ---------- */
    // Sempre tenta adicionar cada coluna; se já existir, SQLite reclama
    // com "duplicate column name" e nós ignoramos. Isso é mais robusto
    // do que checar PRAGMA, porque funciona mesmo se a tabela foi criada
    // por uma versão antiga em outro momento.
    $novas = [
        'cliente_nome_avulso' => "TEXT NOT NULL DEFAULT ''",
        'cliente_celular'     => "TEXT NOT NULL DEFAULT ''",
        'sms_enviado'         => "INTEGER NOT NULL DEFAULT 0",
        'sms_enviado_em'      => "TEXT NOT NULL DEFAULT ''",
    ];
    foreach ($novas as $col => $def) {
        try { $pdo->exec("ALTER TABLE lavagem_entradas ADD COLUMN $col $def"); }
        catch (PDOException $ex) {
            $msg = $ex->getMessage();
            if (!str_contains($msg, 'duplicate column') && !str_contains($msg, 'locked')) {
                throw $ex;
            }
        }
    }

    $ok = true;
}
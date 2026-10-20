<?php
declare(strict_types=1);

/* =========================================================
 *  HELPERS BÁSICOS
 * ========================================================= */

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function validar_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validar_senha(string $senha): array {
    $erros = [];
    if (mb_strlen($senha) < 8)             $erros[] = 'A senha deve ter no mínimo 8 caracteres.';
    if (!preg_match('/\d/', $senha))       $erros[] = 'A senha deve conter ao menos um número.';
    if (!preg_match('/[A-Za-z]/', $senha)) $erros[] = 'A senha deve conter ao menos uma letra.';
    return $erros;
}

/* =========================================================
 *  SESSÃO / AUTENTICAÇÃO / CSRF
 * ========================================================= */

function usuario_logado(): ?array {
    if (empty($_SESSION['usuario_id'])) return null;
    $stmt = db()->prepare('SELECT id, email, is_admin, precisa_trocar_senha, ativo FROM usuarios WHERE id = ?');
    $stmt->execute([$_SESSION['usuario_id']]);
    $u = $stmt->fetch();
    if (!$u || !$u['ativo']) {
        session_destroy();
        return null;
    }
    return $u;
}

function exigir_login(): array {
    $u = usuario_logado();
    if (!$u) { header('Location: index.php'); exit; }

    $atual = basename($_SERVER['PHP_SELF'] ?? '');
    if ($u['precisa_trocar_senha'] && !in_array($atual, ['trocar_senha.php', 'logout.php'], true)) {
        header('Location: trocar_senha.php');
        exit;
    }
    return $u;
}

function exigir_admin(): array {
    $u = exigir_login();
    if (!$u['is_admin']) { http_response_code(403); exit('Acesso restrito ao administrador.'); }
    return $u;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_validar(): void {
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        exit('Sessão expirada. Recarregue a página.');
    }
}

function flash(?string $msg = null, string $tipo = 'info'): ?array {
    if ($msg !== null) { $_SESSION['flash'] = ['msg' => $msg, 'tipo' => $tipo]; return null; }
    if (!empty($_SESSION['flash'])) { $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}

/* =========================================================
 *  PERMISSÕES DE ACESSO
 * ========================================================= */

function modulos_sistema(): array {
    return [
        'dashboard'      => 'Dashboard',
        'clientes'       => 'Clientes',
        'lavagens'       => 'Lavagens',
        'entradas'       => 'Entradas',
        'fornecedores'   => 'Fornecedores',
        'pagamentos'     => 'Pagamentos',
        'administrativo' => 'Administrativo',
        'estoque'        => 'Estoque',
        'comissoes'      => 'Comissões',
    ];
}

function ensure_permissoes_coluna(): void {
    static $ok = false;
    if ($ok) return;

    ensure_usuarios();

    $cols = db()->query("PRAGMA table_info(usuarios)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('permissoes', $cols, true)) {
        try {
            db()->exec("ALTER TABLE usuarios ADD COLUMN permissoes TEXT NOT NULL DEFAULT ''");
        } catch (Throwable $e) { /* já existe */ }
    }
    $ok = true;
}

function permissoes_usuario(int $userId): array {
    ensure_permissoes_coluna();

    $st = db()->prepare('SELECT is_admin, permissoes FROM usuarios WHERE id = ?');
    $st->execute([$userId]);
    $row = $st->fetch();
    if (!$row) return [];

    if ((int)$row['is_admin'] === 1) return array_keys(modulos_sistema());

    $raw = trim((string)($row['permissoes'] ?? ''));
    if ($raw === '') return [];

    $arr = json_decode($raw, true);
    if (!is_array($arr)) {
        $arr = array_filter(array_map('trim', explode(',', $raw)));
    }

    $validos = array_keys(modulos_sistema());
    return array_values(array_intersect($arr, $validos));
}

function tem_permissao(string $modulo): bool {
    $u = usuario_logado();
    if (!$u) return false;
    if ((int)$u['is_admin'] === 1) return true;

    if (!isset($_SESSION['__permissoes_cache'])) {
        $_SESSION['__permissoes_cache'] = permissoes_usuario((int)$u['id']);
    }
    return in_array($modulo, $_SESSION['__permissoes_cache'], true);
}

function exigir_permissao(string $modulo): void {
    if (!tem_permissao($modulo)) {
        flash('Você não tem permissão para acessar este módulo.', 'erro');
        header('Location: dashboard.php');
        exit;
    }
}

function limpar_cache_permissoes(): void {
    unset($_SESSION['__permissoes_cache']);
}

/* =========================================================
 *  EMPRESA
 * ========================================================= */

function empresa(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    if (empty($_SESSION['tenant_db'])) {
        try { $pdo = db(); } catch (Throwable $e) { return $cache = []; }
    } else {
        $pdo = db();
    }

    $existe = $pdo->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='empresa'"
    )->fetchColumn();

    if (!$existe) {
        $pdo->exec("
            CREATE TABLE empresa (
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
        $pdo->exec("INSERT INTO empresa (id) VALUES (1)");
    } else {
        $pdo->exec("INSERT OR IGNORE INTO empresa (id) VALUES (1)");
    }

    $cache = $pdo->query('SELECT * FROM empresa WHERE id = 1')->fetch() ?: [];
    return $cache;
}

function empresa_logo_url(): ?string {
    $e = empresa();
    if (empty($e['logo_path'])) return null;
    $abs = APP_ROOT . '/' . $e['logo_path'];
    if (!is_file($abs)) return null;
    return $e['logo_path'] . '?v=' . filemtime($abs);
}

/* =========================================================
 *  VALIDAÇÕES E FORMATAÇÕES (CPF, CNPJ, CEP, PLACA)
 * ========================================================= */

function validar_cnpj(string $cnpj): bool {
    $cnpj = preg_replace('/\D/', '', $cnpj);
    if (strlen($cnpj) !== 14) return false;
    if (preg_match('/^(\d)\1{13}$/', $cnpj)) return false;
    for ($t = 12; $t < 14; $t++) {
        $d = 0; $c = 0;
        for ($i = $t - 1; $i >= 0; $i--) {
            $d += (int)$cnpj[$i] * (($c % 8) + 2);
            $c++;
        }
        $d = 11 - ($d % 11);
        if ($d >= 10) $d = 0;
        if ((int)$cnpj[$t] !== $d) return false;
    }
    return true;
}

function formatar_cnpj(string $cnpj): string {
    $c = preg_replace('/\D/', '', $cnpj);
    if (strlen($c) !== 14) return $cnpj;
    return substr($c,0,2).'.'.substr($c,2,3).'.'.substr($c,5,3)
         . '/'.substr($c,8,4).'-'.substr($c,12,2);
}

function validar_cpf(string $cpf): bool {
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) !== 11) return false;
    if (preg_match('/^(\d)\1{10}$/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($i = 0; $i < $t; $i++) {
            $d += (int)$cpf[$i] * (($t + 1) - $i);
        }
        $d = ((10 * $d) % 11) % 10;
        if ((int)$cpf[$t] !== $d) return false;
    }
    return true;
}

function formatar_cpf(string $cpf): string {
    $c = preg_replace('/\D/', '', $cpf);
    if (strlen($c) !== 11) return $cpf;
    return substr($c,0,3).'.'.substr($c,3,3).'.'.substr($c,6,3).'-'.substr($c,9,2);
}

function validar_cpf_cnpj(string $doc, string $tipo): bool {
    return $tipo === 'J' ? validar_cnpj($doc) : validar_cpf($doc);
}

function formatar_cpf_cnpj(string $doc, string $tipo): string {
    return $tipo === 'J' ? formatar_cnpj($doc) : formatar_cpf($doc);
}

function formatar_cep(string $cep): string {
    $c = preg_replace('/\D/', '', $cep);
    if (strlen($c) !== 8) return $cep;
    return substr($c, 0, 5) . '-' . substr($c, 5, 2);
}

function normalizar_placa(string $placa): string {
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $placa));
}

function validar_placa(string $placa): bool {
    $p = normalizar_placa($placa);
    return (bool)preg_match('/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/', $p);
}

function formatar_placa(string $placa): string {
    $p = normalizar_placa($placa);
    if (strlen($p) !== 7) return $placa;
    return substr($p, 0, 3) . '-' . substr($p, 3);
}

function formatar_doc_usuario(string $doc): string {
    $d = preg_replace('/\D/', '', $doc);
    if (strlen($d) === 11) return formatar_cpf($d);
    if (strlen($d) === 14) return formatar_cnpj($d);
    return $doc;
}

function formatar_fone_usuario(string $fone): string {
    $f = preg_replace('/\D/', '', $fone);
    if (strlen($f) === 11) return '(' . substr($f,0,2) . ') ' . substr($f,2,5) . '-' . substr($f,7);
    if (strlen($f) === 10) return '(' . substr($f,0,2) . ') ' . substr($f,2,4) . '-' . substr($f,6);
    return $fone;
}

/* =========================================================
 *  MOEDA
 * ========================================================= */

function moeda_para_centavos(?string $s): int {
    if ($s === null) return 0;
    $s = preg_replace('/[^\d,\.]/', '', $s);
    if (str_contains($s, ',')) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    }
    $f = (float)$s;
    return (int)round($f * 100);
}

function centavos_para_moeda(int $c): string {
    return number_format($c / 100, 2, ',', '.');
}

function centavos_para_moeda_brl(int $c): string {
    return 'R$ ' . centavos_para_moeda($c);
}

/* =========================================================
 *  USUÁRIOS & SENHAS
 * ========================================================= */

function ensure_usuarios(): void {
    static $ok = false;
    if ($ok) return;

    $pdo = db();

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

    $cols = $pdo->query("PRAGMA table_info(usuarios)")->fetchAll(PDO::FETCH_COLUMN, 1);
    $novas = [
        'nome'    => "TEXT NOT NULL DEFAULT ''",
        'cpf'     => "TEXT NOT NULL DEFAULT ''",
        'celular' => "TEXT NOT NULL DEFAULT ''",
    ];
    foreach ($novas as $col => $def) {
        if (!in_array($col, $cols, true)) {
            try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN $col $def"); }
            catch (PDOException $ex) {
                if (!str_contains($ex->getMessage(), 'duplicate column')) throw $ex;
            }
        }
    }
    $ok = true;
}

function usuario_completo(int $id): ?array {
    ensure_usuarios();
    $st = db()->prepare('SELECT * FROM usuarios WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function listar_usuarios(): array {
    ensure_usuarios();
    return db()->query(
        'SELECT * FROM usuarios ORDER BY nome COLLATE NOCASE, email COLLATE NOCASE'
    )->fetchAll();
}

function gerar_senha_temporaria(int $tamanho = 10): string {
    if ($tamanho < 8) $tamanho = 8;
    $minusculas = 'abcdefghjkmnpqrstuvwxyz';
    $maiusculas = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $numeros    = '23456789';
    $especiais  = '!@#$%&*';

    $senha = [
        $minusculas[random_int(0, strlen($minusculas) - 1)],
        $maiusculas[random_int(0, strlen($maiusculas) - 1)],
        $numeros   [random_int(0, strlen($numeros)    - 1)],
        $especiais [random_int(0, strlen($especiais)  - 1)],
    ];

    $todos = $minusculas . $maiusculas . $numeros . $especiais;
    while (count($senha) < $tamanho) {
        $senha[] = $todos[random_int(0, strlen($todos) - 1)];
    }
    shuffle($senha);
    return implode('', $senha);
}

/* =========================================================
 *  AUTENTICAÇÃO
 * ========================================================= */

function autenticar(string $email, string $senha): array {
    $email = trim($email);
    if ($email === '' || $senha === '') {
        return ['ok' => false, 'erro' => 'Informe usuário e senha.'];
    }
    if (defined('AUTH_MODE') && AUTH_MODE === 'api' && AUTH_API_URL !== '') {
        return autenticar_via_api($email, $senha);
    }
    return autenticar_local($email, $senha);
}

function autenticar_local(string $email, string $senha): array {
    ensure_usuarios();
    $stmt = db()->prepare('SELECT * FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    if (!$u)                                        return ['ok' => false, 'erro' => 'Credenciais inválidas.'];
    if (!(int)$u['ativo'])                          return ['ok' => false, 'erro' => 'Usuário inativo. Procure o administrador.'];
    if (!password_verify($senha, $u['senha_hash'])) return ['ok' => false, 'erro' => 'Credenciais inválidas.'];

    return [
        'ok' => true,
        'usuario' => [
            'id'                   => (int)$u['id'],
            'email'                => (string)$u['email'],
            'nome'                 => (string)($u['nome'] ?? ''),
            'is_admin'             => (int)$u['is_admin'],
            'precisa_trocar_senha' => (int)$u['precisa_trocar_senha'],
        ],
    ];
}

function autenticar_via_api(string $email, string $senha): array {
    $url = rtrim(AUTH_API_URL, '/') . '/auth/login';
    $payload = json_encode(['email' => $email, 'senha' => $senha], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => defined('AUTH_API_TIMEOUT') ? AUTH_API_TIMEOUT : 8,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Api-Key: ' . AUTH_API_KEY,
        ],
    ]);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false) return ['ok' => false, 'erro' => 'Serviço de autenticação indisponível.'];
    if ($http === 401)   return ['ok' => false, 'erro' => 'Credenciais inválidas.'];
    if ($http >= 500)    return ['ok' => false, 'erro' => 'Serviço de autenticação indisponível.'];
    if ($http !== 200)   return ['ok' => false, 'erro' => "Erro inesperado ({$http})."];

    $json = json_decode($body, true);
    if (!is_array($json) || empty($json['ok']) || empty($json['usuario'])) {
        return ['ok' => false, 'erro' => $json['erro'] ?? 'Resposta inválida.'];
    }

    $u = $json['usuario'];
    return [
        'ok' => true,
        'usuario' => [
            'id'                   => (int)($u['id'] ?? 0),
            'email'                => (string)($u['email'] ?? $email),
            'nome'                 => (string)($u['nome'] ?? ''),
            'is_admin'             => (int)($u['is_admin'] ?? 0),
            'precisa_trocar_senha' => (int)($u['precisa_trocar'] ?? 0),
        ],
        'token' => $json['token'] ?? '',
    ];
}

function sincronizar_usuario_local(array $u): array {
    if (empty($u['email'])) return $u;
    ensure_usuarios();

    $st = db()->prepare('SELECT id FROM usuarios WHERE email = ?');
    $st->execute([$u['email']]);
    $existente = $st->fetchColumn();

    if ($existente) {
        db()->prepare("UPDATE usuarios SET nome=?, is_admin=?, precisa_trocar_senha=?, ativo=1 WHERE id=?")
           ->execute([$u['nome'] ?? '', $u['is_admin'] ?? 0, $u['precisa_trocar_senha'] ?? 0, $existente]);
        $u['id'] = (int)$existente;
        return $u;
    }

    db()->prepare("INSERT INTO usuarios (email, senha_hash, is_admin, precisa_trocar_senha, ativo, nome, cpf, celular) VALUES (?, ?, ?, ?, 1, ?, '', '')")
       ->execute([
           $u['email'], password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
           $u['is_admin'] ?? 0, $u['precisa_trocar_senha'] ?? 0, $u['nome'] ?? '',
       ]);
    $u['id'] = (int)db()->lastInsertId();
    return $u;
}

/* =========================================================
 *  CLIENTES
 * ========================================================= */

function ensure_clientes(): void {
    static $ok = false;
    if ($ok) return;

    $pdo = db();
    $existe = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='clientes'")->fetchColumn();

    if (!$existe) {
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
    }
    $ok = true;
}

function buscar_cliente(int $id): ?array {
    ensure_clientes();
    $stmt = db()->prepare('SELECT * FROM clientes WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/* =========================================================
 *  FORNECEDORES
 * ========================================================= */

function ensure_fornecedores(): void {
    static $ok = false;
    if ($ok) return;

    $pdo = db();
    $existe = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='fornecedores'")->fetchColumn();

    if (!$existe) {
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
    }
    $ok = true;
}

function buscar_fornecedor(int $id): ?array {
    ensure_fornecedores();
    $stmt = db()->prepare('SELECT * FROM fornecedores WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/* =========================================================
 *  LAVAGENS (Catálogo de Serviços)
 * ========================================================= */

function ensure_lavagens(): void {
    static $ok = false;
    if ($ok) return;

    $pdo = db();
    $existe = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='lavagens'")->fetchColumn();

    if (!$existe) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lavagens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL DEFAULT '',
                descricao TEXT NOT NULL DEFAULT '',
                preco_centavos INTEGER NOT NULL DEFAULT 0,
                comissao_centavos INTEGER NOT NULL DEFAULT 0,
                paga_comissao INTEGER NOT NULL DEFAULT 1,
                duracao_min INTEGER NOT NULL DEFAULT 0,
                ativo INTEGER NOT NULL DEFAULT 1,
                ordem INTEGER NOT NULL DEFAULT 0,
                criado_em TEXT NOT NULL DEFAULT (datetime('now','localtime')),
                atualizado_em TEXT NOT NULL DEFAULT (datetime('now','localtime'))
            )
        ");
    } else {
        $cols = $pdo->query("PRAGMA table_info(lavagens)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('comissao_centavos', $cols, true)) {
            try { $pdo->exec("ALTER TABLE lavagens ADD COLUMN comissao_centavos INTEGER NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
        }
        if (!in_array('paga_comissao', $cols, true)) {
            try { $pdo->exec("ALTER TABLE lavagens ADD COLUMN paga_comissao INTEGER NOT NULL DEFAULT 1"); } catch (Throwable $e) {}
        }
    }
    
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_lavagens_nome  ON lavagens(nome)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_lavagens_ativo ON lavagens(ativo)");
    
    $ok = true;
}

function listar_lavagens_ativas(): array {
    ensure_lavagens();
    return db()->query(
        'SELECT * FROM lavagens WHERE ativo = 1 ORDER BY ordem, nome COLLATE NOCASE'
    )->fetchAll();
}

function buscar_lavagem(int $id): ?array {
    ensure_lavagens();
    $stmt = db()->prepare('SELECT * FROM lavagens WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/* =========================================================
 *  ENTRADAS / VEÍCULOS
 * ========================================================= */

function ensure_entradas(): void {
    static $ok = false;
    if ($ok) return;

    $pdo = db();

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

    $pdo->exec("
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
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lavagem_itens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entrada_id INTEGER NOT NULL,
            lavagem_id INTEGER NOT NULL,
            nome TEXT NOT NULL DEFAULT '',
            preco_centavos INTEGER NOT NULL DEFAULT 0
        )
    ");

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
            criado_em TEXT NOT NULL DEFAULT (datetime('now','localtime'))
        )
    ");

    $cols = $pdo->query("PRAGMA table_info(lavagem_entradas)")->fetchAll(PDO::FETCH_COLUMN, 1);
    $novas = [
        'cliente_nome_avulso' => "TEXT NOT NULL DEFAULT ''",
        'cliente_celular'     => "TEXT NOT NULL DEFAULT ''",
        'sms_enviado'         => "INTEGER NOT NULL DEFAULT 0",
        'sms_enviado_em'      => "TEXT NOT NULL DEFAULT ''",
    ];
    foreach ($novas as $col => $def) {
        if (!in_array($col, $cols, true)) {
            try { $pdo->exec("ALTER TABLE lavagem_entradas ADD COLUMN $col $def"); }
            catch (PDOException $ex) {
                if (!str_contains($ex->getMessage(), 'duplicate column')) throw $ex;
            }
        }
    }
    $ok = true;
}

function ensure_lavagem_veiculos(): void {
    static $ok = false;
    if ($ok) return;

    $pdo = db();
    ensure_entradas();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lavagem_veiculos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entrada_id INTEGER NOT NULL,
            ordem INTEGER NOT NULL DEFAULT 1,
            placa TEXT NOT NULL DEFAULT '',
            veiculo_id INTEGER,
            manual_marca TEXT NOT NULL DEFAULT '',
            manual_modelo TEXT NOT NULL DEFAULT '',
            manual_cor TEXT NOT NULL DEFAULT '',
            manual_ano TEXT NOT NULL DEFAULT '',
            criado_em TEXT NOT NULL DEFAULT (datetime('now','localtime'))
        )
    ");

    $cols = $pdo->query("PRAGMA table_info(lavagem_itens)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('veiculo_id', $cols, true)) {
        try { $pdo->exec("ALTER TABLE lavagem_itens ADD COLUMN veiculo_id INTEGER"); }
        catch (Throwable $e) { /* já existe */ }
    }

    $rows = $pdo->query("
        SELECT e.id, e.placa, e.veiculo_id, e.veiculo_manual_marca, e.veiculo_manual_modelo, e.veiculo_manual_cor, e.veiculo_manual_ano
        FROM lavagem_entradas e
        WHERE NOT EXISTS (SELECT 1 FROM lavagem_veiculos v WHERE v.entrada_id = e.id)
    ")->fetchAll();

    if ($rows) {
        $insV = $pdo->prepare("
            INSERT INTO lavagem_veiculos (entrada_id, ordem, placa, veiculo_id, manual_marca, manual_modelo, manual_cor, manual_ano)
            VALUES (?, 1, ?, ?, ?, ?, ?, ?)
        ");
        $updI = $pdo->prepare("UPDATE lavagem_itens SET veiculo_id = ? WHERE entrada_id = ? AND veiculo_id IS NULL");

        foreach ($rows as $r) {
            $insV->execute([
                (int)$r['id'], $r['placa'], $r['veiculo_id'],
                $r['veiculo_manual_marca'], $r['veiculo_manual_modelo'],
                $r['veiculo_manual_cor'],   $r['veiculo_manual_ano'],
            ]);
            $vid = (int)$pdo->lastInsertId();
            $updI->execute([$vid, (int)$r['id']]);
        }
    }
    $ok = true;
}

function ensure_lavagem_pecas(): void {
    static $ok = false;
    if ($ok) return;

    $pdo = db();
    ensure_entradas();
    ensure_estoque();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lavagem_pecas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entrada_id INTEGER NOT NULL,
            veiculo_id INTEGER NOT NULL,
            estoque_item_id INTEGER NOT NULL,
            nome TEXT NOT NULL DEFAULT '',
            quantidade REAL NOT NULL DEFAULT 1,
            preco_unitario_centavos INTEGER NOT NULL DEFAULT 0,
            desconto_centavos INTEGER NOT NULL DEFAULT 0,
            total_centavos INTEGER NOT NULL DEFAULT 0
        )
    ");
    $ok = true;
}

function ensure_comissoes(): void {
    static $ok = false;
    if ($ok) return;

    $pdo = db();
    ensure_lavagens();
    ensure_entradas();

    $colsLav = $pdo->query("PRAGMA table_info(lavagens)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('paga_comissao', $colsLav, true)) {
        try { $pdo->exec("ALTER TABLE lavagens ADD COLUMN paga_comissao INTEGER NOT NULL DEFAULT 1"); }
        catch (Throwable $e) {}
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS comissoes_lancadas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entrada_id INTEGER NOT NULL,
            item_id INTEGER NOT NULL,
            usuario_id INTEGER NOT NULL,
            nome_lavagem TEXT NOT NULL DEFAULT '',
            valor_centavos INTEGER NOT NULL DEFAULT 0,
            data_entrada TEXT NOT NULL,
            pago INTEGER NOT NULL DEFAULT 0
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_comissoes_user ON comissoes_lancadas(usuario_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_comissoes_data ON comissoes_lancadas(data_entrada)");

    $ok = true;
}

function listar_veiculos_entrada(int $entradaId): array {
    ensure_lavagem_veiculos();
    $st = db()->prepare("
        SELECT v.*, (SELECT COALESCE(SUM(i.preco_centavos),0) FROM lavagem_itens i WHERE i.veiculo_id = v.id) AS subtotal
        FROM lavagem_veiculos v
        WHERE v.entrada_id = ?
        ORDER BY v.ordem, v.id
    ");
    $st->execute([$entradaId]);
    return $st->fetchAll();
}

function buscar_veiculo_por_placa(string $placa): ?array {
    $placa = normalizar_placa($placa);
    $st = db()->prepare('SELECT * FROM veiculos WHERE placa = ?');
    $st->execute([$placa]);
    return $st->fetch() ?: null;
}

function contar_lavagens_mes(string $placa, ?int $ano = null, ?int $mes = null): int {
    ensure_entradas();
    $ano = $ano ?? (int)date('Y');
    $mes = $mes ?? (int)date('n');
    $st = db()->prepare("
        SELECT COUNT(*) FROM lavagem_entradas
         WHERE placa = :placa
           AND CAST(strftime('%Y', data_entrada) AS INTEGER) = :ano
           AND CAST(strftime('%m', data_entrada) AS INTEGER) = :mes
    ");
    $st->execute([':placa' => normalizar_placa($placa), ':ano' => $ano, ':mes' => $mes]);
    return (int)$st->fetchColumn();
}

function contar_lavagens_total(string $placa): int {
    ensure_entradas();
    $st = db()->prepare('SELECT COUNT(*) FROM lavagem_entradas WHERE placa = ?');
    $st->execute([normalizar_placa($placa)]);
    return (int)$st->fetchColumn();
}

/* =========================================================
 *  ESTOQUE
 * ========================================================= */

function ensure_estoque(): void {
    static $ok = false;
    if ($ok) return;

    $pdo = db();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS estoque_itens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            codigo TEXT NOT NULL UNIQUE,
            nome TEXT NOT NULL DEFAULT '',
            unidade TEXT NOT NULL DEFAULT 'UN',
            quantidade REAL NOT NULL DEFAULT 0,
            estoque_minimo REAL NOT NULL DEFAULT 0,
            preco_custo_centavos INTEGER NOT NULL DEFAULT 0,
            preco_venda_centavos INTEGER NOT NULL DEFAULT 0,
            ativo INTEGER NOT NULL DEFAULT 1,
            criado_em TEXT NOT NULL DEFAULT (datetime('now','localtime')),
            atualizado_em TEXT NOT NULL DEFAULT (datetime('now','localtime'))
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS estoque_movimentacoes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            item_id INTEGER NOT NULL,
            tipo TEXT NOT NULL,
            quantidade REAL NOT NULL,
            documento TEXT NOT NULL DEFAULT '',
            observacao TEXT NOT NULL DEFAULT '',
            criado_por INTEGER,
            criado_em TEXT NOT NULL DEFAULT (datetime('now','localtime'))
        )
    ");
    $ok = true;
}

function buscar_item_estoque(int $id): ?array {
    ensure_estoque();
    $st = db()->prepare('SELECT * FROM estoque_itens WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function movimentar_estoque(int $itemId, string $tipo, float $qtd, string $documento = '', string $obs = ''): void {
    if ($qtd <= 0) return;
    $u = usuario_logado();
    
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            INSERT INTO estoque_movimentacoes (item_id, tipo, quantidade, documento, observacao, criado_por)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$itemId, $tipo, $qtd, $documento, $obs, (int)($u['id'] ?? 0)]);

        $operador = ($tipo === 'entrada') ? '+' : '-';
        $pdo->prepare("
            UPDATE estoque_itens
            SET quantidade = quantidade {$operador} ?,
                atualizado_em = datetime('now','localtime')
            WHERE id = ?
        ")->execute([$qtd, $itemId]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/* =========================================================
 *  APIBRASIL
 * ========================================================= */

function apibrasil_consultar_placa(string $placa): array {
    $placa = normalizar_placa($placa);
    if (!validar_placa($placa)) {
        return ['ok' => false, 'erro' => 'Placa inválida.'];
    }

    $st = db()->prepare('SELECT * FROM veiculos WHERE placa = ? AND atualizado_em >= datetime("now","-30 days")');
    $st->execute([$placa]);
    $cache = $st->fetch();
    if ($cache) {
        return ['ok' => true, 'dados' => $cache, 'cache' => true];
    }

    if (!defined('APIBRASIL_BEARER_TOKEN') || APIBRASIL_BEARER_TOKEN === '') {
        return ['ok' => false, 'erro' => 'APIBrasil não configurada.'];
    }

    $url = APIBRASIL_BASE_URL . '/vehicles/dados?placa=' . urlencode($placa);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . APIBRASIL_BEARER_TOKEN,
            'DeviceToken: ' . APIBRASIL_DEVICE_TOKEN,
            'Accept: application/json',
        ],
    ]);
    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false) return ['ok' => false, 'erro' => 'Falha de conexão: ' . $err];
    if ($http !== 200)   return ['ok' => false, 'erro' => 'APIBrasil retornou HTTP ' . $http];

    $json = json_decode($body, true);
    if (!is_array($json)) return ['ok' => false, 'erro' => 'Resposta inválida da APIBrasil.'];

    $d = $json['data'] ?? $json;

    $marca  = $d['marca']         ?? $d['MARCA']         ?? '';
    $modelo = $d['modelo']        ?? $d['MODELO']        ?? '';
    $anoFab = $d['anoFabricacao'] ?? $d['ano_fabricacao'] ?? $d['ano'] ?? '';
    $anoMod = $d['anoModelo']     ?? $d['ano_modelo']    ?? '';
    $cor    = $d['cor']           ?? $d['COR']           ?? '';
    $comb   = $d['combustivel']   ?? $d['COMBUSTIVEL']   ?? '';
    $chassi = $d['chassi']        ?? $d['CHASSI']        ?? '';
    $renavam= $d['renavam']       ?? $d['RENAVAM']       ?? '';

    db()->prepare("
        INSERT INTO veiculos (placa, marca, modelo, ano_fabricacao, ano_modelo, cor, combustivel, chassi, renavam, origem, dados_json, atualizado_em)
        VALUES (:placa, :marca, :modelo, :af, :am, :cor, :comb, :chassi, :renavam, 'apibrasil', :json, datetime('now','localtime'))
        ON CONFLICT(placa) DO UPDATE SET
            marca=excluded.marca, modelo=excluded.modelo, ano_fabricacao=excluded.ano_fabricacao, ano_modelo=excluded.ano_modelo,
            cor=excluded.cor, combustivel=excluded.combustivel, chassi=excluded.chassi, renavam=excluded.renavam,
            origem='apibrasil', dados_json=excluded.dados_json, atualizado_em=datetime('now','localtime')
    ")->execute([
        ':placa'  => $placa,  ':marca'  => $marca,  ':modelo' => $modelo,
        ':af'     => $anoFab, ':am'     => $anoMod, ':cor'    => $cor,
        ':comb'   => $comb,   ':chassi' => $chassi, ':renavam'=> $renavam,
        ':json'   => json_encode($json, JSON_UNESCAPED_UNICODE),
    ]);

    $veiculo = db()->prepare('SELECT * FROM veiculos WHERE placa = ?');
    $veiculo->execute([$placa]);
    return ['ok' => true, 'dados' => $veiculo->fetch()];
}

/* =========================================================
 *  PAGAMENTOS (Despesas e Receitas)
 * ========================================================= */

function ensure_pagamentos(): void {
    static $ok = false;
    if ($ok) return;

    $pdo = db();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categorias_pagamento (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL UNIQUE,
            cor TEXT NOT NULL DEFAULT '#64748b',
            icone TEXT NOT NULL DEFAULT '📄',
            ativo INTEGER NOT NULL DEFAULT 1,
            ordem INTEGER NOT NULL DEFAULT 0,
            criado_em TEXT NOT NULL DEFAULT (datetime('now','localtime'))
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pagamentos_despesas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tipo_pessoa TEXT NOT NULL DEFAULT 'avulso',
            pessoa_id INTEGER,
            pessoa_nome TEXT NOT NULL DEFAULT '',
            categoria_id INTEGER NOT NULL,
            descricao TEXT NOT NULL DEFAULT '',
            documento TEXT NOT NULL DEFAULT '',
            valor_centavos INTEGER NOT NULL DEFAULT 0,
            data_vencimento TEXT NOT NULL,
            data_pagamento TEXT NOT NULL DEFAULT '',
            forma_pagamento TEXT NOT NULL DEFAULT '',
            observacoes TEXT NOT NULL DEFAULT '',
            criado_por INTEGER,
            criado_em TEXT NOT NULL DEFAULT (datetime('now','localtime')),
            atualizado_em TEXT NOT NULL DEFAULT (datetime('now','localtime'))
        )
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pag_venc ON pagamentos_despesas(data_vencimento)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pag_pgto ON pagamentos_despesas(data_pagamento)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pag_cat  ON pagamentos_despesas(categoria_id)");

    $qtd = (int)$pdo->query('SELECT COUNT(*) FROM categorias_pagamento')->fetchColumn();
    if ($qtd === 0) {
        $seed = [
            ['Energia','#f59e0b','💡',1], ['Água','#0ea5e9','💧',2], ['Combustível','#ef4444','⛽',3],
            ['Comissão','#22c55e','💰',4], ['Aluguel','#8b5cf6','🏠',5], ['Internet','#06b6d4','🌐',6],
            ['Telefone','#3b82f6','📞',7], ['Manutenção','#64748b','🔧',8], ['Produtos de Limpeza','#14b8a6','🧴',9],
            ['Impostos','#a16207','🏛️',10], ['Salários','#7c3aed','👥',11], ['Marketing','#ec4899','📢',12],
            ['Outros','#94a3b8','📄',99],
        ];
        $ins = $pdo->prepare('INSERT INTO categorias_pagamento (nome, cor, icone, ordem) VALUES (?, ?, ?, ?)');
        foreach ($seed as $s) $ins->execute($s);
    }
    $ok = true;
}

function listar_categorias_pagamento(bool $somenteAtivas = false): array {
    ensure_pagamentos();
    $sql = 'SELECT * FROM categorias_pagamento';
    if ($somenteAtivas) $sql .= ' WHERE ativo = 1';
    $sql .= ' ORDER BY ativo DESC, ordem, nome COLLATE NOCASE';
    return db()->query($sql)->fetchAll();
}

function buscar_categoria_pagamento(int $id): ?array {
    ensure_pagamentos();
    $st = db()->prepare('SELECT * FROM categorias_pagamento WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function status_pagamento(array $p): string {
    if (!empty($p['data_pagamento'])) return 'pago';
    $hoje = date('Y-m-d');
    return $p['data_vencimento'] < $hoje ? 'atrasado' : 'pendente';
}

function rotulo_pessoa_pagamento(array $p): string {
    $nome = $p['pessoa_nome'] ?: '—';
    $tipo = $p['tipo_pessoa'] ?? 'avulso';
    $tag = [
        'usuario'    => 'Usuário',
        'fornecedor' => 'Fornecedor',
        'cliente'    => 'Cliente',
        'avulso'     => 'Avulso',
    ][$tipo] ?? 'Avulso';
    return $nome . ' (' . $tag . ')';
}

function metricas_pagamentos(): array {
    ensure_pagamentos();
    $pdo = db();
    $hoje   = date('Y-m-d');
    $amanha = date('Y-m-d', strtotime('+1 day'));
    $anoMes = date('Y-m');

    $m = [];
    $st = $pdo->prepare("SELECT COALESCE(SUM(valor_centavos),0) FROM pagamentos_despesas WHERE data_pagamento LIKE :mes");
    $st->execute([':mes' => $anoMes . '%']);
    $m['pago_mes'] = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COALESCE(SUM(valor_centavos),0) FROM pagamentos_despesas WHERE data_pagamento = '' AND data_vencimento LIKE :mes");
    $st->execute([':mes' => $anoMes . '%']);
    $m['pendente_mes'] = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COALESCE(SUM(valor_centavos),0), COUNT(*) FROM pagamentos_despesas WHERE data_pagamento = '' AND data_vencimento < :hoje");
    $st->execute([':hoje' => $hoje]);
    $row = $st->fetch(PDO::FETCH_NUM);
    $m['atraso_total']  = (int)$row[0];
    $m['atraso_qtd']    = (int)$row[1];

    $st = $pdo->prepare("SELECT COALESCE(SUM(valor_centavos),0), COUNT(*) FROM pagamentos_despesas WHERE data_pagamento = '' AND data_vencimento = :hoje");
    $st->execute([':hoje' => $hoje]);
    $row = $st->fetch(PDO::FETCH_NUM);
    $m['vence_hoje_total'] = (int)$row[0];
    $m['vence_hoje_qtd']   = (int)$row[1];

    $st = $pdo->prepare("SELECT COALESCE(SUM(valor_centavos),0), COUNT(*) FROM pagamentos_despesas WHERE data_pagamento = '' AND data_vencimento = :amanha");
    $st->execute([':amanha' => $amanha]);
    $row = $st->fetch(PDO::FETCH_NUM);
    $m['vence_amanha_total'] = (int)$row[0];
    $m['vence_amanha_qtd']   = (int)$row[1];

    return $m;
}

function listar_pagamentos(array $f = []): array {
    ensure_pagamentos();
    $where = []; $params = [];

    if (!empty($f['status'])) {
        if ($f['status'] === 'pago') {
            $where[] = "p.data_pagamento <> ''";
        } elseif ($f['status'] === 'pendente') {
            $where[] = "p.data_pagamento = '' AND p.data_vencimento >= :hoje";
            $params[':hoje'] = date('Y-m-d');
        } elseif ($f['status'] === 'atrasado') {
            $where[] = "p.data_pagamento = '' AND p.data_vencimento < :hoje";
            $params[':hoje'] = date('Y-m-d');
        }
    }
    if (!empty($f['categoria_id'])) {
        $where[] = 'p.categoria_id = :cat';
        $params[':cat'] = (int)$f['categoria_id'];
    }
    if (!empty($f['tipo_pessoa'])) {
        $where[] = 'p.tipo_pessoa = :tp';
        $params[':tp'] = $f['tipo_pessoa'];
    }
    if (!empty($f['de'])) { $where[] = 'p.data_vencimento >= :de'; $params[':de'] = $f['de']; }
    if (!empty($f['ate'])) { $where[] = 'p.data_vencimento <= :ate'; $params[':ate'] = $f['ate']; }
    if (!empty($f['q'])) {
        $where[] = '(p.descricao LIKE :q OR p.pessoa_nome LIKE :q OR p.documento LIKE :q)';
        $params[':q'] = '%' . $f['q'] . '%';
    }

    $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT p.*, c.nome AS categoria_nome, c.cor AS categoria_cor, c.icone AS categoria_icone
            FROM pagamentos_despesas p
            LEFT JOIN categorias_pagamento c ON c.id = p.categoria_id
            $sqlWhere ORDER BY p.data_vencimento ASC, p.id DESC";

    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function buscar_pagamento(int $id): ?array {
    ensure_pagamentos();
    $st = db()->prepare('SELECT * FROM pagamentos_despesas WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function formas_pagamento(): array {
    return [
        'PIX'         => 'PIX',
        'CREDITO'     => 'Crédito',
        'DEBITO'      => 'Débito',
        'DINHEIRO'    => 'Dinheiro',
        'CHEQUE'      => 'Cheque',
        'PROMISSORIA' => 'Promissória',
        'OUTRO'       => 'Outro',
    ];
}

function gerar_numero_recibo(): string {
    ensure_entradas();
    $ano = date('Y');
    $st  = db()->prepare("SELECT COUNT(*) FROM pagamentos WHERE recibo_numero LIKE :p");
    $st->execute([':p' => "REC-{$ano}-%"]);
    $n = ((int)$st->fetchColumn()) + 1;
    return sprintf('REC-%s-%06d', $ano, $n);
}

/* =========================================================
 *  SMS
 * ========================================================= */

function enviar_sms(string $telefone, string $mensagem): array {
    $tel = preg_replace('/\D/', '', $telefone);
    if ($tel === '') return ['ok' => false, 'erro' => 'Telefone vazio.'];
    if (strlen($tel) <= 11 && !str_starts_with($tel, '55')) {
        $tel = '55' . $tel;
    }

    $provider = defined('SMS_PROVIDER') ? SMS_PROVIDER : 'log';

    if ($provider === 'log') {
        $log   = APP_ROOT . '/data/sms.log';
        $linha = date('Y-m-d H:i:s') . " | $tel | $mensagem\n";
        @file_put_contents($log, $linha, FILE_APPEND);
        return ['ok' => true, 'info' => 'SMS registrado em data/sms.log (modo teste).'];
    }

    if ($provider === 'comtele') {
        if (SMS_API_KEY === '') return ['ok' => false, 'erro' => 'SMS_API_KEY não configurada.'];
        $ch = curl_init('https://sms.comtele.com.br/api/v2/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'Sender'    => SMS_SENDER,
                'Receivers' => $tel,
                'Content'   => $mensagem,
            ], JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'auth-key: ' . SMS_API_KEY,
            ],
        ]);
        $body = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) return ['ok' => false, 'erro' => 'cURL: ' . $err];
        return ['ok' => $http >= 200 && $http < 300, 'http' => $http, 'info' => (string)$body];
    }

    if ($provider === 'twilio') {
        if (SMS_TWILIO_SID === '' || SMS_TWILIO_TOKEN === '') {
            return ['ok' => false, 'erro' => 'Credenciais Twilio não configuradas.'];
        }
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . SMS_TWILIO_SID . '/Messages.json';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => SMS_TWILIO_SID . ':' . SMS_TWILIO_TOKEN,
            CURLOPT_POSTFIELDS     => http_build_query([
                'From' => SMS_SENDER,
                'To'   => '+' . $tel,
                'Body' => $mensagem,
            ]),
        ]);
        $body = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) return ['ok' => false, 'erro' => 'cURL: ' . $err];
        return ['ok' => $http >= 200 && $http < 300, 'http' => $http, 'info' => (string)$body];
    }

    return ['ok' => false, 'erro' => "Provider SMS desconhecido: $provider"];
}

function sms_veiculo_pronto(array $entrada, ?array $empresa = null): string {
    $emp   = $empresa ?? empresa();
    $nome  = $emp['nome_fantasia'] ?: ($emp['razao_social'] ?: 'Lava-Rápido');
    $placa = formatar_placa($entrada['placa']);
    return "Ola! Seu veiculo {$placa} esta pronto para retirada em {$nome}. Obrigado!";
}
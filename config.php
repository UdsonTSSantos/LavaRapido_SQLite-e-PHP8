<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
        // Em produção com HTTPS, descomente:
        // 'cookie_secure' => true,
    ]);
}

define('APP_ROOT', __DIR__);
define('DB_DIR',  APP_ROOT . '/data');
define('DB_PATH', DB_DIR . '/sistema.sqlite');

if (!is_dir(DB_DIR)) {
    mkdir(DB_DIR, 0775, true);
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
    }
    return $pdo;
}

require_once APP_ROOT . '/functions.php';

/* =========================================================
 *  APIBRASIL (consulta de placa)
 *  Cadastre-se em https://app.apibrasil.io
 *  Credenciais: https://plataforma.apibrasil.com.br/myaccount/credentials
 * ========================================================= */
define('APIBRASIL_BEARER_TOKEN', getenv('APIBRASIL_BEARER_TOKEN') ?: '');
define('APIBRASIL_DEVICE_TOKEN', getenv('APIBRASIL_DEVICE_TOKEN') ?: '');
define('APIBRASIL_BASE_URL',     'https://apibrasil.io/api/v1');


/* =========================================================
 *  AUTENTICAÇÃO
 *  Modo: 'local'  → valida no banco SQLite (padrão)
 *        'api'    → valida em uma API remota
 *
 *  Para ativar a API, mude AUTH_MODE para 'api' e informe
 *  AUTH_API_URL e AUTH_API_KEY.
 * ========================================================= */
defined('AUTH_MODE')        || define('AUTH_MODE',        getenv('AUTH_MODE')        ?: 'local');
defined('AUTH_API_URL')     || define('AUTH_API_URL',     getenv('AUTH_API_URL')     ?: '');
defined('AUTH_API_KEY')     || define('AUTH_API_KEY',     getenv('AUTH_API_KEY')     ?: '');
defined('AUTH_API_TIMEOUT') || define('AUTH_API_TIMEOUT', 8);




/* =========================================================
 *  SMS — aviso ao cliente quando a lavagem estiver pronta
 *
 *  Providers: log (padrão, não envia) | comtele | twilio
 *  Configure via variável de ambiente ou direto aqui.
 * ========================================================= */
define('SMS_PROVIDER',       getenv('SMS_PROVIDER')       ?: 'log');
define('SMS_API_KEY',        getenv('SMS_API_KEY')        ?: '');   // Comtele
define('SMS_SENDER',         getenv('SMS_SENDER')         ?: 'LAVRAPIDO');
define('SMS_TWILIO_SID',     getenv('SMS_TWILIO_SID')     ?: '');
define('SMS_TWILIO_TOKEN',   getenv('SMS_TWILIO_TOKEN')   ?: '');



<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
$u = usuario_logado();
$paginaAtual = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($titulo) ? e($titulo) . ' - ' : '' ?>Lava Rápido System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .sidebar-bg { background: linear-gradient(180deg, #2563eb 0%, #1d4ed8 50%, #1e40af 100%); }
        .nav-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: rgba(255, 255, 255, 0.85); border-radius: 0.75rem; font-weight: 500; transition: all 0.2s ease-in-out; }
        .nav-link:hover { background-color: rgba(255, 255, 255, 0.1); color: #ffffff; }
        .nav-link.active { background-color: rgba(255, 255, 255, 0.2); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.1); }
        .icon-white { filter: brightness(0) invert(1); opacity: 0.85; transition: opacity 0.2s; }
        .nav-link:hover .icon-white, .nav-link.active .icon-white { opacity: 1; }
        .btn-sair { background: linear-gradient(90deg, #f59e0b 0%, #f97316 100%); color: #ffffff; font-weight: 700; padding: 0.75rem 0; border-radius: 0.75rem; text-align: center; transition: opacity 0.2s; display: flex; justify-content: center; }
        .btn-sair:hover { opacity: 0.9; }
        .categoria-texto { color: rgba(255, 255, 255, 0.6); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
    </style>
</head>
<body class="text-slate-800 antialiased min-h-screen flex">

    <!-- SIDEBAR -->
    <aside class="hidden md:flex flex-col w-64 sidebar-bg h-screen sticky top-0 overflow-y-auto shrink-0 z-40">
        <div class="p-6"><h1 class="font-bold text-2xl text-white">Menu</h1></div>
        <nav class="flex-1 px-4 space-y-1">
            <p class="categoria-texto mb-2 ml-2">Operações</p>
            <a href="dashboard.php" class="nav-link <?= $paginaAtual === 'dashboard.php' ? 'active' : '' ?>"><img src="uploads/icones/ico_dashboard.svg" class="w-5 h-5 icon-white"> Dashboard</a>
            <a href="agendamentos.php" class="nav-link <?= $paginaAtual === 'agendamentos.php' ? 'active' : '' ?>"><img src="uploads/icones/ico_date.svg" class="w-5 h-5 icon-white"> Agendamentos</a>
            <a href="entradas.php" class="nav-link <?= in_array($paginaAtual, ['entradas.php', 'entrada_lavagem.php']) ? 'active' : '' ?>"><img src="uploads/icones/ico_car.svg" class="w-5 h-5 icon-white"> Entradas</a>
            <a href="lavagens.php" class="nav-link <?= $paginaAtual === 'lavagens.php' ? 'active' : '' ?>"><img src="uploads/icones/ico_lavagens.svg" class="w-5 h-5 icon-white"> Lavagens</a>
            <a href="pagamentos.php" class="nav-link <?= $paginaAtual === 'pagamentos.php' ? 'active' : '' ?>"><img src="uploads/icones/ico_money.svg" class="w-5 h-5 icon-white"> Pagamentos</a>
            <a href="pagamentos_pendentes.php" class="nav-link <?= $paginaAtual === 'pagamentos_pendentes.php' ? 'active' : '' ?>"><img src="uploads/icones/ico_pendentes.svg" class="w-5 h-5 icon-white"> Pendentes</a>
            
            <p class="categoria-texto mt-6 mb-2 ml-2">Cadastros</p>
            <a href="clientes.php" class="nav-link <?= $paginaAtual === 'clientes.php' ? 'active' : '' ?>"><img src="uploads/icones/ico_clientes.svg" class="w-5 h-5 icon-white"> Clientes</a>
            <a href="lavagens.php" class="nav-link <?= $paginaAtual === 'servicos.php' ? 'active' : '' ?>"><img src="uploads/icones/ico_services.svg" class="w-5 h-5 icon-white"> Serviços</a>
            <a href="estoque.php" class="nav-link <?= $paginaAtual === 'estoque.php' ? 'active' : '' ?>"><img src="uploads/icones/ico_estoque.svg" class="w-5 h-5 icon-white"> Estoque</a>
            <a href="usuarios.php" class="nav-link <?= $paginaAtual === 'usuarios.php' ? 'active' : '' ?>"><img src="uploads/icones/ico_users.svg" class="w-5 h-5 icon-white"> Equipe</a>
        </nav>
        <div class="p-4 mt-auto"><a href="logout.php" class="btn-sair">SAIR</a></div>
    </aside>

    <!-- CONTEÚDO PRINCIPAL -->
    <main class="flex-1 flex flex-col min-h-screen overflow-x-hidden">
        
        <!-- FLASH MESSAGES (Avisos de Sucesso/Erro) -->
        <!-- Aumentei o padding-top (pt-8) para o conteúdo não ficar colado no teto -->
        <div class="px-8 pt-8 w-full max-w-7xl mx-auto">
            <?php
            if (isset($_SESSION['flash'])) {
                $flash_data = $_SESSION['flash'];
                unset($_SESSION['flash']);

                $msg = '';
                $tipo = 'sucesso';

                if (is_array($flash_data)) {
                    if (isset($flash_data['msg'])) {
                        $msg = $flash_data['msg'];
                        $tipo = $flash_data['tipo'] ?? 'sucesso';
                    } elseif (count($flash_data) > 0) {
                        $chaves = array_keys($flash_data);
                        if ($chaves[0] !== 'tipo') {
                            $msg = $chaves[0];
                            $tipo = $flash_data[$chaves[0]];
                        }
                    }
                } else {
                    $msg = $flash_data;
                }

                if (!empty($msg) && $msg !== 'tipo') {
                    $cores = ($tipo === 'sucesso' || $tipo === 'success') 
                        ? 'bg-emerald-100 border-emerald-300 text-emerald-800' 
                        : 'bg-rose-100 border-rose-300 text-rose-800';
                    
                    echo '<div class="mb-4 rounded-xl border ' . $cores . ' px-4 py-3 text-sm font-semibold shadow-sm">' . e($msg) . '</div>';
                }
            }
            ?>
        </div>

        <div class="flex-1 px-8 py-6 max-w-7xl mx-auto w-full">
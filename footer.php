            </div> <!-- Fecha a div onde o conteúdo das páginas é injetado -->
            
            <!-- RODAPÉ (AST7) -->
            <footer class="w-full mt-auto py-6 text-center text-xs text-slate-400 border-t border-slate-200 bg-slate-50/50">
                <p class="font-medium">
                    AST7 &copy; Copyright 2026 - AST7 softwares - 
                    <a href="http://www.ast7net.com.br" target="_blank" class="text-slate-500 hover:text-brand-600 transition-colors">
                        www.ast7net.com.br
                    </a>
                </p>
            </footer>

        </main> <!-- Fecha a tag <main> aberta no header.php -->

    <!-- Script para inicializar os ícones e o menu mobile -->
    <script>
        // Inicializa ícones Lucide
        lucide.createIcons();

        // Lógica do Menu Mobile
        const btnMenu = document.getElementById('btnMenuMobile');
        const sidebar = document.getElementById('sidebar');
        
        if (btnMenu && sidebar) {
            btnMenu.addEventListener('click', () => {
                sidebar.classList.toggle('hidden');
                sidebar.classList.toggle('fixed');
                sidebar.classList.toggle('inset-0');
                sidebar.classList.toggle('z-50');
                
                // No mobile, a sidebar ocupa a tela inteira
                if(!sidebar.classList.contains('hidden')) {
                    sidebar.classList.remove('w-64');
                    sidebar.classList.add('w-full');
                } else {
                    sidebar.classList.add('w-64');
                    sidebar.classList.remove('w-full');
                }
            });
        }
    </script>
</body>
</html>
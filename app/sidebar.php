<?php
?>
<header>
    <nav id="sidebar" class="d-flex flex-column p-3">
        <img src="assets/imagens/2blog.png" class="align-self-center" height=160 width=180 alt="" srcset="">
        <h4 class="text-center mb-4 py-3">
            <i class="bi bi-gear"></i> Sistema OS
        </h4>
        
        <!-- Perfil do usuário -->
        <div class="dropdown mb-4 px-3">
                <div>
                    <strong>
                        <?php 
                        if (isset($is_admin) && $is_admin) {
                            echo htmlspecialchars($_SESSION['usuario_nome'] ?? '');
                        } else {
                            echo htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário');
                        }
                        ?>
                    </strong><br/>
                    <small>
                        <?php 
                        if (isset($is_admin) && $is_admin) {
                            echo 'Administrador';
                        } elseif (isset($is_audatex) && $is_audatex) {
                            echo 'Operador Audatex';
                        } else {
                            echo 'Usuário Comum';
                        }
                        ?>
                    </small>
                </div>
        </div>

        <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="index.php" class="nav-link">
                        <i class="bi bi-plus-circle"></i> Menu
                    </a>
                </li>
                <?php if ($_SESSION['usuario_tipo'] === 'admin' || $_SESSION['usuario_tipo'] === 'usuario'): ?>
                <li class="nav-item">
                    <a href="nova_os_veiculo.php" class="nav-link">
                        <i class="bi bi-plus-circle"></i> Nova OS Veículo
                    </a>
                </li>
                <?php endif ?>
                <li class="nav-item">
                    <a href="minhas_os.php" class="nav-link">
                        <i class="bi bi-car-front"></i> Minhas OS
                    </a>
                </li>
                <?php if ($_SESSION['usuario_tipo'] === 'admin' || $_SESSION['usuario_tipo'] === 'audatex'): ?>
                <li class="nav-item">
                    <a href="dashboard_audatex.php" class="nav-link">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="os_pendentes_audatex.php" class="nav-link">
                        <i class="bi bi-list-check"></i> OS para Cotar
                    </a>
                </li>
                <?php endif ?>
                
                <?php if ($_SESSION['usuario_tipo'] === 'admin'): ?>
                <li class="nav-item">
                    <a href="usuarios.php" class="nav-link">
                        <i class="bi bi-list-check"></i> Usuários
                    </a>
                </li>
                <?php endif ?>

                <?php if ($_SESSION['usuario_tipo'] === 'admin' || $_SESSION['usuario_tipo'] === 'audatex'): ?>
                <li class="nav-item">
                    <a href="cadastrar_pecas.php" class="nav-link">
                        <i class="bi bi-tools"></i> Cadastrar Peças
                    </a>
                </li>
                <?php endif ?>
                            
            <!-- Menu comum a todos -->
            <li class="nav-item">
                <a href="relatorios_os.php" class="nav-link">
                    <i class="bi bi-graph-up"></i> Relatórios
                </a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link mt-4 text-danger">
                    <i class="bi bi-box-arrow-right"></i> Sair
                </a>
            </li>
        </ul>
        
        <small class="text-white p-4 text-center">
            Sistema OS Veículos v1.0<br>
            <span class="badge bg-info">OS Veículos - Audatex</span>
        </small>
    </nav>
</header>
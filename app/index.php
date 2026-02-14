<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'includes/db.php';
require_once 'includes/funcoes_padrao.php';
require_once 'includes/funcoes_os_veiculos.php';

// Buscar notificações do usuário
$total_notificacoes = buscarNotificacoesUsuario($conn, $_SESSION['usuario_id']);

// Buscar detalhes das notificações para o dropdown
$notificacoes_detalhes = buscarDetalhesNotificacoes($conn, $_SESSION['usuario_id'], 5);
require_once 'header.php';
?>
<body class="d-flex flex-row">
  <?php require_once 'sidebar.php'; ?>
  <main id="content">
      <div class="row">
        <div class="col-md-8">
          <h1>Bem-vindo, <?= htmlspecialchars(formatarPatente($_SESSION['usuario_posto_graduacao']) ?? '') . ' ' . htmlspecialchars($_SESSION['usuario_nome_guerra'] ?? '') ?>!</h1>
          <p>Use o menu à esquerda para navegar pelo sistema.</p>
        </div>
      </div>

      <div class="card border-warning mb-4">
        <div class="card-header bg-warning">
          <h5 class="mb-0"><i class="bi bi-car-front"></i> Módulo OS Veículos</h5>
        </div>
        <div class="card-body">
          <p>Gerencie o sistema de ordens de serviço para veículos:</p>
          
          <div class="d-flex justify-content-around">
            <img src="assets/imagens/gol1.png" alt="" srcset="">       
            <img src="assets/imagens/hilux1.png" alt="" srcset="">       
            <img src="assets/imagens/sentra1.png" alt="" srcset="">       
          </div>
          <div class="d-flex justify-content-around">
            <a href="nova_os_veiculo.php" class="btn btn-outline-warning me-2">
              <i class="bi bi-plus-circle"></i> Abrir Os
            </a>
            <a href="minhas_os.php" class="btn btn-outline-info me-2">
              <i class="bi bi-tools"></i> Minhas OS
            </a>
            <a href="relatorios_os.php" class="btn btn-outline-success">
              <i class="bi bi-graph-up"></i> Relatórios OS
            </a>
          </div>
        </div>
      </div>
  </main>
</body>
</html>
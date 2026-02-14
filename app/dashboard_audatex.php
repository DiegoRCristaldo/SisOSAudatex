<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/funcoes_os_veiculos.php';

// Verificar se é operador Audatex
$tipo_usuario = $_SESSION['usuario_tipo'] ?? '';
if (strpos($tipo_usuario, 'audatex') != false) {
    header("Location: index.php");
    exit;
}

// Buscar estatísticas
$os_pendentes = contarOSPendentesAudatex($conn);
$os_em_cotacao = contarOSStatusAudatex($conn, 'em_cotacao');
$os_cotadas_hoje = contarOSCotadasHoje($conn);

// Buscar últimas OS pendentes
$sql_pendentes = "SELECT c.*, u.nome as oficina_nome 
                  FROM chamados c
                  JOIN usuarios u ON c.id_usuario_abriu = u.id
                  WHERE c.status_os = 'cotacao_pendente'
                  ORDER BY c.data_abertura ASC
                  LIMIT 10";
$result_pendentes = $conn->query($sql_pendentes);

// Buscar últimas OS cotadas hoje
$sql_cotadas_hoje = "SELECT c.*, u.nome as oficina_nome,
                     TIMESTAMPDIFF(MINUTE, c.data_abertura, c.data_cotacao) as tempo_cotacao
                     FROM chamados c
                     JOIN usuarios u ON c.id_usuario_abriu = u.id
                     WHERE DATE(c.data_cotacao) = CURDATE() 
                     AND c.status_os IN ('cotado', 'aguardando_aprovacao')
                     ORDER BY c.data_cotacao DESC
                     LIMIT 5";
$result_cotadas_hoje = $conn->query($sql_cotadas_hoje);

// Buscar métricas por tipo de serviço
$sql_metricas_tipo = "SELECT tipo_servico, COUNT(*) as total,
                      AVG(TIMESTAMPDIFF(MINUTE, data_abertura, data_cotacao)) as tempo_medio
                      FROM chamados
                      WHERE data_cotacao IS NOT NULL
                      AND data_cotacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                      GROUP BY tipo_servico
                      ORDER BY total DESC";
$result_metricas_tipo = $conn->query($sql_metricas_tipo);

require_once 'header.php';
?>
</head>
<body class="d-flex flex-row">
    <?php require_once 'sidebar.php'; ?>
    
    <main id="content" class="p-4">
        <div class="container-fluid">
            <!-- Cabeçalho -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <h1 class="text-warning">
                        <i class="bi bi-speedometer2"></i> Dashboard Audatex
                    </h1>
                    <p class="lead">Controle de cotações e ordens de serviço</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="os_pendentes_audatex.php" class="btn btn-warning btn-lg m-1">
                        <i class="bi bi-list-check"></i> Ver OS Pendentes
                    </a>
                    <a href="nova_os_veiculo.php?mode=audatex" class="btn btn-success btn-lg m-1">
                        <i class="bi bi-plus-circle"></i> Nova Cotação
                    </a>
                </div>
            </div>
            
            <!-- Alertas -->
            <div class="alert alert-warning">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                    </div>
                    <div class="col">
                        <strong>Instruções:</strong> Use o software Audatex para realizar as cotações, 
                        depois registre os valores no sistema. Mantenha o prazo máximo de 24 horas úteis.
                    </div>
                </div>
            </div>
            
            <!-- Cards de Métricas -->
            <div class="row mt-4">
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs fw-bold text-danger text-uppercase mb-1">
                                        OS Pendentes
                                    </div>
                                    <div class="h5 mb-0 fw-bold"><?= $os_pendentes ?></div>
                                    <div class="mt-2">
                                        <small class="text-muted">Aguardando cotação</small>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-clock-fill text-danger fa-2x"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-0">
                            <a href="os_pendentes_audatex.php" class="text-danger text-decoration-none">
                                Ver todas →
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs fw-bold text-warning text-uppercase mb-1">
                                        Em Cotação
                                    </div>
                                    <div class="h5 mb-0 fw-bold"><?= $os_em_cotacao ?></div>
                                    <div class="mt-2">
                                        <small class="text-muted">Em processamento</small>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-gear-fill text-warning fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs fw-bold text-success text-uppercase mb-1">
                                        Cotadas Hoje
                                    </div>
                                    <div class="h5 mb-0 fw-bold"><?= $os_cotadas_hoje ?></div>
                                    <div class="mt-2">
                                        <small class="text-muted">Já processadas</small>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-check-circle-fill text-success fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gráfico e Lista -->
            <div class="row mt-4">
                <!-- Gráfico de distribuição por tipo -->
                <div class="col-lg-8 mb-4">
                    <div class="card shadow h-100">
                        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold">
                                <i class="bi bi-exclamation-triangle"></i> OS Pendentes para Cotação
                            </h6>
                            <span class="badge bg-light text-danger"><?= $os_pendentes ?> pendente(s)</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if ($result_pendentes->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="80">OS #</th>
                                            <th>Veículo</th>
                                            <th>Oficina</th>
                                            <th>Data Abertura</th>
                                            <th>Prioridade</th>
                                            <th width="120">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($os = $result_pendentes->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-secondary">#<?= $os['id'] ?></span>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($os['placa_veiculo']) ?></strong><br>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($os['marca_veiculo']) ?> 
                                                    <?= htmlspecialchars($os['modelo_veiculo']) ?>
                                                </small>
                                            </td>
                                            <td><?= htmlspecialchars($os['oficina_nome']) ?></td>
                                            <td>
                                                <?= date('d/m/Y H:i', strtotime($os['data_abertura'])) ?>
                                            </td>
                                            <td>
                                                <?php
                                                $prioridades = [
                                                    'baixa' => ['label' => 'Baixa', 'color' => 'success'],
                                                    'media' => ['label' => 'Média', 'color' => 'warning'],
                                                    'alta' => ['label' => 'Alta', 'color' => 'danger']
                                                ];
                                                $prio = $os['prioridade'];
                                                ?>
                                                <span class="badge bg-<?= $prioridades[$prio]['color'] ?>">
                                                    <?= $prioridades[$prio]['label'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="iniciar_cotacao.php?id=<?= $os['id'] ?>" 
                                                    class="btn btn-warning" title="Iniciar Cotação">
                                                        <i class="bi bi-play-fill"></i>
                                                    </a>
                                                    <a href="detalhes_os.php?id=<?= $os['id'] ?>" 
                                                    class="btn btn-info" title="Ver Detalhes">
                                                        <i class="bi bi-eye-fill"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-check-circle-fill text-success fs-1"></i>
                                <h5 class="mt-3">Todas as OS estão em dia!</h5>
                                <p class="text-muted">Não há ordens pendentes para cotação.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer text-center">
                            <a href="os_pendentes_audatex.php" class="btn btn-outline-danger">
                                <i class="bi bi-list-ul"></i> Ver todas as OS pendentes
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Últimas OS cotadas hoje -->
                <div class="col-lg-4 mb-4">
                    <div class="card shadow h-100">
                        <div class="card-header bg-success text-white">
                            <h6 class="m-0 fw-bold">
                                <i class="bi bi-clock-history"></i> Últimas Cotadas Hoje
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php if ($result_cotadas_hoje->num_rows > 0): ?>
                                    <?php while ($os = $result_cotadas_hoje->fetch_assoc()): ?>
                                    <a href="detalhes_os.php?id=<?= $os['id'] ?>" 
                                       class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?= htmlspecialchars($os['placa_veiculo']) ?></h6>
                                        </div>
                                        <p class="mb-1"><?= htmlspecialchars($os['oficina_nome']) ?></p>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($os['marca_veiculo']) ?> 
                                            <?= htmlspecialchars($os['modelo_veiculo']) ?>
                                        </small>
                                    </a>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="list-group-item text-center text-muted py-4">
                                        <i class="bi bi-inbox fs-1"></i>
                                        <p class="mt-2">Nenhuma OS cotada hoje</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script></script>
</body>
</html>
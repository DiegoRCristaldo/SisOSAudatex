<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/funcoes_os_veiculos.php';
require_once 'includes/funcoes_padrao.php';

// Verificar se é operador Audatex
$tipo_usuario = $_SESSION['usuario_tipo'] ?? '';
if (strpos($tipo_usuario, 'admin') === false && strpos($tipo_usuario, 'audatex') === false) {
    header("Location: index.php");
    exit;
}

// Filtros
$status = $_GET['status'] ?? 'cotacao_pendente';
$prioridade = $_GET['prioridade'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

// Construir query com filtros
$sql = "SELECT c.*, u.om as oficina_nome,
               GROUP_CONCAT(DISTINCT 
                   CASE 
                       WHEN ois.peca_id > 0 THEN p.nome
                       ELSE ois.descricao_manual
                   END 
                   SEPARATOR ', '
               ) as pecas_selecionadas,
               GROUP_CONCAT(DISTINCT 
                   CASE 
                       WHEN ois.peca_id = 0 THEN 'manual'
                       ELSE 'lista'
                   END
               ) as tipos_pecas,
               COUNT(DISTINCT ois.id) as total_pecas,
               SUM(CASE WHEN ois.peca_id = 0 THEN 1 ELSE 0 END) as total_manuais,
               TIMESTAMPDIFF(HOUR, c.data_abertura, NOW()) as horas_aguardando
        FROM chamados c
        JOIN usuarios u ON c.id_usuario_abriu = u.id
        LEFT JOIN os_itens_selecionados ois ON c.id = ois.os_id
        LEFT JOIN os_pecas_cadastradas p ON ois.peca_id = p.id
        WHERE c.status_os = ?
        GROUP BY c.id";
$params = [$status];
$types = "s";

// Adicionar filtros restantes
if (!empty($prioridade)) {
    $sql .= " AND c.prioridade = ?";
    $params[] = $prioridade;
    $types .= "s";
}

if (!empty($data_inicio)) {
    $sql .= " AND DATE(c.data_abertura) >= ?";
    $params[] = $data_inicio;
    $types .= "s";
}

if (!empty($data_fim)) {
    $sql .= " AND DATE(c.data_abertura) <= ?";
    $params[] = $data_fim;
    $types .= "s";
}

$sql .= " ORDER BY 
          CASE c.prioridade 
            WHEN 'alta' THEN 1 
            WHEN 'media' THEN 2 
            WHEN 'baixa' THEN 3 
          END,
          c.data_abertura ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Erro na preparação da query: " . $conn->error);
}

// Verifique se há parâmetros para bind
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    die("Erro na execução: " . $stmt->error);
}

$result = $stmt->get_result();
$total_os = $result->num_rows;

require_once 'header.php';
?>
</head>
<body class="d-flex flex-row">
    <?php require_once 'sidebar.php'; ?>
    
    <main id="content" class="p-4">
        <div class="container-fluid">
            <!-- Cabeçalho -->
            <div class="row mb-4">
                <div class="col">
                    <h1 class="text-warning">
                        <i class="bi bi-list-check"></i> OS para Cotação Audatex
                    </h1>
                    <p class="lead">Gerencie as ordens de serviço pendentes de cotação</p>
                </div>
                <div class="col-auto">
                    <div class="btn-group" role="group">
                        <a href="dashboard_audatex.php" class="btn btn-outline-warning">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                        <a href="os_cotadas.php" class="btn btn-outline-success">
                            <i class="bi bi-check-circle"></i> OS Cotadas
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-funnel"></i> Filtros</h6>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="cotacao_pendente" <?= $status == 'cotacao_pendente' ? 'selected' : '' ?>>Pendente Cotação</option>
                                <option value="em_cotacao" <?= $status == 'em_cotacao' ? 'selected' : '' ?>>Em Cotação</option>
                                <option value="cotado" <?= $status == 'cotado' ? 'selected' : '' ?>>Cotado</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Prioridade</label>
                            <select name="prioridade" class="form-select">
                                <option value="">Todas</option>
                                <option value="alta" <?= $prioridade == 'alta' ? 'selected' : '' ?>>Alta</option>
                                <option value="media" <?= $prioridade == 'media' ? 'selected' : '' ?>>Média</option>
                                <option value="baixa" <?= $prioridade == 'baixa' ? 'selected' : '' ?>>Baixa</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Data Início</label>
                            <input type="date" name="data_inicio" class="form-control" 
                                   value="<?= $data_inicio ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Data Fim</label>
                            <input type="date" name="data_fim" class="form-control" 
                                   value="<?= $data_fim ?>">
                        </div>
                        
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between">
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-filter"></i> Aplicar Filtros
                                </button>
                                <a href="os_pendentes_audatex.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> Limpar Filtros
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Resumo -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card bg-danger text-dark shadow-sm">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="mb-0">Total de OS</h6>
                                    <h3 class="mb-0"><?= $total_os ?></h3>
                                </div>
                                <i class="bi bi-list-check fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card bg-warning text-white shadow-sm">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="mb-0">Prioridade Alta</h6>
                                    <?php
                                    $sql_alta = "SELECT COUNT(*) as total FROM chamados 
                                                WHERE status_os = 'cotacao_pendente' 
                                                AND prioridade = 'alta'";
                                    $result_alta = $conn->query($sql_alta);
                                    $alta = $result_alta->fetch_assoc()['total'];
                                    ?>
                                    <h3 class="mb-0"><?= $alta ?></h3>
                                </div>
                                <i class="bi bi-arrow-up fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card bg-secondary text-white shadow-sm">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="mb-0">Prontas Hoje</h6>
                                    <?php
                                    $sql_hoje = "SELECT COUNT(*) as total FROM chamados 
                                                WHERE status_os = 'cotado' 
                                                AND DATE(data_cotacao) = CURDATE()";
                                    $result_hoje = $conn->query($sql_hoje);
                                    $hoje = $result_hoje->fetch_assoc()['total'];
                                    ?>
                                    <h3 class="mb-0"><?= $hoje ?></h3>
                                </div>
                                <i class="bi bi-check-circle fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabela de OS -->
            <div class="card shadow">
                <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">
                        <i class="bi bi-exclamation-triangle"></i> Lista de OS para Cotação
                    </h6>
                    <span class="badge bg-light text-danger"><?= $total_os ?> OS</span>
                </div>
                <div class="card-body p-0">
                    <?php if ($total_os > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="tabela-os">
                            <thead class="table-light">
                                <tr>
                                    <th width="80">OS #</th>
                                    <th>Veículo</th>
                                    <th>Oficina</th>
                                    <th>Peças Selecionadas</th>
                                    <th>Data Abertura</th>
                                    <th>Prioridade</th>
                                    <th width="150">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($os = $result->fetch_assoc()):     
                                    // Peças selecionadas
                                    $pecas_selecionadas = $os['pecas_selecionadas'] ?? '';
                                ?>
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
                                    <td>
                                        <?= htmlspecialchars($os['oficina_nome']) ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($pecas_selecionadas)): ?>
                                            <small><?= htmlspecialchars($pecas_selecionadas) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Nenhuma peça selecionada</span>
                                        <?php endif; ?>
                                    </td>
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
                                        <span class="badge bg-<?= $prioridades[$prio]['color'] ?? 'success'?>">
                                            <?= $prioridades[$prio]['label'] ?? 'Baixa'?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <?php if ($os['status_os'] == 'cotacao_pendente'): ?>
                                            <a href="iniciar_cotacao.php?id=<?= $os['id'] ?>" 
                                               class="btn btn-warning" title="Iniciar Cotação">
                                                <i class="bi bi-play-fill"></i> Cotar
                                            </a>
                                            <?php elseif ($os['status_os'] == 'em_cotacao'): ?>
                                            <a href="finalizar_cotacao.php?id=<?= $os['id'] ?>" 
                                               class="btn btn-success" title="Finalizar Cotação">
                                                <i class="bi bi-check-circle"></i> Finalizar
                                            </a>
                                            <?php endif; ?>
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
                        <p class="text-muted">Não há ordens pendentes para cotação com os filtros atuais.</p>
                        <a href="os_pendentes_audatex.php" class="btn btn-warning">
                            <i class="bi bi-filter-circle"></i> Limpar filtros
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i> 
                                Clique em "Cotar" para iniciar a cotação no sistema Audatex.
                            </small>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="exportar_os.php?<?= http_build_query($_GET) ?>" 
                               class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-download"></i> Exportar Excel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script>
    $(document).ready(function() {
        $('#tabela-os').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json'
            },
            pageLength: 25,
            order: [[5, 'desc']], // Ordenar por tempo decrescente
            columnDefs: [
                { orderable: false, targets: [7] } // Coluna de ações não ordenável
            ]
        });
    });
    
    // Auto-refresh a cada 5 minutos
    setTimeout(function() {
        location.reload();
    }, 5 * 60 * 1000);
    </script>
</body>
</html>
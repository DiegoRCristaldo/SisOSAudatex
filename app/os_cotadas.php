<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/funcoes_os_veiculos.php';
require_once 'includes/funcoes_cotacao.php';

// Verificar se é operador Audatex ou admin
$tipo_usuario = $_SESSION['usuario_tipo'] ?? '';
if (strpos($tipo_usuario, 'audatex') === false && strpos($tipo_usuario, 'admin') === false) {
    header("Location: index.php");
    exit;
}

// Filtros
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$status = $_GET['status'] ?? 'cotado';
$oficina = $_GET['oficina'] ?? '';
$operador = $_GET['operador'] ?? '';
$placa = $_GET['placa'] ?? '';

// Construir query com filtros
$sql = "SELECT c.*, 
               u.om as oficina_nome,
               u.nome as usuario_nome,
               aud.nome as operador_nome,
               (SELECT SUM(valor_total) FROM os_itens_cotacao WHERE chamado_id = c.id AND tipo = 'peca') as total_pecas,
               (SELECT COUNT(*) FROM os_itens_cotacao WHERE chamado_id = c.id AND tipo = 'peca') as qtd_itens,
               TIMESTAMPDIFF(HOUR, c.data_inicio_cotacao, c.data_cotacao) as tempo_cotacao_horas
        FROM chamados c
        JOIN usuarios u ON c.id_usuario_abriu = u.id
        LEFT JOIN usuarios aud ON c.operador_audatex_id = aud.id
        WHERE c.status_os IN ('cotado', 'aguardando_aprovacao', 'aprovado', 'executando', 'concluido')";
        
$params = [];
$types = "";

// Filtro por data de cotação
if (!empty($data_inicio)) {
    $sql .= " AND DATE(c.data_cotacao) >= ?";
    $params[] = $data_inicio;
    $types .= "s";
}

if (!empty($data_fim)) {
    $sql .= " AND DATE(c.data_cotacao) <= ?";
    $params[] = $data_fim;
    $types .= "s";
}

// Filtro por status
if (!empty($status) && $status != 'todos') {
    $sql .= " AND c.status_os = ?";
    $params[] = $status;
    $types .= "s";
}

// Filtro por oficina
if (!empty($oficina)) {
    $sql .= " AND u.om LIKE ?";
    $params[] = "%$oficina%";
    $types .= "s";
}

// Filtro por operador Audatex
if (!empty($operador)) {
    $sql .= " AND aud.nome LIKE ?";
    $params[] = "%$operador%";
    $types .= "s";
}

// Filtro por placa do veículo
if (!empty($placa)) {
    $sql .= " AND c.placa_veiculo LIKE ?";
    $params[] = "%$placa%";
    $types .= "s";
}

$sql .= " ORDER BY c.data_cotacao DESC, c.id DESC";

// Debug: verificar query (remover em produção)
// error_log("SQL Query: " . $sql);
// error_log("Params: " . print_r($params, true));

// Executar consulta
try {
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Erro na preparação da query: " . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Erro na execução: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $total_os = $result->num_rows;
    
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

// Calcular estatísticas
try {
    $sql_stats = "SELECT 
                    COUNT(*) as total_cotacoes,
                    SUM(total_pecas) as valor_total_cotacoes,
                    AVG(TIMESTAMPDIFF(HOUR, data_inicio_cotacao, data_cotacao)) as tempo_medio_cotacao
                  FROM chamados 
                  WHERE status_os IN ('cotado', 'aguardando_aprovacao', 'aprovado', 'executando', 'concluido')
                  AND data_cotacao BETWEEN ? AND ?";
    
    $stmt_stats = $conn->prepare($sql_stats);
    if (!$stmt_stats) {
        throw new Exception("Erro na preparação da query stats: " . $conn->error);
    }
    
    $stmt_stats->bind_param("ss", $data_inicio, $data_fim);
    $stmt_stats->execute();
    $stats_result = $stmt_stats->get_result();
    $stats = $stats_result->fetch_assoc();
    
} catch (Exception $e) {
    $stats = ['total_cotacoes' => 0, 'valor_total_cotacoes' => 0, 'tempo_medio_cotacao' => 0];
}

require_once 'header.php';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OS Cotadas - Histórico</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .card-stats {
            border-radius: 10px;
            transition: transform 0.3s;
        }
        .card-stats:hover {
            transform: translateY(-5px);
        }
        .badge-status {
            font-size: 0.85em;
            padding: 5px 10px;
        }
        .table-hover tbody tr {
            cursor: pointer;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,.025);
        }
        .valor-cell {
            font-family: 'Courier New', monospace;
            text-align: right;
        }
        .data-range {
            background-color: #e3f2fd;
            border-left: 4px solid #2196F3;
        }
    </style>
</head>
<body class="d-flex flex-row">
    <?php require_once 'sidebar.php'; ?>
    
    <main id="content" class="p-4 w-100">
        <div class="container-fluid">
            <!-- Cabeçalho -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <h1 class="text-success">
                        <i class="bi bi-check-circle"></i> OS Cotadas
                    </h1>
                    <p class="lead">Histórico de consultas de valores já realizadas</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="os_pendentes_audatex.php" class="btn btn-warning">
                        <i class="bi bi-list-check"></i> Ver OS Pendentes
                    </a>
                    <a href="exportar_cotacoes.php?<?= http_build_query($_GET) ?>" class="btn btn-outline-success">
                        <i class="bi bi-download"></i> Exportar
                    </a>
                </div>
            </div>
            
            <!-- Estatísticas -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card card-stats bg-primary text-white shadow">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="mb-0">Total de Cotações</h6>
                                    <h2 class="mb-0"><?= number_format($stats['total_cotacoes'] ?? 0, 0, ',', '.') ?></h2>
                                </div>
                                <i class="bi bi-list-check fs-1 opacity-50"></i>
                            </div>
                            <small>No período selecionado</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card card-stats bg-success text-white shadow">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="mb-0">Valor Total</h6>
                                    <h2 class="mb-0">R$ <?= number_format($stats['valor_total_cotacoes'] ?? 0, 2, ',', '.') ?></h2>
                                </div>
                                <i class="bi bi-cash-coin fs-1 opacity-50"></i>
                            </div>
                            <small>Somente peças</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card card-stats bg-info text-white shadow">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="mb-0">Tempo Médio</h6>
                                    <h2 class="mb-0"><?= number_format($stats['tempo_medio_cotacao'] ?? 0, 1, ',', '.') ?>h</h2>
                                </div>
                                <i class="bi bi-clock-history fs-1 opacity-50"></i>
                            </div>
                            <small>Por cotação</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card card-stats bg-warning text-dark shadow">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="mb-0">OS no Período</h6>
                                    <h2 class="mb-0"><?= number_format($total_os, 0, ',', '.') ?></h2>
                                </div>
                                <i class="bi bi-file-earmark-text fs-1 opacity-50"></i>
                            </div>
                            <small>Com filtros atuais</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-funnel"></i> Filtros de Pesquisa</h6>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3">
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
                        
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="todos" <?= $status == 'todos' ? 'selected' : '' ?>>Todos</option>
                                <option value="cotado" <?= $status == 'cotado' ? 'selected' : '' ?>>Cotado</option>
                                <option value="aguardando_aprovacao" <?= $status == 'aguardando_aprovacao' ? 'selected' : '' ?>>Aguardando Aprovação</option>
                                <option value="aprovado" <?= $status == 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                                <option value="executando" <?= $status == 'executando' ? 'selected' : '' ?>>Em Execução</option>
                                <option value="concluido" <?= $status == 'concluido' ? 'selected' : '' ?>>Concluído</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Oficina</label>
                            <input type="text" name="oficina" class="form-control" 
                                   value="<?= htmlspecialchars($oficina) ?>"
                                   placeholder="Nome da oficina...">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Operador</label>
                            <input type="text" name="operador" class="form-control" 
                                   value="<?= htmlspecialchars($operador) ?>"
                                   placeholder="Nome do operador...">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Placa do Veículo</label>
                            <input type="text" name="placa" class="form-control" 
                                   value="<?= htmlspecialchars($placa) ?>"
                                   placeholder="Ex: ABC1D23">
                        </div>
                        
                        <div class="col-md-9">
                            <!-- Espaço vazio para alinhar botões -->
                        </div>
                        
                        <div class="col-md-3">
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-search"></i> Pesquisar
                                </button>
                                <a href="os_cotadas.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> Limpar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Período Selecionado -->
            <div class="alert data-range mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-calendar-range"></i>
                        <strong>Período selecionado:</strong> 
                        <?= date('d/m/Y', strtotime($data_inicio)) ?> 
                        até 
                        <?= date('d/m/Y', strtotime($data_fim)) ?>
                    </div>
                    <div>
                        <span class="badge bg-primary"><?= $total_os ?> OS encontradas</span>
                    </div>
                </div>
            </div>
            
            <!-- Tabela de OS Cotadas -->
            <div class="card shadow">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">
                        <i class="bi bi-check-circle"></i> Histórico de OS Cotadas
                    </h6>
                    <span class="badge bg-light text-success"><?= $total_os ?> OS</span>
                </div>
                <div class="card-body p-0">
                    <?php if ($total_os > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="tabela-os-cotadas">
                            <thead class="table-light">
                                <tr>
                                    <th width="80">OS #</th>
                                    <th>Veículo</th>
                                    <th>Oficina</th>
                                    <th>Status</th>
                                    <th>Operador</th>
                                    <th>Data Cotação</th>
                                    <th>Tempo</th>
                                    <th>Itens</th>
                                    <th>Valor Total</th>
                                    <th width="100">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($os = $result->fetch_assoc()): 
                                    // Definir cores dos status
                                    $status_colors = [
                                        'cotado' => 'secondary',
                                        'aguardando_aprovacao' => 'primary',
                                        'aprovado' => 'info',
                                        'executando' => 'warning',
                                        'concluido' => 'success'
                                    ];
                                    
                                    $status_labels = [
                                        'cotado' => 'Cotado',
                                        'aguardando_aprovacao' => 'Aguardando Aprovação',
                                        'aprovado' => 'Aprovado',
                                        'executando' => 'Em Execução',
                                        'concluido' => 'Concluído'
                                    ];
                                    
                                    $status_color = $status_colors[$os['status_os']] ?? 'secondary';
                                    $status_label = $status_labels[$os['status_os']] ?? $os['status_os'];
                                    
                                    // Format timing
                                    $tempo_cotacao = $os['tempo_cotacao_horas'] ?? 0;
                                    $tempo_text = $tempo_cotacao > 0 ? 
                                        number_format($tempo_cotacao, 1, ',', '.') . 'h' : 
                                        '<span class="text-muted">-</span>';
                                ?>
                                <tr onclick="window.location='detalhes_os.php?id=<?= $os['id'] ?>'" style="cursor: pointer;">
                                    <td>
                                        <span class="badge bg-dark">#<?= $os['id'] ?></span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($os['placa_veiculo']) ?></strong><br>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($os['marca_veiculo']) ?> 
                                            <?= htmlspecialchars($os['modelo_veiculo']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($os['oficina_nome']) ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($os['usuario_nome']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $status_color ?> badge-status">
                                            <?= $status_label ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($os['operador_nome'] ?? 'Não informado') ?></small>
                                    </td>
                                    <td>
                                        <?= $os['data_cotacao'] ? date('d/m/Y H:i', strtotime($os['data_cotacao'])) : '-' ?>
                                    </td>
                                    <td>
                                        <?= $tempo_text ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?= $os['qtd_itens'] ?? 0 ?></span>
                                    </td>
                                    <td class="valor-cell fw-bold">
                                        <?php if ($os['total_pecas']): ?>
                                            R$ <?= number_format($os['total_pecas'], 2, ',', '.') ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="detalhes_os.php?id=<?= $os['id'] ?>" 
                                               class="btn btn-outline-info"
                                               title="Ver Detalhes"
                                               onclick="event.stopPropagation()">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="reabrir_cotacao.php?id=<?= $os['id'] ?>" 
                                               class="btn btn-outline-warning"
                                               title="Reabrir Cotação"
                                               onclick="event.stopPropagation()">
                                                <i class="bi bi-arrow-repeat"></i>
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
                        <i class="bi bi-search text-muted fs-1"></i>
                        <h5 class="mt-3">Nenhuma OS encontrada</h5>
                        <p class="text-muted">Não há ordens de serviço cotadas com os filtros atuais.</p>
                        <a href="os_cotadas.php" class="btn btn-success">
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
                                Clique em qualquer linha para ver detalhes da OS.
                            </small>
                        </div>
                        <div class="col-md-6 text-end">
                            <?php if ($total_os > 0): ?>
                            <?php 
                            // Calcular total geral
                            $result->data_seek(0);
                            $total_geral = 0;
                            while ($os = $result->fetch_assoc()) {
                                $total_geral += $os['total_pecas'];
                            }
                            ?>
                            <small class="text-muted me-3">
                                Total: R$ <?= number_format($total_geral, 2, ',', '.') ?>
                            </small>
                            <a href="exportar_cotacoes.php?<?= http_build_query($_GET) ?>" 
                               class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-download"></i> Exportar Excel
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gráfico de distribuição por status -->
            <?php if ($total_os > 0): 
            try {
                // Buscar distribuição por status
                $sql_distribuicao = "SELECT status_os, COUNT(*) as total 
                                    FROM chamados 
                                    WHERE status_os IN ('cotado', 'aguardando_aprovacao', 'aprovado', 'executando', 'concluido')
                                    AND data_cotacao BETWEEN ? AND ?
                                    GROUP BY status_os";
                $stmt_dist = $conn->prepare($sql_distribuicao);
                $stmt_dist->bind_param("ss", $data_inicio, $data_fim);
                $stmt_dist->execute();
                $dist_result = $stmt_dist->get_result();
            ?>
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-pie-chart"></i> Distribuição por Status</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th>Quantidade</th>
                                            <th>Porcentagem</th>
                                            <th width="100"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_geral = 0;
                                        $dist_data = [];
                                        while ($dist = $dist_result->fetch_assoc()) {
                                            $total_geral += $dist['total'];
                                            $dist_data[] = $dist;
                                        }
                                        
                                        foreach ($dist_data as $dist):
                                            $percent = $total_geral > 0 ? ($dist['total'] / $total_geral) * 100 : 0;
                                            $status_color = $status_colors[$dist['status_os']] ?? 'secondary';
                                            $status_label = $status_labels[$dist['status_os']] ?? $dist['status_os'];
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?= $status_color ?>">
                                                    <?= $status_label ?>
                                                </span>
                                            </td>
                                            <td><?= $dist['total'] ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-<?= $status_color ?>" 
                                                         role="progressbar" 
                                                         style="width: <?= $percent ?>%">
                                                        <?= number_format($percent, 1) ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <a href="os_cotadas.php?status=<?= $dist['status_os'] ?>&data_inicio=<?= $data_inicio ?>&data_fim=<?= $data_fim ?>" 
                                                   class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-filter"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-bar-chart"></i> Top 5 Oficinas</h6>
                        </div>
                        <div class="card-body">
                            <?php
                            $sql_oficinas = "SELECT u.om, COUNT(c.id) as total_os, SUM(c.total_geral) as valor_total
                                            FROM chamados c
                                            JOIN usuarios u ON c.id_usuario_abriu = u.id
                                            WHERE c.status_os IN ('cotado', 'aguardando_aprovacao', 'aprovado', 'executando', 'concluido')
                                            AND c.data_cotacao BETWEEN ? AND ?
                                            GROUP BY u.om
                                            ORDER BY valor_total DESC
                                            LIMIT 5";
                            $stmt_oficinas = $conn->prepare($sql_oficinas);
                            $stmt_oficinas->bind_param("ss", $data_inicio, $data_fim);
                            $stmt_oficinas->execute();
                            $oficinas_result = $stmt_oficinas->get_result();
                            ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Oficina</th>
                                            <th>OS</th>
                                            <th>Valor Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($oficina = $oficinas_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($oficina['om']) ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-primary"><?= $oficina['total_os'] ?></span>
                                            </td>
                                            <td class="fw-bold">
                                                R$ <?= number_format($oficina['valor_total'] ?? 0, 2, ',', '.') ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php 
            } catch (Exception $e) {
                // Silenciar erro na seção de gráficos
            }
            endif; ?>
        </div>
    </main>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script>
    $(document).ready(function() {
        // Inicializar DataTables
        $('#tabela-os-cotadas').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json'
            },
            pageLength: 25,
            order: [[5, 'desc']], // Ordenar por data de cotação decrescente
            columnDefs: [
                { orderable: false, targets: [9] }, // Coluna de ações não ordenável
                { searchable: false, targets: [6, 7, 8, 9] } // Colunas não pesquisáveis
            ]
        });
        
        // Inicializar datepicker para melhor UX
        flatpickr("input[type=date]", {
            dateFormat: "Y-m-d",
            locale: "pt"
        });
        
        // Configurar clique na linha
        $('#tabela-os-cotadas tbody tr').click(function(e) {
            // Não redirecionar se clicar em um botão
            if (!$(e.target).closest('a, button').length) {
                window.location = $(this).find('a.btn-outline-info').attr('href');
            }
        });
    });
    
    // Função para formatar valores
    function formatarValor(valor) {
        return 'R$ ' + parseFloat(valor).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    </script>
</body>
</html>
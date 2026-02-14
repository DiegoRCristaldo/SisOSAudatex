<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'includes/db.php';
require_once 'includes/funcoes_os_veiculos.php';

// Verificar tipo de usuário
$usuario_id = $_SESSION['usuario_id'];
$tipo_usuario = $_SESSION['usuario_tipo'];
$is_admin = strpos($tipo_usuario, 'admin') !== false;
$is_audatex = strpos($tipo_usuario, 'audatex') !== false;
$is_ti = strpos($tipo_usuario, 'admin') !== false || strpos($tipo_usuario, 'tecnico') !== false;

// Filtros
$status = $_GET['status'] ?? '';
$prioridade = $_GET['prioridade'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

// Construir query baseada no tipo de usuário
$sql = "SELECT c.*, u.om as oficina_nome,
               GROUP_CONCAT(DISTINCT p.nome SEPARATOR ', ') as pecas_selecionadas
        FROM chamados c
        JOIN usuarios u ON c.id_usuario_abriu = u.id
        LEFT JOIN os_itens_selecionados ois ON c.id = ois.os_id
        LEFT JOIN os_pecas_cadastradas p ON ois.peca_id = p.id";

// Condições baseadas no tipo de usuário
$conditions = [];
$params = [];
$types = "";

if ($tipo_usuario) {
    // Usuário vê apenas suas próprias OS
    $conditions[] = "c.id_usuario_abriu = ?";
    $params[] = $usuario_id;
    $types .= "i";
} elseif ($is_audatex) {
    // Audatex vê TODAS as OS para poder cotar
    // Não há restrição por usuário
} else {
    // Outros usuários vêem apenas suas próprias OS
    $conditions[] = "c.id_usuario_abriu = ?";
    $params[] = $usuario_id;
    $types .= "i";
}

// Adicionar filtro de status se especificado
if (!empty($status)) {
    $conditions[] = "c.status_os = ?";
    $params[] = $status;
    $types .= "s";
}

// Adicionar filtro de prioridade
if (!empty($prioridade)) {
    $conditions[] = "c.prioridade = ?";
    $params[] = $prioridade;
    $types .= "s";
}

// Adicionar filtro de data início
if (!empty($data_inicio)) {
    $conditions[] = "DATE(c.data_abertura) >= ?";
    $params[] = $data_inicio;
    $types .= "s";
}

// Adicionar filtro de data fim
if (!empty($data_fim)) {
    $conditions[] = "DATE(c.data_abertura) <= ?";
    $params[] = $data_fim;
    $types .= "s";
}

// Combinar condições
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " GROUP BY c.id 
          ORDER BY 
          CASE c.prioridade 
            WHEN 'alta' THEN 1 
            WHEN 'media' THEN 2 
            WHEN 'baixa' THEN 3 
          END,
          c.data_abertura DESC";

// Executar consulta
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Erro na preparação da query: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    die("Erro na execução: " . $stmt->error);
}

$result = $stmt->get_result();
$total_os = $result->num_rows;

// Contadores para os cards
$os_pendentes = 0;
$os_para_cotar = 0;

$os_para_cotar = contarOSPendentesAudatex($conn);
$os_pendentes = contarOSOficina($conn, $usuario_id);

require_once 'header.php';
?>
    <style>
        .status-badge {
            font-size: 0.75em;
            padding: 3px 8px;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,.025);
        }
    </style>
</head>
<body class="d-flex flex-row">
    <?php require_once 'sidebar.php'; ?>
     
    <main id="content" class="p-4 w-100">
        <div class="container-fluid">
            <!-- Cabeçalho -->
                         <?php if ($_SESSION['usuario_tipo'] === 'admin' || $_SESSION['usuario_tipo'] === 'audatex'): ?>
            <!-- Painel Audatex -->
            <div class="row my-4">
                <div class="col-md-8">
                    <h1 class="text-warning">
                        <i class="bi bi-tools"></i> Painel Audatex
                    </h1>
                    <p class="lead">Controle de cotações e ordens de serviço</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="os_pendentes_audatex.php" class="btn btn-warning btn-lg">
                        <i class="bi bi-list-check"></i> Ver OS Pendentes
                    </a>
                </div>
            </div>
            
            <!-- Cards de status Audatex -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card bg-danger text-white shadow">
                        <div class="card-body">
                            <h5 class="card-title">Para Cotar</h5>
                            <h2 class="display-4"><?= $os_para_cotar ?></h2>
                            <small>OS aguardando cotação</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card bg-warning text-dark shadow">
                        <div class="card-body">
                            <h5 class="card-title">Em Cotação</h5>
                            <h2 class="display-4"><?= contarOSStatusAudatex($conn, 'em_cotacao') ?></h2>
                            <small>Em processamento</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card bg-secondary text-white shadow">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-list-check"></i> Total de OS</h5>
                            <h2 class="display-4"><?= $total_os ?></h2>
                            <a href="minhas_os.php" class="text-white">Ver todas</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif ?>
            <div class="row mb-4">
                <div class="col-md-8">
                    <h1><i class="bi bi-car-front"></i> Minhas OS</h1>
                    <p class="lead">Acompanhe o status das suas ordens de serviço</p>
                </div>
                
                <div class="col-md-4 text-end">
                    <a href="nova_os_veiculo.php" class="btn btn-success btn-lg">
                        <i class="bi bi-plus-circle"></i> Nova OS Veículo
                    </a>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-funnel"></i> Filtros</h6>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <?php if (!$is_audatex): ?>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">Todos os Status</option>
                                <option value="cotacao_pendente" <?= $status == 'cotacao_pendente' ? 'selected' : '' ?>>Pendente Cotação</option>
                                <option value="em_cotacao" <?= $status == 'em_cotacao' ? 'selected' : '' ?>>Em Cotação</option>
                                <option value="cotado" <?= $status == 'cotado' ? 'selected' : '' ?>>Cotado</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
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
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-filter"></i> Aplicar Filtros
                                </button>
                                <a href="minhas_os.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> Limpar Filtros
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Tabela de OS -->
            <div class="card shadow">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">
                        <i class="bi bi-list-ul"></i> Minhas Ordens de Serviço
                    </h6>
                    <span class="badge bg-light text-primary"><?= $total_os ?> OS</span>
                </div>
                <div class="card-body p-0">
                    <?php if ($total_os > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="tabela-minhas-os">
                            <thead class="table-light">
                                <tr>
                                    <th width="80">OS #</th>
                                    <th>Veículo</th>
                                    <th>Oficina</th>
                                    <th>Status</th>
                                    <th>Peças</th>
                                    <th>Data Abertura</th>
                                    <th>Prioridade</th>
                                    <th width="180">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($os = $result->fetch_assoc()): 
                                // Peças selecionadas
                                    $pecas_selecionadas = $os['pecas_selecionadas'] ?? '';
                                    
                                    // Status
                                    $status_classes = [
                                        'cotacao_pendente' => ['label' => 'Pendente Cotação', 'color' => 'warning'],
                                        'em_cotacao' => ['label' => 'Em Cotação', 'color' => 'info'],
                                        'cotado' => ['label' => 'Cotado', 'color' => 'secondary']
                                    ];
                                    $status_info = $status_classes[$os['status_os']] ?? ['label' => 'Desconhecido', 'color' => 'secondary'];
                                    
                                    // Prioridade
                                    $prioridades = [
                                        'baixa' => ['label' => 'Baixa', 'color' => 'success'],
                                        'media' => ['label' => 'Média', 'color' => 'warning'],
                                        'alta' => ['label' => 'Alta', 'color' => 'danger']
                                    ];
                                    $prio_info = $prioridades[$os['prioridade']] ?? ['label' => 'Baixa', 'color' => 'success'];
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
                                        <span class="badge bg-<?= $status_info['color'] ?> status-badge">
                                            <?= $status_info['label'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($pecas_selecionadas)): ?>
                                            <small title="<?= htmlspecialchars($pecas_selecionadas) ?>">
                                                <?= strlen($pecas_selecionadas) > 30 ? 
                                                   substr(htmlspecialchars($pecas_selecionadas), 0, 30) . '...' : 
                                                   htmlspecialchars($pecas_selecionadas) ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">Nenhuma</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y H:i', strtotime($os['data_abertura'])) ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $prio_info['color'] ?>">
                                            <?= $prio_info['label'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="detalhes_os.php?id=<?= $os['id'] ?>" 
                                               class="btn btn-info" title="Ver Detalhes">
                                                <i class="bi bi-eye-fill"></i>
                                            </a>
                                            
                                            <?php if ($is_audatex && $os['status_os'] == 'cotacao_pendente'): ?>
                                            <a href="iniciar_cotacao.php?id=<?= $os['id'] ?>" 
                                               class="btn btn-warning" title="Iniciar Cotação">
                                                <i class="bi bi-play-fill"></i> Cotar
                                            </a>
                                            <?php elseif ($is_audatex && $os['status_os'] == 'em_cotacao'): ?>
                                            <a href="finalizar_cotacao.php?id=<?= $os['id'] ?>" 
                                               class="btn btn-success" title="Finalizar Cotação">
                                                <i class="bi bi-check-circle"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox text-muted fs-1"></i>
                        <h5 class="mt-3">Nenhuma OS encontrada</h5>
                        <p class="text-muted">Não há ordens de serviço com os filtros atuais.</p>
                        <a href="minhas_os.php" class="btn btn-primary">
                            <i class="bi bi-filter-circle"></i> Limpar filtros
                        </a>
                        <a href="nova_os_veiculo.php" class="btn btn-success">
                            <i class="bi bi-plus-circle"></i> Nova OS
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i> 
                                <?php if ($is_audatex): ?>
                                Clique em "Cotar" para iniciar a cotação no sistema Audatex.
                                <?php else: ?>
                                Clique no ícone de olho para ver detalhes da OS.
                                <?php endif; ?>
                            </small>
                        </div>
                        <div class="col-md-6 text-end">
                            <?php if ($total_os > 0): ?>
                            <a href="exportar_minhas_os.php?<?= http_build_query($_GET) ?>" 
                               class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-download"></i> Exportar Excel
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
    $(document).ready(function() {
        $('#tabela-minhas-os').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json'
            },
            pageLength: 25,
            order: [[0, 'desc']], // Ordenar por ID decrescente (mais recentes primeiro)
            columnDefs: [
                { orderable: false, targets: [8] } // Coluna de ações não ordenável
            ]
        });
    });
    </script>
</body>
</html>
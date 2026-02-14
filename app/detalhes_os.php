<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/funcoes_os_veiculos.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Verificar se o ID da OS foi passado
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: minhas_os.php");
    exit;
}

$os_id = intval($_GET['id']);
$usuario_id = $_SESSION['usuario_id'];
$tipo_usuario = $_SESSION['usuario_tipo'];
$is_usuario = strpos($tipo_usuario, 'usuario') !== false;
$is_audatex = strpos($tipo_usuario, 'audatex') !== false || strpos($tipo_usuario, 'admin') !== false;

// Buscar dados da OS
$sql = "SELECT c.*, u.om as oficina_nome, u.nome as usuario_nome,
               GROUP_CONCAT(DISTINCT p.nome SEPARATOR ', ') as pecas_selecionadas,
               TIMESTAMPDIFF(HOUR, c.data_abertura, NOW()) as horas_aguardando,
               c.data_cotacao, c.total_pecas, c.observacoes_audatex,
               c.data_aprovacao, c.data_conclusao
        FROM chamados c
        JOIN usuarios u ON c.id_usuario_abriu = u.id
        LEFT JOIN os_itens_selecionados ois ON c.id = ois.os_id
        LEFT JOIN os_pecas_cadastradas p ON ois.peca_id = p.id
        WHERE c.id = ?
        GROUP BY c.id";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $os_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: minhas_os.php");
    exit;
}

$os = $result->fetch_assoc();

// Verificar permissão (oficina só vê suas próprias OS, audatex vê todas)
if ($is_usuario && $os['id_usuario_abriu'] != $usuario_id) {
    header("Location: minhas_os.php");
    exit;
}

// Buscar histórico da OS
$sql_historico = "SELECT h.*, u.nome as usuario_nome 
                 FROM historico_os_veiculos h
                 JOIN usuarios u ON h.usuario_id = u.id
                 WHERE h.chamado_id = ?
                 ORDER BY h.data_acao DESC";
$stmt_historico = $conn->prepare($sql_historico);
$stmt_historico->bind_param("i", $os_id);
$stmt_historico->execute();
$historico_result = $stmt_historico->get_result();

// BUSCAR FOTOS DA OS - CORRIGIDO: removido o filtro por tipo
$sql_fotos = "SELECT * FROM chamados_arquivos 
              WHERE chamado_id = ?
              ORDER BY data_upload ASC";
$stmt_fotos = $conn->prepare($sql_fotos);
if (!$stmt_fotos) {
    // Log do erro para debug
    error_log("Erro ao preparar query de fotos: " . $conn->error);
    $fotos = [];
} else {
    $stmt_fotos->bind_param("i", $os_id);
    $stmt_fotos->execute();
    $fotos_result = $stmt_fotos->get_result();
    $fotos = [];
    while ($foto = $fotos_result->fetch_assoc()) {
        $fotos[] = $foto;
    }
    $stmt_fotos->close();
}

// Status traduzidos
$status_labels = [
    'cotacao_pendente' => 'Pendente Cotação',
    'em_cotacao' => 'Em Cotação',
    'cotado' => 'Cotado'
];

// Cores dos status
$status_colors = [
    'cotacao_pendente' => 'warning',
    'em_cotacao' => 'info',
    'cotado' => 'secondary'
];

require_once 'header.php';
?>
    <style>
        .info-card {
            border-left: 4px solid #0d6efd;
            padding-left: 15px;
            margin-bottom: 20px;
        }
        .peca-item {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .foto-thumbnail {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 5px;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .foto-thumbnail:hover {
            transform: scale(1.03);
        }
        .modal-foto {
            max-width: 90vw;
            max-height: 90vh;
        }
        .fotos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .foto-card {
            position: relative;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            overflow: hidden;
        }
        .foto-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px;
            font-size: 12px;
            text-align: center;
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
                    <h1>
                        <i class="bi bi-file-text"></i> OS #<?= $os['id'] ?>
                        <span class="badge bg-<?= $status_colors[$os['status_os']] ?>">
                            <?= $status_labels[$os['status_os']] ?>
                        </span>
                    </h1>
                    <p class="lead">Detalhes completos da ordem de serviço</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="minhas_os.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Voltar
                    </a>
                    <?php if ($is_audatex && $os['status_os'] == 'cotacao_pendente'): ?>
                    <a href="iniciar_cotacao.php?id=<?= $os['id'] ?>" class="btn btn-warning">
                        <i class="bi bi-play-fill"></i> Iniciar Cotação
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Alertas -->
            <?php if ($os['horas_aguardando'] > 24 && $os['status_os'] == 'cotacao_pendente'): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> 
                <strong>ATENÇÃO:</strong> Esta OS está atrasada! Aguardando há <?= $os['horas_aguardando'] ?> horas.
            </div>
            <?php endif; ?>
            
            <!-- Informações Principais -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="bi bi-car-front"></i> Informações do Veículo</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Placa:</strong><br>
                                    <span class="fs-5"><?= htmlspecialchars($os['placa_veiculo']) ?></span>
                                </div>
                                <div class="col-md-6">
                                    <strong>Modelo:</strong><br>
                                    <?= htmlspecialchars($os['marca_veiculo']) ?> 
                                    <?= htmlspecialchars($os['modelo_veiculo']) ?>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Ano:</strong><br>
                                    <?= htmlspecialchars($os['ano_veiculo']) ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Cor:</strong><br>
                                    <?= htmlspecialchars($os['cor_veiculo']) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="bi bi-building"></i> Informações da OS</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Oficina:</strong><br>
                                    <?= htmlspecialchars($os['oficina_nome']) ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Aberto por:</strong><br>
                                    <?= htmlspecialchars($os['usuario_nome']) ?>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Data Abertura:</strong><br>
                                    <?= date('d/m/Y H:i', strtotime($os['data_abertura'])) ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Prioridade:</strong><br>
                                    <?php
                                    $prioridade_labels = [
                                        'baixa' => '<span class="badge bg-success">Baixa</span>',
                                        'media' => '<span class="badge bg-warning">Média</span>',
                                        'alta' => '<span class="badge bg-danger">Alta</span>'
                                    ];
                                    echo $prioridade_labels[$os['prioridade']] ?? '<span class="badge bg-success">Baixa</span>';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- FOTOS DO VEÍCULO -->
            <?php if (!empty($fotos)): ?>
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0">
                                <i class="bi bi-images"></i> Fotos do Veículo
                                <span class="badge bg-light text-dark ms-2"><?= count($fotos) ?></span>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="fotos-grid">
                                <?php foreach ($fotos as $index => $foto): 
                                    // Verificar se o caminho existe
                                    $caminho = htmlspecialchars($foto['caminho_arquivo']);
                                    $nome = htmlspecialchars($foto['nome_arquivo']);
                                    
                                    // Verificar se o arquivo existe fisicamente
                                    $caminho_completo = $_SERVER['DOCUMENT_ROOT'] . '/OSAudatex/app/' . ltrim($caminho, '/');
                                    $caminho_completo = str_replace('//', '/', $caminho_completo);
                                    
                                    if (file_exists($caminho_completo)) {
                                        $url_foto = $caminho;
                                    } else {
                                        // Se não encontrar, usar uma imagem placeholder
                                        $url_foto = 'assets/imagens/sem-imagem.jpg';
                                    }
                                ?>
                                <div class="foto-card">
                                    <img src="<?= $url_foto ?>" 
                                         class="foto-thumbnail" 
                                         alt="Foto <?= $index + 1 ?>"
                                         data-bs-toggle="modal" 
                                         data-bs-target="#modalFoto"
                                         data-foto-src="<?= $url_foto ?>"
                                         data-foto-nome="<?= $nome ?>">
                                    <div class="foto-overlay">
                                        <?= $nome ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Peças Selecionadas -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-secondary text-white">
                            <h6 class="mb-0"><i class="bi bi-tools"></i> Peças Selecionadas</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($os['pecas_selecionadas'])): ?>
                                <div class="peca-item">
                                    <?= htmlspecialchars($os['pecas_selecionadas']) ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Nenhuma peça selecionada</p>
                            <?php endif; ?>
                            
                            <?php if (!empty($os['observacoes'])): ?>
                            <hr>
                            <h6><i class="bi bi-pencil"></i> Observações:</h6>
                            <p><?= nl2br(htmlspecialchars($os['observacoes'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($os['status_os'] == 'cotado' || $os['status_os'] == 'aprovado' || $os['status_os'] == 'concluido'): ?>
            <!-- Informações de Cotação -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-dark text-white">
                            <h6 class="mb-0"><i class="bi bi-cash-coin"></i> Informações de Cotação</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>Data da Cotação:</strong><br>
                                    <?= $os['data_cotacao'] ? date('d/m/Y H:i', strtotime($os['data_cotacao'])) : 'Não cotado' ?>
                                </div>
                                <div class="col-md-4">
                                    <strong>Valor Total:</strong><br>
                                    <?php if ($os['total_pecas']): ?>
                                        R$ <?= number_format($os['total_pecas'], 2, ',', '.') ?>
                                    <?php else: ?>
                                        Não informado
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <strong>Status Atual:</strong><br>
                                    <span class="badge bg-<?= $status_colors[$os['status_os']] ?>">
                                        <?= $status_labels[$os['status_os']] ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if (!empty($os['observacoes_audatex'])): ?>
                            <hr>
                            <strong>Observações da Cotação:</strong><br>
                            <p><?= nl2br(htmlspecialchars($os['observacoes_audatex'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Histórico -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="bi bi-clock-history"></i> Histórico da OS</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($historico_result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Data/Hora</th>
                                            <th>Usuário</th>
                                            <th>Ação</th>
                                            <th>Detalhes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($historico = $historico_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($historico['data_acao'])) ?></td>
                                            <td><?= htmlspecialchars($historico['usuario_nome']) ?></td>
                                            <td><?= htmlspecialchars($historico['acao']) ?></td>
                                            <td><?= nl2br(htmlspecialchars($historico['detalhes'])) ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <p class="text-muted">Nenhum registro no histórico</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Botões de Ação -->
            <?php if ($is_audatex || $is_usuario): ?>
            <div class="row">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-gear"></i> Ações</h6>
                        </div>
                        <div class="card-body">
                            <div class="btn-group" role="group">
                                <a href="minhas_os.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Voltar para Lista
                                </a>
                                
                                <?php if ($is_audatex): ?>
                                    <?php if ($os['status_os'] == 'cotacao_pendente'): ?>
                                    <a href="iniciar_cotacao.php?id=<?= $os['id'] ?>" class="btn btn-warning">
                                        <i class="bi bi-play-fill"></i> Iniciar Cotação
                                    </a>
                                    <?php elseif ($os['status_os'] == 'em_cotacao'): ?>
                                    <a href="finalizar_cotacao.php?id=<?= $os['id'] ?>" class="btn btn-success">
                                        <i class="bi bi-check-circle"></i> Finalizar Cotação
                                    </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <!-- Botão para imprimir -->
                                <button onclick="window.print()" class="btn btn-outline-secondary">
                                    <i class="bi bi-printer"></i> Imprimir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Modal para visualização ampliada das fotos -->
    <div class="modal fade" id="modalFoto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFotoLabel">Foto do Veículo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalFotoImg" src="" class="img-fluid modal-foto" alt="Foto ampliada">
                    <p class="mt-3" id="modalFotoNome"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <a id="downloadFoto" href="#" class="btn btn-primary" download>
                        <i class="bi bi-download"></i> Baixar
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Configurar modal para mostrar foto ampliada
    document.addEventListener('DOMContentLoaded', function() {
        var modalFoto = document.getElementById('modalFoto');
        var modalFotoImg = document.getElementById('modalFotoImg');
        var modalFotoLabel = document.getElementById('modalFotoLabel');
        var modalFotoNome = document.getElementById('modalFotoNome');
        var downloadFoto = document.getElementById('downloadFoto');
        
        if (modalFoto) {
            modalFoto.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var fotoSrc = button.getAttribute('data-foto-src');
                var fotoNome = button.getAttribute('data-foto-nome');
                
                modalFotoImg.src = fotoSrc;
                modalFotoImg.alt = fotoNome;
                modalFotoNome.textContent = fotoNome;
                downloadFoto.href = fotoSrc;
                downloadFoto.download = fotoNome;
            });
        }
    });
    
    // Auto-refresh a cada 2 minutos para atualizar status
    setTimeout(function() {
        location.reload();
    }, 2 * 60 * 1000);
    </script>
</body>
</html>
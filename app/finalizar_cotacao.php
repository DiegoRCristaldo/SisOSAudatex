<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/funcoes_os_veiculos.php';
require_once 'includes/funcoes_cotacao.php';

// Verificar permissões
$tipo_usuario = $_SESSION['usuario_tipo'] ?? '';
$os_id = validarPermissaoAudatex($tipo_usuario, $_GET['id'] ?? 0);
$audatex_id = $_SESSION['usuario_id'];

// Buscar dados da OS
$os = obterInformacoesOS($conn, $os_id, 'em_cotacao', $audatex_id);

if (!$os) {
    header("Location: os_pendentes_audatex.php?status=em_cotacao");
    exit;
}

// Buscar itens já cotados
$itens_result = obterItensCotadosOS($conn, $os_id);

// Calcular totais
$totais = calcularTotaisOS($conn, $os_id);

// Processar o formulário de finalização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simplesmente atualizar status para "cotado" com data
    $sql_update = "UPDATE chamados SET 
                   status_os = 'cotado',
                   data_cotacao = NOW()
                   WHERE id = ?";
    
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("i", $os_id);
    
    if ($stmt_update->execute()) {
        // Registrar no histórico
        registrarHistoricoOS($conn, $os_id, $audatex_id, 'Consulta Finalizada', 
                            "Consulta de valores das peças finalizada. Total: R$ " . number_format($totais['total_pecas'], 2, ',', '.'));
        
        // Redirecionar com mensagem de sucesso
        $_SESSION['msg_sucesso'] = "Consulta de valores finalizada!";
        header("Location: os_pendentes_audatex.php?status=cotado");
        exit;
    } else {
        $erro = "Erro ao finalizar consulta: " . $stmt_update->error;
    }
}

require_once 'header.php';
?>
    <style>
        .total-row {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .header-veiculo {
            background-color: #e3f2fd;
            border-left: 4px solid #2196F3;
        }
        .font-monospace {
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body class="d-flex flex-row">
    <?php require_once 'sidebar.php'; ?>
    
    <main id="content" class="p-4 w-100">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-10 mx-auto">
                    <div class="card shadow-lg">
                        <div class="card-header bg-success text-white">
                            <h4 class="mb-0"><i class="bi bi-check-circle"></i> Finalizar Consulta - OS #<?= $os['id'] ?></h4>
                        </div>
                        
                        <div class="card-body">
                            <?php if (isset($erro)): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> <?= $erro ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Resumo -->
                            <div class="alert alert-info mb-4">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Veículo:</strong> <?= htmlspecialchars($os['placa_veiculo']) ?><br>
                                        <strong>Oficina:</strong> <?= htmlspecialchars($os['oficina_nome']) ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Código Audatex:</strong> <?= htmlspecialchars($os['codigo_cotacao_audatex']) ?><br>
                                        <strong>Iniciada em:</strong> <?= date('d/m/Y H:i', strtotime($os['data_inicio_cotacao'])) ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Lista de Peças Consultadas -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="bi bi-list-check"></i> Peças Consultadas (<?= $totais['qtd_itens'] ?> itens)</h5>
                                </div>
                                <div class="card-body">
                                    <?= gerarTabelaItensCotados($itens_result) ?>
                                </div>
                            </div>
                            
                            <!-- Formulário de Confirmação -->
                            <form method="POST">
                                <div class="alert alert-success">
                                    <h6><i class="bi bi-check-circle"></i> Confirmação</h6>
                                    <p class="mb-3">
                                        Todas as peças foram consultadas no sistema Audatex e os valores estão corretos.
                                        Ao finalizar, a OS será enviada para a oficina com os valores consultados.
                                    </p>
                                    
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="confirmacao_final" required>
                                        <label class="form-check-label" for="confirmacao_final">
                                            Confirmo que os valores das peças estão corretos e posso finalizar esta consulta.
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="os_pendentes_audatex.php?status=em_cotacao" class="btn btn-secondary">
                                        <i class="bi bi-x-circle"></i> Voltar
                                    </a>
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="bi bi-check-circle"></i> Finalizar Consulta
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="card-footer text-muted">
                            <div class="row">
                                <div class="col-md-6">
                                    <small>
                                        <i class="bi bi-clock"></i> 
                                        Tempo de consulta: 
                                        <?php 
                                        $inicio = new DateTime($os['data_inicio_cotacao']);
                                        $agora = new DateTime();
                                        $diferenca = $agora->diff($inicio);
                                        echo $diferenca->h . 'h ' . $diferenca->i . 'min';
                                        ?>
                                    </small>
                                </div>
                                <div class="col-md-6 text-end">
                                    <small>
                                        Operador: <?= htmlspecialchars($_SESSION['usuario_nome']) ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/funcoes_os_veiculos.php';

$msg = '';
$msg_type = '';

// Buscar peças cadastradas
$sql_pecas = "SELECT * FROM os_pecas_cadastradas WHERE ativo = 1 ORDER BY categoria, nome";
$result_pecas = $conn->query($sql_pecas);
$pecas = [];
while ($row = $result_pecas->fetch_assoc()) {
    $pecas[$row['categoria']][] = $row;
}

// Processar o formulário - MODIFICADO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coletar dados
    $dados = [
        'prioridade' => $_POST['prioridade'] ?? 'baixa',
        'placa' => strtoupper(trim($_POST['placa'])),
        'eb' => strtoupper(trim($_POST['eb'])),
        'marca' => trim($_POST['marca']),
        'modelo' => trim($_POST['modelo']),
        'ano' => intval($_POST['ano']),
        'cor' => trim($_POST['cor']),
        'chassi' => trim($_POST['chassi'])
    ];
    
    // Ajustar marca se for "outra"
    if ($dados['marca'] === 'outra') {
        $dados['marca'] = trim($_POST['marca_outra'] ?? '');
    }
    
    $itens_selecionados = $_POST['itens'] ?? [];
    
    // Processar peças manuais do JavaScript - NOVO
    $pecas_manuais_array = [];
    $total_pecas_manuais = intval($_POST['total_pecas_manuais'] ?? 0);
    
    for ($i = 0; $i < $total_pecas_manuais; $i++) {
        if (isset($_POST['peca_manual_' . $i])) {
            $peca_manual = trim($_POST['peca_manual_' . $i]);
            if (!empty($peca_manual)) {
                $pecas_manuais_array[] = $peca_manual;
            }
        }
    }
    
    // Validar dados
    $erros = validarDadosOS($dados);
    
    // Validar peças
    if (empty($itens_selecionados) && empty($pecas_manuais_array)) {
        $erros[] = "Selecione pelo menos uma peça da lista OU informe peças manualmente!";
    }
    
    // Debug - REMOVER APÓS TESTES
    error_log("DEBUG - Itens selecionados: " . print_r($itens_selecionados, true));
    error_log("DEBUG - Peças manuais: " . print_r($pecas_manuais_array, true));
    
    if (!empty($erros)) {
        $msg = implode("<br>", $erros);
        $msg_type = 'danger';
    } else {
        try {
            // Processar OS usando função
            $os_id = processarOSVeiculo($conn, $dados, $itens_selecionados, $pecas_manuais_array, $_FILES['fotos'] ?? []);
            
            $msg = "OS cadastrada com sucesso! Aguarde a cotação Audatex.";
            $msg_type = 'success';
            
            header("refresh:3;url=minhas_os.php");
            exit;
            
        } catch (Exception $e) {
            $msg = "Erro ao salvar OS: " . $e->getMessage();
            $msg_type = 'danger';
            error_log("Erro ao processar OS: " . $e->getMessage());
        }
    }
}

require_once 'header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova OS Veículo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .peca-card:hover {
            border-color: #0d6efd;
            cursor: pointer;
        }
        .peca-card.selected {
            border-color: #198754;
            background-color: #f8f9fa;
        }
        .upload-area {
            border: 2px dashed #6c757d;
            border-radius: 5px;
            padding: 40px;
            text-align: center;
            background-color: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-area:hover {
            background-color: #e9ecef;
            border-color: #0d6efd;
        }
        .foto-preview {
            position: relative;
        }
        .btn-remover-foto {
            position: absolute;
            top: 5px;
            right: 5px;
            z-index: 10;
        }
        .tab-content {
            padding-top: 20px;
        }
    </style>
</head>
<body class="d-flex flex-row">
    <?php require_once 'sidebar.php'; ?>
    
    <main id="content" class="p-4 flex-grow-1">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col">
                    <h1><i class="bi bi-car-front"></i> Nova Ordem de Serviço</h1>
                    <p class="lead">Preencha os dados do veículo para solicitar cotação Audatex</p>
                </div>
                <div class="col-auto">
                    <a href="minhas_os.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Voltar
                    </a>
                </div>
            </div>
            
            <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show" role="alert">
                <?= $msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <form method="post" enctype="multipart/form-data" id="form-os">
                <ul class="nav nav-tabs mb-3" id="osTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="dados-tab" data-bs-toggle="tab" data-bs-target="#dados" type="button">
                            <i class="bi bi-car-front"></i> Dados do Veículo
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="itens-tab" data-bs-toggle="tab" data-bs-target="#itens" type="button">
                            <i class="bi bi-list-check"></i> Peças/Itens
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="fotos-tab" data-bs-toggle="tab" data-bs-target="#fotos" type="button">
                            <i class="bi bi-images"></i> Fotos
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="revisao-tab" data-bs-toggle="tab" data-bs-target="#revisao" type="button">
                            <i class="bi bi-check-circle"></i> Revisão
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content border-start border-end border-bottom p-4">
                    
                    <!-- TAB 1: Dados do Veículo -->
                    <div class="tab-pane fade show active" id="dados" role="tabpanel">
                        <?php include 'includes/form_dados_veiculo.php'; ?>
                    </div>
                    
                    <!-- TAB 2: Peças/Itens -->
                    <div class="tab-pane fade" id="itens" role="tabpanel">
                        <?php include 'includes/form_pecas_itens.php'; ?>
                    </div>
                    
                    <!-- TAB 3: Fotos -->
                    <div class="tab-pane fade" id="fotos" role="tabpanel">
                        <?php include 'includes/form_fotos.php'; ?>
                    </div>
                    
                    <!-- TAB 4: Revisão -->
                    <div class="tab-pane fade" id="revisao" role="tabpanel">
                        <?php include 'includes/form_revisao.php'; ?>
                    </div>
                </div>
            </form>
        </div>
    </main>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php require 'assets/nova_os_veiculo_js.php' ?>
</body>
</html>
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
$os = obterInformacoesOS($conn, $os_id, 'cotacao_pendente');

if (!$os) {
    header("Location: os_pendentes_audatex.php");
    exit;
}

// Buscar peças selecionadas
$pecas_result = obterPecasSelecionadasOS($conn, $os_id);

// Processar o formulário de início de cotação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Iniciar transação para garantir consistência
    $conn->begin_transaction();
    
    try {
        // 1. Atualizar status da OS para "em_cotacao"
        $sql_update = "UPDATE chamados SET 
                       status_os = 'em_cotacao',
                       operador_audatex_id = ?,
                       data_inicio_cotacao = NOW()
                       WHERE id = ?";
        
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $audatex_id, $os_id);
        $stmt_update->execute();
        
        // 2. Inserir peças na tabela de cotação
        $total_geral = 0;
        
        if (isset($_POST['pecas'])) {
            foreach ($_POST['pecas'] as $peca_id => $peca_data) {
                $valor_unitario = str_replace(['.', ','], ['', '.'], $peca_data['valor_unitario']);
                $quantidade = floatval($peca_data['quantidade']);
                $valor_total = $valor_unitario * $quantidade;
                $total_geral += $valor_total;
                
                $sql_item = "INSERT INTO os_itens_cotacao 
                            (chamado_id, tipo, codigo_audatex, descricao, 
                             quantidade, valor_unitario, valor_total, acao)
                            VALUES (?, 'peca', ?, ?, ?, ?, ?, 'substituir')";
                
                $stmt_item = $conn->prepare($sql_item);
                $descricao = $peca_data['descricao'] ?? '';
                $codigo_audatex = $peca_data['codigo_audatex'] ?? '';
                
                $stmt_item->bind_param("issddd", 
                    $os_id, 
                    $codigo_audatex,
                    $descricao,
                    $quantidade,
                    $valor_unitario,
                    $valor_total
                );
                $stmt_item->execute();
            }
        }
        
        // 3. Atualizar totais na OS
        $sql_update_totais = "UPDATE chamados SET 
                              total_pecas = ?,
                              total_geral = ?,
                              codigo_cotacao_audatex = ?
                              WHERE id = ?";
        
        $stmt_totais = $conn->prepare($sql_update_totais);
        $codigo_cotacao = $_POST['codigo_cotacao_audatex'] ?? '';
        $stmt_totais->bind_param("ddss", $total_geral, $total_geral, $codigo_cotacao, $os_id);
        $stmt_totais->execute();
        
        // 4. Registrar no histórico
        registrarHistoricoOS($conn, $os_id, $audatex_id, 'Início de Consulta', 
                            "Operador iniciou consulta de valores das peças. Total: R$ " . number_format($total_geral, 2, ',', '.'));
        
        // Confirmar transação
        $conn->commit();
        
        // Redirecionar com mensagem de sucesso
        $_SESSION['msg_sucesso'] = "Consulta iniciada com sucesso! Total: R$ " . number_format($total_geral, 2, ',', '.');
        header("Location: os_pendentes_audatex.php?status=em_cotacao");
        exit;
        
    } catch (Exception $e) {
        // Rollback em caso de erro
        $conn->rollback();
        $erro = "Erro ao iniciar consulta: " . $e->getMessage();
    }
}

require_once 'header.php';
?>
    <style>
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,.025);
        }
        .valor-input {
            text-align: right;
        }
        .total-row {
            background-color: #f8f9fa;
            font-weight: bold;
            font-size: 1.1em;
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
                <div class="col-md-12">
                    <div class="card shadow-lg">
                        <div class="card-header bg-warning text-dark">
                            <h4 class="mb-0"><i class="bi bi-search"></i> Consulta de Valores - OS #<?= $os['id'] ?></h4>
                        </div>
                        
                        <div class="card-body">
                            <?php if (isset($erro)): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> <?= $erro ?>
                            </div>
                            <?php endif; ?>
                            
                            <?= gerarCabecalhoVeiculo($os) ?>
                            
                            <!-- Descrição do Problema -->
                            <?php if (!empty($os['descricao_problema'])): ?>
                            <div class="alert alert-info mb-4">
                                <h6><i class="bi bi-exclamation-triangle"></i> Descrição do Problema:</h6>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($os['descricao_problema'])) ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" id="formCotacao">
                                <!-- Tabela de Peças -->
                                <div class="card mb-4">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0"><i class="bi bi-tools"></i> Peças para Consulta de Valores</h5>
                                    </div>
                                    <div class="card-body">
                                        <?= gerarTabelaPecasParaCotacao($pecas_result, 'edicao') ?>
                                        
                                        <div class="mt-3">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label class="form-label"><strong>Código da Consulta Audatex:</strong></label>
                                                    <input type="text" name="codigo_cotacao_audatex" 
                                                           class="form-control"
                                                           placeholder="Ex: AUD-<?= date('Y') ?>-<?= str_pad($os['id'], 5, '0', STR_PAD_LEFT) ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label"><strong>Observações:</strong></label>
                                                    <input type="text" name="observacoes_audatex" 
                                                           class="form-control"
                                                           placeholder="Observações sobre os valores consultados...">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Instruções -->
                                <div class="alert alert-secondary mb-4">
                                    <h6><i class="bi bi-info-circle"></i> Instruções:</h6>
                                    <ol class="mb-0">
                                        <li>Consulte o sistema Audatex para obter os valores atualizados das peças</li>
                                        <li>Preencha o código Audatex de cada peça</li>
                                        <li>Insira o valor unitário conforme consultado</li>
                                        <li>Verifique se as quantidades estão corretas</li>
                                        <li>O sistema calculará automaticamente o total</li>
                                        <li>Clique em "Iniciar Consulta" para registrar</li>
                                    </ol>
                                </div>
                                
                                <!-- Confirmação -->
                                <div class="mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="confirmacao" required>
                                        <label class="form-check-label" for="confirmacao">
                                            Confirmo que consultei os valores no sistema Audatex e as informações acima estão corretas.
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Botões -->
                                <div class="d-flex justify-content-between">
                                    <a href="os_pendentes_audatex.php" class="btn btn-secondary">
                                        <i class="bi bi-x-circle"></i> Cancelar
                                    </a>
                                    <div>
                                        <span class="me-3 fw-bold">Total: <span id="total-exibicao">R$ 0,00</span></span>
                                        <button type="submit" class="btn btn-warning btn-lg">
                                            <i class="bi bi-search"></i> Iniciar Consulta de Valores
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <div class="card-footer text-muted">
                            <div class="row">
                                <div class="col-md-6">
                                    <small>
                                        <i class="bi bi-clock-history"></i> 
                                        Status atual: 
                                        <span class="badge bg-warning">Aguardando Consulta</span>
                                    </small>
                                </div>
                                <div class="col-md-6 text-end">
                                    <small>
                                        Operador: <?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Audatex') ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script>
    // Função para formatar valor em real
    function formatarValor(input) {
        let value = input.value.replace(/\D/g, '');
        value = (value / 100).toFixed(2) + '';
        value = value.replace(".", ",");
        value = value.replace(/(\d)(\d{3})(\d{3}),/g, "$1.$2.$3,");
        value = value.replace(/(\d)(\d{3}),/g, "$1.$2,");
        input.value = value;
        
        // Disparar evento de mudança para calcular totais
        const event = new Event('change');
        input.dispatchEvent(event);
    }
    
    // Converter valor formatado para número
    function valorParaNumero(valorFormatado) {
        if (!valorFormatado) return 0;
        let valor = valorFormatado.toString().replace(/\./g, '').replace(',', '.');
        return parseFloat(valor) || 0;
    }
    
    // Calcular total de uma peça
    function calcularTotalPeca(element) {
        const linha = element.closest('tr');
        const quantidade = linha.querySelector('.quantidade').value;
        const valorUnitario = linha.querySelector('.valor-unitario').value;
        const valorTotalInput = linha.querySelector('.valor-total');
        
        const qtd = parseFloat(quantidade) || 0;
        const valor = valorParaNumero(valorUnitario);
        const total = qtd * valor;
        
        valorTotalInput.value = total.toFixed(2).replace('.', ',');
        valorTotalInput.value = valorTotalInput.value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        
        calcularTotalPecas();
    }
    
    // Calcular total de todas as peças
    function calcularTotalPecas() {
        let total = 0;
        document.querySelectorAll('.valor-total').forEach(input => {
            total += valorParaNumero(input.value);
        });
        
        // Atualizar campo de total
        const totalInput = document.getElementById('total-pecas');
        const valorFormatado = total.toFixed(2).replace('.', ',')
                                         .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        totalInput.value = valorFormatado;
        
        // Atualizar exibição
        document.getElementById('total-exibicao').textContent = 'R$ ' + valorFormatado;
    }
    
    // Prevenir envio do formulário com Enter
    document.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && e.target.type !== 'textarea' && e.target.type !== 'text') {
            e.preventDefault();
        }
    });
    
    // Validar formulário antes de enviar
    document.getElementById('formCotacao').addEventListener('submit', function(e) {
        let temErro = false;
        let mensagens = [];
        
        // Verificar se todos os valores unitários foram preenchidos
        const valores = document.querySelectorAll('.valor-unitario');
        valores.forEach((input, index) => {
            if (!input.value || valorParaNumero(input.value) <= 0) {
                temErro = true;
                input.classList.add('is-invalid');
                if (!mensagens.includes('Preencha todos os valores unitários')) {
                    mensagens.push('Preencha todos os valores unitários');
                }
            } else {
                input.classList.remove('is-invalid');
            }
        });
        
        // Verificar códigos Audatex
        const codigos = document.querySelectorAll('[name*="codigo_audatex"]');
        codigos.forEach((input, index) => {
            if (!input.value.trim()) {
                temErro = true;
                input.classList.add('is-invalid');
                if (!mensagens.includes('Preencha todos os códigos Audatex')) {
                    mensagens.push('Preencha todos os códigos Audatex');
                }
            } else {
                input.classList.remove('is-invalid');
            }
        });
        
        if (temErro) {
            e.preventDefault();
            alert('Erros encontrados:\n\n' + mensagens.join('\n'));
        }
    });
    
    // Inicializar cálculos
    document.addEventListener('DOMContentLoaded', function() {
        calcularTotalPecas();
        
        // Adicionar foco automático no primeiro campo de valor
        const primeiroValor = document.querySelector('.valor-unitario');
        if (primeiroValor) {
            primeiroValor.focus();
        }
    });
    
    // Permitir navegação com Tab entre campos de valor
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Tab') {
            const valores = document.querySelectorAll('.valor-unitario');
            const indexAtual = Array.from(valores).indexOf(document.activeElement);
            
            if (indexAtual !== -1 && indexAtual < valores.length - 1) {
                e.preventDefault();
                valores[indexAtual + 1].focus();
            }
        }
    });
    </script>
</body>
</html>
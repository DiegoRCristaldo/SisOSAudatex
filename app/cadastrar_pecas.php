<?php
session_start();
require_once 'includes/db.php';

$msg = '';
$msg_type = '';

// Definir categorias padrão
$categorias = [
    'portas-dianteiras' => 'Portas Dianteiras',
    'portas-traseiras' => 'Portas Traseiras',
    'carroceria-dianteira-externa' => 'Carroceria Dianteira Externa',
    'carroceria-central-externa' => 'Carroceria Central Externa',
    'carroceria-traseira-externa' => 'Carroceria Traseira Externa',
    'carroceria-dianteira-interna' => 'Carroceria Dianteira Interna',
    'carroceria-central-interna' => 'Carroceria Central Interna',
    'carroceria-traseira-interna' => 'Carroceria Traseira Interna',
    'carroceria-pintura' => 'Carroceria/ Pintura',
    'radiador-arcondicionado' => 'Radiador/ Ar Condicionado',
    'motor-gasolina' => 'Motor Gasolina 1.0 Ltr',
    'motor' => 'Motor 1.6 L',
    'motor-b4d' => 'Motor 1.0 L B4D',
    'motor-h4m' => 'Motor 1.6 Ltr H4M',
    'cambio-manual' => 'Câmbio Manual',
    'cambio-cvt-automatico' => 'Câmbio 4/CVT Automático',
    'escapamento' => 'Escapamento',
    'sistema-combustivel' => 'Sistema de Combustível',
    'suspensao-dianteira' => 'Suspensão Dianteira',
    'suspensao-traseira' => 'Suspensão Traseira',
    'painel-principal' => 'Painel Principal',
    'bancos-dianteiros' => 'Bancos Dianteiros',
    'bancos-traseiros' => 'Bancos Traseiros',
    'trabalho-adicional-alinham-medir' => 'Trabalho Adicional Alinham/Medir',
    'trabalho-adicional-motor-cambio' => 'Trabalho Adicional Motor/Câmbio'
];

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cadastrar nova peça
    if (isset($_POST['acao']) && $_POST['acao'] === 'cadastrar_peca') {
        $codigo = trim($_POST['codigo_referencia']);
        $nome = trim($_POST['nome']);
        $categoria = trim($_POST['categoria']);
        
        // Verificar se código já existe
        $sql_check = "SELECT id FROM os_pecas_cadastradas WHERE codigo_referencia = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("s", $codigo);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $msg = "Código já cadastrado!";
            $msg_type = 'danger';
        } else {
            $sql = "INSERT INTO os_pecas_cadastradas 
                    (codigo_referencia, nome, categoria, cadastrado_por) 
                    VALUES (?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", 
                $codigo, $nome, $categoria, $_SESSION['usuario_id']
            );
            
            if ($stmt->execute()) {
                $msg = "Peça cadastrada com sucesso!";
                $msg_type = 'success';
            } else {
                $msg = "Erro ao cadastrar peça: " . $stmt->error;
                $msg_type = 'danger';
            }
        }
    }
    
    // Editar peça
    if (isset($_POST['acao']) && $_POST['acao'] === 'editar_peca' && isset($_POST['peca_id'])) {
        $peca_id = intval($_POST['peca_id']);
        $codigo = trim($_POST['codigo_referencia']);
        $nome = trim($_POST['nome']);
        $categoria = trim($_POST['categoria']);
        
        // Verificar se código já existe (exceto para esta peça)
        $sql_check = "SELECT id FROM os_pecas_cadastradas 
                     WHERE codigo_referencia = ? AND id != ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("si", $codigo, $peca_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $msg = "Código já cadastrado para outra peça!";
            $msg_type = 'danger';
        } else {
            $sql = "UPDATE os_pecas_cadastradas SET 
                    codigo_referencia = ?,
                    nome = ?,
                    categoria = ?
                    WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", 
                $codigo, $nome, $categoria, $peca_id
            );
            
            if ($stmt->execute()) {
                $msg = "Peça atualizada com sucesso!";
                $msg_type = 'success';
            } else {
                $msg = "Erro ao atualizar peça: " . $stmt->error;
                $msg_type = 'danger';
            }
        }
    }
    
    // Adicionar nova categoria
    if (isset($_POST['acao']) && $_POST['acao'] === 'adicionar_categoria') {
        $nova_categoria = trim($_POST['nova_categoria']);
        $nome_categoria = trim($_POST['nome_categoria']);
        
        if (!empty($nova_categoria) && !empty($nome_categoria)) {
            // Verificar se categoria já existe
            if (!isset($categorias[$nova_categoria])) {
                $categorias[$nova_categoria] = $nome_categoria;
                $msg = "Categoria adicionada com sucesso!";
                $msg_type = 'success';
            } else {
                $msg = "Categoria já existe!";
                $msg_type = 'warning';
            }
        } else {
            $msg = "Preencha todos os campos da categoria!";
            $msg_type = 'danger';
        }
    }
}

// Processar exclusão (GET)
if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $peca_id = intval($_GET['excluir']);
    
    // Verificar se peça está sendo usada em alguma OS
    $sql_check = "SELECT COUNT(*) as total FROM os_itens_selecionados WHERE peca_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $peca_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $row = $result_check->fetch_assoc();
    
    $sql = "DELETE FROM os_pecas_cadastradas WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $peca_id);
    
    if ($stmt->execute()) {
        $msg = "Peça excluída com sucesso!";
        $msg_type = 'success';
    } else {
        $msg = "Erro ao excluir peça: " . $stmt->error;
        $msg_type = 'danger';
    }
}

// Buscar peça para edição
$peca_editar = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $peca_id = intval($_GET['editar']);
    $sql = "SELECT * FROM os_pecas_cadastradas WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $peca_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $peca_editar = $result->fetch_assoc();
}

// Buscar peças cadastradas
$sql_pecas = "SELECT p.*, u.nome as cadastrado_por_nome 
              FROM os_pecas_cadastradas p
              LEFT JOIN usuarios u ON p.cadastrado_por = u.id
              ORDER BY p.categoria, p.nome";
$result_pecas = $conn->query($sql_pecas);

// Contar peças por categoria
$sql_categorias = "SELECT categoria, COUNT(*) as total 
                   FROM os_pecas_cadastradas 
                   WHERE ativo = 1 
                   GROUP BY categoria 
                   ORDER BY categoria";
$result_categorias = $conn->query($sql_categorias);

require_once 'header.php';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Peças</title>
    <link rel="stylesheet" href="assets/bootstrap-5.3.8-dist/css/bootstrap.min.css"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .categoria-badge {
            font-size: 0.8em;
            cursor: pointer;
        }
        .categoria-badge:hover {
            opacity: 0.8;
        }
        .form-control[readonly] {
            background-color: #f8f9fa;
        }
        .valor-input {
            text-align: right;
        }
    </style>
</head>
<body class="d-flex flex-row">
    <?php 
    $is_audatex = true;
    require_once 'sidebar.php'; 
    ?>
    
    <main id="content" class="p-4">
        <div class="container-fluid">
            <h1><i class="bi bi-tools"></i> Gerenciar Peças</h1>
            <p class="lead">Cadastre, edite e gerencie o catálogo de peças</p>
            
            <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show" role="alert">
                <?= $msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-8">
                    <!-- Formulário de cadastro/edição -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header <?= $peca_editar ? 'bg-warning text-dark' : 'bg-success text-white' ?>">
                            <h5 class="mb-0">
                                <i class="bi <?= $peca_editar ? 'bi-pencil' : 'bi-plus-circle' ?>"></i>
                                <?= $peca_editar ? 'Editar Peça' : 'Nova Peça' ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="acao" value="<?= $peca_editar ? 'editar_peca' : 'cadastrar_peca' ?>">
                                <?php if ($peca_editar): ?>
                                <input type="hidden" name="peca_id" value="<?= $peca_editar['id'] ?>">
                                <?php endif; ?>
                                
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Código Referência *</label>
                                        <input type="number" name="codigo_referencia" 
                                               class="form-control" required
                                               value="<?= $peca_editar['codigo_referencia'] ?? '' ?>">
                                        <small class="text-muted">Código único da peça</small>
                                    </div>
                                    
                                    <div class="col-md-8">
                                        <label class="form-label">Nome da Peça *</label>
                                        <input type="text" name="nome" 
                                               class="form-control" required
                                               value="<?= $peca_editar['nome'] ?? '' ?>"
                                               placeholder="Ex: Porta dianteira esquerda completa">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Categoria *</label>
                                        <select name="categoria" class="form-select" required>
                                            <option value="">Selecione...</option>
                                            <?php foreach ($categorias as $key => $nome): ?>
                                            <option value="<?= $key ?>" 
                                                <?= ($peca_editar['categoria'] ?? '') === $key ? 'selected' : '' ?>>
                                                <?= $nome ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <div class="d-flex justify-content-between">
                                            <?php if ($peca_editar): ?>
                                            <a href="cadastrar_pecas.php" class="btn btn-secondary">
                                                <i class="bi bi-x-circle"></i> Cancelar Edição
                                            </a>
                                            <?php endif; ?>
                                            <button type="submit" class="btn <?= $peca_editar ? 'btn-warning' : 'btn-success' ?>">
                                                <i class="bi <?= $peca_editar ? 'bi-save' : 'bi-save' ?>"></i>
                                                <?= $peca_editar ? 'Atualizar Peça' : 'Cadastrar Peça' ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Formulário para adicionar categoria -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-tags"></i> Adicionar Nova Categoria</h5>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="acao" value="adicionar_categoria">
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Código da Categoria</label>
                                        <input type="text" name="nova_categoria" 
                                               class="form-control"
                                               placeholder="Ex: sistema-eletrico">
                                        <small class="text-muted">Usar letras minúsculas e hífens</small>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Nome da Categoria</label>
                                        <input type="text" name="nome_categoria" 
                                               class="form-control"
                                               placeholder="Ex: Sistema Elétrico">
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-info">
                                            <i class="bi bi-plus-circle"></i> Adicionar Categoria
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Lista de peças cadastradas -->
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-list-ul"></i> Peças Cadastradas (<?= $result_pecas->num_rows ?>)</h5>
                            <div>
                                <a href="exportar_pecas.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-download"></i> Exportar
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="100">Código</th>
                                            <th>Nome</th>
                                            <th width="200">Categoria</th>
                                            <th width="150">Cadastrado por</th>
                                            <th width="100" class="text-center">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $result_pecas->data_seek(0); // Reset pointer
                                        while ($peca = $result_pecas->fetch_assoc()): 
                                        ?>
                                        <tr>
                                            <td>
                                                <code class="text-primary"><?= htmlspecialchars($peca['codigo_referencia']) ?></code>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($peca['nome']) ?></strong>
                                                <?php if ($peca['descricao']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars(substr($peca['descricao'], 0, 60)) ?>...</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info" title="<?= $categorias[$peca['categoria']] ?? $peca['categoria'] ?>">
                                                    <?= $categorias[$peca['categoria']] ?? ucfirst($peca['categoria']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if($peca['cadastrado_por_nome'] === null): ?>
                                                <span class="text-muted">Sistema</span>
                                                <?php else: ?>
                                                <?= htmlspecialchars($peca['cadastrado_por_nome']) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="cadastrar_pecas.php?editar=<?= $peca['id'] ?>" 
                                                    class="btn btn-outline-warning"
                                                    title="Editar">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="cadastrar_pecas.php?excluir=<?= $peca['id'] ?>" 
                                                    class="btn btn-outline-danger"
                                                    title="Excluir"
                                                    onclick="return confirm('Tem certeza que deseja excluir esta peça?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if ($result_pecas->num_rows === 0): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-tools text-muted fs-1"></i>
                                <h5 class="mt-3">Nenhuma peça cadastrada</h5>
                                <p class="text-muted">Comece cadastrando sua primeira peça usando o formulário acima.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer text-muted">
                            <small>
                                <i class="bi bi-info-circle"></i> 
                                Clique nos ícones de edição ou exclusão para gerenciar as peças.
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- Estatísticas -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-graph-up"></i> Estatísticas</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h6>Total de Peças Cadastradas:</h6>
                                <div class="display-6 fw-bold text-primary"><?= $result_pecas->num_rows ?></div>
                            </div>
                            
                            <hr>
                            
                        <div class="card shadow-sm">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0"><i class="bi bi-tag"></i> Peças por Categoria:</h5>
                            </div>
                            <ul class="list-group list-group-flush">
                                <?php 
                                $result_categorias->data_seek(0); // Reset pointer
                                while ($cat = $result_categorias->fetch_assoc()): 
                                ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><?= $categorias[$cat['categoria']] ?? ucfirst($cat['categoria']) ?></span>
                                    <span class="badge bg-primary rounded-pill"><?= $cat['total'] ?></span>
                                </li>
                                <?php endwhile; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
        
    </main>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    
    // Função para selecionar categoria ao clicar no badge
    function selecionarCategoria(categoria) {
        const select = document.querySelector('select[name="categoria"]');
        select.value = categoria;
        
        // Destacar a categoria selecionada
        document.querySelectorAll('.categoria-badge').forEach(badge => {
            badge.classList.remove('bg-success');
        });
        
        const badge = document.querySelector(`.categoria-badge[title="${categorias[categoria]}"]`);
        if (badge) {
            badge.classList.add('bg-success');
            
            // Remover destaque após 2 segundos
            setTimeout(() => {
                badge.classList.remove('bg-success');
            }, 2000);
        }
    }
    
    // Auto-foco no campo de código quando carregar
    document.addEventListener('DOMContentLoaded', function() {
        const codigoInput = document.querySelector('input[name="codigo_referencia"]');
        if (codigoInput && !codigoInput.value) {
            codigoInput.focus();
        }
        
        // Adicionar classe de validação nos campos obrigatórios
        const requiredInputs = document.querySelectorAll('input[required], select[required], textarea[required]');
        requiredInputs.forEach(input => {
            if (!input.value) {
                input.classList.add('is-invalid');
            }
            
            input.addEventListener('input', function() {
                if (this.value) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            });
        });
        
        // Scroll para o formulário se estiver editando
        <?php if ($peca_editar): ?>
        document.querySelector('.card-header.bg-warning').scrollIntoView({ behavior: 'smooth' });
        <?php endif; ?>
    });
    
    // Confirmar exclusão
    function confirmarExclusao(event) {
        if (!confirm('Tem certeza que deseja excluir esta peça?')) {
            event.preventDefault();
            return false;
        }
        return true;
    }
    </script>
</body>
</html>
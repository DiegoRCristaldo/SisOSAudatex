<?php
include 'includes/auth.php';
include 'includes/db.php';
require_once 'includes/funcoes_padrao.php';

// Definir período padrão (últimos 30 dias)
$data_inicio = date('Y-m-01'); // Primeiro dia do mês atual
$data_fim = date('Y-m-d');     // Data atual

// Processar filtros
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filtro_tipo = $_POST['filtro_tipo'] ?? 'personalizado';
    $data_inicio = $_POST['data_inicio'] ?? $data_inicio;
    $data_fim = $_POST['data_fim'] ?? $data_fim;
    
    // Ajustar datas baseado no tipo de filtro
    switch ($filtro_tipo) {
        case 'hoje':
            $data_inicio = date('Y-m-d');
            $data_fim = date('Y-m-d');
            break;
        case 'ontem':
            $data_inicio = date('Y-m-d', strtotime('-1 day'));
            $data_fim = date('Y-m-d', strtotime('-1 day'));
            break;
        case 'semana':
            $data_inicio = date('Y-m-d', strtotime('-7 days'));
            $data_fim = date('Y-m-d');
            break;
        case 'mes':
            $data_inicio = date('Y-m-01');
            $data_fim = date('Y-m-d');
            break;
        case 'ano':
            $data_inicio = date('Y-01-01');
            $data_fim = date('Y-m-d');
            break;
    }
}

// Buscar dados dos chamados
$query_chamados = "
    SELECT 
        c.*,
        u.nome as usuario_nome,
        u.nome_guerra,
        u.posto_graduacao,
        u.om as om,
        t.nome as tecnico_nome
    FROM chamados c
    LEFT JOIN usuarios u ON c.id_usuario_abriu = u.id
    LEFT JOIN usuarios t ON c.id_tecnico_responsavel = t.id
    WHERE DATE(c.data_abertura) BETWEEN ? AND ?
    ORDER BY c.data_abertura DESC
";

$stmt = $conn->prepare($query_chamados);
$stmt->bind_param('ss', $data_inicio, $data_fim);
$stmt->execute();
$result_chamados = $stmt->get_result();
$chamados = $result_chamados->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Estatísticas para os gráficos
$estatisticas = [
    'total_chamados' => 0,
    'por_status' => ['cotacao_pendente' => 0,'em_cotacao' => 0,'cotado' => 0],
    'por_prioridade' => ['baixa' => 0, 'media' => 0, 'alta' => 0],
    'por_dia' => [],
    'por_om' => []
];

$mapeamento_om = formatarOM();

// Processar estatísticas
foreach ($chamados as $chamado) {
    $estatisticas['total_chamados']++;
    
    // Por status
    $estatisticas['por_status'][$chamado['status_os']]++;
    
    // Por prioridade
    $estatisticas['por_prioridade'][$chamado['prioridade']]++;
    
    // Por dia - armazenar com timestamp para ordenação
    $timestamp = strtotime($chamado['data_abertura']);
    $data_key = date('Y-m-d', $timestamp);
    $data_label = date('d/m', $timestamp);
    $estatisticas['por_dia'][$data_key] = [
        'label' => $data_label,
        'valor' => ($estatisticas['por_dia'][$data_key]['valor'] ?? 0) + 1,
        'timestamp' => $timestamp
    ];
    
    // Por om - usando o campo do banco
    $om = $chamado['om'];
    $om_label = $mapeamento_om[$om];
    $estatisticas['por_om'][$om_label] = ($estatisticas['por_om'][$om_label] ?? 0) + 1;
}

$oms_esperadas = ['2°B Log','28°BI Mec','CIOU','11°Pel PE','Cmdo 11ªBda Inf Mec','Cia Cmdo 11ªBda Inf Mec','2ªCia Com','PMGU','EsPCEx'];
foreach ($oms_esperadas as $om) {
    if (!isset($estatisticas['por_om'][$om])) {
        $estatisticas['por_om'][$om] = 0;
    }
}

// Ordenar as datas cronologicamente
if (!empty($estatisticas['por_dia'])) {
    // Ordenar por timestamp (mais antigo primeiro)
    uasort($estatisticas['por_dia'], function($a, $b) {
        return $a['timestamp'] <=> $b['timestamp'];
    });
    
    // Criar arrays separados para labels e valores ordenados
    $labels_ordenados = [];
    $valores_ordenados = [];
    
    foreach ($estatisticas['por_dia'] as $item) {
        $labels_ordenados[] = $item['label'];
        $valores_ordenados[] = $item['valor'];
    }
    
    $estatisticas['por_dia_ordenado'] = [
        'labels' => $labels_ordenados,
        'valores' => $valores_ordenados
    ];
} else {
    $estatisticas['por_dia_ordenado'] = [
        'labels' => ['Nenhum'],
        'valores' => [0]
    ];
}

// Ordenar os arrays
ksort($estatisticas['por_om']);

// Preparar dados para os gráficos
function prepararDadosGrafico($dados) {
    $labels = [];
    $valores = [];
    
    foreach ($dados as $label => $valor) {
        $labels[] = $label;
        $valores[] = $valor;
    }
    
    return [
        'labels' => $labels,
        'valores' => $valores
    ];
}

$grafico_status = prepararDadosGrafico($estatisticas['por_status']);
$grafico_prioridade = prepararDadosGrafico($estatisticas['por_prioridade']);
$grafico_dia = $estatisticas['por_dia_ordenado']; // Usar dados ordenados
$grafico_om = prepararDadosGrafico($estatisticas['por_om']);

require 'header.php';
?>

</head>
<body class="bg-light">
    <div class="d-flex flex-row">
        <?php require_once 'sidebar.php' ?>
        <div class="container-fluid row mt-4 py-3">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>📊 Relatórios de Cotações</h1>
                    <a href="index.php" class="btn btn-secondary">⬅ Voltar ao Menu</a>
                </div>

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Filtros</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Tipo de Filtro</label>
                                <select name="filtro_tipo" class="form-select" id="filtroTipo">
                                    <option value="personalizado" <?= ($_POST['filtro_tipo'] ?? '') === 'personalizado' ? 'selected' : '' ?>>Data Personalizada</option>
                                    <option value="hoje" <?= ($_POST['filtro_tipo'] ?? '') === 'hoje' ? 'selected' : '' ?>>Hoje</option>
                                    <option value="ontem" <?= ($_POST['filtro_tipo'] ?? '') === 'ontem' ? 'selected' : '' ?>>Ontem</option>
                                    <option value="semana" <?= ($_POST['filtro_tipo'] ?? '') === 'semana' ? 'selected' : '' ?>>Últimos 7 Dias</option>
                                    <option value="mes" <?= ($_POST['filtro_tipo'] ?? '') === 'mes' ? 'selected' : '' ?>>Este Mês</option>
                                    <option value="ano" <?= ($_POST['filtro_tipo'] ?? '') === 'ano' ? 'selected' : '' ?>>Este Ano</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Data Início</label>
                                <input type="date" name="data_inicio" class="form-control" value="<?= $data_inicio ?>" id="dataInicio">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Data Fim</label>
                                <input type="date" name="data_fim" class="form-control" value="<?= $data_fim ?>" id="dataFim">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Cards de Estatísticas -->
                <div class="row mb-4">
                    <div class="col-md-3 py-1">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Total de Cotações</h5>
                                <h2 class="text-primary"><?= $estatisticas['total_chamados'] ?></h2>
                                <small class="text-muted">Período: <?= date('d/m/Y', strtotime($data_inicio)) ?> - <?= date('d/m/Y', strtotime($data_fim)) ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 py-1">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Cotação Pendente</h5>
                                <h2 class="text-warning"><?= $estatisticas['por_status']['cotacao_pendente'] ?></h2>
                                <small class="text-muted"><?= $estatisticas['total_chamados'] > 0 ? round(($estatisticas['por_status']['cotacao_pendente'] / $estatisticas['total_chamados']) * 100, 1) : 0 ?>% do total</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 py-1">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Em Cotação</h5>
                                <h2 class="text-info"><?= $estatisticas['por_status']['em_cotacao'] ?></h2>
                                <small class="text-muted"><?= $estatisticas['total_chamados'] > 0 ? round(($estatisticas['por_status']['em_cotacao'] / $estatisticas['total_chamados']) * 100, 1) : 0 ?>% do total</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 py-1">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Cotado</h5>
                                <h2 class="text-success"><?= $estatisticas['por_status']['cotado'] ?></h2>
                                <small class="text-muted"><?= $estatisticas['total_chamados'] > 0 ? round(($estatisticas['por_status']['cotado'] / $estatisticas['total_chamados']) * 100, 1) : 0 ?>% do total</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card card-chart">
                            <div class="card-header">
                                <h6 class="mb-0">Cotações por Status</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="chartStatus"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card card-chart">
                            <div class="card-header">
                                <h6 class="mb-0">Cotações por Prioridade</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="chartPrioridade"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card card-chart">
                            <div class="card-header">
                                <h6 class="mb-0">Cotações por om</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="chartom"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card card-chart">
                            <div class="card-header">
                                <h6 class="mb-0">Cotações por Dia (Evolução)</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="chartDia"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabela de Chamados -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Cotações do Período (<?= count($chamados) ?> encontrados)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Título</th>
                                        <th>om</th>
                                        <th>Status</th>
                                        <th>Prioridade</th>
                                        <th>Data Abertura</th>
                                        <th>Usuário</th>
                                        <th>Técnico</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($chamados as $chamado): ?>
                                    <tr>
                                        <td><?= $chamado['id'] ?></td>
                                        <td>
                                            <?php
                                            // Formatar o título: remover o IP se for usuário comum
                                            $titulo = htmlspecialchars($chamado['titulo']);
                                            if ($_SESSION['usuario_tipo'] === 'usuario') {
                                                // Remove tudo após o último " - " (incluindo o IP)
                                                $titulo = preg_replace('/ - [^-]+$/', '', $titulo);
                                            }
                                            echo $titulo;
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= $mapeamento_om[$chamado['om']] ?? 'Não definida' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $chamado['status_os'] === 'aberto' ? 'warning' : 
                                                ($chamado['status_os'] === 'em_andamento' ? 'info' : 'success')
                                            ?>">
                                                <?= ucfirst($chamado['status_os']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $chamado['prioridade'] === 'alta' ? 'danger' : 
                                                ($chamado['prioridade'] === 'media' ? 'warning' : 'secondary')
                                            ?>">
                                                <?= ucfirst($chamado['prioridade']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($chamado['data_abertura'])) ?></td>
                                        <td><?= htmlspecialchars($chamado['nome_guerra']) ?></td>
                                        <td><?= $chamado['tecnico_nome'] ? htmlspecialchars($chamado['tecnico_nome']) : 'Não atribuído' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($chamados)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center">Nenhum chamado encontrado para o período selecionado</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Dados dos gráficos
    const dadosGraficos = {
        status: {
            labels: <?= json_encode($grafico_status['labels']) ?>,
            data: <?= json_encode($grafico_status['valores']) ?>
        },
        prioridade: {
            labels: <?= json_encode($grafico_prioridade['labels']) ?>,
            data: <?= json_encode($grafico_prioridade['valores']) ?>
        },
        dia: {
            labels: <?= json_encode($grafico_dia['labels']) ?>,
            data: <?= json_encode($grafico_dia['valores']) ?>
        },
        om: {
            labels: <?= json_encode($grafico_om['labels']) ?>,
            data: <?= json_encode($grafico_om['valores']) ?>
        }
    };

    // Configuração dos gráficos
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Iniciando gráficos...');
        console.log('Dados disponíveis:', dadosGraficos);

        const cores = {
            status: ['#ffc107', '#0dcaf0', '#198754'],
            prioridade: ['#6c757d', '#ffc107', '#dc3545'],
            om: ['#6610f2', '#198754', '#fd7e14', '#20c997', '#6c757d']
        };

        // Gráfico de Status
        if (document.getElementById('chartStatus')) {
            new Chart(document.getElementById('chartStatus'), {
                type: 'doughnut',
                data: {
                    labels: dadosGraficos.status.labels,
                    datasets: [{
                        data: dadosGraficos.status.data,
                        backgroundColor: cores.status
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Gráfico de Prioridade
        if (document.getElementById('chartPrioridade')) {
            new Chart(document.getElementById('chartPrioridade'), {
                type: 'pie',
                data: {
                    labels: dadosGraficos.prioridade.labels,
                    datasets: [{
                        data: dadosGraficos.prioridade.data,
                        backgroundColor: cores.prioridade
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Gráfico de om
        if (document.getElementById('chartom')) {
            new Chart(document.getElementById('chartom'), {
                type: 'bar',
                data: {
                    labels: dadosGraficos.om.labels,
                    datasets: [{
                        label: 'Quantidade',
                        data: dadosGraficos.om.data,
                        backgroundColor: cores.om
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Gráfico de Evolução por Dia
        if (document.getElementById('chartDia')) {
            new Chart(document.getElementById('chartDia'), {
                type: 'line',
                data: {
                    labels: dadosGraficos.dia.labels,
                    datasets: [{
                        label: 'Cotações por Dia',
                        data: dadosGraficos.dia.data,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Controle dos campos de data
        const filtroTipo = document.getElementById('filtroTipo');
        const dataInicio = document.getElementById('dataInicio');
        const dataFim = document.getElementById('dataFim');

        function atualizarCamposData() {
            if (filtroTipo.value === 'personalizado') {
                dataInicio.disabled = false;
                dataFim.disabled = false;
            } else {
                dataInicio.disabled = true;
                dataFim.disabled = true;
            }
        }

        filtroTipo.addEventListener('change', atualizarCamposData);
        atualizarCamposData();
    });

    window.addEventListener('resize', function() {
        Chart.helpers.each(Chart.instances, function(instance) {
            instance.resize();
        });
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
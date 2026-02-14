<?php
session_start();
require_once 'includes/db.php';

// Verificar permissões
$tipo_usuario = $_SESSION['usuario_tipo'] ?? '';
if (strpos($tipo_usuario, 'audatex') === false && strpos($tipo_usuario, 'admin') === false) {
    header("Location: index.php");
    exit;
}

// Filtros (mesmos da página principal)
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$status = $_GET['status'] ?? 'cotado';
$oficina = $_GET['oficina'] ?? '';
$operador = $_GET['operador'] ?? '';
$placa = $_GET['placa'] ?? '';

// Construir query (mesma da página principal)
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

// Aplicar filtros
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

if (!empty($status) && $status != 'todos') {
    $sql .= " AND c.status_os = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($oficina)) {
    $sql .= " AND u.om LIKE ?";
    $params[] = "%$oficina%";
    $types .= "s";
}

if (!empty($operador)) {
    $sql .= " AND aud.nome LIKE ?";
    $params[] = "%$operador%";
    $types .= "s";
}

if (!empty($placa)) {
    $sql .= " AND c.placa_veiculo LIKE ?";
    $params[] = "%$placa%";
    $types .= "s";
}

$sql .= " ORDER BY c.data_cotacao DESC, c.id DESC";

// Executar consulta
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Definir cabeçalhos para download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=os_cotadas_' . date('Y-m-d') . '.csv');

// Criar output
$output = fopen('php://output', 'w');

// Cabeçalhos do CSV
fputcsv($output, [
    'OS #',
    'Placa',
    'Veículo',
    'Oficina',
    'Solicitante',
    'Status',
    'Operador Audatex',
    'Data Abertura',
    'Data Início Cotação',
    'Data Cotação',
    'Tempo Cotação (h)',
    'Qtd Itens',
    'Valor Total',
    'Código Audatex',
    'Observações'
], ';');

// Dados
while ($os = $result->fetch_assoc()) {
    // Status traduzido
    $status_labels = [
        'cotado' => 'Cotado',
        'aguardando_aprovacao' => 'Aguardando Aprovação',
        'aprovado' => 'Aprovado',
        'executando' => 'Em Execução',
        'concluido' => 'Concluído'
    ];
    
    $status = $status_labels[$os['status_os']] ?? $os['status_os'];
    
    fputcsv($output, [
        $os['id'],
        $os['placa_veiculo'],
        $os['marca_veiculo'] . ' ' . $os['modelo_veiculo'],
        $os['oficina_nome'],
        $os['usuario_nome'],
        $status,
        $os['operador_nome'] ?? '',
        date('d/m/Y H:i', strtotime($os['data_abertura'])),
        $os['data_inicio_cotacao'] ? date('d/m/Y H:i', strtotime($os['data_inicio_cotacao'])) : '',
        $os['data_cotacao'] ? date('d/m/Y H:i', strtotime($os['data_cotacao'])) : '',
        $os['tempo_cotacao_horas'] ?? 0,
        $os['qtd_itens'] ?? 0,
        $os['total_pecas'] ? number_format($os['total_pecas'], 2, ',', '.') : '0,00',
        $os['codigo_cotacao_audatex'] ?? '',
        $os['observacoes_audatex'] ?? ''
    ], ';');
}

fclose($output);
exit;
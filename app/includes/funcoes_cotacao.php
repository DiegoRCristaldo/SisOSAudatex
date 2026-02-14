<?php
// Funções para gerenciar cotações/consultas de valores

/**
 * Obtém informações básicas da OS
 */
function obterInformacoesOS($conn, $os_id, $status_esperado = null) {
    $sql = "SELECT c.*, u.om as oficina_nome, u.nome as usuario_nome,
                   c.descricao as descricao_problema
            FROM chamados c
            JOIN usuarios u ON c.id_usuario_abriu = u.id
            WHERE c.id = ?";
    
    if ($status_esperado) {
        $sql .= " AND c.status_os = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $os_id, $status_esperado);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $os_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    return $result->fetch_assoc();
}

/**
 * Obtém peças selecionadas para uma OS
 * Usado em iniciar_cotacao.php
 */
function obterPecasSelecionadasOS($conn, $os_id) {
    $sql = "SELECT 
                ois.id as item_id,
                CASE 
                    WHEN ois.peca_id > 0 THEN p.id
                    ELSE 0
                END as id,
                CASE 
                    WHEN ois.peca_id > 0 THEN p.nome
                    ELSE ois.descricao_manual
                END as descricao,
                p.codigo_referencia,
                p.aplicacao,
                ois.quantidade,
                ois.descricao_manual,
                CASE 
                    WHEN ois.peca_id = 0 THEN 'manual'
                    ELSE 'lista'
                END as tipo,
                CASE 
                    WHEN ois.peca_id = 0 THEN 'warning'
                    ELSE 'primary'
                END as badge_color
            FROM os_itens_selecionados ois
            LEFT JOIN os_pecas_cadastradas p ON ois.peca_id = p.id
            WHERE ois.os_id = ?
            ORDER BY ois.id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $os_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result;
}

/**
 * Obtém itens já cotados/consultados para uma OS
 * Usado em finalizar_cotacao.php
 */
function obterItensCotadosOS($conn, $os_id) {
    $sql = "SELECT * FROM os_itens_cotacao 
            WHERE chamado_id = ? AND tipo = 'peca'
            ORDER BY id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $os_id);
    $stmt->execute();
    
    return $stmt->get_result();
}

/**
 * Calcula totais da OS
 */
function calcularTotaisOS($conn, $os_id) {
    $sql = "SELECT 
                (SELECT SUM(valor_total) FROM os_itens_cotacao WHERE chamado_id = ? AND tipo = 'peca') as total_pecas,
                (SELECT COUNT(*) FROM os_itens_cotacao WHERE chamado_id = ? AND tipo = 'peca') as qtd_itens";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $os_id, $os_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $totais = $result->fetch_assoc();
    
    // Se não houver itens cotados ainda, calcular baseado nas peças selecionadas
    if (!$totais['total_pecas']) {
        $sql = "SELECT COUNT(*) as qtd_pecas FROM os_itens_selecionados WHERE os_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $os_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $pecas = $result->fetch_assoc();
        
        $totais['qtd_itens'] = $pecas['qtd_pecas'] ?? 0;
        $totais['total_pecas'] = 0;
    }
    
    return $totais;
}

/**
 * Gera o HTML da tabela de peças para iniciar cotação
 */
function gerarTabelaPecasParaCotacao($pecas_result, $modo = 'edicao') {
    $html = '';
    
    if ($pecas_result->num_rows > 0) {
        $html .= '<div class="table-responsive">';
        $html .= '<table class="table table-hover align-middle" id="tabela-pecas">';
        $html .= '<thead class="table-light">';
        $html .= '<tr>';
        $html .= '<th width="5%">#</th>';
        $html .= '<th width="40%">Peça / Descrição</th>';
        $html .= '<th width="15%">Código Audatex</th>';
        $html .= '<th width="10%" class="text-center">Qtd</th>';
        $html .= '<th width="15%" class="text-end">Valor Unitário</th>';
        $html .= '<th width="15%" class="text-end">Valor Total</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        $contador = 1;
        
        while ($peca = $pecas_result->fetch_assoc()) {
            // Gerar ID único para peças manuais
            if ($peca['tipo'] === 'manual') {
                $peca_id = 'manual_' . $peca['item_id'];
            } else {
                $peca_id = $peca['id'];
            }
            
            $descricao = htmlspecialchars($peca['descricao']);
            $quantidade = floatval($peca['quantidade']);
            $tipo = $peca['tipo'];
            
            $html .= '<tr data-peca-id="' . $peca_id . '" data-tipo="' . $tipo . '">';
            $html .= '<td class="text-center">' . $contador . '</td>';
            
            // Descrição da peça
            $html .= '<td>';
            $html .= '<strong>' . $descricao . '</strong>';
            
            // Informações adicionais baseadas no tipo
            if ($tipo === 'lista') {
                if (!empty($peca['codigo_referencia'])) {
                    $html .= '<br><small class="text-muted">Código: ' . htmlspecialchars($peca['codigo_referencia']) . '</small>';
                }
                if (!empty($peca['aplicacao'])) {
                    $html .= '<br><small class="text-muted">' . htmlspecialchars($peca['aplicacao']) . '</small>';
                }
                $html .= '<br><span class="badge bg-primary">Lista</span>';
            } else {
                $html .= '<br><span class="badge bg-warning">Informada manualmente</span>';
            }
            $html .= '</td>';
            
            // Código Audatex - campo de entrada
            $html .= '<td>';
            $html .= '<input type="text" 
                             name="pecas[' . $peca_id . '][codigo_audatex]" 
                             class="form-control form-control-sm codigo-audatex" 
                             placeholder="Código Audatex"
                             required>';
            $html .= '</td>';
            
            // Quantidade
            $html .= '<td class="text-center">';
            $html .= '<input type="number" 
                             name="pecas[' . $peca_id . '][quantidade]" 
                             class="form-control form-control-sm quantidade" 
                             value="' . $quantidade . '" 
                             min="0.01" step="0.01" required 
                             onchange="calcularTotalPeca(this)">';
            $html .= '</td>';
            
            // Valor Unitário
            $html .= '<td class="text-end">';
            $html .= '<div class="input-group input-group-sm">';
            $html .= '<span class="input-group-text">R$</span>';
            $html .= '<input type="text" 
                             name="pecas[' . $peca_id . '][valor_unitario]" 
                             class="form-control form-control-sm valor-unitario text-end" 
                             placeholder="0,00"
                             oninput="formatarValor(this)"
                             onchange="calcularTotalPeca(this)"
                             required>';
            $html .= '</div>';
            $html .= '</td>';
            
            // Valor Total
            $html .= '<td class="text-end">';
            // Campo hidden com a descrição
            $html .= '<input type="hidden" 
                             name="pecas[' . $peca_id . '][descricao]" 
                             value="' . $descricao . '">';
            $html .= '<div class="input-group input-group-sm">';
            $html .= '<span class="input-group-text">R$</span>';
            $html .= '<input type="text" 
                             class="form-control form-control-sm valor-total text-end font-monospace" 
                             readonly>';
            $html .= '</div>';
            $html .= '</td>';
            
            $html .= '</tr>';
            $contador++;
        }
        
        // Linha de total
        $html .= '<tr class="total-row">';
        $html .= '<td colspan="5" class="text-end"><strong>Total de Peças:</strong></td>';
        $html .= '<td class="text-end">';
        $html .= '<div class="input-group input-group-sm">';
        $html .= '<span class="input-group-text">R$</span>';
        $html .= '<input type="text" 
                         id="total-pecas" 
                         class="form-control form-control-sm text-end font-monospace" 
                         readonly>';
        $html .= '</div>';
        $html .= '</td>';
        $html .= '</tr>';
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
    } else {
        $html .= '<div class="alert alert-warning">';
        $html .= '<i class="bi bi-exclamation-triangle"></i> ';
        $html .= 'Nenhuma peça selecionada para esta OS.';
        $html .= '</div>';
    }
    
    return $html;
}

/**
 * Gera o HTML da tabela de itens já cotados
 */
function gerarTabelaItensCotados($itens_result) {
    ob_start();
    
    if ($itens_result->num_rows > 0):
        $contador = 0;
        $total_geral = 0;
        ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th width="5%">#</th>
                        <th width="15%">Código Audatex</th>
                        <th width="45%">Descrição</th>
                        <th width="10%">Qtd</th>
                        <th width="15%">Unitário</th>
                        <th width="15%">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($item = $itens_result->fetch_assoc()): 
                        $contador++;
                        $total_geral += $item['valor_total'];
                    ?>
                    <tr>
                        <td class="text-center"><?= $contador ?></td>
                        <td><?= htmlspecialchars($item['codigo_audatex']) ?></td>
                        <td><?= htmlspecialchars($item['descricao']) ?></td>
                        <td class="text-center"><?= $item['quantidade'] ?></td>
                        <td class="text-end font-monospace">R$ <?= number_format($item['valor_unitario'], 2, ',', '.') ?></td>
                        <td class="text-end font-monospace fw-bold">R$ <?= number_format($item['valor_total'], 2, ',', '.') ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot class="total-row">
                    <tr>
                        <td colspan="5" class="text-end"><strong>TOTAL GERAL:</strong></td>
                        <td class="text-end font-monospace fw-bold fs-5">
                            R$ <?= number_format($total_geral, 2, ',', '.') ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php
    else:
        ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> Nenhuma peça foi consultada ainda.
        </div>
        <?php
    endif;
    
    return ob_get_clean();
}

/**
 * Gera o HTML do cabeçalho com informações do veículo
 */
function gerarCabecalhoVeiculo($os) {
    ob_start();
    ?>
    <div class="card mb-4 header-veiculo">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <h5><i class="bi bi-car-front"></i> Veículo</h5>
                    <div class="fs-4 fw-bold"><?= htmlspecialchars($os['placa_veiculo']) ?></div>
                    <div>
                        <?= htmlspecialchars($os['marca_veiculo']) ?> 
                        <?= htmlspecialchars($os['modelo_veiculo']) ?>
                    </div>
                    <div class="text-muted">
                        Ano: <?= htmlspecialchars($os['ano_veiculo']) ?> | 
                        Cor: <?= htmlspecialchars($os['cor_veiculo']) ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <h5><i class="bi bi-building"></i> Oficina</h5>
                    <div class="fw-bold"><?= htmlspecialchars($os['oficina_nome']) ?></div>
                    <div class="text-muted">Solicitante: <?= htmlspecialchars($os['usuario_nome']) ?></div>
                </div>
                <div class="col-md-3">
                    <h5><i class="bi bi-wrench"></i> Prioridade</h5>
                    <div>
                        <span class="badge bg-<?= 
                            $os['prioridade'] == 'alta' ? 'danger' : 
                            ($os['prioridade'] == 'media' ? 'warning' : 'success') 
                        ?>">
                            <?= ucfirst($os['prioridade'])?>
                        </span>
                    </div>
                </div>
                <div class="col-md-3">
                    <h5><i class="bi bi-clock"></i> Tempo</h5>
                    <div class="fw-bold">
                        <?php 
                        $horas = round((time() - strtotime($os['data_abertura'])) / 3600, 1);
                        echo $horas . ' horas';
                        ?>
                    </div>
                    <div class="text-muted">
                        Aberta: <?= date('d/m/Y H:i', strtotime($os['data_abertura'])) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Valida permissões do usuário Audatex
 */
function validarPermissaoAudatex($tipo_usuario, $os_id) {
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: login.php");
        exit;
    }
    
    $is_audatex = strpos($tipo_usuario, 'audatex') !== false || 
                  strpos($tipo_usuario, 'admin') !== false;
    
    if (!$is_audatex) {
        header("Location: minhas_os.php");
        exit;
    }
    
    if (!$os_id || !is_numeric($os_id)) {
        header("Location: os_pendentes_audatex.php");
        exit;
    }
    
    return intval($os_id);
}
?>
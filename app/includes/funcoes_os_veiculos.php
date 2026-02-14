<?php
// Funções específicas para o módulo OS Veículos

function contarOSPendentesAudatex($conn) {
    $sql = "SELECT COUNT(*) as total FROM chamados WHERE status_os = 'cotacao_pendente'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

function contarOSOficina($conn, $oficina_id) {
    $sql = "SELECT COUNT(*) as total FROM chamados 
            WHERE id_usuario_abriu = ? AND status_os IN ('cotacao_pendente', 'em_cotacao')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $oficina_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

function contarOSStatus($conn, $oficina_id, $status) {
    $sql = "SELECT COUNT(*) as total FROM chamados 
            WHERE id_usuario_abriu = ? AND status_os = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $oficina_id, $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

function contarOSStatusAudatex($conn, $status) {
    $sql = "SELECT COUNT(*) as total FROM chamados WHERE status_os = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

function contarOSCotadasHoje($conn) {
    $sql = "SELECT COUNT(*) as total FROM chamados 
            WHERE DATE(data_cotacao) = CURDATE() AND status_os = 'cotado'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

// Função para registrar histórico de OS
function registrarHistoricoOS($conn, $chamado_id, $usuario_id, $acao, $detalhes = '') {
    $sql = "INSERT INTO historico_os_veiculos (chamado_id, usuario_id, acao, detalhes) 
            VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $chamado_id, $usuario_id, $acao, $detalhes);
    return $stmt->execute();
}

// Função para validar placa de veículo (Mercosul ou antiga)
function validarPlaca($placa) {
    $placa = strtoupper($placa);
    $placa = str_replace('-', '', $placa);
    
    // Formato Mercosul: ABC1D23
    $padrao_mercosul = '/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/';
    
    // Formato antigo: ABC1234
    $padrao_antigo = '/^[A-Z]{3}[0-9]{4}$/';
    
    return preg_match($padrao_mercosul, $placa) || preg_match($padrao_antigo, $placa);
}

// Nova função: Processar OS Veículo
function processarOSVeiculo($conn, $dados, $itens_selecionados, $pecas_manuais_array, $files) {
    try {
        $conn->begin_transaction();
        
        // Inserir o chamado/OS
        $sql = "INSERT INTO chamados (
            titulo, prioridade, id_usuario_abriu,
            placa_veiculo, eb_veiculo, chassi_veiculo, marca_veiculo, modelo_veiculo, 
            ano_veiculo, cor_veiculo, status_os
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'cotacao_pendente')";
        
        $titulo = "OS Veículo - " . $dados['placa'] . " - " . $dados['marca'] . " " . $dados['modelo'];
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erro ao preparar SQL: " . $conn->error);
        }
        
        $stmt->bind_param(
            "ssisssssis",
            $titulo, $dados['prioridade'], $_SESSION['usuario_id'],
            $dados['placa'], $dados['eb'], $dados['chassi'], $dados['marca'], $dados['modelo'], 
            $dados['ano'], $dados['cor']
        );
        
        if ($stmt->execute()) {
            $os_id = $stmt->insert_id;
            
            // Salvar peças manuais
            if (!empty($pecas_manuais_array)) {
                salvarPecasManuais($conn, $os_id, $pecas_manuais_array);
            }
            
            // Salvar itens selecionados da lista
            if (!empty($itens_selecionados)) {
                salvarItensSelecionados($conn, $os_id, $itens_selecionados);
            }
            
            // Processar upload de fotos
            if (!empty($files['name'][0])) {
                processarUploadFotos($conn, $os_id, $files);
            }
            
            // Registrar histórico
            registrarHistoricoOS(
                $conn, 
                $os_id, 
                $_SESSION['usuario_id'], 
                'OS criada', 
                "Veículo: {$dados['placa']} - {$dados['marca']} {$dados['modelo']} - {$dados['ano']}"
            );
            
            // Criar notificação para operador Audatex
            criarNotificacaoAudatex($conn, $os_id, $dados['placa'], $dados['marca'], $dados['modelo']);
            
            $conn->commit();
            return $os_id;
        } else {
            throw new Exception("Erro ao cadastrar OS: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function salvarPecasManuais($conn, $os_id, $pecas_manuais_array) {
    // Debug
    error_log("DEBUG salvarPecasManuais - OS: $os_id, Total peças: " . count($pecas_manuais_array));
    
    if (empty($pecas_manuais_array)) {
        error_log("DEBUG - Nenhuma peça manual para salvar");
        return;
    }
    
    // Preparar observações
    $observacoes = "PEÇAS INFORMADAS MANUALMENTE:\n" . implode("\n", $pecas_manuais_array);
    
    // Verificar se já existe observação
    $sql_check = "SELECT observacoes_audatex FROM chamados WHERE id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $os_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    $row = $result->fetch_assoc();
    
    if (!empty($row['observacoes_audatex'])) {
        $observacoes = $row['observacoes_audatex'] . "\n\n" . $observacoes;
    }
    
    // Atualizar observações
    $sql_obs = "UPDATE chamados SET observacoes_audatex = ? WHERE id = ?";
    $stmt_obs = $conn->prepare($sql_obs);
    $stmt_obs->bind_param("si", $observacoes, $os_id);
    
    if (!$stmt_obs->execute()) {
        error_log("ERRO ao salvar observações: " . $stmt_obs->error);
    }
    $stmt_obs->close();
    
    // Salvar cada peça manual
    $sucessos = 0;
    foreach ($pecas_manuais_array as $peca_manual) {
        $sql_manual = "INSERT INTO os_itens_selecionados (os_id, peca_id, descricao_manual, quantidade) 
                      VALUES (?, 0, ?, 1)";
        $stmt_manual = $conn->prepare($sql_manual);
        $stmt_manual->bind_param("is", $os_id, $peca_manual);
        
        if ($stmt_manual->execute()) {
            $sucessos++;
            error_log("SUCESSO - Peça manual salva: ID " . $stmt_manual->insert_id . " - " . $peca_manual);
        } else {
            error_log("ERRO ao salvar peça manual '$peca_manual': " . $stmt_manual->error);
        }
        $stmt_manual->close();
    }
    
    error_log("DEBUG - Total peças manuais salvas: $sucessos de " . count($pecas_manuais_array));
}

// Função para salvar itens selecionados
function salvarItensSelecionados($conn, $os_id, $itens_selecionados) {
    foreach ($itens_selecionados as $item_id) {
        $sql_item = "INSERT INTO os_itens_selecionados (os_id, peca_id, quantidade) 
                    VALUES (?, ?, 1)";
        $stmt_item = $conn->prepare($sql_item);
        $stmt_item->bind_param("ii", $os_id, $item_id);
        $stmt_item->execute();
    }
}

// Função para criar notificação Audatex
function criarNotificacaoAudatex($conn, $os_id, $placa, $marca, $modelo) {
    $sql_notif = "INSERT INTO notificacoes (usuario_id, chamado_id, tipo, mensagem)
                 SELECT id, ?, 'atualizacao', ?
                 FROM usuarios 
                 WHERE tipo LIKE '%audatex%' AND status = 'ativo'";
    $stmt_notif = $conn->prepare($sql_notif);
    $msg_notif = "Nova OS para cotação: $placa - $marca $modelo";
    $stmt_notif->bind_param("is", $os_id, $msg_notif);
    $stmt_notif->execute();
}

// Função para processar upload de fotos
function processarUploadFotos($conn, $os_id, $files) {
    $upload_dir = 'uploads/os_veiculos/' . $os_id . '/';
    
    // Criar diretório se não existir
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            throw new Exception("Falha ao criar diretório para fotos");
        }
    }
    
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $tmp_name = $files['tmp_name'][$i];
            $original_name = basename($files['name'][$i]);
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            
            // Validar extensão
            if (!in_array($ext, $allowed_ext)) {
                continue;
            }
            
            // Validar tamanho (10MB)
            if ($files['size'][$i] > 10 * 1024 * 1024) {
                continue;
            }
            
            // Gerar nome único
            $new_name = uniqid('foto_', true) . '.' . $ext;
            $destination = $upload_dir . $new_name;
            
            // Mover arquivo
            if (move_uploaded_file($tmp_name, $destination)) {
                $sql = "INSERT INTO chamados_arquivos (chamado_id, nome_arquivo, caminho_arquivo, tipo) 
                        VALUES (?, ?, ?, 'foto')";
                
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    error_log("Erro ao preparar SQL para foto: " . $conn->error);
                    continue;
                }
                
                $stmt->bind_param("iss", $os_id, $original_name, $destination);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

// Função para validar dados da OS
function validarDadosOS($dados) {
    $erros = [];
    
    // Validar placa
    if (empty($dados['placa'])) {
        $erros[] = "Placa é obrigatória";
    } elseif (!validarPlaca($dados['placa'])) {
        $erros[] = "Placa inválida! Use formato Mercosul (ABC1D23) ou antigo (ABC1234)";
    }
    
    // Validar EB
    if (empty($dados['eb'])) {
        $erros[] = "EB é obrigatório";
    }
    
    // Validar chassi
    if (empty($dados['chassi'])) {
        $erros[] = "Chassi é obrigatório";
    }
    
    // Validar marca
    if (empty($dados['marca'])) {
        $erros[] = "Marca é obrigatória";
    }
    
    // Validar modelo
    if (empty($dados['modelo'])) {
        $erros[] = "Modelo é obrigatório";
    }
    
    // Validar ano
    if (empty($dados['ano']) || $dados['ano'] == 0) {
        $erros[] = "Ano é obrigatório";
    }
    
    return $erros;
}

// Função para processar peças manuais
function processarPecasManuais($pecas_manuais_input) {
    $pecas_manuais_array = [];
    
    if (!empty($pecas_manuais_input)) {
        $linhas = explode("\n", $pecas_manuais_input);
        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if (!empty($linha)) {
                // Remover marcadores como •, -, *, números com ponto
                $linha = preg_replace('/^[•\-*\d\.\s]+/', '', $linha);
                if (!empty($linha)) {
                    $pecas_manuais_array[] = $linha;
                }
            }
        }
    }
    
    return $pecas_manuais_array;
}

?>
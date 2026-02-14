<?php

/**
 * Busca notificações não lidas para o usuário
 */
function buscarNotificacoesUsuario($conn, $usuario_id) {
    $sql = "SELECT COUNT(*) as total 
            FROM notificacoes 
            WHERE usuario_id = ? 
            AND lida = 0";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    }
    
    return 0;
}

/**
 * Busca detalhes das notificações do usuário
 */
function buscarDetalhesNotificacoes($conn, $usuario_id, $limite = 10) {
    $sql = "SELECT n.*, c.titulo, c.status_os,
                   u.nome as tecnico_nome, u.nome_guerra as tecnico_nome_guerra
            FROM notificacoes n
            INNER JOIN chamados c ON n.chamado_id = c.id
            LEFT JOIN usuarios u ON c.id_tecnico_responsavel = u.id
            WHERE n.usuario_id = ?
            ORDER BY n.data_criacao DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $usuario_id, $limite);
    
    if ($stmt->execute()) {
        return $stmt->get_result();
    }
    
    return false;
}

function patentes() {
    $patentes = [
        "cel" => "Cel", 
        "tc" => "TC", 
        "maj" => "Maj", 
        "cap" => "Cap", 
        "1ten" => "1°Ten", 
        "2ten" => "2°Ten", 
        "asp" => "Asp", 
        "s_ten" => "S Ten", 
        "1sgt" => "1°Sgt", 
        "2sgt" => "2°Sgt", 
        "3sgt" => "3°Sgt", 
        "cb" => "Cb", 
        "sd_ep" => "Sd EP",
        "sd_ev" => "Sd EV"
    ];

    return $patentes;
}

/**
 * Retorna o label formatado de uma patente
 */
function formatarPatente($codigo_patente) {
    $patentes = patentes();
    
    return $patentes[$codigo_patente] ?? $codigo_patente;
}

/**
 * Retorna o label formatado de uma OM
 */
function formatarOM() {
    $om = [
        "2blog" => "2°B Log",
        "28bimec" => "28°BI Mec",
        "ciou" => "CIOU",
        "11pelpe" => "11°Pel PE",
        "cmdo11bda" => "Cmdo 11ªBda Inf Mec",
        "ciacmdo11bda" => "Cia Cmdo 11ªBda Inf Mec",
        "2ciacom" => "2ªCia Com",
        "pmgu" => "PMGU",
        "espcex" => "EsPCEx"
    ];
        
    return $om;
}
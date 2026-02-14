<?php

$linhas = file('pfsense.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$dados = [];

foreach ($linhas as $linha) {
    $partes = preg_split('/\s+/', trim($linha));
    
    if (count($partes) >= 3) {
        $dados[] = [
            'nome' => $partes[1],
            'ip' => $partes[0],
            'mac' => implode(' ', array_slice($partes, 2))
        ];
    }
}

// Ordenar por nome
usort($dados, function($a, $b) {
    return strcmp($a['nome'], $b['nome']);
});

// Gerar e exibir comandos SQL
foreach ($dados as $equip) {
    echo "INSERT INTO equipamentos(nome, ip, mac) VALUES('{$equip['nome']}', '{$equip['ip']}', '{$equip['mac']}');\n";
}
?>
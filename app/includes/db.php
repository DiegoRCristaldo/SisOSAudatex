<?php
$dados = include('dados.php');

$host = $dados['host'];
$user = $dados['user'];
$senha = $dados['senha'];
$banco = $dados['banco'];

// ⭐ TORNAR AS CONFIGURAÇÕES DE EMAIL DISPONÍVEIS GLOBALMENTE
$GLOBALS['email_config'] = [
    'hostEmail' => $dados['hostEmail'],
    'email' => $dados['email'],
    'senhaEmail' => $dados['senhaEmail'],
    'portaEmail' => $dados['portaEmail']
];

$conn = new mysqli($host, $user, $senha, $banco);

if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}
?>
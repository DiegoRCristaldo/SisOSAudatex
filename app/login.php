<?php
session_start();
include 'includes/db.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    $sql = "SELECT * FROM usuarios WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();

    if ($usuario && password_verify($senha, $usuario['senha'])) {
        // Verificar status da conta
        if (isset($usuario['status']) && $usuario['status'] !== 'ativo') {
            $msg = "Conta inativa ou pendente de aprovação!";
        } else {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_tipo'] = $usuario['tipo'];
            $_SESSION['usuario_posto_graduacao'] = $usuario['posto_graduacao'];
            $_SESSION['usuario_nome_guerra'] = $usuario['nome_guerra'];
            
            // Campos adicionais para oficinas
            if (strpos($usuario['tipo'], 'oficina') !== false) {
                $_SESSION['oficina_cnpj'] = $usuario['cnpj'] ?? '';
                $_SESSION['oficina_telefone'] = $usuario['telefone'] ?? '';
            }
            
            header("Location: index.php");
            exit;
        }
    } else {
        $msg = "E-mail ou senha incorretos!";
    }
}

require 'header.php';
?>
</head>
<body>
    <main class="login">
        <div class="login-card">
            <h2>Sistema OS Veículos</h2>
            <?php if ($msg): ?>
                <div class="alert alert-danger"><?= $msg ?></div>
            <?php endif; ?>
            
            <div class="login-options mb-4">
                <div class="row text-center">
                    <div class="col-4">
                        <i class="bi bi-building fs-1 text-success"></i>
                        <p class="mb-0"><small>Usuário</small></p>
                    </div>
                    <div class="col-4">
                        <i class="bi bi-tools fs-1 text-secondary"></i>
                        <p class="mb-0"><small>Audatex</small></p>
                    </div>
                    <div class="col-4">
                        <i class="bi bi-gear fs-1 text-light"></i>
                        <p class="mb-0"><small>Administrador</small></p>
                    </div>
                </div>
            </div>
            
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="Digite seu e-mail" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Senha</label>
                    <input type="password" name="senha" class="form-control" placeholder="Digite sua senha" required>
                </div>
                <button type="submit" class="btn btn-success w-100 mb-1">Entrar</button>
                <a href="controler/password_reset_request.php" class="btn btn-secondary w-100 mb-1">Esqueci minha senha</a>
                <a href="criar_conta.php" class="btn btn-light w-100 mb-1">Criar conta</a>
            </form>
            
            <div class="mt-3 text-center">
                <small class="text-muted">Escolha o tipo de acesso:</small><br>
                <span class="badge bg-success">Usuário</span>
                <span class="badge bg-secondary">Operador Audatex</span>
                <span class="badge bg-light text-dark">Administrador (TI)</span>
            </div>
        </div>
    </main>
</body>
</html>
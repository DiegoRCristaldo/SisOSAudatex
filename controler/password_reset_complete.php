<?php
require '../includes/db.php';

$id_usuario = isset($_GET['id_usuario']) ? intval($_GET['id_usuario']) : 0;
$error = '';
$success = '';

// Verificar se o ID do usuário é válido
if ($id_usuario > 0) {
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: password_reset_request.php");
        exit;
    }
    $stmt->close();
} else {
    header("Location: password_reset_request.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_usuario = intval($_POST['id_usuario']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validações
    if (empty($password) || empty($confirm_password)) {
        $error = 'Todos os campos são obrigatórios.';
    } elseif ($password !== $confirm_password) {
        $error = 'As senhas não coincidem.';
    } elseif (strlen($password) < 6) {
        $error = 'A senha deve ter pelo menos 6 caracteres.';
    } else {
        // Atualizar a senha do usuário
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmtUpdate = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        $stmtUpdate->bind_param("si", $password_hash, $id_usuario);
        
        if ($stmtUpdate->execute()) {
            // Apagar os códigos associados ao usuário
            $stmtDelete = $conn->prepare("DELETE FROM recuperacao_senha WHERE id_usuario = ?");
            $stmtDelete->bind_param("i", $id_usuario);
            $stmtDelete->execute();
            $stmtDelete->close();
            
            $success = 'Senha atualizada com sucesso!';
            // Redirecionar após 3 segundos
            header("refresh:3;url=../login.php");
        } else {
            $error = 'Erro ao atualizar a senha. Tente novamente.';
        }
        $stmtUpdate->close();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/2blog.png" type="image/png">
    <title>Nova Senha - HelpDesk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="senha">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header text-center py-4">
                        <h3><i class="bi bi-lock"></i> Nova Senha</h3>
                    </div>
                    <div class="card-body p-5">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?= $success ?>
                                <p>Redirecionando para login...</p>
                            </div>
                        <?php else: ?>
                        
                        <form method="POST">
                            <input type="hidden" name="id_usuario" value="<?= $id_usuario ?>">
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Nova Senha</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" 
                                           required minlength="6" placeholder="Mínimo 6 caracteres">
                                    <span class="input-group-text password-toggle" onclick="togglePassword('password')">
                                        <i class="bi bi-eye"></i>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirmar Nova Senha</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           required minlength="6" placeholder="Digite a senha novamente">
                                    <span class="input-group-text password-toggle" onclick="togglePassword('confirm_password')">
                                        <i class="bi bi-eye"></i>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-circle"></i> Redefinir Senha
                                </button>
                            </div>
                        </form>
                        
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
        
        // Validação em tempo real
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                alert('As senhas não coincidem!');
                confirmPassword.focus();
            }
        });
    </script>
</body>
</html>
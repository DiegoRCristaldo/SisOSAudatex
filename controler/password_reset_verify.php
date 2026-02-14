<?php
require '../includes/db.php';

$id_usuario = isset($_GET['id_usuario']) ? intval($_GET['id_usuario']) : 0;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_usuario = intval($_POST['id_usuario']);
    $codigo = trim($_POST['codigo']);

    // Verificar se o código existe e se está dentro do prazo de 15 minutos
    $stmt = $conn->prepare("SELECT * FROM recuperacao_senha 
                           WHERE id_usuario = ? AND codigo = ? 
                           AND criado_em > NOW() - INTERVAL 15 MINUTE");
    $stmt->bind_param("is", $id_usuario, $codigo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Código válido - Prossegue para redefinir a senha
        header("Location: password_reset_complete.php?id_usuario=$id_usuario");
        exit;
    } else {
        $error = 'Código inválido ou expirado.';
    }
    $stmt->close();
}

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
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/2blog.png" type="image/png">
    <title>Verificar Código - HelpDesk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="senha">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header text-center py-4">
                        <h3><i class="bi bi-shield-check"></i> Verificar Código</h3>
                    </div>
                    <div class="card-body p-5">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <p class="text-muted mb-4">
                            Digite o código de 6 dígitos que enviamos para seu email.
                            O código é válido por 15 minutos.
                        </p>
                        
                        <form method="POST">
                            <input type="hidden" name="id_usuario" value="<?= $id_usuario ?>">
                            
                            <div class="mb-3">
                                <label for="codigo" class="form-label">Código de Verificação</label>
                                <input type="text" class="form-control code-input" id="codigo" name="codigo" 
                                       required maxlength="6" pattern="[0-9]{6}" 
                                       placeholder="000000" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                <div class="form-text">Apenas números (6 dígitos)</div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-circle"></i> Verificar Código
                                </button>
                                <a href="password_reset_request.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-repeat"></i> Reenviar Código
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Foco automático no campo de código
        document.getElementById('codigo').focus();
        
        // Auto avançar para próximo campo (se tiver múltiplos inputs)
        const codeInput = document.getElementById('codigo');
        codeInput.addEventListener('input', function() {
            if (this.value.length === 6) {
                this.form.submit();
            }
        });
    </script>
</body>
</html>
<?php
session_start();

require_once 'includes/db.php';
require_once 'includes/funcoes_padrao.php';

// Se o usuário já estiver logado, redireciona
if (isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$mensagem = '';
$tipo_mensagem = '';

// Processar o formulário de cadastro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    $posto_graduacao = $_POST['posto_graduacao'];
    $nome_guerra = trim($_POST['nome_guerra']);

    // Validações
    if (empty($nome) || empty($email) || empty($senha) || empty($posto_graduacao) || empty($nome_guerra)) {
        $mensagem = "Todos os campos são obrigatórios!";
        $tipo_mensagem = "danger";
    } elseif ($senha !== $confirmar_senha) {
        $mensagem = "As senhas não coincidem!";
        $tipo_mensagem = "danger";
    } elseif (strlen($senha) < 6) {
        $mensagem = "A senha deve ter pelo menos 6 caracteres!";
        $tipo_mensagem = "danger";
    } else {
        // Verificar se email já existe
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $mensagem = "Este email já está cadastrado!";
            $tipo_mensagem = "danger";
        } else {
            // Criar o usuário
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $tipo = 'usuario'; // Definido como padrão
            
            $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo, posto_graduacao, nome_guerra, data_cadastro) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssssss", $nome, $email, $senha_hash, $tipo, $posto_graduacao, $nome_guerra);
            
            if ($stmt->execute()) {
                $mensagem = "Perfil criado com sucesso! Você já pode fazer login.";
                $tipo_mensagem = "success";
                
                // Limpar o formulário
                $_POST = [];
            } else {
                $mensagem = "Erro ao criar perfil. Tente novamente.";
                $tipo_mensagem = "danger";
            }
        }
        $stmt->close();
    }
}

$patentes = patentes();
$om = formatarOM();

require 'header.php';
?>
</head>
<body class="criar-conta">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card card-custom">
                    <div class="card-header card-header-custom">
                        <div class="logo-text">
                            <i class="bi bi-person-plus-fill me-2"></i>SISTECINF
                        </div>
                        <p class="subtitle">Crie sua conta e solicite suporte técnico</p>
                    </div>
                    
                    <div class="card-body p-5">
                        <?php if ($mensagem): ?>
                        <div class="alert alert-<?= $tipo_mensagem ?> alert-dismissible fade show" role="alert">
                            <?= $mensagem ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="formCadastro">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nome" class="form-label">
                                        <i class="bi bi-person me-1"></i>Nome Completo *
                                    </label>
                                    <input type="text" class="form-control form-control-lg" id="nome" name="nome" 
                                           value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required 
                                           placeholder="Seu nome completo">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">
                                        <i class="bi bi-envelope me-1"></i>Email *
                                    </label>
                                    <input type="email" class="form-control form-control-lg" id="email" name="email" 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required 
                                           placeholder="seu.email@exemplo.com">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="posto_graduacao" class="form-label">
                                        <i class="bi bi-star me-1"></i>Posto/Graduação *
                                    </label>
                                    <select class="form-select form-select-lg" id="posto_graduacao" name="posto_graduacao" required>
                                        <option value="">Selecione seu posto/graduação</option>
                                        <?php foreach ($patentes as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= ($_POST['posto_graduacao'] ?? '') == $key ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="nome_guerra" class="form-label">
                                        <i class="bi bi-person-badge me-1"></i>Nome de Guerra *
                                    </label>
                                    <input type="text" class="form-control form-control-lg" id="nome_guerra" name="nome_guerra" 
                                           value="<?= htmlspecialchars($_POST['nome_guerra'] ?? '') ?>" required 
                                           placeholder="Seu nome de guerra">
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label for="om" class="form-label">
                                        <i class="bi bi-star me-1"></i>Organização Militar *
                                    </label>
                                    <select class="form-select form-select-lg" id="om" name="posto_graduacao" required>
                                        <option value="">Selecione sua Organização Militar</option>
                                        <?php foreach ($om as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= ($_POST['om'] ?? '') == $key ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="senha" class="form-label">
                                        <i class="bi bi-lock me-1"></i>Senha *
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control form-control-lg" id="senha" name="senha" 
                                               required placeholder="Mínimo 6 caracteres">
                                        <span class="input-group-text password-toggle" onclick="togglePassword('senha')">
                                            <i class="bi bi-eye"></i>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="confirmar_senha" class="form-label">
                                        <i class="bi bi-lock-fill me-1"></i>Confirmar Senha *
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control form-control-lg" id="confirmar_senha" 
                                               name="confirmar_senha" required placeholder="Digite novamente a senha">
                                        <span class="input-group-text password-toggle" onclick="togglePassword('confirmar_senha')">
                                            <i class="bi bi-eye"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="termos" required>
                                    <label class="form-check-label" for="termos">
                                        Concordo com os <a href="#" class="text-decoration-none">Termos de Uso</a> e 
                                        <a href="#" class="text-decoration-none">Política de Privacidade</a>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-gradient btn-lg">
                                    <i class="bi bi-person-plus me-2"></i>Criar Perfil
                                </button>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="mb-0">Já possui uma conta?</p>
                            <a href="login.php" class="btn btn-outline-primary mt-2">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Fazer Login
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4 text-white">
                    <p class="mb-0">© 2025 HelpDesk - Sistema de Suporte Técnico</p>
                    <small>Desenvolvido para facilitar seu atendimento</small>
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
        
        // Validação em tempo real das senhas
        document.getElementById('formCadastro').addEventListener('submit', function(e) {
            const senha = document.getElementById('senha');
            const confirmarSenha = document.getElementById('confirmar_senha');
            
            if (senha.value !== confirmarSenha.value) {
                e.preventDefault();
                alert('As senhas não coincidem!');
                confirmarSenha.focus();
            }
            
            if (senha.value.length < 6) {
                e.preventDefault();
                alert('A senha deve ter pelo menos 6 caracteres!');
                senha.focus();
            }
        });
    </script>
</body>
</html>
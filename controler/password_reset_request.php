<?php
require '../includes/db.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$email_config = $GLOBALS['email_config'];
$hostEmail = $email_config['hostEmail'];
$username = $email_config['email'];
$password = $email_config['senhaEmail'];
$port = $email_config['portaEmail'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
    if (!$stmt) {
        die("Erro na preparação da query: " . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $id_usuario = $user['id'];
        $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $stmtInsert = $conn->prepare("INSERT INTO recuperacao_senha (id_usuario, codigo) VALUES (?, ?)");
        if (!$stmtInsert) {
            die("Erro na preparação da query INSERT: " . $conn->error);
        }
        
        $stmtInsert->bind_param("is", $id_usuario, $codigo);
        
        if ($stmtInsert->execute()) {
            $mail = new PHPMailer(true);
            try {
                // Configurações SMTP do Gmail
                $mail->isSMTP();
                $mail->Host = $hostEmail;
                $mail->SMTPAuth = true;
                $mail->Username = $username;
                $mail->Password = $password;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $port;
                $mail->CharSet = 'UTF-8';
                
                // Debug (opcional - descomente se precisar)
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );

                // Remetente e destinatário
                $mail->setFrom($username, 'SisTecInf');
                $mail->addAddress($email);

                // Conteúdo do e-mail
                $mail->isHTML(true);
                $mail->Subject = 'Recuperação de senha - SisTecInf';
                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <h2 style='color: #343a40;'>Recuperação de Senha</h2>
                        <p>Olá,</p>
                        <p>Seu código de recuperação é: <strong style='font-size: 24px; color: #007bff;'>$codigo</strong></p>
                        <p>Este código é válido por 15 minutos.</p>
                        <p>Se você não solicitou esta recuperação, ignore este email.</p>
                        <hr>
                        <p style='color: #6c757d; font-size: 12px;'>SisTecInf - Sistema de Gerenciamento de Chamados</p>
                    </div>
                ";
                $mail->AltBody = "Seu código de recuperação é: $codigo (válido por 15 minutos)";

                if ($mail->send()) {
                    header("Location: password_reset_verify.php?id_usuario=$id_usuario");
                    exit;
                } else {
                    $error = "Falha ao enviar o e-mail. Tente novamente.";
                }
            } catch (Exception $e) {
                $error = "Falha ao enviar o e-mail. Erro: {$mail->ErrorInfo}";
                
                // Fallback para desenvolvimento em caso de erro
                if (strpos($error, 'authenticate') !== false) {
                    $error .= "<br><br><div class='alert alert-warning'>
                                <strong>⚠️ Problema de Autenticação:</strong><br>
                                Você precisa usar uma <strong>Senha de App</strong> do Google.<br>
                                <small>Acesse: https://myaccount.google.com/apppasswords</small>
                                <br><br>
                                <strong>Código Gerado:</strong> $codigo<br>
                                <a href='password_reset_verify.php?id_usuario=$id_usuario' class='btn btn-sm btn-success mt-2'>
                                    Continuar com o código
                                </a>
                              </div>";
                }
            }
        } else {
            $error = "Erro ao gerar código de recuperação: " . $stmtInsert->error;
        }
        $stmtInsert->close();
    } else {
        $error = 'E-mail não encontrado em nosso sistema.';
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/2blog.png" type="image/png">
    <title>Recuperar Senha - HelpDesk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="senha">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header text-center py-4">
                        <h3><i class="bi bi-key"></i> Recuperar Senha</h3>
                    </div>
                    <div class="card-body p-5">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email cadastrado</label>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       placeholder="seu.email@exemplo.com">
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-send"></i> Enviar Código
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-3">
                            <a href="../login.php" class="text-decoration-none">
                                <i class="bi bi-arrow-left"></i> Voltar para login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
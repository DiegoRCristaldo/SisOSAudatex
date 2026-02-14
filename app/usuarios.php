<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/funcoes_padrao.php';

// Verifica se é admin
if ($_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Criar usuário
if (isset($_POST['acao']) && $_POST['acao'] === 'criar') {
    $nome = mb_convert_case($_POST['nome'], MB_CASE_TITLE);
    $email = $_POST['email'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    $tipo = $_POST['tipo'];
    $posto_graduacao = $_POST['posto_graduacao'];
    $nome_guerra = strtoupper($_POST['nome_guerra']);
    $om = $_POST['om'];
    $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo, posto_graduacao, nome_guerra, om, data_cadastro) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssssss", $nome, $email, $senha, $tipo, $posto_graduacao, $nome_guerra, $om);
    $stmt->execute();
    header("Location: usuarios.php");
    exit();
}

// Editar usuário
if (isset($_POST['acao']) && $_POST['acao'] === 'editar') {
    $id = $_POST['id'];
    $nome = mb_convert_case($_POST['nome'], MB_CASE_TITLE);
    $email = $_POST['email'];
    $tipo = $_POST['tipo'];
    $posto_graduacao = $_POST['posto_graduacao'];
    $nome_guerra = strtoupper($_POST['nome_guerra']);
    $om = $_POST['om'];

    if (!empty($_POST['senha'])) {
        $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE usuarios SET nome=?, email=?, senha=?, tipo=?, posto_graduacao=?, nome_guerra=?, om=? WHERE id=?");
        $stmt->bind_param("ssssssi", $nome, $email, $senha, $tipo, $posto_graduacao, $nome_guerra, $om, $id);
    } else {
        $stmt = $conn->prepare("UPDATE usuarios SET nome=?, email=?, tipo=?, posto_graduacao=?, nome_guerra=?, om=? WHERE id=?");
        $stmt->bind_param("ssssssi", $nome, $email, $tipo, $posto_graduacao, $nome_guerra, $om, $id);
    }
    $stmt->execute();
    header("Location: usuarios.php");
    exit();
}

// Excluir usuário
if (isset($_GET['excluir'])) {
    $id = $_GET['excluir'];
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: usuarios.php");
    exit();
}

// Lista usuários
$result = $conn->query("SELECT * FROM usuarios ORDER BY id DESC");
$usuarios = $result->fetch_all(MYSQLI_ASSOC);

$patentes = patentes();
$omFormatada = formatarOM();

require 'header.php';
?>
</head>
<body class="d-flex flex-row bg-light">
    <?php require_once 'sidebar.php' ?>
    <div class="container mt-4">
        <h1 class="mb-4">👥 Gerenciar Usuários</h1>
        <div class="d-flex justify-content-between">
            <div class="mb-3">
                <a href="index.php" class="btn btn-secondary">⬅ Voltar ao Menu</a>
            </div>
            <!-- Botão adicionar -->
            <button class="btn btn-primary mb-3 w-25" data-bs-toggle="modal" data-bs-target="#modalCriar">+ Novo Usuário</button>
        </div>
    
        <!-- Tabela -->
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Posto/ Graduação</th>
                        <th>Nome Completo</th>
                        <th>Nome de Guerra</th>
                        <th>Organização Militar</th>
                        <th>Email</th>
                        <th>Tipo</th>
                        <th>Data Cadastro</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($patentes[$u['posto_graduacao']] ?? $u['posto_graduacao']) ?></td>                    <td><?= htmlspecialchars($u['nome']) ?></td>
                        <td><?= htmlspecialchars($u['nome_guerra']) ?></td>
                        <td><?= htmlspecialchars($omFormatada[$u['om']]) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= mb_convert_case($u['tipo'], MB_CASE_TITLE) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($u['data_cadastro'])) ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $u['id'] ?>">Editar</button>
                            <a href="usuarios.php?excluir=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Excluir este usuário?')">Excluir</a>
                        </td>
                    </tr>

                    <!-- Modal Editar -->
                    <div class="modal fade" id="modalEditar<?= $u['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                        <form method="POST">
                            <div class="modal-header">
                            <h5 class="modal-title">Editar Usuário</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                            <input type="hidden" name="acao" value="editar">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <div class="mb-3">
                                <label>Posto/ Graduação</label>
                                <select name="posto_graduacao" class="form-select" required>
                                    <?php foreach($patentes as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= $u['posto_graduacao'] == $key ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label>Nome Completo</label>
                                <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($u['nome']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label>Nome de Guerra</label>
                                <input type="text" name="nome_guerra" class="form-control" value="<?= htmlspecialchars($u['nome_guerra']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label>Organização Militar</label>
                                <select name="om" class="form-select" required>
                                    <option value="2blog">2°Batalhão Logístico</option>
                                    <option value="28bimec">28°Batalhão de Infantaria Mecanizada</option>
                                    <option value="11pelpe">11°Pelotão de Polícia do Exército</option>
                                    <option value="cmdo11bda">Comando da 11ªBrigada de Infantaria Mecanizada</option>
                                    <option value="ciacmdo11bda">Companhia de Comando da 11ªBrigada de Infantaria Mecanizada</option>
                                    <option value="2ciacom">2ªComapanhia de Comunicações</option>
                                    <option value="pmgu">Posto Médico da Guarnição</option>
                                    <option value="espcex">Escola Preparatória de Cadetes do Exército</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label>Senha (deixe em branco para não alterar)</label>
                                <input type="password" name="senha" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label>Tipo</label>
                                <select name="tipo" class="form-select" required>
                                    <option value="admin" <?= $u['tipo'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                                    <option value="tecnico" <?= $u['tipo'] == 'audatex' ? 'selected' : '' ?>>Audatex</option>
                                    <option value="usuario" <?= $u['tipo'] == 'usuario' ? 'selected' : '' ?>>Usuário</option>
                                </select>
                            </div>
                            </div>
                            <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                            </div>
                        </form>
                        </div>
                    </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Criar -->
    <div class="modal fade" id="modalCriar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                <h5 class="modal-title">Novo Usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                <input type="hidden" name="acao" value="criar">
                <div class="mb-3">
                    <label>Posto/ Graduação</label>
                    <select name="posto_graduacao" class="form-select" required>
                        <option value="">Selecione</option>
                        <?php foreach($patentes as $key => $label): ?>
                        <option value="<?= $key ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Nome Completo</label>
                    <input type="text" name="nome" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Nome de Guerra</label>
                    <input type="text" name="nome_guerra" class="form-control" required>
                </div>
                    <div class="mb-3">
                        <label>Organização Militar</label>
                        <select name="om" class="form-select" required>
                            <option value="2blog">2°Batalhão Logístico</option>
                            <option value="28bimec">28°Batalhão de Infantaria Mecanizada</option>
                            <option value="11pelpe">11°Pelotão de Polícia do Exército</option>
                            <option value="cmdo11bda">Comando da 11ªBrigada de Infantaria Mecanizada</option>
                            <option value="ciacmdo11bda">Companhia de Comando da 11ªBrigada de Infantaria Mecanizada</option>
                            <option value="2ciacom">2ªComapanhia de Comunicações</option>
                            <option value="pmgu">Posto Médico da Guarnição</option>
                            <option value="espcex">Escola Preparatória de Cadetes do Exército</option>
                        </select>
                    </div>
                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Senha</label>
                    <input type="password" name="senha" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Tipo</label>
                    <select name="tipo" class="form-select" required>
                        <option value="admin">Admin</option>
                        <option value="audatex">Audatex</option>
                        <option value="usuario">Usuário</option>
                    </select>
                </div>
                </div>
                <div class="modal-footer">
                <button type="submit" class="btn btn-success">Adicionar</button>
                </div>
            </form>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
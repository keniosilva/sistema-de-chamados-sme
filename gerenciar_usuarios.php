<?php
require_once 'connection.php';
require_once 'helpers.php';
verificarPermissao(['admin']);

$mensagem = '';
$erro = '';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Consultar unidades escolares
$stmt = $conn->prepare("SELECT id, nome_unidade FROM unidades_escolares ORDER BY nome_unidade");
$stmt->execute();
$unidades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Consultar usuários
$stmt = $conn->prepare("
    SELECT u.id, u.nome, u.nome_usuario, u.email, u.tipo_usuario, u.id_unidade_escolar, ue.nome_unidade
    FROM usuarios u
    LEFT JOIN unidades_escolares ue ON u.id_unidade_escolar = ue.id
    ORDER BY u.nome
");
$stmt->execute();
$usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Processar ações
if ($_POST) {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao == 'cadastrar') {
        $nome = trim($_POST['nome'] ?? '');
        $nome_usuario = trim($_POST['nome_usuario'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $tipo_usuario = $_POST['tipo_usuario'] ?? '';
        $id_unidade_escolar = null;

        if ($nome && $nome_usuario && $email && $senha && $tipo_usuario) {
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE nome_usuario = ?");
            $stmt->bind_param("s", $nome_usuario);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                
                if (in_array($tipo_usuario, ['unidade_escolar', 'secretaria', 'tecnico_geral', 'tecnico_informatica', 'casa_da_merenda', 'almoxarifado'])) {
                    if ($tipo_usuario == 'unidade_escolar') {
                        $id_unidade_escolar = $_POST['id_unidade_escolar'] ?? null;
                    } else {
                        $stmt_sme = $conn->prepare("SELECT id FROM unidades_escolares WHERE nome_unidade = ?");
                        $nome_sme = 'SME';
                        $stmt_sme->bind_param("s", $nome_sme);
                        $stmt_sme->execute();
                        $result_sme = $stmt_sme->get_result();
                        $sme_data = $result_sme->fetch_assoc();
                        $id_unidade_escolar = $sme_data['id'] ?? null;
                        $stmt_sme->close();
                    }
                }
                
                $stmt = $conn->prepare("INSERT INTO usuarios (nome, nome_usuario, email, senha, tipo_usuario, id_unidade_escolar, primeiro_login) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $primeiro_login = 1;
                $stmt->bind_param("sssssii", $nome, $nome_usuario, $email, $senha_hash, $tipo_usuario, $id_unidade_escolar, $primeiro_login);
                
                if ($stmt->execute()) {
                    $mensagem = 'Usuário cadastrado com sucesso!';
                    header("Location: gerenciar_usuarios.php?mensagem=" . urlencode($mensagem));
                    exit();
                } else {
                    $erro = 'Erro ao cadastrar usuário: ' . $stmt->error;
                    error_log("Erro ao cadastrar usuário: " . $stmt->error);
                }
            } else {
                $erro = 'Já existe um usuário com este nome de usuário.';
            }
            $stmt->close();
        } else {
            $erro = 'Por favor, preencha todos os campos obrigatórios.';
        }
    } else if ($acao == 'editar') {
        $id = $_POST['id'];
        $nome = trim($_POST['nome'] ?? '');
        $nome_usuario = trim($_POST['nome_usuario'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $tipo_usuario = $_POST['tipo_usuario'] ?? '';
        $id_unidade_escolar = null;
        
        if ($nome && $nome_usuario && $email && $tipo_usuario) {
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE nome_usuario = ? AND id != ?");
            $stmt->bind_param("si", $nome_usuario, $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                if (in_array($tipo_usuario, ['unidade_escolar', 'secretaria', 'tecnico_geral', 'tecnico_informatica', 'casa_da_merenda', 'almoxarifado'])) {
                    if ($tipo_usuario == 'unidade_escolar') {
                        $id_unidade_escolar = $_POST['id_unidade_escolar'] ?? null;
                    } else {
                        $stmt_sme = $conn->prepare("SELECT id FROM unidades_escolares WHERE nome_unidade = ?");
                        $nome_sme = 'SME';
                        $stmt_sme->bind_param("s", $nome_sme);
                        $stmt_sme->execute();
                        $result_sme = $stmt_sme->get_result();
                        $sme_data = $result_sme->fetch_assoc();
                        $id_unidade_escolar = $sme_data['id'] ?? null;
                        $stmt_sme->close();
                    }
                } else {
                    $id_unidade_escolar = null;
                }

                if ($senha) {
                    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE usuarios SET nome = ?, nome_usuario = ?, email = ?, senha = ?, tipo_usuario = ?, id_unidade_escolar = ?, primeiro_login = ? WHERE id = ?");
                    $primeiro_login = 1;
                    $stmt->bind_param("sssssiii", $nome, $nome_usuario, $email, $senha_hash, $tipo_usuario, $id_unidade_escolar, $primeiro_login, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE usuarios SET nome = ?, nome_usuario = ?, email = ?, tipo_usuario = ?, id_unidade_escolar = ? WHERE id = ?");
                    $stmt->bind_param("ssssii", $nome, $nome_usuario, $email, $tipo_usuario, $id_unidade_escolar, $id);
                }

                if ($stmt->execute()) {
                    $mensagem = 'Usuário atualizado com sucesso!';
                    header("Location: gerenciar_usuarios.php?mensagem=" . urlencode($mensagem));
                    exit();
                } else {
                    $erro = 'Erro ao atualizar usuário: ' . $stmt->error;
                    error_log("Erro ao atualizar usuário: " . $stmt->error);
                }
            } else {
                $erro = 'Já existe outro usuário com este nome de usuário.';
            }
            $stmt->close();
        } else {
            $erro = 'Por favor, preencha todos os campos obrigatórios.';
        }
    } else if ($acao == 'excluir') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $mensagem = 'Usuário excluído com sucesso!';
            header("Location: gerenciar_usuarios.php?mensagem=" . urlencode($mensagem));
            exit();
        } else {
            $erro = 'Erro ao excluir usuário: ' . $stmt->error;
            error_log("Erro ao excluir usuário: " . $stmt->error);
        }
        $stmt->close();
    } else if ($acao == 'reset_senha') {
        $id = $_POST['id'];
        $senha_padrao = 'admin123';
        $senha_hash = password_hash($senha_padrao, PASSWORD_DEFAULT);
        $primeiro_login = 1;

        $stmt = $conn->prepare("UPDATE usuarios SET senha = ?, primeiro_login = ? WHERE id = ?");
        $stmt->bind_param("sii", $senha_hash, $primeiro_login, $id);
        if ($stmt->execute()) {
            $mensagem = 'Senha resetada com sucesso para "mudar123". O usuário precisará alterá-la no próximo login.';
            header("Location: gerenciar_usuarios.php?mensagem=" . urlencode($mensagem));
            exit();
        } else {
            $erro = 'Erro ao resetar senha: ' . $stmt->error;
            error_log("Erro ao resetar senha: " . $stmt->error);
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Sistema de Chamados</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.8rem;
        }
        
        .container {
            max-width: 1100px;
            margin: 3rem auto;
            padding: 0 2rem;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        h1, h2 {
            margin-bottom: 1.5rem;
        }
        
        h1 {
            font-size: 2rem;
        }
        
        h2 {
            font-size: 1.5rem;
            color: #333;
            border-bottom: 2px solid #eee;
            padding-bottom: 0.5rem;
        }
        
        .table {
            margin-top: 1.5rem;
        }
        
        .table th, .table td {
            vertical-align: middle;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
            white-space: nowrap;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 0.9rem;
            text-decoration: none;
            color: white;
            font-weight: 500;
            transition: opacity 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .btn-primary {
            background: #007bff;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 2rem;
            border: 1px solid #888;
            width: 90%;
            max-width: 700px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .unidade-group {
            display: none;
        }
        
        .form-label {
            font-weight: 500;
            color: #333;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ced4da;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .alert {
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
     <div class="header">
        <div class="header-content">
            <h1>Gerenciar Unidades Escolares</h1>
            <div class="nav-links">
                <a href="admin_dashboard.php">Voltar ao Dashboard</a>
                <a href="gerenciar_unidades.php">Gerenciar Unidades</a>
                <a href="logout.php">Sair</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <?php if ($mensagem): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($erro): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Cadastrar Novo Usuário</h2>
            
            <form method="POST">
                <input type="hidden" name="acao" value="cadastrar">
                
                <div class="mb-3">
                    <label for="nome" class="form-label">Nome Completo</label>
                    <input type="text" class="form-control" id="nome" name="nome" required>
                </div>
                
                <div class="mb-3">
                    <label for="nome_usuario" class="form-label">Nome de Usuário</label>
                    <input type="text" class="form-control" id="nome_usuario" name="nome_usuario" required>
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                
                <div class="mb-3">
                    <label for="senha" class="form-label">Senha</label>
                    <input type="password" class="form-control" id="senha" name="senha" required>
                </div>
                
                <div class="mb-3">
                    <label for="tipo_usuario" class="form-label">Tipo de Usuário</label>
                    <select class="form-select" id="tipo_usuario" name="tipo_usuario" onchange="toggleUnidade()" required>
                        <option value="admin">Administrador</option>
                        <option value="unidade_escolar">Unidade Escolar</option>
                        <option value="secretaria">Secretaria</option>
                        <option value="tecnico_geral">Técnico Geral</option>
                        <option value="tecnico_informatica">Técnico Informática</option>
                        <option value="casa_da_merenda">Casa da Merenda</option>
                        <option value="almoxarifado">Almoxarifado</option>
                    </select>
                </div>
                
                <div id="unidade-group" class="mb-3 unidade-group">
                    <label for="id_unidade_escolar" class="form-label">Unidade Escolar</label>
                    <select class="form-select" id="id_unidade_escolar" name="id_unidade_escolar">
                        <option value="">Selecione a unidade</option>
                        <?php foreach ($unidades as $unidade): ?>
                            <option value="<?php echo htmlspecialchars($unidade['id']); ?>"><?php echo htmlspecialchars($unidade['nome_unidade']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Cadastrar</button>
            </form>
        </div>
        
        <div class="card">
            <h2>Lista de Usuários</h2>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Nome de Usuário</th>
                            <th>E-mail</th>
                            <th>Tipo</th>
                            <th>Unidade Escolar</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($usuario['id']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['nome']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['nome_usuario'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($usuario['email'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($usuario['tipo_usuario']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['nome_unidade'] ?? 'N/A'); ?></td>
                                <td class="actions">
                                    <button class="btn btn-primary btn-sm" onclick='editarUsuario(<?php echo json_encode($usuario, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                        <i class="bi bi-pencil"></i> Editar
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja resetar a senha deste usuário? A senha será definida para \'admin123\'.');">
                                        <input type="hidden" name="acao" value="reset_senha">
                                        <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                                        <button type="submit" class="btn btn-warning btn-sm">
                                            <i class="bi bi-arrow-counterclockwise"></i> Reset Senha
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este usuário?');">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="bi bi-trash"></i> Excluir
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Modal de Edição -->
        <div id="modalEdicao" class="modal">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" id="edit_id" name="id">
                    
                    <div class="mb-3">
                        <label for="edit_nome" class="form-label">Nome Completo</label>
                        <input type="text" class="form-control" id="edit_nome" name="nome" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_nome_usuario" class="form-label">Nome de Usuário</label>
                        <input type="text" class="form-control" id="edit_nome_usuario" name="nome_usuario" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_senha" class="form-label">Nova Senha (opcional)</label>
                        <input type="password" class="form-control" id="edit_senha" name="senha">
                    </div>
                    <div class="mb-3">
                        <label for="edit_tipo_usuario" class="form-label">Tipo de Usuário</label>
                        <select class="form-select" id="edit_tipo_usuario" name="tipo_usuario" onchange="toggleEditUnidade()" required>
                            <option value="admin">Administrador</option>
                            <option value="unidade_escolar">Unidade Escolar</option>
                            <option value="secretaria">Secretaria</option>
                            <option value="tecnico_geral">Técnico Geral</option>
                            <option value="tecnico_informatica">Técnico Informática</option>
                            <option value="casa_da_merenda">Casa da Merenda</option>
                            <option value="almoxarifado">Almoxarifado</option>
                        </select>
                    </div>
                    
                    <div id="edit-unidade-group" class="mb-3 unidade-group">
                        <label for="edit_id_unidade_escolar" class="form-label">Unidade Escolar</label>
                        <select class="form-select" id="edit_id_unidade_escolar" name="id_unidade_escolar">
                            <option value="">Selecione a unidade</option>
                            <?php foreach ($unidades as $unidade): ?>
                                <option value="<?php echo htmlspecialchars($unidade['id']); ?>"><?php echo htmlspecialchars($unidade['nome_unidade']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="actions">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Salvar</button>
                        <button type="button" class="btn btn-secondary" onclick="fecharModal()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function toggleUnidade() {
            const tipoUsuario = document.getElementById('tipo_usuario').value;
            const unidadeGroup = document.getElementById('unidade-group');
            const unidadeSelect = document.getElementById('id_unidade_escolar');
            
            if (tipoUsuario === 'unidade_escolar') {
                unidadeGroup.style.display = 'block';
                unidadeSelect.required = true;
                unidadeSelect.disabled = false;
                unidadeSelect.value = '';
            } else if (tipoUsuario === 'secretaria' || tipoUsuario === 'tecnico_geral' || tipoUsuario === 'tecnico_informatica' || tipoUsuario === 'casa_da_merenda' || tipoUsuario === 'almoxarifado') {
                unidadeGroup.style.display = 'block';
                unidadeSelect.required = true;
                unidadeSelect.disabled = true;
                for (let option of unidadeSelect.options) {
                    if (option.text === 'SME') {
                        option.selected = true;
                        break;
                    }
                }
            } else {
                unidadeGroup.style.display = 'none';
                unidadeSelect.required = false;
                unidadeSelect.value = '';
                unidadeSelect.disabled = false;
            }
        }
        
        function toggleEditUnidade() {
            const tipoUsuario = document.getElementById('edit_tipo_usuario').value;
            const unidadeGroup = document.getElementById('edit-unidade-group');
            const unidadeSelect = document.getElementById('edit_id_unidade_escolar');
            
            if (tipoUsuario === 'unidade_escolar') {
                unidadeGroup.style.display = 'block';
                unidadeSelect.required = true;
                unidadeSelect.disabled = false;
            } else if (tipoUsuario === 'secretaria' || tipoUsuario === 'tecnico_geral' || tipoUsuario === 'tecnico_informatica' || tipoUsuario === 'casa_da_merenda' || tipoUsuario === 'almoxarifado') {
                unidadeGroup.style.display = 'block';
                unidadeSelect.required = true;
                unidadeSelect.disabled = true;
                for (let option of unidadeSelect.options) {
                    if (option.text === 'SME') {
                        option.selected = true;
                        break;
                    }
                }
            } else {
                unidadeGroup.style.display = 'none';
                unidadeSelect.required = false;
                unidadeSelect.value = '';
                unidadeSelect.disabled = false;
            }
        }

        function editarUsuario(usuario) {
            // Garantir que os valores sejam strings, tratando null
            document.getElementById('edit_id').value = usuario.id || '';
            document.getElementById('edit_nome').value = usuario.nome || '';
            document.getElementById('edit_nome_usuario').value = usuario.nome_usuario || '';
            document.getElementById('edit_email').value = usuario.email || '';
            document.getElementById('edit_tipo_usuario').value = usuario.tipo_usuario || '';
            document.getElementById('edit_id_unidade_escolar').value = usuario.id_unidade_escolar || '';
            
            toggleEditUnidade();
            
            document.getElementById('modalEdicao').style.display = 'block';
        }
        
        function fecharModal() {
            document.getElementById('modalEdicao').style.display = 'none';
            document.getElementById('edit_senha').value = '';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('modalEdicao');
            if (event.target == modal) {
                fecharModal();
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
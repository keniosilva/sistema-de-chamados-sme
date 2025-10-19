<?php
ob_start();
require_once 'connection.php';
require_once 'helpers.php';
verificarPermissao(['admin']);

$mensagem = '';
$erro = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao == 'cadastrar') {
        $nome_unidade = trim($_POST['nome_unidade'] ?? '');
        $endereco = trim($_POST['endereco'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if ($nome_unidade) {
            // Verificar se nome já existe
            $stmt = $conn->prepare("SELECT id FROM unidades_escolares WHERE nome_unidade = ?");
            $stmt->bind_param("s", $nome_unidade);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                $stmt = $conn->prepare("INSERT INTO unidades_escolares (nome_unidade, endereco, telefone, email) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $nome_unidade, $endereco, $telefone, $email);
                
                if ($stmt->execute()) {
                    $mensagem = 'Unidade escolar cadastrada com sucesso!';
                } else {
                    $erro = 'Erro ao cadastrar unidade escolar: ' . $stmt->error;
                }
            } else {
                $erro = 'Já existe uma unidade com este nome.';
            }
            $stmt->close();
        } else {
            $erro = 'Por favor, preencha o campo Nome da Unidade.';
        }
    }
    
    if ($acao == 'editar') {
        $id = (int)($_POST['id'] ?? 0);
        $nome_unidade = trim($_POST['nome_unidade'] ?? '');
        $endereco = trim($_POST['endereco'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if ($id && $nome_unidade) {
            $stmt = $conn->prepare("UPDATE unidades_escolares SET nome_unidade = ?, endereco = ?, telefone = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $nome_unidade, $endereco, $telefone, $email, $id);
            
            if ($stmt->execute()) {
                $mensagem = 'Unidade escolar atualizada com sucesso!';
            } else {
                $erro = 'Erro ao atualizar unidade escolar: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $erro = 'Por favor, preencha o campo Nome da Unidade.';
        }
    }
}

// Processar exclusão
if (isset($_GET['deletar'])) {
    $id = (int)($_GET['deletar'] ?? 0);
    if ($id) {
        // Verificar se a unidade tem usuários ou chamados associados
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM usuarios WHERE id_unidade_escolar = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $usuarios = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM chamados WHERE id_unidade_escolar = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $chamados = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        if ($usuarios > 0 || $chamados > 0) {
            $erro = 'Não é possível excluir a unidade pois ela possui ' . ($usuarios > 0 ? "usuários ($usuarios)" : '') . 
                    ($usuarios > 0 && $chamados > 0 ? ' e ' : '') . 
                    ($chamados > 0 ? "chamados ($chamados)" : '') . ' associados.';
        } else {
            $stmt = $conn->prepare("DELETE FROM unidades_escolares WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $mensagem = 'Unidade escolar excluída com sucesso!';
            } else {
                $erro = 'Erro ao excluir unidade escolar: ' . $stmt->error;
            }
            $stmt->close();
        }
    } else {
        $erro = 'ID inválido para exclusão.';
    }
}

// Buscar unidades escolares
$stmt = $conn->query("
    SELECT ue.*, 
           COUNT(u.id) as total_usuarios,
           COUNT(c.id) as total_chamados
    FROM unidades_escolares ue
    LEFT JOIN usuarios u ON ue.id = u.id_unidade_escolar
    LEFT JOIN chamados c ON ue.id = c.id_unidade_escolar
    GROUP BY ue.id
    ORDER BY ue.nome_unidade
");
$unidades = $stmt;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Unidades Escolares - Sistema de Chamados</title>
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
            padding: 1rem 0;
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
            font-size: 1.5rem;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            margin-left: 1rem;
            font-weight: 500;
        }
        
        .nav-links a:hover {
            text-decoration: underline;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .card h2 {
            color: #333;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: transform 0.2s;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            margin-right: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .erro {
            background: #fee;
            color: #c33;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border-left: 4px solid #c33;
        }
        
        .sucesso {
            background: #dfd;
            color: #060;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border-left: 4px solid #060;
        }
        
        .table-box {
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #667eea;
            color: white;
            font-weight: 600;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 90%;
        }
        
        .close {
            float: right;
            font-size: 1.5rem;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Gerenciar Unidades Escolares</h1>
            <div class="nav-links">
                <a href="admin_dashboard.php">Voltar ao Dashboard</a>
                <a href="gerenciar_usuarios.php">Gerenciar Usuários</a>
                <a href="logout.php">Sair</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <?php if ($mensagem): ?>
            <div class="sucesso"><?php echo htmlspecialchars($mensagem); ?></div>
        <?php endif; ?>
        <?php if ($erro): ?>
            <div class="erro"><?php echo htmlspecialchars($erro); ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Cadastrar Nova Unidade Escolar</h2>
            
            <form method="POST">
                <input type="hidden" name="acao" value="cadastrar">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nome_unidade">Nome da Unidade:</label>
                        <input type="text" id="nome_unidade" name="nome_unidade" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="telefone">Telefone:</label>
                        <input type="text" id="telefone" name="telefone">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="endereco">Endereço Completo:</label>
                    <input type="text" id="endereco" name="endereco">
                </div>
                
                <button type="submit" class="btn">Cadastrar</button>
            </form>
        </div>
        
        <div class="card">
            <h2>Unidades Escolares Cadastradas</h2>
            
            <?php if ($unidades->num_rows > 0): ?>
                <div class="table-box">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome da Unidade</th>
                                <th>Endereço</th>
                                <th>Telefone</th>
                                <th>Email</th>
                                <th>Usuários</th>
                                <th>Chamados</th>
                                <th>Cadastro</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($unidade = $unidades->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $unidade['id']; ?></td>
                                    <td><?php echo htmlspecialchars($unidade['nome_unidade']); ?></td>
                                    <td><?php echo htmlspecialchars($unidade['endereco']); ?></td>
                                    <td><?php echo htmlspecialchars($unidade['telefone']); ?></td>
                                    <td><?php echo htmlspecialchars($unidade['email']); ?></td>
                                    <td><?php echo $unidade['total_usuarios']; ?></td>
                                    <td><?php echo $unidade['total_chamados']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($unidade['data_cadastro'])); ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-small" onclick="editarUnidade(<?php echo htmlspecialchars(json_encode($unidade)); ?>)">Editar</button>
                                        <a href="?deletar=<?php echo $unidade['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Tem certeza que deseja excluir esta unidade?')">Excluir</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Nenhuma unidade escolar encontrada.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal de Edição -->
    <div id="modalEdicao" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal()">&times;</span>
            <h2>Editar Unidade Escolar</h2>
            
            <form method="POST">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" id="edit_id" name="id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_nome_unidade">Nome da Unidade:</label>
                        <input type="text" id="edit_nome_unidade" name="nome_unidade" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_telefone">Telefone:</label>
                        <input type="text" id="edit_telefone" name="telefone">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_email">Email:</label>
                        <input type="email" id="edit_email" name="email">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_endereco">Endereço Completo:</label>
                    <input type="text" id="edit_endereco" name="endereco">
                </div>
                
                <button type="submit" class="btn">Salvar Alterações</button>
                <button type="button" class="btn btn-secondary" onclick="fecharModal()">Cancelar</button>
            </form>
        </div>
    </div>
    
    <script>
        function editarUnidade(unidade) {
            document.getElementById('edit_id').value = unidade.id;
            document.getElementById('edit_nome_unidade').value = unidade.nome_unidade;
            document.getElementById('edit_endereco').value = unidade.endereco;
            document.getElementById('edit_telefone').value = unidade.telefone || '';
            document.getElementById('edit_email').value = unidade.email || '';
            
            document.getElementById('modalEdicao').style.display = 'block';
        }
        
        function fecharModal() {
            document.getElementById('modalEdicao').style.display = 'none';
        }
        
        // Fechar modal ao clicar fora dele
        window.onclick = function(event) {
            const modal = document.getElementById('modalEdicao');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
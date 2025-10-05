<?php
session_start();
require_once 'connection.php';
verificarPermissao(['tecnico', 'admin']);

// Definir variáveis de sessão planas para compatibilidade com outros scripts
$_SESSION['tipo_usuario'] = $_SESSION['user']['tipo_usuario'];
$_SESSION['usuario_id'] = $_SESSION['user']['id'];

$chamado_id = $_GET['id'] ?? 0;

if (!$chamado_id) {
    header("Location: " . ($_SESSION['tipo_usuario'] == 'admin' ? 'admin_dashboard.php' : 'tecnico_dashboard.php') . "?erro=Chamado inválido");
    exit();
}

// Verificar se o chamado existe e se o técnico tem permissão
$stmt = $conn->prepare("SELECT * FROM chamados WHERE id = ? AND (id_tecnico_responsavel = ? OR ? = 'admin')");
$stmt->bind_param("iis", $chamado_id, $_SESSION['usuario_id'], $_SESSION['tipo_usuario']);
$stmt->execute();
$chamado = $stmt->get_result()->fetch_assoc();

if (!$chamado) {
    header("Location: " . ($_SESSION['tipo_usuario'] == 'admin' ? 'admin_dashboard.php' : 'tecnico_dashboard.php') . "?erro=Chamado não encontrado ou sem permissão");
    exit();
}

// Obter o nome do usuário a partir do banco
$stmt = $conn->prepare("SELECT nome FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $_SESSION['usuario_id']);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$usuario_nome = $usuario['nome'];

$erro = '';
$sucesso = '';

if ($_POST) {
    $observacao = trim($_POST['observacao'] ?? '');
    
    if ($observacao) {
        $observacao = filter_var($observacao, FILTER_SANITIZE_STRING);
        $observacao_atual = $chamado['observacoes_tecnico'];
        $nova_observacao = $observacao_atual ? $observacao_atual . "\n\n--- " . date('d/m/Y H:i') . " - " . $usuario_nome . " ---\n" . $observacao : "--- " . date('d/m/Y H:i') . " - " . $usuario_nome . " ---\n" . $observacao;
        
        $stmt = $conn->prepare("UPDATE chamados SET observacoes_tecnico = ? WHERE id = ?");
        $stmt->bind_param("si", $nova_observacao, $chamado_id);
        
        if ($stmt->execute()) {
            header("Location: ver_chamado.php?id=" . $chamado_id . "&sucesso=Observação adicionada com sucesso");
            exit();
        } else {
            $erro = 'Erro ao adicionar observação.';
        }
    } else {
        $erro = 'Por favor, digite uma observação.';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Observação - Chamado #<?php echo $chamado['id']; ?></title>
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
        
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .card h2 {
            color: #333;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
            font-family: inherit;
            min-height: 120px;
            resize: vertical;
        }
        
        .form-group textarea:focus {
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
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .erro {
            background: #fee;
            color: #c33;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border-left: 4px solid #c33;
        }
        
        .observacoes-atuais {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            white-space: pre-line;
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Adicionar Observação - Chamado #<?php echo $chamado['id']; ?></h1>
            <a href="ver_chamado.php?id=<?php echo $chamado['id']; ?>" class="btn btn-secondary">Voltar</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2>Adicionar Observação Técnica</h2>
            
            <?php if ($erro): ?>
                <div class="erro"><?php echo htmlspecialchars($erro); ?></div>
            <?php endif; ?>
            
            <?php if ($chamado['observacoes_tecnico']): ?>
                <h3 style="color: #667eea; margin-bottom: 1rem;">Observações Anteriores:</h3>
                <div class="observacoes-atuais"><?php echo htmlspecialchars($chamado['observacoes_tecnico']); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="observacao">Nova Observação:</label>
                    <textarea id="observacao" name="observacao" placeholder="Digite aqui suas observações sobre o atendimento..." required></textarea>
                </div>
                
                <button type="submit" class="btn">Adicionar Observação</button>
                <a href="ver_chamado.php?id=<?php echo $chamado['id']; ?>" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</body>
</html>
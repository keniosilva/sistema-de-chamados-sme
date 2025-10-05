<?php
session_start();
include __DIR__ . '/connection.php';

// Garantir que o usuário está logado
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$erro = '';
$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

    if ($nova_senha && $confirmar_senha) {
        if ($nova_senha === $confirmar_senha) {
            $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $user_id = $_SESSION['user']['id'];

            $stmt = $conn->prepare("UPDATE usuarios SET senha = ?, primeiro_login = 0 WHERE id = ?");
            $stmt->bind_param("si", $senha_hash, $user_id);

            if ($stmt->execute()) {
                $mensagem = "Senha alterada com sucesso! Você será redirecionado.";
                // Atualizar a sessão para refletir o primeiro_login
                $_SESSION['user']['primeiro_login'] = 0;
                // Redirecionar com base no tipo de usuário
                $tipo_usuario = $_SESSION['user']['tipo_usuario'];
                $redirect_url = '';
                if ($tipo_usuario === 'admin') {
                    $redirect_url = 'admin_dashboard.php';
                } elseif ($tipo_usuario === 'tecnico_geral') {
                    $redirect_url = 'manutencao_dashboard.php';
                } elseif ($tipo_usuario === 'tecnico_informatica') {
                    $redirect_url = 'tecnico_dashboard.php';
                } elseif ($tipo_usuario === 'casa_da_merenda') {
                    $redirect_url = 'merenda_dashboard.php';
                } elseif ($tipo_usuario === 'almoxarifado') {
                    $redirect_url = 'almoxarifado_dashboard.php';
                } elseif ($tipo_usuario === 'secretaria') {
                    $redirect_url = 'secretaria_dashboard.php';
                } else {
                    $redirect_url = 'unidade_dashboard.php';
                }
                header("Refresh:2;url=$redirect_url");
            } else {
                $erro = "Erro ao alterar a senha: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $erro = "As senhas não coincidem.";
        }
    } else {
        $erro = "Por favor, preencha ambos os campos de senha.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alterar Senha - Sistema de Chamados</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            color: #666;
            font-size: 0.9rem;
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
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .erro {
            background: #fee;
            color: #c33;
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border-left: 4px solid #c33;
        }
        
        .mensagem {
            background: #efe;
            color: #3c3;
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border-left: 4px solid #3c3;
        }
        
        .footer {
            text-align: center;
            margin-top: 2rem;
            color: #666;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="images/logo.png" alt="prefeitura" class="agosto-lilas-img">
            <h1>Alterar Senha</h1>
            <p>Por favor, altere sua senha para continuar</p>
        </div>
        
        <?php if ($erro): ?>
            <div class="erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <?php if ($mensagem): ?>
            <div class="mensagem"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="nova_senha">Nova Senha:</label>
                <input type="password" id="nova_senha" name="nova_senha" required>
            </div>
            
            <div class="form-group">
                <label for="confirmar_senha">Confirmar Nova Senha:</label>
                <input type="password" id="confirmar_senha" name="confirmar_senha" required>
            </div>
            
            <button type="submit" class="btn">Alterar Senha</button>
        </form>
        
        <div class="footer">
            <p>© 2025 Prefeitura Municipal de Bayeux</p>
        </div>
    </div>
</body>
</html>
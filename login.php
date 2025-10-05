<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Include database connection
$connection_file = __DIR__ . '/connection.php';
if (!file_exists($connection_file)) {
    die("Erro: O arquivo connection.php não foi encontrado.");
}
require_once $connection_file;

// Verify database connection
if ($conn->connect_error) {
    error_log("Erro de conexão com o banco de dados: " . $conn->connect_error);
    die("Erro de conexão com o banco de dados.");
}

// Set UTF-8 charset
if (!$conn->set_charset("utf8mb4")) {
    error_log("Erro ao definir charset: " . $conn->error);
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_usuario = trim($_POST['nome_usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($nome_usuario) || empty($senha)) {
        $erro = "Por favor, preencha todos os campos.";
    } else {
        // Prepare SQL query to fetch user
        $sql = "SELECT id, nome, nome_usuario, senha, tipo_usuario, id_unidade_escolar, primeiro_login 
                FROM usuarios 
                WHERE nome_usuario = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $nome_usuario);
            if ($stmt->execute()) {
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($id, $nome, $nome_usuario_db, $senhaDB, $tipo_usuario, $id_unidade, $primeiro_login);
                    $stmt->fetch();

                    if (password_verify($senha, $senhaDB)) {
                        // Store user data in session
                        $_SESSION['user'] = [
                            'id' => $id,
                            'nome' => $nome,
                            'nome_usuario' => $nome_usuario_db,
                            'tipo_usuario' => $tipo_usuario,
                            'id_unidade_escolar' => $id_unidade,
                            'primeiro_login' => $primeiro_login
                        ];
                        error_log("Login bem-sucedido para $nome_usuario, tipo: $tipo_usuario");

                        // Redirect based on primeiro_login
                        if ($primeiro_login) {
                            header("Location: alterar_senha.php");
                            exit();
                        }

                        // Log redirection
                        error_log("Redirecionando usuário $nome_usuario com tipo $tipo_usuario para o dashboard correspondente");

                        // Redirect based on user type
                        $redirect_map = [
                            'admin' => 'admin_dashboard.php',
                            'tecnico_informatica' => 'tecnico_dashboard.php',
                            'tecnico_geral' => 'manutencao_dashboard.php',
                            'almoxarifado' => 'almoxarifado_dashboard.php',
                            'casa_da_merenda' => 'merenda_dashboard.php',
                            'secretaria' => 'secretaria_dashboard.php',
                            'unidade_escolar' => 'unidade_dashboard.php'
                        ];

                        $redirect_page = $redirect_map[$tipo_usuario] ?? 'unidade_dashboard.php';
                        header("Location: $redirect_page");
                        exit();
                    } else {
                        $erro = "Nome de usuário ou senha incorretos.";
                    }
                } else {
                    $erro = "Nome de usuário ou senha incorretos.";
                }
            } else {
                $erro = "Erro ao executar a consulta.";
                error_log("Erro na execução da query: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $erro = "Erro na preparação da consulta.";
            error_log("Erro na preparação da query: " . $conn->error);
        }
    }
}

// Close database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Chamados - Login</title>
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
        
        .login-container {
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
        
        .footer {
            text-align: center;
            margin-top: 2rem;
            color: #666;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="header">
            <img src="images/logo.png" alt="Prefeitura de Bayeux" class="agosto-lilas-img">
            <h1>Sistema de Chamados</h1>
            <p>Prefeitura Municipal de Bayeux<br>Secretaria Municipal de Educação</p>
        </div>
        
        <?php if (!empty($erro)): ?>
            <div class="erro"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="nome_usuario">Nome de Usuário:</label>
                <input type="text" id="nome_usuario" name="nome_usuario" value="<?php echo isset($_POST['nome_usuario']) ? htmlspecialchars($_POST['nome_usuario'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="senha">Senha:</label>
                <input type="password" id="senha" name="senha" required>
            </div>
            
            <button type="submit" class="btn">Entrar</button>
        </form>
        
        <div class="footer">
            <p>© 2025 Prefeitura Municipal de Bayeux</p>
        </div>
    </div>
</body>
</html>
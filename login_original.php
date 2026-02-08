<?php
// [PHP PERMANECE EXATAMENTE IGUAL]
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$connection_file = __DIR__ . '/connection.php';
if (!file_exists($connection_file)) {
    die("Erro: O arquivo connection.php não foi encontrado.");
}
require_once $connection_file;

if ($conn->connect_error) {
    error_log("Erro de conexão com o banco de dados: " . $conn->connect_error);
    die("Erro de conexão com o banco de dados.");
}

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
                        $_SESSION['user'] = [
                            'id' => $id,
                            'nome' => $nome,
                            'nome_usuario' => $nome_usuario_db,
                            'tipo_usuario' => $tipo_usuario,
                            'id_unidade_escolar' => $id_unidade,
                            'primeiro_login' => $primeiro_login
                        ];
                        error_log("Login bem-sucedido para $nome_usuario, tipo: $tipo_usuario");

                        if ($primeiro_login) {
                            header("Location: alterar_senha.php");
                            exit();
                        }

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

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Chamados - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
            position: relative;
            overflow: hidden;
        }
        
        .login-container {
            background: white;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 420px;
            border: 1px solid #e1e5e9;
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .header img {
            max-height: 110px;
            margin-bottom: 1rem;
        }

        .header h1 {
            color: #2d3748;
            font-size: 1.6rem;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            color: #4a5568;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 1.4rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.6rem;
            color: #2d3748;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 0.9rem;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.25s ease, box-shadow 0.25s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 3.2rem;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #718096;
            cursor: pointer;
            font-size: 1.1rem;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle:hover {
            color: #2d3748;
        }
        
        .btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.35);
        }
        
        .erro {
            background: #fff5f5;
            color: #c53030;
            padding: 0.9rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #c53030;
            font-size: 0.95rem;
        }
        
        .footer {
            text-align: center;
            margin-top: 2rem;
            color: #718096;
            font-size: 0.85rem;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 1.8rem;
            }
            .header h1 {
                font-size: 1.45rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="header">
            <img src="images/logo.png" alt="Prefeitura de Bayeux">
            <h1>Sistema de Chamados</h1>
            <p>Prefeitura Municipal de Bayeux<br>Secretaria Municipal de Educação</p>
        </div>
        
        <?php if (!empty($erro)): ?>
            <div class="erro"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="nome_usuario">Nome de Usuário:</label>
                <input type="text" id="nome_usuario" name="nome_usuario" 
                       value="<?php echo isset($_POST['nome_usuario']) ? htmlspecialchars($_POST['nome_usuario'], ENT_QUOTES, 'UTF-8') : ''; ?>" 
                       required>
            </div>
            
            <div class="form-group">
                <label for="senha">Senha:</label>
                <div class="password-wrapper">
                    <input type="password" id="senha" name="senha" required>
                    <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Mostrar/ocultar senha">
                        <i class="fas fa-eye" id="eye-icon"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn">Entrar</button>
        </form>
        
        <div class="footer">
            <p>© 2026 Prefeitura Municipal de Bayeux</p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById('senha');
            const eyeIcon = document.getElementById('eye-icon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
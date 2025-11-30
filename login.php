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

        /* 🎄 NEVE DISCRETA 🎄 */
        .snowflake {
            position: fixed;
            top: -5px;
            color: rgba(227, 242, 253, 0.6);
            user-select: none;
            z-index: 999;
            pointer-events: none;
            animation: snowfall 12s linear infinite;
            font-size: 0.4rem;
        }

        @keyframes snowfall {
            to { transform: translateY(100vh); }
        }
        
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            border: 2px solid rgba(255, 215, 0, 0.2);
            position: relative;
        }
        
        /* 🎄 ÁRVORE NATALINA MAIS VISÍVEL E GRANDE 🎄 */
        .header {
            text-align: center;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .header::before {
            content: '🎄';
            position: absolute;
            left: 50%;
            top: -30px;
            transform: translateX(-50%);
            font-size: 3rem;
            z-index: 10;
            animation: treeGlow 3s ease-in-out infinite alternate;
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.6);
        }

        @keyframes treeGlow {
            0% { 
                transform: translateX(-50%) translateY(0px) scale(1);
                filter: drop-shadow(0 0 5px rgba(34, 197, 94, 0.8));
            }
            100% { 
                transform: translateX(-50%) translateY(-3px) scale(1.05);
                filter: drop-shadow(0 0 15px rgba(255, 215, 0, 1));
            }
        }
        
        .header h1 {
            color: #333;
            margin: 2rem 0 0.5rem 0;
            font-size: 1.5rem;
        }
        
        .header p {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        /* 🎄 BANNER NATALINO CHAMATIVO 🎄 */
        .natal-banner {
            text-align: center;
            margin: 1.5rem 0;
            padding: 1rem;
            background: linear-gradient(135deg, #2c5530 0%, #b92b27 50%, #c0392b 100%);
            color: white;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(44, 85, 48, 0.3);
            animation: bannerPulse 2s ease-in-out infinite;
        }

        @keyframes bannerPulse {
            0%, 100% { 
                transform: scale(1);
                box-shadow: 0 8px 25px rgba(44, 85, 48, 0.3);
            }
            50% { 
                transform: scale(1.02);
                box-shadow: 0 12px 35px rgba(44, 85, 48, 0.5);
            }
        }

        .natal-banner::before {
            content: '✨';
            position: absolute;
            top: 8px;
            left: 15px;
            font-size: 1.2rem;
            animation: sparkle 2s ease-in-out infinite;
        }

        .natal-banner::after {
            content: '✨';
            position: absolute;
            top: 8px;
            right: 15px;
            font-size: 1.2rem;
            animation: sparkle 2s ease-in-out infinite 1s;
        }

        @keyframes sparkle {
            0%, 100% { opacity: 0.6; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.3); }
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

        .form-group input[type="text"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
        }

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-wrapper input {
            width: 100%;
            padding: 0.75rem 2.5rem 0.75rem 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
            flex: 1;
        }

        .password-wrapper input:focus {
            outline: none;
            border-color: #667eea;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 1.1rem;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }

        .password-toggle:hover {
            color: #333;
        }
        
        .btn {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .btn::before {
            content: '🎁';
            margin-right: 8px;
            font-size: 1rem;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
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
        
        @media (max-width: 480px) {
            .header::before {
                font-size: 2.5rem;
                top: -25px;
            }
            .natal-banner {
                font-size: 0.95rem;
                padding: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="header">
            <img src="images/logo.png" alt="Prefeitura de Bayeux" style="max-height: 120px; margin-bottom: 0.5rem;">
            <h1>Sistema de Chamados</h1>
            <p>Prefeitura Municipal de Bayeux<br>Secretaria Municipal de Educação</p>
        </div>

        <!-- 🎄 BANNER NATALINO CHAMATIVO 🎄 -->
        <div class="natal-banner">
            🎄 FELIZ NATAL E BOAS FESTAS! 🎅
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
            <p>© 2025 Prefeitura Municipal de Bayeux</p>
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

        // 🎄 NEVE DISCRETA 🎄
        setTimeout(() => {
            setInterval(() => {
                const snowflake = document.createElement('div');
                snowflake.classList.add('snowflake');
                snowflake.innerHTML = '❄️';
                snowflake.style.left = Math.random() * 100 + 'vw';
                snowflake.style.opacity = 0.5;
                snowflake.style.animationDuration = '12s';
                document.body.appendChild(snowflake);
                
                setTimeout(() => snowflake.remove(), 12000);
            }, 6000);
        }, 1000);
    </script>
</body>
</html>
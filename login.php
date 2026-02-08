<?php
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
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
    <title>Sistema de Chamados - Login de Carnaval</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #9b59b6 0%, #e91e63 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            top: -10px;
            z-index: 0;
            animation: fall linear infinite;
        }

        @keyframes fall {
            to { transform: translateY(100vh) rotate(360deg); }
        }
        
        .login-container {
            background: white;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 420px;
            border: 2px solid #f1c40f;
            position: relative;
            z-index: 10;
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
            border-color: #e91e63;
            box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.15);
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
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
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
            box-shadow: 0 10px 25px rgba(46, 204, 113, 0.35);
        }

        .btn-secondary {
            background: #718096;
            margin-top: 10px;
        }

        .btn-secondary:hover {
            background: #4a5568;
            box-shadow: 0 10px 25px rgba(113, 128, 150, 0.35);
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

        .forgot-password {
            text-align: right;
            margin-top: -10px;
            margin-bottom: 20px;
        }

        .forgot-password a {
            color: #e91e63;
            font-size: 0.85rem;
            text-decoration: none;
            font-weight: 500;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }
        
        .footer {
            text-align: center;
            margin-top: 2rem;
            color: #718096;
            font-size: 0.85rem;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .modal-header {
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .modal-header h2 {
            font-size: 1.3rem;
            color: #2d3748;
        }

        .unidade-info {
            background: #f7fafc;
            padding: 1rem;
            border-radius: 6px;
            margin-top: 1rem;
            border-left: 4px solid #f1c40f;
            display: none;
        }

        .unidade-info p {
            font-size: 0.9rem;
            color: #4a5568;
        }

        .unidade-info strong {
            display: block;
            color: #2d3748;
            margin-top: 4px;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.5rem;
            color: #a0aec0;
            cursor: pointer;
            line-height: 1;
        }

        .close-modal:hover {
            color: #4a5568;
        }

        <?php
        for ($i = 0; $i < 30; $i++) {
            $left = rand(0, 100);
            $duration = rand(3, 7);
            $delay = rand(0, 5);
            $colors = ['#f1c40f', '#2ecc71', '#e91e63', '#3498db', '#fff'];
            $color = $colors[array_rand($colors)];
            echo ".confetti:nth-child($i) { 
                left: $left%; 
                animation-duration: {$duration}s; 
                animation-delay: -{$delay}s; 
                background-color: $color;
            }\n";
        }
        ?>
        
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
    <?php for ($i = 0; $i < 30; $i++): ?>
        <div class="confetti"></div>
    <?php endfor; ?>

    <div class="login-container">
        <div class="header">
            <img src="images/logo.png" alt="Prefeitura de Bayeux">
            <h1>Sistema de Chamados</h1>
            <p>Prefeitura Municipal de Bayeux<br>Secretaria Municipal de Educação</p>
            <div style="font-size: 2rem; margin-top: 10px;">🎭✨</div>
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

            <div class="forgot-password">
                <a href="javascript:void(0)" onclick="openResetModal()">Esqueceu a senha?</a>
            </div>
            
            <button type="submit" class="btn">Entrar</button>
        </form>
        
        <div class="footer">
            <p>© 2026 Prefeitura Municipal de Bayeux</p>
        </div>
    </div>

    <!-- Modal Reset Senha -->
    <div id="resetModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" onclick="closeResetModal()">&times;</span>
            <div class="modal-header">
                <h2>Recuperar Senha</h2>
                <p style="font-size: 0.85rem; color: #718096;">Insira seu usuário para resetar a senha</p>
            </div>
            
            <div class="form-group">
                <label for="reset_usuario">Nome de Usuário:</label>
                <input type="text" id="reset_usuario" placeholder="Digite seu usuário">
            </div>

            <div id="unidadeDisplay" class="unidade-info">
                <p>Unidade Escolar / Setor:</p>
                <strong id="unidadeNome"></strong>
            </div>

            <div id="resetMessage" style="margin-top: 15px; font-size: 0.9rem; display: none;"></div>

            <button type="button" id="btnBuscar" class="btn" onclick="buscarUnidade()" style="margin-top: 10px;">Verificar Usuário</button>
            <button type="button" id="btnResetar" class="btn" onclick="resetarSenha()" style="margin-top: 10px; display: none; background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);">Resetar para Senha Padrão</button>
            <button type="button" class="btn btn-secondary" onclick="closeResetModal()" style="margin-top: 10px;">Cancelar</button>
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

        function openResetModal() {
            document.getElementById('resetModal').style.display = 'flex';
            document.getElementById('reset_usuario').value = '';
            document.getElementById('unidadeDisplay').style.display = 'none';
            document.getElementById('btnResetar').style.display = 'none';
            document.getElementById('btnBuscar').style.display = 'block';
            document.getElementById('resetMessage').style.display = 'none';
        }

        function closeResetModal() {
            document.getElementById('resetModal').style.display = 'none';
        }

        function buscarUnidade() {
            const usuario = document.getElementById('reset_usuario').value.trim();
            const msgDiv = document.getElementById('resetMessage');
            const btnBuscar = document.getElementById('btnBuscar');
            
            if (!usuario) {
                alert('Por favor, digite o nome de usuário.');
                return;
            }

            btnBuscar.disabled = true;
            btnBuscar.textContent = 'Buscando...';

            const formData = new FormData();
            formData.append('action', 'buscar_unidade');
            formData.append('nome_usuario', usuario);

            fetch('reset_senha_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na rede: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                btnBuscar.disabled = false;
                btnBuscar.textContent = 'Verificar Usuário';
                
                if (data.success) {
                    document.getElementById('unidadeNome').textContent = data.unidade;
                    document.getElementById('unidadeDisplay').style.display = 'block';
                    document.getElementById('btnBuscar').style.display = 'none';
                    document.getElementById('btnResetar').style.display = 'block';
                    msgDiv.style.display = 'none';
                } else {
                    msgDiv.textContent = data.message;
                    msgDiv.style.color = '#c53030';
                    msgDiv.style.display = 'block';
                }
            })
            .catch(error => {
                btnBuscar.disabled = false;
                btnBuscar.textContent = 'Verificar Usuário';
                console.error('Erro:', error);
                alert('Erro ao processar a solicitação. Verifique se o arquivo reset_senha_handler.php está no mesmo diretório.');
            });
        }

        function resetarSenha() {
            const usuario = document.getElementById('reset_usuario').value.trim();
            const msgDiv = document.getElementById('resetMessage');
            const btnResetar = document.getElementById('btnResetar');

            if (!confirm('Deseja realmente resetar a senha para o padrão "admin123"?')) {
                return;
            }

            btnResetar.disabled = true;
            btnResetar.textContent = 'Processando...';

            const formData = new FormData();
            formData.append('action', 'resetar_senha');
            formData.append('nome_usuario', usuario);

            fetch('reset_senha_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na rede: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                btnResetar.disabled = false;
                btnResetar.textContent = 'Resetar para Senha Padrão';
                
                msgDiv.textContent = data.message;
                msgDiv.style.display = 'block';
                
                if (data.success) {
                    msgDiv.style.color = '#27ae60';
                    document.getElementById('btnResetar').style.display = 'none';
                    setTimeout(() => {
                        closeResetModal();
                    }, 3000);
                } else {
                    msgDiv.style.color = '#c53030';
                }
            })
            .catch(error => {
                btnResetar.disabled = false;
                btnResetar.textContent = 'Resetar para Senha Padrão';
                console.error('Erro:', error);
                alert('Erro ao resetar a senha.');
            });
        }

        window.onclick = function(event) {
            const modal = document.getElementById('resetModal');
            if (event.target == modal) {
                closeResetModal();
            }
        }
    </script>
</body>
</html>

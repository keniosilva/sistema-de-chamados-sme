<?php
// Ativar exibição de erros para debug (pode ser removido em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$connection_file = __DIR__ . '/connection.php';
if (!file_exists($connection_file)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erro: connection.php não encontrado no servidor.']);
    exit;
}

require_once $connection_file;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $nome_usuario = trim($_POST['nome_usuario'] ?? '');

    if (empty($nome_usuario)) {
        echo json_encode(['success' => false, 'message' => 'Nome de usuário é obrigatório.']);
        exit;
    }

    if ($action === 'buscar_unidade') {
        // Ajustado para usar 'nome_unidade' conforme o SQL fornecido
        $sql = "SELECT u.id, ue.nome_unidade as unidade 
                FROM usuarios u 
                LEFT JOIN unidades_escolares ue ON u.id_unidade_escolar = ue.id 
                WHERE u.nome_usuario = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $nome_usuario);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $unidade = $row['unidade'] ?? 'Setor Administrativo / Sem Unidade Vinculada';
                echo json_encode(['success' => true, 'unidade' => $unidade]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Usuário não encontrado no sistema.']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro na preparação da consulta: ' . $conn->error]);
        }
    } elseif ($action === 'resetar_senha') {
        // Gerar hash para a senha padrão 'admin123'
        $nova_senha_hash = password_hash('admin123', PASSWORD_DEFAULT);
        
        // Atualiza a senha e marca como primeiro_login para forçar a troca no próximo acesso
        $sql = "UPDATE usuarios SET senha = ?, primeiro_login = 1 WHERE nome_usuario = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $nova_senha_hash, $nome_usuario);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode(['success' => true, 'message' => 'Senha resetada com sucesso para admin123!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Nenhuma alteração realizada. Verifique se o usuário está correto.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao executar o reset: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro na preparação do reset: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método de requisição inválido.']);
}

if (isset($conn)) {
    $conn->close();
}
?>

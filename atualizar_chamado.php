<?php
session_start();
require_once 'connection.php';
verificarPermissao(['tecnico', 'admin']);

$chamado_id = $_GET['id'] ?? 0;
$acao = $_GET['acao'] ?? '';

if (!$chamado_id || !in_array($acao, ['iniciar', 'concluir', 'cancelar'])) {
    header("Location: " . ($_SESSION['tipo_usuario'] == 'admin' ? 'admin_dashboard.php' : 'tecnico_dashboard.php'));
    exit();
}

// Verificar se o chamado existe e se o técnico tem permissão
$stmt = $conn->prepare("SELECT * FROM chamados WHERE id = ? AND (id_tecnico_responsavel = ? OR ? = 'admin')");
$stmt->bind_param("iis", $chamado_id, $_SESSION['usuario_id'], $_SESSION['tipo_usuario']);
$stmt->execute();
$chamado = $stmt->get_result()->fetch_assoc();

if (!$chamado) {
    header("Location: " . ($_SESSION['tipo_usuario'] == 'admin' ? 'admin_dashboard.php' : 'tecnico_dashboard.php'));
    exit();
}

$novo_status = '';
$data_fechamento = null;

switch ($acao) {
    case 'iniciar':
        if ($chamado['status'] == 'aberto') {
            $novo_status = 'em_andamento';
        }
        break;
    case 'concluir':
        if ($chamado['status'] == 'em_andamento') {
            $novo_status = 'concluido';
            $data_fechamento = date('Y-m-d H:i:s');
        }
        break;
    case 'cancelar':
        if (in_array($chamado['status'], ['aberto', 'em_andamento'])) {
            $novo_status = 'cancelado';
            $data_fechamento = date('Y-m-d H:i:s');
        }
        break;
}

if ($novo_status) {
    if ($data_fechamento) {
        $stmt = $conn->prepare("UPDATE chamados SET status = ?, data_fechamento = ? WHERE id = ?");
        $stmt->bind_param("ssi", $novo_status, $data_fechamento, $chamado_id);
    } else {
        $stmt = $conn->prepare("UPDATE chamados SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $novo_status, $chamado_id);
    }
    
    $stmt->execute();
}

header("Location: ver_chamado.php?id=" . $chamado_id);
exit();
?>


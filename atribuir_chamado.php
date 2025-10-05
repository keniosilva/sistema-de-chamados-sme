<?php
session_start();
require_once 'connection.php';
verificarPermissao(['tecnico', 'admin']);

$chamado_id = $_GET['id'] ?? 0;

if (!$chamado_id) {
    header("Location: " . ($_SESSION['tipo_usuario'] == 'admin' ? 'admin_dashboard.php' : 'tecnico_dashboard.php'));
    exit();
}

// Verificar se o chamado existe e está disponível
$stmt = $conn->prepare("SELECT * FROM chamados WHERE id = ? AND (id_tecnico_responsavel IS NULL OR id_tecnico_responsavel = ?)");
$stmt->bind_param("ii", $chamado_id, $_SESSION['usuario_id']);
$stmt->execute();
$chamado = $stmt->get_result()->fetch_assoc();

if (!$chamado) {
    header("Location: " . ($_SESSION['tipo_usuario'] == 'admin' ? 'admin_dashboard.php' : 'tecnico_dashboard.php'));
    exit();
}

// Atribuir chamado ao técnico atual
$stmt = $conn->prepare("UPDATE chamados SET id_tecnico_responsavel = ? WHERE id = ?");
$stmt->bind_param("ii", $_SESSION['usuario_id'], $chamado_id);

if ($stmt->execute()) {
    header("Location: ver_chamado.php?id=" . $chamado_id);
} else {
    header("Location: " . ($_SESSION['tipo_usuario'] == 'admin' ? 'admin_dashboard.php' : 'tecnico_dashboard.php') . "?erro=1");
}
exit();
?>


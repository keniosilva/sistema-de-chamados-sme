<?php
session_start();
require_once 'connection.php';
require_once 'auth.php';
require_once 'helpers.php';
verificarPermissao(['almoxarifado']);

$chamado_id = $_GET['id'] ?? 0;

if (!$chamado_id) {
    header("Location: almoxarifado_dashboard.php?erro=ID do chamado não fornecido.");
    exit();
}

// Verificar se o chamado existe e pertence ao almoxarifado
$stmt = $conn->prepare("SELECT * FROM chamados WHERE id = ? AND setor_destino = 'almoxarifado'");
$stmt->bind_param("i", $chamado_id);
$stmt->execute();
$chamado = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$chamado) {
    header("Location: almoxarifado_dashboard.php?erro=Chamado não encontrado ou não pertence ao almoxarifado.");
    exit();
}

// Verificar se já foi confirmado pelo almoxarifado
if ($chamado['almoxarifado_confirmacao_entrega']) {
    header("Location: almoxarifado_dashboard.php?erro=Entrega já confirmada pelo almoxarifado.");
    exit();
}

// Confirmar entrega do almoxarifado
$stmt = $conn->prepare("UPDATE chamados SET almoxarifado_confirmacao_entrega = TRUE, status = 'aguardando_recebimento' WHERE id = ?");
$stmt->bind_param("i", $chamado_id);
if ($stmt->execute()) {
    // Verificar se há um ofício de entrega associado
    $stmt_oficio = $conn->prepare("SELECT id FROM oficios WHERE id_chamado = ? AND tipo_oficio = 'entrega'");
    $stmt_oficio->bind_param("i", $chamado_id);
    $stmt_oficio->execute();
    $oficio = $stmt_oficio->get_result()->fetch_assoc();
    $stmt_oficio->close();

    if (!$oficio) {
        // Gerar ofício de entrega se não existir (assumindo que gerar_pdf_oficio.php lida com isso)
        header("Location: gerar_pdf_oficio.php?id=$chamado_id&tipo=entrega");
        exit();
    }

    header("Location: almoxarifado_dashboard.php?sucesso=Entrega confirmada pelo almoxarifado. Aguardando confirmação da unidade.");
} else {
    header("Location: almoxarifado_dashboard.php?erro=Erro ao confirmar entrega: " . $conn->error);
}
$stmt->close();
exit();
?>
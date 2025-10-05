<?php
session_start();
require_once 'connection.php';
require_once 'auth.php';
restrictAccess(['almoxarifado', 'casa_da_merenda']);

$chamado_id = $_GET['id'] ?? 0;

if (!$chamado_id) {
    header("Location: almoxarifado_dashboard.php?erro=ID do chamado inválido");
    exit();
}

// Verificar se o chamado existe e é do tipo correto
$stmt = $conn->prepare(
    "SELECT c.*, ue.nome_unidade 
     FROM chamados c 
     LEFT JOIN unidades_escolares ue ON c.id_unidade_escolar = ue.id 
     WHERE c.id = ? AND c.tipo_manutencao = ?"
);
$tipo_manutencao = $_SESSION['user']['tipo_usuario'];
$stmt->bind_param("is", $chamado_id, $tipo_manutencao);
$stmt->execute();
$chamado = $stmt->get_result()->fetch_assoc();

if (!$chamado) {
    header("Location: almoxarifado_dashboard.php?erro=Chamado não encontrado ou não permitido");
    exit();
}

// Verificar se já existe um Ofício de Entrega
$stmt = $conn->prepare("SELECT id FROM oficios WHERE id_chamado = ? AND tipo_oficio = 'entrega'");
$stmt->bind_param("i", $chamado_id);
$stmt->execute();
$existing_oficio = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing_oficio) {
    header("Location: almoxarifado_dashboard.php?erro=Ofício de Entrega já gerado");
    exit();
}

// Gerar Ofício de Entrega
$numero_oficio = "ENTREGA-" . str_pad($chamado_id, 6, '0', STR_PAD_LEFT) . "/" . date('Y');
$data_oficio = date('Y-m-d');
$conteudo_oficio = "Ofício de Entrega\n\n" .
                   "Chamado: #$chamado_id\n" .
                   "Unidade Escolar: " . htmlspecialchars($chamado['nome_unidade']) . "\n" .
                   "Tipo de Manutenção: " . getNomeTipoManutencao($chamado['tipo_manutencao']) . "\n" .
                   "Descrição: " . htmlspecialchars($chamado['descricao']) . "\n\n" .
                   "Este ofício confirma a entrega dos itens solicitados no chamado acima descrito.\n" .
                   "Data: " . date('d/m/Y') . "\n" .
                   "Responsável: " . htmlspecialchars($_SESSION['user']['nome']) . "\n\n" .
                   "Assinatura: ______________________________\n";
$hash_validacao = hash('sha256', $numero_oficio . $data_oficio . $conteudo_oficio);

$stmt = $conn->prepare(
    "INSERT INTO oficios (id_chamado, numero_oficio, data_oficio, conteudo_oficio, hash_validacao, tipo_oficio) 
     VALUES (?, ?, ?, ?, ?, 'entrega')"
);
$stmt->bind_param("issss", $chamado_id, $numero_oficio, $data_oficio, $conteudo_oficio, $hash_validacao);
if ($stmt->execute()) {
    // Atualizar o status do chamado para 'aguardando_recebimento' e marcar almoxarifado_confirmacao_entrega como 1
    $update_stmt = $conn->prepare("UPDATE chamados SET status = 'aguardando_recebimento', almoxarifado_confirmacao_entrega = 1 WHERE id = ?");
    $update_stmt->bind_param("i", $chamado_id);
    $update_stmt->execute();
    $update_stmt->close();

    header("Location: almoxarifado_dashboard.php?sucesso=Ofício de Entrega gerado e chamado atualizado com sucesso");
} else {
    header("Location: almoxarifado_dashboard.php?erro=Erro ao gerar Ofício de Entrega: " . $conn->error);
}
$stmt->close();
exit();
?>
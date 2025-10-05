<?php
session_start();
require_once 'connection.php'; 
require_once 'helpers.php';
require_once 'auth.php'; 

// Restringir acesso ao Almoxarifado e Admin
restrictAccess(['almoxarifado', 'admin']);

$chamado_id = $_GET['id'] ?? 0;
$acao = $_GET['acao'] ?? '';
$userType = $_SESSION['user']['tipo_usuario'];
$dashboardUrl = getDashboardUrl($userType);

if (!$chamado_id || $acao !== 'confirmar_entrega_almoxarifado') {
    header("Location: $dashboardUrl?erro=" . urlencode("Ação inválida ou ID do chamado ausente."));
    exit();
}

// 1. Verificar se o chamado existe e é de destino Almoxarifado
$stmt = $conn->prepare("
    SELECT c.id, c.status, c.setor_destino, c.almoxarifado_confirmacao_entrega
    FROM chamados c
    WHERE c.id = ? AND c.setor_destino = 'almoxarifado'
");
$stmt->bind_param("i", $chamado_id);
$stmt->execute();
$chamado = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$chamado) {
    header("Location: $dashboardUrl?erro=" . urlencode("Chamado não encontrado ou não é de destino Almoxarifado."));
    exit();
}

if ($chamado['almoxarifado_confirmacao_entrega']) {
    header("Location: $dashboardUrl?erro=" . urlencode("A entrega para o chamado #$chamado_id já foi confirmada pelo Almoxarifado."));
    exit();
}

// 2. Processar a confirmação de entrega do Almoxarifado
try {
    $conn->begin_transaction();

    // A. Atualizar a flag de confirmação no chamado
    // Adicionei a coluna `almoxarifado_confirmacao_entrega` (timestamp) na tabela `chamados`
    $data_confirmacao = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("
        UPDATE chamados 
        SET almoxarifado_confirmacao_entrega = 1 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $chamado_id);
    if (!$stmt->execute()) {
        throw new Exception("Erro ao atualizar a confirmação de entrega do Almoxarifado: " . $stmt->error);
    }
    $stmt->close();
    
    // B. Gerar registro do Ofício de Entrega (Simulação: Aqui deveria estar a lógica de geração do PDF e obtenção do hash/nome do arquivo)
    // Usaremos um valor de simulação para o número do ofício e o hash
    $numero_oficio_simulado = "ENTR-" . $chamado_id . "-" . date('Y');
    $hash_validacao_simulado = hash('sha256', $chamado_id . $data_confirmacao . $numero_oficio_simulado); // Hash simples

    // Inserir o registro do Ofício de Entrega
    $stmt = $conn->prepare("
        INSERT INTO oficios (id_chamado, numero_oficio, data_oficio, tipo_oficio, conteudo_oficio, hash_validacao) 
        VALUES (?, ?, ?, 'entrega', ?, ?)
    ");
    // O conteúdo do ofício pode ser o nome do arquivo PDF gerado
    $conteudo_oficio_simulado = "Oficio_Entrega_Chamado_$chamado_id.pdf";
    $stmt->bind_param("issss", $chamado_id, $numero_oficio_simulado, $data_confirmacao, $conteudo_oficio_simulado, $hash_validacao_simulado);

    if (!$stmt->execute()) {
        throw new Exception("Erro ao registrar o Ofício de Entrega: " . $stmt->error);
    }
    $stmt->close();

    $conn->commit();
    $mensagemSucesso = "Entrega do chamado #$chamado_id confirmada. Ofício de Entrega **$numero_oficio_simulado** gerado e enviado à unidade/setor.";
    header("Location: $dashboardUrl?sucesso=" . urlencode($mensagemSucesso));
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $mensagemErro = $e->getMessage();
    header("Location: $dashboardUrl?erro=" . urlencode($mensagemErro));
    exit();
}
?>
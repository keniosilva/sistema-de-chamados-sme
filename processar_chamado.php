<?php
session_start();
require_once 'connection.php';
require_once 'helpers.php'; // Inclui o arquivo que contém verificarPermissao() e getDashboardUrl()

// --- Configuração Inicial e Segurança ---

// Verifica se a sessão do usuário está ativa
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['tipo_usuario'])) {
    header("Location: login.php");
    exit();
}

$userType = $_SESSION['user']['tipo_usuario'];
$dashboardUrl = getDashboardUrl($userType); // Agora usa a função de helpers.php

// Proteção: Somente usuários com permissão para gerenciar podem processar chamados
$permissoesGerenciamento = ['admin', 'tecnico_informatica', 'tecnico_geral', 'tecnico', 'manutencao', 'almoxarifado', 'casa_da_merenda'];
verificarPermissao($permissoesGerenciamento);

// Redireciona se não for um método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $dashboardUrl?erro=" . urlencode("Acesso inválido."));
    exit();
}

// --- Coleta e Validação de Dados ---

$acao = $_POST['acao'] ?? '';
$chamadoId = (int)($_POST['id'] ?? 0);
$novoStatus = $_POST['status'] ?? '';
// O ID do técnico virá como 0 se 'Nenhum' for selecionado (pois value="" é tratado como 0 em (int)), convertemos para NULL
$idTecnicoResponsavel = (int)($_POST['id_tecnico_responsavel'] ?? 0); 
$idTecnicoResponsavel = $idTecnicoResponsavel > 0 ? $idTecnicoResponsavel : NULL; 
$observacoesTecnico = trim($_POST['observacoes_tecnico'] ?? '');

// Validação crítica
if ($acao !== 'atualizar' || $chamadoId === 0 || empty($novoStatus)) {
    header("Location: $dashboardUrl?erro=" . urlencode("Dados incompletos ou ação inválida."));
    exit();
}

$statusPermitidos = ['aberto', 'em_andamento', 'concluido', 'cancelado', 'aguardando_recebimento'];
if (!in_array($novoStatus, $statusPermitidos)) {
    header("Location: $dashboardUrl?erro=" . urlencode("Status inválido."));
    exit();
}

// --- Lógica de Atualização (Data de Fechamento) ---

$dataFechamento = NULL;

if ($novoStatus === 'concluido' || $novoStatus === 'cancelado') {
    // Se o status é de finalização, define a data de fechamento
    $dataFechamento = date('Y-m-d H:i:s');
    
    $sql = "UPDATE chamados SET status = ?, id_tecnico_responsavel = ?, observacoes_tecnico = ?, data_fechamento = ? WHERE id = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        // Tipos: s(status), i(tecnicoID), s(obs), s(data), i(chamadoID)
        // Note: id_tecnico_responsavel (i) deve ser NULL ou um inteiro positivo.
        // Se for NULL (do ternário acima), o bind_param com 'i' deve funcionar no MySQL.
        $stmt->bind_param("sissi", $novoStatus, $idTecnicoResponsavel, $observacoesTecnico, $dataFechamento, $chamadoId);
    }
} else {
    // Se o status é 'aberto' ou 'em_andamento', a data de fechamento deve ser NULL
    // Para garantir que a data_fechamento seja NULL no banco, ajustamos o SQL para evitar o problema de tipo com bind_param
    $sql = "UPDATE chamados SET status = ?, id_tecnico_responsavel = ?, observacoes_tecnico = ?, data_fechamento = NULL WHERE id = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        // Tipos: s(status), i(tecnicoID), s(obs), i(chamadoID)
        $stmt->bind_param("sisi", $novoStatus, $idTecnicoResponsavel, $observacoesTecnico, $chamadoId);
    }
}


// --- Execução da Query e Redirecionamento ---

if (isset($stmt)) {
    if ($stmt->execute()) {
        $mensagemSucesso = "Chamado #$chamadoId atualizado para: " . ucfirst(str_replace('_', ' ', $novoStatus)) . ".";
        
        // Redireciona de volta para a tela de gerenciar_chamados com sucesso
        header("Location: gerenciar_chamados.php?id=$chamadoId&sucesso=" . urlencode($mensagemSucesso));
        exit();
    } else {
        $mensagemErro = "Erro ao atualizar chamado: " . $stmt->error;
        // Redireciona de volta para a tela de gerenciar_chamados com erro
        header("Location: gerenciar_chamados.php?id=$chamadoId&erro=" . urlencode($mensagemErro));
        exit();
    }
    $stmt->close();
} else {
    $mensagemErro = "Erro na preparação da consulta SQL.";
    header("Location: $dashboardUrl?erro=" . urlencode($mensagemErro));
    exit();
}

$conn->close();
?>

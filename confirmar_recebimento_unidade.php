<?php
session_start();
require_once 'connection.php';
require_once 'helpers.php';

// Verificar permissão para 'unidade_escolar' ou 'secretaria'
verificarPermissao(['unidade_escolar', 'secretaria']);

if (!isset($_GET['id']) || !isset($_GET['tipo'])) {
    header('Location: ' . ($_SESSION['user']['tipo_usuario'] === 'secretaria' ? 'secretaria_dashboard.php' : 'unidade_dashboard.php') . '?erro=Parâmetros inválidos para confirmação.');
    exit;
}

$chamado_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
$tipo_confirmacao = $_GET['tipo']; // 'almoxarifado' ou 'merenda'

if (!$chamado_id || !in_array($tipo_confirmacao, ['almoxarifado', 'merenda'])) {
    header('Location: ' . ($_SESSION['user']['tipo_usuario'] === 'secretaria' ? 'secretaria_dashboard.php' : 'unidade_dashboard.php') . '?erro=ID do chamado ou tipo de confirmação inválido.');
    exit;
}

// Verificar se o chamado pertence à unidade logada (para unidade_escolar) ou foi aberto pela secretaria (para secretaria)
$where_clause = '';
$param_types = 'i';
$bind_values = [$chamado_id];

if ($_SESSION['user']['tipo_usuario'] === 'unidade_escolar') {
    $where_clause = 'AND id_unidade_escolar = ?';
    $param_types .= 'i';
    $bind_values[] = $_SESSION['user']['id_unidade_escolar'];
} else {
    $where_clause = 'AND id_usuario_abertura = ?';
    $param_types .= 'i';
    $bind_values[] = $_SESSION['user']['id'];
}

$stmt = $conn->prepare(
    "SELECT id, status, almoxarifado_confirmacao_entrega, merenda_confirmacao_entrega, confirmacao_recebimento_unidade, setor_destino
     FROM chamados
     WHERE id = ? $where_clause"
);
$stmt->bind_param($param_types, ...$bind_values);
$stmt->execute();
$result = $stmt->get_result();
$chamado = $result->fetch_assoc();
$stmt->close();

if (!$chamado) {
    header('Location: ' . ($_SESSION['user']['tipo_usuario'] === 'secretaria' ? 'secretaria_dashboard.php' : 'unidade_dashboard.php') . '?erro=Chamado não encontrado ou não pertence à sua unidade/secretaria.');
    exit;
}

if ($chamado['status'] !== 'aguardando_recebimento') {
    header('Location: ' . ($_SESSION['user']['tipo_usuario'] === 'secretaria' ? 'secretaria_dashboard.php' : 'unidade_dashboard.php') . '?erro=Chamado não está aguardando recebimento.');
    exit;
}

$update_column = '';
$setor_destino_esperado = '';

if ($tipo_confirmacao === 'almoxarifado') {
    $update_column = 'almoxarifado_confirmacao_entrega';
    $setor_destino_esperado = 'almoxarifado';
    if ($chamado['almoxarifado_confirmacao_entrega'] == 0) {
        header('Location: ' . ($_SESSION['user']['tipo_usuario'] === 'secretaria' ? 'secretaria_dashboard.php' : 'unidade_dashboard.php') . '?erro=Almoxarifado ainda não confirmou a entrega.');
        exit;
    }
} elseif ($tipo_confirmacao === 'merenda') {
    $update_column = 'merenda_confirmacao_entrega';
    $setor_destino_esperado = 'casa_da_merenda';
    if ($chamado['merenda_confirmacao_entrega'] == 0) {
        header('Location: ' . ($_SESSION['user']['tipo_usuario'] === 'secretaria' ? 'secretaria_dashboard.php' : 'unidade_dashboard.php') . '?erro=Casa da Merenda ainda não confirmou a entrega.');
        exit;
    }
}

if ($chamado['setor_destino'] !== $setor_destino_esperado) {
    header('Location: ' . ($_SESSION['user']['tipo_usuario'] === 'secretaria' ? 'secretaria_dashboard.php' : 'unidade_dashboard.php') . '?erro=Tipo de confirmação incompatível com o setor de destino do chamado.');
    exit;
}

// Atualizar o status do chamado para 'concluido' e confirmar recebimento pela unidade/secretaria
$stmt = $conn->prepare(
    "UPDATE chamados SET confirmacao_recebimento_unidade = 1, status = 'concluido', data_fechamento = NOW() WHERE id = ?"
);
$stmt->bind_param("i", $chamado_id);

if ($stmt->execute()) {
    header('Location: ' . ($_SESSION['user']['tipo_usuario'] === 'secretaria' ? 'secretaria_dashboard.php' : 'unidade_dashboard.php') . '?sucesso=Recebimento do chamado ' . $chamado_id . ' confirmado com sucesso!');
} else {
    header('Location: ' . ($_SESSION['user']['tipo_usuario'] === 'secretaria' ? 'secretaria_dashboard.php' : 'unidade_dashboard.php') . '?erro=Erro ao confirmar recebimento: ' . $conn->error);
}
$stmt->close();
exit;

?>
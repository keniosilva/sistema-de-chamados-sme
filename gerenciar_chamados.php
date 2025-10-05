<?php
session_start();
require_once 'connection.php';
require_once 'helpers.php';
require_once 'auth.php';

// Proteção: só admins e setores envolvidos podem acessar
verificarPermissao(['admin', 'tecnico', 'casa_da_merenda', 'almoxarifado', 'manutencao', 'tecnico_geral', 'tecnico_informatica']);

// Inicializar variáveis
$mensagemErro = '';
$mensagemSucesso = '';
$chamado = null;
$tecnicos = [];
$statusPermitidos = ['aberto', 'em_andamento', 'concluido', 'cancelado', 'aguardando_recebimento'];

// Processamento POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $chamadoId = filter_input(INPUT_POST, 'chamado_id', FILTER_VALIDATE_INT);
    $novoStatus = filter_input(INPUT_POST, 'status', FILTER_DEFAULT);
    $idTecnico = filter_input(INPUT_POST, 'id_tecnico_responsavel', FILTER_VALIDATE_INT);
    $observacoes = filter_input(INPUT_POST, 'observacoes_tecnico', FILTER_DEFAULT);

    if (!$chamadoId || !in_array($novoStatus, $statusPermitidos)) {
        $mensagemErro = 'Dados inválidos para atualização.';
    } else {
        $setor_user = $_SESSION['user']['tipo_usuario'];
        $setClauses = ["status = ?", "observacoes_tecnico = ?"];
        $params = "ss";
        $values = [$novoStatus, $observacoes];

        if ($novoStatus === 'aguardando_recebimento') {
            if ($setor_user === 'almoxarifado') {
                $setClauses[] = "almoxarifado_confirmacao_entrega = TRUE";
            } elseif ($setor_user === 'casa_da_merenda') {
                $setClauses[] = "merenda_confirmacao_entrega = TRUE";
            }
        }

        if ($idTecnico && $setor_user === 'admin') {
            $setClauses[] = "id_tecnico_responsavel = ?";
            $params .= "i";
            $values[] = $idTecnico;
        }

        $sql = "UPDATE chamados SET " . implode(', ', $setClauses) . " WHERE id = ?";
        $params .= "i";
        $values[] = $chamadoId;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($params, ...$values);

        if ($stmt->execute()) {
            $mensagemSucesso = 'Chamado atualizado com sucesso!';
            header("Location: gerenciar_chamados.php?id=$chamadoId&sucesso=" . urlencode($mensagemSucesso));
            exit();
        } else {
            error_log("Erro ao atualizar o chamado: " . $conn->error . " | Query: " . $sql);
            $mensagemErro = 'Erro ao atualizar o chamado. Detalhes: ' . $conn->error;
        }
        $stmt->close();
    }
}

// Lógica de Busca e Exibição
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $userType = $_SESSION["user"]["tipo_usuario"] ?? "unidade_escolar";
    $dashboardUrl = getDashboardUrl($userType);
    header("Location: $dashboardUrl?erro=" . urlencode("ID do chamado inválido ou ausente."));
    exit();
}

$chamadoId = (int)$_GET["id"];

$stmt = $conn->prepare("
    SELECT c.*, u.nome as nome_usuario, ue.nome_unidade, t.nome as nome_tecnico,
           o.numero_oficio, o.tipo_oficio, o.data_oficio
    FROM chamados c
    LEFT JOIN usuarios u ON c.id_usuario_abertura = u.id
    LEFT JOIN unidades_escolares ue ON c.id_unidade_escolar = ue.id
    LEFT JOIN usuarios t ON c.id_tecnico_responsavel = t.id
    LEFT JOIN oficios o ON c.id = o.id_chamado AND (o.tipo_oficio = 'entrega' OR o.tipo_oficio = 'entrega_merenda')
    WHERE c.id = ?
");
$stmt->bind_param("i", $chamadoId);
$stmt->execute();
$result = $stmt->get_result();
$chamado = $result->fetch_assoc();
$stmt->close();

if (!$chamado) {
    $userType = $_SESSION["user"]["tipo_usuario"] ?? "unidade_escolar";
    $dashboardUrl = getDashboardUrl($userType);
    header("Location: $dashboardUrl?erro=" . urlencode("Chamado não encontrado."));
    exit();
}

$dashboardUrl = getDashboardUrl($_SESSION["user"]["tipo_usuario"]);

// Carregar lista de técnicos (apenas para admin)
$pode_atribuir = $_SESSION['user']['tipo_usuario'] === 'admin';
if ($pode_atribuir) {
    $stmt = $conn->prepare("SELECT id, nome FROM usuarios WHERE tipo_usuario = 'tecnico_informatica'");
    $stmt->execute();
    $tecnicos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Chamado #<?php echo htmlspecialchars($chamadoId); ?> - Sistema de Chamados</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.5rem;
        }
        
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        h1, h2 {
            margin-bottom: 1rem;
        }
        
        h1 {
            font-size: 1.8rem;
        }
        
        h2 {
            font-size: 1.4rem;
            color: #333;
            border-bottom: 2px solid #eee;
            padding-bottom: 0.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            border: 1px solid #eee;
        }
        
        .info-label {
            font-weight: bold;
            color: #666;
            display: block;
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }
        
        .info-value {
            color: #333;
            font-size: 1.1rem;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        
        .status-aberto { background: #fff3cd; color: #856404; }
        .status-em_andamento { background: #d1ecf1; color: #0c5460; }
        .status-concluido { background: #d4edda; color: #155724; }
        .status-cancelado { background: #f8d7da; color: #721c24; }
        .status-aguardando_recebimento { background: #e2e3e5; color: #383d41; }
        
        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            text-decoration: none;
            color: white;
            font-weight: bold;
            transition: opacity 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn:hover { opacity: 0.9; }
        
        .btn-primary { background: #007bff; }
        .btn-secondary { background: #6c757d; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; }
        .btn-info { background: #17a2b8; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Gerenciar Chamado #<?php echo htmlspecialchars($chamadoId); ?></h1>
            <a href="<?php echo $dashboardUrl; ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar ao Dashboard</a>
        </div>
    </div>

    <div class="container">
        <?php if ($mensagemSucesso): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($mensagemSucesso); ?>
            </div>
        <?php endif; ?>

        <?php if ($mensagemErro): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($mensagemErro); ?>
            </div>
        <?php endif; ?>

        <?php if ($chamado): ?>
            <div class="card">
                <h2>Detalhes do Chamado</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">ID:</span>
                        <span class="info-value"><?php echo htmlspecialchars($chamado['id']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Tipo:</span>
                        <span class="info-value"><?php echo ucfirst(htmlspecialchars($chamado['tipo_manutencao'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Status:</span>
                        <span class="info-value">
                            <span class="status-badge status-<?php echo htmlspecialchars($chamado['status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($chamado['status']))); ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Data de Abertura:</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($chamado['data_abertura'])); ?></span>
                    </div>
                    <?php if ($chamado['data_fechamento']): ?>
                        <div class="info-item">
                            <span class="info-label">Data de Fechamento:</span>
                            <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($chamado['data_fechamento'])); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <span class="info-label">Usuário Abertura:</span>
                        <span class="info-value"><?php echo htmlspecialchars($chamado['nome_usuario']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Técnico Responsável:</span>
                        <span class="info-value"><?php echo htmlspecialchars($chamado['nome_tecnico'] ?? 'Não atribuído'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Unidade Escolar:</span>
                        <span class="info-value"><?php echo htmlspecialchars($chamado['nome_unidade'] ?? 'N/A'); ?></span>
                    </div>
                </div>

                <h2>Descrição</h2>
                <p><?php echo nl2br(htmlspecialchars($chamado['descricao'])); ?></p>

                <h2>Observações</h2>
                <p><?php echo nl2br(htmlspecialchars($chamado['observacoes_tecnico'] ?? 'Nenhuma observação.')); ?></p>

                <?php if ($chamado['numero_oficio']): ?>
                    <h2>Ofício de Entrega</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Número do Ofício:</span>
                            <span class="info-value"><?php echo htmlspecialchars($chamado['numero_oficio']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Data do Ofício:</span>
                            <span class="info-value"><?php echo date('d/m/Y', strtotime($chamado['data_oficio'])); ?></span>
                        </div>
                    </div>
                    <div class="actions">
                        <a href="gerar_pdf_oficio.php?id=<?php echo $chamado['id']; ?>&tipo=<?php echo $chamado['tipo_oficio']; ?>" class="btn btn-info" target="_blank">
                            <i class="bi bi-file-earmark-pdf"></i> Baixar PDF do Ofício
                        </a>
                    </div>
                <?php endif; ?>

                <h2>Atualizar Chamado</h2>
                <form method="POST">
                    <input type="hidden" name="chamado_id" value="<?php echo $chamado['id']; ?>">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select" required>
                            <?php foreach ($statusPermitidos as $status): ?>
                                <?php
                                    $disabled = false;
                                    $userType = $_SESSION['user']['tipo_usuario'];
                                    if ($chamado['status'] == 'concluido' && !in_array($userType, ['admin', 'tecnico_geral'])) {
                                        $disabled = true;
                                    }
                                    if ($userType === 'unidade_escolar') {
                                        $disabled = true;
                                    }
                                    $selected = $chamado['status'] == $status ? 'selected' : '';
                                    $displayText = ucfirst(str_replace('_', ' ', $status));
                                ?>
                                <option value="<?php echo $status; ?>" <?php echo $selected; ?> <?php echo $disabled ? 'disabled' : ''; ?>>
                                    <?php echo $displayText; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($pode_atribuir): ?>
                        <div class="mb-3">
                            <label for="id_tecnico_responsavel" class="form-label">Técnico Responsável</label>
                            <select name="id_tecnico_responsavel" id="id_tecnico_responsavel" class="form-select">
                                <option value="">-- Selecione um Técnico --</option>
                                <?php foreach ($tecnicos as $tecnico): ?>
                                    <option value="<?php echo $tecnico['id']; ?>" <?php echo $chamado['id_tecnico_responsavel'] == $tecnico['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tecnico['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="id_tecnico_responsavel" value="<?php echo $chamado['id_tecnico_responsavel'] ?? ''; ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="observacoes_tecnico" class="form-label">Observações (Setor)</label>
                        <textarea name="observacoes_tecnico" id="observacoes_tecnico" class="form-control" rows="5"><?php echo htmlspecialchars($chamado['observacoes_tecnico'] ?? ''); ?></textarea>
                    </div>
                    <div class="actions">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Atualizar Chamado</button>
                        <a href="<?php echo $dashboardUrl; ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
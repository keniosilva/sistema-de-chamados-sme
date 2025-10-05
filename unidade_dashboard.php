<?php
session_start();
require_once 'connection.php';
require_once 'helpers.php';

// Verificar permissão
verificarPermissao(['unidade_escolar']);

// Validar variáveis de sessão
if (!isset($_SESSION['user']['id_unidade_escolar']) || !isset($_SESSION['user']['nome'])) {
    header('Location: login.php?erro=Sessão inválida');
    exit;
}

// --- CONFIGURAÇÃO DE PAGINAÇÃO ---
$limite = 10;
$pagina = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$offset = ($pagina - 1) * $limite;

// Buscar informações da unidade escolar
$unidade = ['nome_unidade' => 'Unidade Não Encontrada'];
$stmt = $conn->prepare("SELECT nome_unidade FROM unidades_escolares WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $_SESSION['user']['id_unidade_escolar']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $unidade = $row;
    }
    $stmt->close();
} else {
    error_log("Erro ao preparar consulta de unidade escolar: " . $conn->error);
}

// --- 1. Contar Total de Chamados para Paginação ---
$totalChamados = 0;
$stmtTotal = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM chamados 
    WHERE id_unidade_escolar = ?
");
if ($stmtTotal) {
    $stmtTotal->bind_param("i", $_SESSION['user']['id_unidade_escolar']);
    $stmtTotal->execute();
    $totalChamados = $stmtTotal->get_result()->fetch_assoc()['total'];
    $stmtTotal->close();
}
$totalPaginas = ceil($totalChamados / $limite);


// --- 2. Buscar Chamados DA PÁGINA ATUAL (COM JOIN NA TABELA OFÍCIOS) ---
$chamados = [];
$stmt = $conn->prepare("
    SELECT c.*, u.nome as nome_tecnico, c.almoxarifado_confirmacao_entrega, c.confirmacao_recebimento_unidade, c.merenda_confirmacao_entrega, c.setor_destino,
           o.numero_oficio, o.tipo_oficio
    FROM chamados c 
    LEFT JOIN usuarios u ON c.id_tecnico_responsavel = u.id 
    LEFT JOIN oficios o ON c.id = o.id_chamado AND (o.tipo_oficio = 'entrega' OR o.tipo_oficio = 'entrega_merenda')
    WHERE c.id_unidade_escolar = ? 
    ORDER BY c.data_abertura DESC
    LIMIT ? OFFSET ?
");
if ($stmt) {
    $stmt->bind_param("iii", $_SESSION['user']['id_unidade_escolar'], $limite, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $chamados[] = $row;
    }
    $stmt->close();
} else {
    error_log("Erro ao preparar consulta de chamados: " . $conn->error);
}

// Contar chamados aguardando confirmação de entrega
$pending_count = 0;
$pending_stmt = $conn->prepare("
    SELECT COUNT(*) as pending 
    FROM chamados c 
    WHERE c.id_unidade_escolar = ? 
    AND c.status = 'aguardando_recebimento' 
    AND (
        (c.setor_destino = 'almoxarifado' AND c.almoxarifado_confirmacao_entrega = 1)
        OR
        (c.setor_destino = 'casa_da_merenda' AND c.merenda_confirmacao_entrega = 1)
    )
    AND c.confirmacao_recebimento_unidade = 0
");
if ($pending_stmt) {
    $pending_stmt->bind_param("i", $_SESSION['user']['id_unidade_escolar']);
    $pending_stmt->execute();
    $result = $pending_stmt->get_result();
    $pending_count = $result->fetch_assoc()['pending'];
    $pending_stmt->close();
} else {
    error_log("Erro ao preparar consulta de chamados pendentes: " . $conn->error);
}

// Contar chamados concluídos
$total_concluidos = 0;
$stmt_concluidos = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM chamados c 
    WHERE c.id_unidade_escolar = ? 
    AND c.status = 'concluido'
");
if ($stmt_concluidos) {
    $stmt_concluidos->bind_param("i", $_SESSION['user']['id_unidade_escolar']);
    $stmt_concluidos->execute();
    $result = $stmt_concluidos->get_result();
    $total_concluidos = $result->fetch_assoc()['total'];
    $stmt_concluidos->close();
} else {
    error_log("Erro ao preparar consulta de chamados concluídos: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Unidade Escolar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .card-stats { margin-bottom: 20px; text-align: center; }
        .card-stats h5 { font-size: 1.25rem; color: #495057; }
        .card-stats p { font-size: 2.5rem; font-weight: bold; }
        .status-aberto { background-color: #0dcaf0; color: white; padding: 0.25em 0.5em; border-radius: 0.25em; }
        .status-em_andamento { background-color: #ffc107; color: black; padding: 0.25em 0.5em; border-radius: 0.25em; }
        .status-concluido { background-color: #198754; color: white; padding: 0.25em 0.5em; border-radius: 0.25em; }
        .status-cancelado { background-color: #dc3545; color: white; padding: 0.25em 0.5em; border-radius: 0.25em; }
        .status-aguardando_recebimento { background-color: #6f42c1; color: white; padding: 0.25em 0.5em; border-radius: 0.25em; }
        .badge-entrega-pendente { background-color: #ffc107; color: black; }
        .badge-entrega-confirmada { background-color: #198754; color: white; }
        .tipo-badge { padding: 0.25em 0.5em; background-color: #e9ecef; border-radius: 0.25em; }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <div id="page-content-wrapper" class="main-content bg-light flex-grow-1">
            <div class="container-fluid">
                <h1 class="mt-4">Dashboard - Unidade Escolar</h1>
                <p class="lead">Bem-vindo(a), <?php echo htmlspecialchars($_SESSION['user']['nome']); ?> (<?php echo htmlspecialchars($unidade['nome_unidade']); ?>)</p>
                <a href="logout.php" class="btn btn-danger float-end mb-3"><i class="bi bi-box-arrow-right"></i> Sair</a>
                <hr>

                <?php if (!empty($_GET['sucesso'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_GET['sucesso']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($_GET['erro'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_GET['erro']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white card-stats">
                            <div class="card-body">
                                <h5><i class="bi bi-journal-plus"></i> Abrir Novo Chamado</h5>
                                <p><a href="novo_chamado.php" class="btn btn-lg btn-light text-primary mt-2">NOVO</a></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-warning text-dark card-stats">
                            <div class="card-body">
                                <h5><i class="bi bi-clock-history"></i> Aguardando Confirmação</h5>
                                <p class="text-warning-emphasis"><?php echo $pending_count; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white card-stats">
                            <div class="card-body">
                                <h5><i class="bi bi-check-circle"></i> Chamados Concluídos</h5>
                                <p><?php echo $total_concluidos; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h2 class="mb-0"><i class="bi bi-list-task"></i> Meus Chamados Recentes (Página <?php echo $pagina; ?> de <?php echo $totalPaginas; ?>)</h2>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Tipo</th>
                                        <th>Destino</th>
                                        <th>Descrição (Início)</th>
                                        <th>Data Abertura</th>
                                        <th>Status</th>
                                        <th>Técnico</th>
                                        <th>Entrega</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($chamados)): ?>
                                        <?php foreach ($chamados as $chamado): ?>
                                            <tr>
                                                <td><?php echo $chamado['id']; ?></td>
                                                <td><span class="tipo-badge"><?php echo getNomeTipoManutencao($chamado['tipo_manutencao']); ?></span></td>
                                                <td><?php echo getNomeSetorDestino($chamado['setor_destino']); ?></td>
                                                <td><?php echo htmlspecialchars(substr($chamado['descricao'], 0, 50)) . (strlen($chamado['descricao']) > 50 ? '...' : ''); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($chamado['data_abertura'])); ?></td>
                                                <td><span class="status status-<?php echo $chamado['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $chamado['status'])); ?></span></td>
                                                <td><?php echo htmlspecialchars($chamado['nome_tecnico'] ?? 'Não atribuído'); ?></td>
                                                
                                                <td>
                                                    <?php
                                                    $is_almoxarifado = $chamado['setor_destino'] == 'almoxarifado';
                                                    $is_merenda = $chamado['setor_destino'] == 'casa_da_merenda';
                                                    
                                                    $confirmacao_almoxarifado = intval($chamado['almoxarifado_confirmacao_entrega'] ?? 0);
                                                    $confirmacao_merenda = intval($chamado['merenda_confirmacao_entrega'] ?? 0);
                                                    $confirmacao_unidade = intval($chamado['confirmacao_recebimento_unidade'] ?? 0);

                                                    // Situação 1: Aguardando Recebimento da Unidade
                                                    if ($chamado["status"] == "aguardando_recebimento" && $confirmacao_unidade == 0) {
                                                        
                                                        $setor_confirmou = false;
                                                        $setor_label = '';
                                                        $tipo_param = '';

                                                        // Almoxarifado confirmou
                                                        if ($is_almoxarifado && $confirmacao_almoxarifado == 1) {
                                                            $setor_confirmou = true;
                                                            $setor_label = '(Almox.)';
                                                            $tipo_param = 'almoxarifado';
                                                        
                                                        // Merenda confirmou
                                                        } elseif ($is_merenda && $confirmacao_merenda == 1) {
                                                            $setor_confirmou = true;
                                                            $setor_label = '(Merenda)';
                                                            $tipo_param = 'merenda';
                                                        }

                                                        if ($setor_confirmou) {
                                                            echo '<span class="badge badge-entrega-pendente">Aguardando Recebimento ' . $setor_label . '</span>';
                                                            
                                                            // Botão de Confirmação de Recebimento
                                                            echo '<br><a href="confirmar_recebimento_unidade.php?id=' . $chamado['id'] . '&tipo=' . $tipo_param . '" class="btn btn-sm btn-outline-success mt-1">Confirmar Recebimento</a>';

                                                        } else {
                                                            echo '<span class="badge bg-info text-dark">Aguardando Confirmação do Setor</span>';
                                                        }
                                                    
                                                    // Situação 2: Concluído e Recebimento pela Unidade Confirmado
                                                    } elseif ($chamado["status"] == "concluido" && $confirmacao_unidade == 1) {
                                                        $setor_label = '';
                                                        if ($is_almoxarifado) {
                                                            $setor_label = '(Almox.)';
                                                        } elseif ($is_merenda) {
                                                            $setor_label = '(Merenda)';
                                                        }
                                                        echo '<span class="badge badge-entrega-confirmada">Recebimento Confirmado ' . $setor_label . '</span>';
                                                    
                                                    // Situação 3: Qualquer Outra Situação 
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                               <a href="ver_chamado.php?id=<?php echo $chamado["id"]; ?>" class="btn btn-sm btn-primary mb-1" title="Detalhes do Chamado"><i class="bi bi-search"></i> Ver Chamado</a>                                                  
                                                    <?php 
                                                    // Lógica para o botão de Ofício
                                                    $has_oficio = !empty($chamado['numero_oficio']) && !empty($chamado['tipo_oficio']);
                                                    $oficio_tipo = $chamado['tipo_oficio'];
                                                    
                                                    // O botão Ver Ofício aparece se o chamado está em fase de entrega OU foi concluído E o ofício foi gerado.
                                                    if (($chamado['status'] == 'aguardando_recebimento' || $chamado['status'] == 'concluido') && $has_oficio): 
                                                    ?>
                                                        <a href="gerar_pdf_oficio.php?id=<?php echo $chamado['id']; ?>&tipo=<?php echo $oficio_tipo; ?>" class="btn btn-sm btn-info mt-1" target="_blank" title="Baixar Ofício de Entrega">
                                                            <i class="bi bi-file-earmark-pdf"></i> Ofício
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                                </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">
                                                Nenhum chamado encontrado. 
                                                <a href="novo_chamado.php" class="btn btn-link">Abrir primeiro chamado</a>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPaginas > 1): ?>
                        <nav aria-label="Navegação de páginas">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo ($pagina <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?pagina=<?php echo $pagina - 1; ?>">Anterior</a>
                                </li>

                                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                    <li class="page-item <?php echo ($pagina == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <li class="page-item <?php echo ($pagina >= $totalPaginas) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?pagina=<?php echo $pagina + 1; ?>">Próxima</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
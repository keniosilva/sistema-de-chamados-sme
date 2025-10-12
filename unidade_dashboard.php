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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap">
    <link rel="stylesheet" href="css/style.css">
    <style>
        :root {
            --title-color: #00695C; /* Deep teal for title */
            --primary-color: #0288D1; /* Blue for primary elements */
            --accent-color: #FF6B6B; /* Coral for accents */
            --background-color: #F5F7FA; /* Soft gray background */
            --card-bg: #FFFFFF; /* White for cards */
            --table-header-bg: #37474F; /* Dark gray for table header */
            --text-color: #263238; /* Dark gray for text */
        }

        body {
            background-color: var(--background-color);
            font-family: 'Roboto', Arial, sans-serif;
            color: var(--text-color);
        }

        .main-content {
            padding: 30px;
        }

        h1 {
            color: var(--title-color);
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .lead {
            color: #455A64;
            font-size: 1.2rem;
            margin-bottom: 20px;
        }

        .card-stats {
            background: linear-gradient(135deg, var(--card-bg), #ECEFF1);
            border: none;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            text-align: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card-stats:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        }

        .card-stats h5 {
            font-size: 1.3rem;
            color: var(--text-color);
            margin-bottom: 15px;
            font-weight: 500;
        }

        .card-stats p {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .card.bg-primary {
            background: linear-gradient(135deg, var(--primary-color), #0277BD) !important;
            color: #FFF;
        }

        .card.bg-warning {
            background: linear-gradient(135deg, var(--accent-color), #FF8A80) !important;
            color: #FFF;
        }

        .card.bg-success {
            background: linear-gradient(135deg, #2E7D32, #4CAF50) !important;
            color: #FFF;
        }

        .card.shadow-sm {
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }

        .card-header.bg-primary {
            background: linear-gradient(135deg, var(--primary-color), #0277BD) !important;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }

        .table {
            background-color: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }

        .table thead {
            background-color: var(--table-header-bg);
            color: #FFF;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(2, 136, 209, 0.05);
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #FAFAFA;
        }

        .status-aberto {
            background-color: var(--primary-color);
            color: #FFF;
            padding: 0.3em 0.7em;
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .status-em_andamento {
            background-color: var(--accent-color);
            color: #FFF;
            padding: 0.3em 0.7em;
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .status-concluido {
            background-color: #2E7D32;
            color: #FFF;
            padding: 0.3em 0.7em;
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .status-cancelado {
            background-color: #D32F2F;
            color: #FFF;
            padding: 0.3em 0.7em;
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .status-aguardando_recebimento {
            background-color: #6A1B9A;
            color: #FFF;
            padding: 0.3em 0.7em;
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .badge-entrega-pendente {
            background-color: var(--accent-color);
            color: #FFF;
            padding: 0.3em 0.7em;
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .badge-entrega-confirmada {
            background-color: #2E7D32;
            color: #FFF;
            padding: 0.3em 0.7em;
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .tipo-badge {
            background-color: rgba(2, 136, 209, 0.1);
            color: var(--primary-color);
            padding: 0.3em 0.7em;
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 5px;
            transition: background-color 0.2s ease;
        }

        .btn-primary:hover {
            background-color: #0277BD;
            border-color: #0277BD;
        }

        .btn-info {
            background-color: #0288D1;
            border-color: #0288D1;
            border-radius: 5px;
            transition: background-color 0.2s ease;
        }

        .btn-info:hover {
            background-color: #0277BD;
            border-color: #0277BD;
        }

        .btn-outline-success {
            border-color: #2E7D32;
            color: #2E7D32;
            border-radius: 5px;
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        .btn-outline-success:hover {
            background-color: #2E7D32;
            color: #FFF;
        }

        .btn-danger {
            background-color: #D32F2F;
            border-color: #D32F2F;
            border-radius: 5px;
            transition: background-color 0.2s ease;
        }

        .btn-danger:hover {
            background-color: #B71C1C;
            border-color: #B71C1C;
        }

        .btn-link {
            color: var(--primary-color);
            transition: color 0.2s ease;
        }

        .btn-link:hover {
            color: #0277BD;
        }

        .pagination .page-link {
            color: var(--primary-color);
            border-radius: 5px;
            transition: background-color 0.2s ease;
        }

        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: #FFF;
        }

        .pagination .page-link:hover {
            background-color: rgba(2, 136, 209, 0.1);
            color: var(--primary-color);
        }

        .alert {
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        hr {
            border-top: 2px solid rgba(2, 136, 209, 0.2);
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <div id="page-content-wrapper" class="main-content flex-grow-1">
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
                        <div class="card bg-warning text-white card-stats">
                            <div class="card-body">
                                <h5><i class="bi bi-clock-history"></i> Aguardando Confirmação</h5>
                                <p><?php echo $pending_count; ?></p>
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
                                <thead>
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
                                                            echo '<br><a href="confirmar_recebimento_unidade.php?id=' . $chamado['id'] . '&tipo=' . $tipo_param . '" class="btn btn-sm btn-outline-success mt-1">Confirmar Recebimento</a>';
                                                        } else {
                                                            echo '<span class="badge bg-info text-white">Aguardando Confirmação do Setor</span>';
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
                                                    $has_oficio = !empty($chamado['numero_oficio']) && !empty($chamado['tipo_oficio']);
                                                    $oficio_tipo = $chamado['tipo_oficio'];
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
<?php
session_start();
require_once 'connection.php';
require_once 'helpers.php'; // Inclui helpers para getDashboardUrl, getNomeTipoManutencao, etc.

// Verificar permissão
verificarPermissao(['secretaria']);

$_SESSION['tipo_usuario'] = $_SESSION['user']['tipo_usuario'];
$_SESSION['usuario_id'] = $_SESSION['user']['id'];

$mensagemErro = $_GET['erro'] ?? '';
$mensagemSucesso = $_GET['sucesso'] ?? '';

// Buscar estatísticas dos chamados da secretaria
$stats = [
    'total' => 0,
    'aberto' => 0,
    'em_andamento' => 0,
    'concluido' => 0,
    'cancelado' => 0
];

// Consulta de estatísticas por status (filtrando apenas os que a secretaria abriu)
$sql_stats = "SELECT status, COUNT(*) as total FROM chamados WHERE id_usuario_abertura = ? GROUP BY status";
$stmt_stats = $conn->prepare($sql_stats);
if ($stmt_stats) {
    $stmt_stats->bind_param("i", $_SESSION['user']['id']);
    $stmt_stats->execute();
    $result_stats = $stmt_stats->get_result();
    while ($row = $result_stats->fetch_assoc()) {
        $stats[$row['status']] = $row['total'];
        $stats['total'] += $row['total'];
    }
    $stmt_stats->close();
}

// --- Paginação e Consulta Principal ---

// Paginação básica
$pagina = (int)($_GET['pagina'] ?? 1);
$limite = 10;
$offset = ($pagina - 1) * $limite;

// Filtro por status (opcional)
$filtroStatus = $_GET['status'] ?? 'todos';

// Condição base: Chamados abertos pela secretaria
$whereClause = 'WHERE c.id_usuario_abertura = ?';
$paramTypes = 'i';
$bindValues = [$_SESSION['user']['id']];

if ($filtroStatus !== 'todos') {
    $whereClause .= ' AND c.status = ?';
    $paramTypes .= 's';
    $bindValues[] = $filtroStatus;
}

// Contar o total de chamados para paginação
$sql_total = "SELECT COUNT(*) as total FROM chamados c $whereClause";
$stmt_total = $conn->prepare($sql_total);
if ($stmt_total) {
    $stmt_total->bind_param($paramTypes, ...$bindValues);
    $stmt_total->execute();
    $total_chamados = $stmt_total->get_result()->fetch_assoc()['total'];
    $stmt_total->close();
} else {
    $total_chamados = 0;
}
$total_paginas = ceil($total_chamados / $limite);

// Consulta principal com JOIN na tabela ofícios
$sql_chamados = "
    SELECT c.id, c.tipo_manutencao, c.setor_destino, c.descricao, c.status, c.data_abertura,
           COALESCE(ue.nome_unidade, 'Secretaria de Educação') as origem,
           u.nome as tecnico_nome,
           c.almoxarifado_confirmacao_entrega, c.merenda_confirmacao_entrega, c.confirmacao_recebimento_unidade,
           o.numero_oficio, o.tipo_oficio
    FROM chamados c
    LEFT JOIN unidades_escolares ue ON c.id_unidade_escolar = ue.id
    LEFT JOIN usuarios u ON c.id_tecnico_responsavel = u.id
    LEFT JOIN oficios o ON c.id = o.id_chamado AND (o.tipo_oficio = 'entrega' OR o.tipo_oficio = 'entrega_merenda')
    $whereClause
    ORDER BY c.data_abertura DESC
    LIMIT ? OFFSET ?
";

$paramTypes .= 'ii';
$bindValues[] = $limite;
$bindValues[] = $offset;

$todos_chamados = [];
$stmt_chamados = $conn->prepare($sql_chamados);

if ($stmt_chamados) {
    $stmt_chamados->bind_param($paramTypes, ...$bindValues);
    $stmt_chamados->execute();
    $result_chamados = $stmt_chamados->get_result();
    while ($row = $result_chamados->fetch_assoc()) {
        $todos_chamados[] = $row;
    }
    $stmt_chamados->close();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Secretaria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .card-stats {
            margin-bottom: 20px;
            text-align: center;
        }
        .card-stats h5 {
            font-size: 1.25rem;
            color: #495057;
        }
        .card-stats p {
            font-size: 2.5rem;
            font-weight: bold;
        }
        .status-aberto { background-color: #0dcaf0; color: white; padding: 0.25em 0.5em; border-radius: 0.25em; }
        .status-em_andamento { background-color: #ffc107; color: black; padding: 0.25em 0.5em; border-radius: 0.25em; }
        .status-concluido { background-color: #198754; color: white; padding: 0.25em 0.5em; border-radius: 0.25em; }
        .status-cancelado { background-color: #dc3545; color: white; padding: 0.25em 0.5em; border-radius: 0.25em; }
        .status-aguardando_recebimento { background-color: #6f42c1; color: white; padding: 0.25em 0.5em; border-radius: 0.25em; }
        .setor-badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.375rem;
        }
        .setor-badge[data-setor='manutencao_geral'] { background-color: #6f42c1; }
        .setor-badge[data-setor='informatica'] { background-color: #fd7e14; }
        .setor-badge[data-setor='almoxarifado'] { background-color: #20c997; }
        .setor-badge[data-setor='casa_da_merenda'] { background-color: #6c757d; }
        .badge-entrega-pendente { background-color: #ffc107; color: black; }
        .badge-entrega-confirmada { background-color: #198754; color: white; }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <div id="page-content-wrapper" class="main-content bg-light flex-grow-1">
            <div class="container-fluid">
                <h1 class="mt-4">Dashboard - Secretaria</h1>
                <p class="lead">Bem-vindo(a), <?php echo htmlspecialchars($_SESSION['user']['nome']); ?></p>
                <a href="logout.php" class="btn btn-danger float-end mb-3"><i class="bi bi-box-arrow-right"></i> Sair</a>
                <hr>

                <?php if (!empty($mensagemSucesso)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($mensagemSucesso); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($mensagemErro)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($mensagemErro); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-info text-white card-stats">
                            <div class="card-body">
                                <h5><i class="bi bi-list-ol"></i> Total de Chamados</h5>
                                <p><?php echo $stats['total']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-primary text-white card-stats">
                            <div class="card-body">
                                <h5><i class="bi bi-clock"></i> Abertos</h5>
                                <p><?php echo $stats['aberto']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark card-stats">
                            <div class="card-body">
                                <h5><i class="bi bi-gear"></i> Em Andamento</h5>
                                <p><?php echo $stats['em_andamento']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white card-stats">
                            <div class="card-body">
                                <h5><i class="bi bi-check-circle"></i> Concluídos</h5>
                                <p><?php echo $stats['concluido']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <a href="novo_chamado.php" class="btn btn-primary mb-4"><i class="bi bi-plus-circle"></i> Abrir Novo Chamado</a>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h2 class="mb-0"><i class="bi bi-list-task"></i> Meus Chamados Abertos (Página <?php echo $pagina; ?> de <?php echo $total_paginas; ?>)</h2>
                        <small>Chamados que você iniciou ou que foram abertos pela Secretaria de Educação.</small>
                    </div>
                    <div class="card-body">
                        <?php if (empty($todos_chamados)): ?>
                            <p>Nenhum chamado aberto pela Secretaria de Educação encontrado.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Origem</th>
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
                                        <?php foreach ($todos_chamados as $chamado): ?>
                                            <tr>
                                                <td><?= $chamado['id'] ?></td>
                                                <td><?= htmlspecialchars($chamado['origem']) ?></td>
                                                <td><span class="tipo-badge"><?= getNomeTipoManutencao($chamado['tipo_manutencao']) ?></span></td>
                                                <td>
                                                    <span class="setor-badge" data-setor="<?= $chamado['setor_destino'] ?>">
                                                        <?= getNomeSetorDestino($chamado['setor_destino']) ?>
                                                    </span>
                                                </td>
                                                <td><span class="status status-<?= $chamado['status'] ?>"><?= ucfirst(str_replace('_', ' ', $chamado['status'])) ?></span></td>
                                                <td><?= date('d/m/Y H:i', strtotime($chamado['data_abertura'])) ?></td>
                                                <td><?= $chamado['tecnico_nome'] ? htmlspecialchars($chamado['tecnico_nome']) : 'Não atribuído' ?></td>
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
                                                    <a href="ver_chamado.php?id=<?= $chamado['id'] ?>" class="btn btn-sm btn-primary mb-1" title="Detalhes do Chamado"><i class="bi bi-search"></i> Ver</a>
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
                                    </tbody>
                                </table>
                            </div>

                            <?php if ($total_paginas > 1): ?>
                                <nav aria-label="Navegação de páginas">
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?php echo ($pagina <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?pagina=<?php echo $pagina - 1; ?>&status=<?= $filtroStatus ?>">Anterior</a>
                                        </li>
                                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                            <li class="page-item <?php echo ($i == $pagina) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?pagina=<?php echo $i; ?>&status=<?= $filtroStatus ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?php echo ($pagina >= $total_paginas) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?pagina=<?php echo $pagina + 1; ?>&status=<?= $filtroStatus ?>">Próxima</a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>

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
<?php
session_start();
require_once 'connection.php';
require_once 'helpers.php';
verificarPermissao(['almoxarifado']);

$_SESSION['tipo_usuario'] = $_SESSION['user']['tipo_usuario'];
$_SESSION['usuario_id'] = $_SESSION['user']['id'];

$mensagemErro = $_GET['erro'] ?? '';
$mensagemSucesso = $_GET['sucesso'] ?? '';
$dadosChamados = [];
$totalChamados = 0;
$stats = ['aberto' => 0, 'em_andamento' => 0, 'concluido' => 0, 'cancelado' => 0, 'aguardando_recebimento' => 0, 'total' => 0];

// Consulta de estatísticas por status
$stmt = $conn->prepare("SELECT status, COUNT(*) as total FROM chamados WHERE setor_destino = 'almoxarifado' GROUP BY status");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $stats[$row['status']] = $row['total'];
        $stats['total'] += $row['total'];
    }
    $stmt->close();
}

// Consulta de estatísticas por tipo (apenas um tipo aqui)
$statsType = ['almoxarifado' => $stats['total']];

// Dados para gráfico de linha (chamados por dia nos últimos 30 dias)
$lineData = ['labels' => [], 'data' => []];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $lineData['labels'][] = date('d/m', strtotime($date));
    $lineData['data'][$date] = 0;
}
$stmt = $conn->prepare("SELECT DATE(data_abertura) as dia, COUNT(*) as total FROM chamados WHERE setor_destino = 'almoxarifado' AND data_abertura >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY dia");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $lineData['data'][$row['dia']] = $row['total'];
    }
    $stmt->close();
}
$lineData['data'] = array_values($lineData['data']);

// Filtro por status (com validação)
$validStatuses = ['todos', 'aberto', 'em_andamento', 'concluido', 'cancelado', 'aguardando_recebimento'];
$filtroStatus = in_array($_GET['status'] ?? 'todos', $validStatuses) ? ($_GET['status'] ?? 'todos') : 'todos';

// Paginação básica (com validação)
$pagina = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$limite = 10;
$offset = ($pagina - 1) * $limite;

// Consulta principal com JOINs, filtro e paginação
$whereClause = 'WHERE c.setor_destino = ?';
$paramTypes = 's';
$bindValues = ['almoxarifado'];

if ($filtroStatus !== 'todos') {
    $whereClause .= ' AND c.status = ?';
    $paramTypes .= 's';
    $bindValues[] = $filtroStatus;
}

// Contar o total de chamados para paginação
$sql_total = "SELECT COUNT(*) as total FROM chamados c $whereClause";
$stmt_total = $conn->prepare($sql_total);
$total_chamados = 0;
if ($stmt_total) {
    $stmt_total->bind_param($paramTypes, ...$bindValues);
    $stmt_total->execute();
    $result_total = $stmt_total->get_result();
    $total_chamados = $result_total->fetch_assoc()['total'];
    $stmt_total->close();
} else {
    error_log("Erro na contagem total de chamados: " . $conn->error);
}
$total_paginas = ceil($total_chamados / $limite);

// Consulta principal
$sql_chamados = "
    SELECT c.id, c.tipo_manutencao, c.setor_destino, c.descricao, c.status, c.data_abertura,
           COALESCE(ue.nome_unidade, 'Secretaria de Educação') as origem,
           u.nome as nome_usuario, ut.nome as nome_tecnico,
           c.almoxarifado_confirmacao_entrega, c.confirmacao_entrega
    FROM chamados c
    LEFT JOIN unidades_escolares ue ON c.id_unidade_escolar = ue.id
    LEFT JOIN usuarios u ON c.id_usuario_abertura = u.id
    LEFT JOIN usuarios ut ON c.id_tecnico_responsavel = ut.id

    $whereClause
    ORDER BY c.data_abertura DESC
    LIMIT ? OFFSET ?
";

$paramTypes .= 'ii';
$bindValues[] = $limite;
$bindValues[] = $offset;

$stmt_chamados = $conn->prepare($sql_chamados);
if ($stmt_chamados) {
    $stmt_chamados->bind_param($paramTypes, ...$bindValues);
    $stmt_chamados->execute();
    $result_chamados = $stmt_chamados->get_result();
    while ($row = $result_chamados->fetch_assoc()) {
        $dadosChamados[] = $row;
    }
    $stmt_chamados->close();
} else {
    error_log("Erro na consulta principal: " . $conn->error);
}

// Consulta para chamados dos últimos 30 dias
$stmt_30dias = $conn->prepare("SELECT COUNT(*) as total FROM chamados WHERE setor_destino = 'almoxarifado' AND data_abertura >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$total_30dias = 0;
if ($stmt_30dias) {
    $stmt_30dias->execute();
    $result_30dias = $stmt_30dias->get_result();
    $total_30dias = $result_30dias->fetch_assoc()['total'];
    $stmt_30dias->close();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Almoxarifado</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Sistema de Chamados</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['user']['nome'] ?? 'Usuário') ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Sair</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Conteúdo Principal -->
    <div class="container-fluid mt-4">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-10">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h2 class="mb-0"><i class="bi bi-tools"></i> Dashboard - Almoxarifado</h2>
                    </div>
                    <div class="card-body">
                        <!-- Mensagens -->
                        <?php if ($mensagemErro): ?>
                            <div class="alert alert-danger" role="alert">
                                <?= htmlspecialchars($mensagemErro) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($mensagemSucesso): ?>
                            <div class="alert alert-success" role="alert">
                                <?= htmlspecialchars($mensagemSucesso) ?>
                            </div>
                        <?php endif; ?>

                        <div class="row row-cols-1 row-cols-md-4 g-4 mb-4">
                            <div class="col">
                                <div class="card shadow-sm text-center">
                                    <div class="card-body">
                                        <div class="h1 mb-0 text-warning"><?= $stats['aberto'] ?></div>
                                        <div class="text-muted">Abertos</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card shadow-sm text-center">
                                    <div class="card-body">
                                        <div class="h1 mb-0 text-info"><?= $stats['em_andamento'] ?></div>
                                        <div class="text-muted">Em Andamento</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card shadow-sm text-center">
                                    <div class="card-body">
                                        <div class="h1 mb-0 text-success"><?= $stats['concluido'] ?></div>
                                        <div class="text-muted">Concluídos</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card shadow-sm text-center">
                                    <div class="card-body">
                                        <div class="h1 mb-0 text-danger"><?= $stats['cancelado'] ?></div>
                                        <div class="text-muted">Cancelados</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-4">
                                <canvas id="pieChart" height="200"></canvas>
                            </div>
                            <div class="col-md-4">
                                <canvas id="barChart" height="200"></canvas>
                            </div>
                            <div class="col-md-4">
                                <canvas id="lineChart" height="200"></canvas>
                            </div>
                        </div>

                        <!-- Filtro por Status -->
                        <div class="mb-3">
                            <form class="row g-3 align-items-center">
                                <div class="col-auto">
                                    <label for="filtroStatus" class="col-form-label">Filtrar por Status:</label>
                                </div>
                                <div class="col-auto">
                                    <select id="filtroStatus" name="status" class="form-select" onchange="this.form.submit()">
                                        <option value="todos" <?= $filtroStatus === 'todos' ? 'selected' : '' ?>>Todos</option>
                                        <option value="aberto" <?= $filtroStatus === 'aberto' ? 'selected' : '' ?>>Aberto</option>
                                        <option value="em_andamento" <?= $filtroStatus === 'em_andamento' ? 'selected' : '' ?>>Em Andamento</option>
                                        <option value="concluido" <?= $filtroStatus === 'concluido' ? 'selected' : '' ?>>Concluído</option>
                                        <option value="cancelado" <?= $filtroStatus === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                        <option value="aguardando_recebimento" <?= $filtroStatus === 'aguardando_recebimento' ? 'selected' : '' ?>>Aguardando Recebimento</option>
                                    </select>
                                </div>
                            </form>
                        </div>

                        <!-- Tabela de Chamados -->
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle">
                                <thead class="table-info">
                                    <tr>
                                        <th scope="col">ID</th>
                                        <th scope="col">Origem</th>
                                        <th scope="col">Usuário</th>
                                        <th scope="col">Tipo</th>
                                        <th scope="col">Descrição</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Data Abertura</th>

                                        <th scope="col">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($dadosChamados)): ?>
                                        <?php foreach ($dadosChamados as $chamado): ?>
                                            <tr>
                                                <td><?= $chamado['id'] ?></td>
                                                <td><?= htmlspecialchars($chamado['origem'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($chamado['nome_usuario'] ?? 'N/A') ?></td>
                                                <td><span class="tipo-badge"><?= getNomeTipoManutencao($chamado['tipo_manutencao']) ?></span></td>
                                                <td><?= htmlspecialchars(substr($chamado['descricao'], 0, 50)) . (strlen($chamado['descricao']) > 50 ? '...' : '') ?></td>
                                                <td><span class="badge 
                                                    <?= $chamado['status'] === 'aberto' ? 'bg-warning' : 
                                                        ($chamado['status'] === 'em_andamento' ? 'bg-info' : 
                                                        ($chamado['status'] === 'concluido' ? 'bg-success' : 'bg-danger')) ?>">
                                                    <?= ucfirst(str_replace('_', ' ', htmlspecialchars($chamado['status']))) ?>
                                                </span></td>
                                                <td><?= date('d/m/Y H:i', strtotime($chamado['data_abertura'])) ?></td>

                                                <td>
                                                    <a href="ver_chamado.php?id=<?= $chamado['id'] ?>" class="btn btn-primary btn-sm">
                                                        <i class="bi bi-eye"></i> Ver
                                                    </a>
                                                    <?php if ($chamado['status'] != 'concluido' && $chamado['status'] != 'cancelado'): ?>
                                                        <a href="gerenciar_chamados.php?id=<?= $chamado['id'] ?>" class="btn btn-warning btn-sm">
                                                            <i class="bi bi-gear"></i> Gerenciar
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Nenhum chamado encontrado.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginação -->
                        <?php if ($total_paginas > 1): ?>
                            <nav aria-label="Paginação">
                                <ul class="pagination justify-content-center">
                                    <?php if ($pagina > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?= $pagina - 1; ?>&status=<?= $filtroStatus ?>">Anterior</a>
                                        </li>
                                    <?php endif; ?>
                                    <?php 
                                    $startPage = max(1, $pagina - 2);
                                    $endPage = min($total_paginas, $pagina + 2);
                                    for ($i = $startPage; $i <= $endPage; $i++): 
                                    ?>
                                        <li class="page-item <?= ($i == $pagina) ? 'active' : '' ?>">
                                            <a class="page-link" href="?pagina=<?= $i ?>&status=<?= $filtroStatus ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <?php if ($pagina < $total_paginas): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?= $pagina + 1; ?>&status=<?= $filtroStatus ?>">Próxima</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>

                        <!-- Seção de Relatórios -->
                        <div class="card shadow-sm mt-4">
                            <div class="card-header bg-secondary text-white">
                                <h3 class="mb-0"><i class="bi bi-bar-chart"></i> Relatórios Rápidos</h3>
                            </div>
                            <div class="card-body">
                                <p><strong>Total de chamados nos últimos 30 dias:</strong> <?= $total_30dias ?></p>
                                <a href="relatorios.php" class="btn btn-info">Ver Relatórios Detalhados</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gráfico de Pizza (Distribuição por Status)
        new Chart(document.getElementById('pieChart').getContext('2d'), {
            type: 'pie',
            data: {
                labels: ['Aberto', 'Em Andamento', 'Concluído', 'Cancelado'],
                datasets: [{
                    data: [<?= $stats['aberto'] ?? 0 ?>, <?= $stats['em_andamento'] ?? 0 ?>, <?= $stats['concluido'] ?? 0 ?>, <?= $stats['cancelado'] ?? 0 ?>],
                    backgroundColor: ['#ffc107', '#0dcaf0', '#198754', '#dc3545']
                }]
            },
            options: { 
                responsive: true,
                plugins: { legend: { position: 'top' }, tooltip: { enabled: true } }
            }
        });

        // Gráfico de Barras (Distribuição por Tipo)
        new Chart(document.getElementById('barChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Almoxarifado'],
                datasets: [{
                    label: 'Chamados por Tipo',
                    data: [<?= $statsType['almoxarifado'] ?? 0 ?>],
                    backgroundColor: ['#0dcaf0']
                }]
            },
            options: { 
                responsive: true,
                plugins: { legend: { position: 'top' }, tooltip: { enabled: true } }
            }
        });

        // Gráfico de Linha (Chamados nos Últimos 30 Dias)
        new Chart(document.getElementById('lineChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode($lineData['labels']) ?>,
                datasets: [{
                    label: 'Chamados por Dia',
                    data: <?= json_encode($lineData['data']) ?>,
                    borderColor: '#0dcaf0',
                    fill: false
                }]
            },
            options: { 
                responsive: true,
                plugins: { legend: { position: 'top' }, tooltip: { enabled: true } }
            }
        });
    </script>
</body>
</html>
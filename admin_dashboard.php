<?php
session_start();
require_once 'connection.php';
require_once 'auth.php';
require_once 'helpers.php'; // Inclui helpers, mas getNomeTipoManutencao deve estar apenas em connection.php
restrictAccess(['admin']);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$mensagemErro = $_GET['erro'] ?? '';
$mensagemSucesso = $_GET['sucesso'] ?? '';
$dadosChamados = [];
$totalChamados = 0;
$stats = ['aberto' => 0, 'em_andamento' => 0, 'concluido' => 0, 'cancelado' => 0];
$statsType = ['geral' => 0, 'informatica' => 0, 'almoxarifado' => 0, 'casa_da_merenda' => 0];

// Filtro por status e tipo
$filtroStatus = $_GET['status'] ?? 'todos';
$filtroTipo = $_GET['tipo'] ?? 'todos';

// Paginação
$itensPorPagina = 10;
$paginaAtual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Consulta de estatísticas por status
$stmt = $conn->prepare("SELECT status, COUNT(*) as total FROM chamados GROUP BY status");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $stats[$row['status']] = $row['total'];
    }
    $stmt->close();
} else {
    error_log("Erro ao preparar consulta de estatísticas por status: " . $conn->error);
    $mensagemErro = "Erro ao carregar estatísticas por status.";
}

// Consulta de estatísticas por tipo
$stmt = $conn->prepare("SELECT tipo_manutencao, COUNT(*) as total FROM chamados GROUP BY tipo_manutencao");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (isset($statsType[$row['tipo_manutencao']])) {
            $statsType[$row['tipo_manutencao']] = $row['total'];
        }
    }
    $stmt->close();
} else {
    error_log("Erro ao preparar consulta de estatísticas por tipo: " . $conn->error);
    $mensagemErro = "Erro ao carregar estatísticas por tipo.";
}

// Dados para gráfico de linha (chamados por dia nos últimos 30 dias)
$lineData = ['labels' => [], 'data' => []];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $lineData['labels'][] = date('d/m', strtotime($date));
    $lineData['data'][$date] = 0;
}
$stmt = $conn->prepare("SELECT DATE(data_abertura) as dia, COUNT(*) as total FROM chamados WHERE data_abertura >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY dia");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $lineData['data'][$row['dia']] = $row['total'];
    }
    $stmt->close();
} else {
    error_log("Erro ao preparar consulta de chamados por dia: " . $conn->error);
    $mensagemErro = "Erro ao carregar dados do gráfico.";
}
$lineData['data'] = array_values($lineData['data']);

// Consulta principal com JOINs, filtros e paginação
$conditions = [];
$params = [];
$types = '';
if ($filtroStatus !== 'todos') {
    $conditions[] = "c.status = ?";
    $params[] = $filtroStatus;
    $types .= 's';
}
if ($filtroTipo !== 'todos') {
    $conditions[] = "c.tipo_manutencao = ?";
    $params[] = $filtroTipo;
    $types .= 's';
}
$whereClause = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);

// Adicionar LIMIT e OFFSET aos parâmetros
$params[] = $itensPorPagina;
$params[] = $offset;
$types .= 'ii';

$query = "SELECT c.id, c.tipo_manutencao, c.descricao, c.status, c.data_abertura, 
                 ue.nome_unidade, u.nome as nome_usuario 
          FROM chamados c 
          LEFT JOIN unidades_escolares ue ON c.id_unidade_escolar = ue.id 
          JOIN usuarios u ON c.id_usuario_abertura = u.id 
          $whereClause 
          ORDER BY c.data_abertura DESC 
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $dadosChamados[] = $row;
    }
    $stmt->close();
    if (empty($dadosChamados) && ($filtroStatus !== 'todos' || $filtroTipo !== 'todos')) {
        error_log("Nenhum chamado encontrado. Query: $query, Params: " . json_encode($params));
        $mensagemErro = "Nenhum chamado encontrado para o filtro selecionado.";
    }
} else {
    $mensagemErro = "Erro ao preparar consulta de chamados: " . $conn->error;
    error_log("Erro na consulta principal: $query, Erro: " . $conn->error);
}

// Total para estatísticas e paginação
$totalQuery = "SELECT COUNT(*) as total FROM chamados c $whereClause";
$stmt = $conn->prepare($totalQuery);
if ($stmt) {
    if (!empty($types) && $types !== 'ii') {
        $stmt->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $totalChamados = $result->fetch_assoc()['total'] ?? 0;
    $stmt->close();
    error_log("Total Chamados Calculado: $totalChamados, Query: $totalQuery, Params: " . json_encode(array_slice($params, 0, -2)));
} else {
    $mensagemErro = "Erro ao preparar consulta de total de chamados: " . $conn->error;
    error_log("Erro na consulta de total: $totalQuery, Erro: " . $conn->error);
}

// Calcular total de páginas
$totalPaginas = ceil($totalChamados / $itensPorPagina);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Administração</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Sistema de Chamados</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="gerenciar_unidades.php"><i class="bi bi-building"></i> Gerenciar Unidades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gerenciar_usuarios.php"><i class="bi bi-people"></i> Gerenciar Usuários</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['user']['nome'] ?? 'Admin') ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Sair</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-10">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h2 class="mb-0"><i class="bi bi-tools"></i> Dashboard Administrativo - Todos os Chamados</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($mensagemSucesso): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($mensagemSucesso) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <?php if ($mensagemErro): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($mensagemErro) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="row row-cols-1 row-cols-md-4 g-4 mb-4">
                            <div class="col">
                                <div class="card shadow-sm text-center">
                                    <div class="card-body">
                                        <div class="h1 mb-0 text-primary"><?= $stats['aberto'] ?? 0 ?></div>
                                        <div class="text-muted">Abertos</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card shadow-sm text-center">
                                    <div class="card-body">
                                        <div class="h1 mb-0 text-info"><?= $stats['em_andamento'] ?? 0 ?></div>
                                        <div class="text-muted">Em Andamento</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card shadow-sm text-center">
                                    <div class="card-body">
                                        <div class="h1 mb-0 text-success"><?= $stats['concluido'] ?? 0 ?></div>
                                        <div class="text-muted">Concluídos</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card shadow-sm text-center">
                                    <div class="card-body">
                                        <div class="h1 mb-0 text-danger"><?= $stats['cancelado'] ?? 0 ?></div>
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

                        <div class="mb-4">
                            <form method="GET" class="d-flex align-items-center">
                                <label for="status" class="me-2">Filtrar por status:</label>
                                <select name="status" id="status" class="form-select w-auto me-2">
                                    <option value="todos" <?= $filtroStatus === 'todos' ? 'selected' : '' ?>>Todos</option>
                                    <option value="aberto" <?= $filtroStatus === 'aberto' ? 'selected' : '' ?>>Aberto</option>
                                    <option value="em_andamento" <?= $filtroStatus === 'em_andamento' ? 'selected' : '' ?>>Em Andamento</option>
                                    <option value="concluido" <?= $filtroStatus === 'concluido' ? 'selected' : '' ?>>Concluído</option>
                                    <option value="cancelado" <?= $filtroStatus === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                </select>
                                <label for="tipo" class="me-2">Filtrar por tipo:</label>
                                <select name="tipo" id="tipo" class="form-select w-auto me-2">
                                    <option value="todos" <?= $filtroTipo === 'todos' ? 'selected' : '' ?>>Todos</option>
                                    <option value="geral" <?= $filtroTipo === 'geral' ? 'selected' : '' ?>>Manutenção Geral</option>
                                    <option value="informatica" <?= $filtroTipo === 'informatica' ? 'selected' : '' ?>>Informática</option>
                                    <option value="almoxarifado" <?= $filtroTipo === 'almoxarifado' ? 'selected' : '' ?>>Almoxarifado</option>
                                    <option value="casa_da_merenda" <?= $filtroTipo === 'casa_da_merenda' ? 'selected' : '' ?>>Casa da Merenda</option>
                                </select>
                                <input type="hidden" name="pagina" value="1">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
                            </form>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle">
                                <thead class="table-primary">
                                    <tr>
                                        <th scope="col">ID</th>
                                        <th scope="col">Unidade</th>
                                        <th scope="col">Tipo</th>
                                        <th scope="col">Descrição</th>
                                        <th scope="col">Solicitante</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Data Abertura</th>
                                        <th scope="col">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($dadosChamados)): ?>
                                        <?php foreach ($dadosChamados as $chamado): ?>
                                            <tr>
                                                <td>#<?= htmlspecialchars($chamado['id']) ?></td>
                                                <td><?= htmlspecialchars($chamado['nome_unidade'] ?? 'Não especificado') ?></td>
                                                <td><?= htmlspecialchars(getNomeTipoManutencao($chamado['tipo_manutencao'])) ?></td>
                                                <td><?= htmlspecialchars(substr($chamado['descricao'], 0, 50)) . (strlen($chamado['descricao']) > 50 ? '...' : '') ?></td>
                                                <td><?= htmlspecialchars($chamado['nome_usuario']) ?></td>
                                                <td>
                                                    <span class="badge 
                                                        <?= $chamado['status'] === 'aberto' ? 'bg-warning' : 
                                                            ($chamado['status'] === 'em_andamento' ? 'bg-info' : 
                                                            ($chamado['status'] === 'concluido' ? 'bg-success' : 'bg-danger')) ?>">
                                                        <?= ucfirst(str_replace('_', ' ', htmlspecialchars($chamado['status']))) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('d/m/Y H:i', strtotime($chamado['data_abertura'])) ?></td>
                                                <td>
                                                    <a href="ver_chamado.php?id=<?= $chamado['id'] ?>" class="btn btn-primary btn-sm">
                                                        <i class="bi bi-eye"></i> Ver
                                                    </a>
                                                    <a href="gerenciar_chamados.php?id=<?= $chamado['id'] ?>" class="btn btn-warning btn-sm">
                                                        <i class="bi bi-gear"></i> Gerenciar
                                                    </a>
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
                        <nav aria-label="Paginação de chamados">
                            <ul class="pagination justify-content-center mt-4">
                                <li class="page-item <?= $paginaAtual <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?status=<?= urlencode($filtroStatus) ?>&tipo=<?= urlencode($filtroTipo) ?>&pagina=<?= $paginaAtual - 1 ?>">Anterior</a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                    <li class="page-item <?= $i === $paginaAtual ? 'active' : '' ?>">
                                        <a class="page-link" href="?status=<?= urlencode($filtroStatus) ?>&tipo=<?= urlencode($filtroTipo) ?>&pagina=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= $paginaAtual >= $totalPaginas ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?status=<?= urlencode($filtroStatus) ?>&tipo=<?= urlencode($filtroTipo) ?>&pagina=<?= $paginaAtual + 1 ?>">Próximo</a>
                                </li>
                            </ul>
                        </nav>

                        <div class="card shadow-sm mt-4">
                            <div class="card-header bg-secondary text-white">
                                <h3 class="mb-0"><i class="bi bi-bar-chart"></i> Relatórios Rápidos</h3>
                            </div>
                            <div class="card-body">
                                <p>Total de chamados com filtros aplicados: <strong><?= htmlspecialchars($totalChamados) ?></strong></p>
                                <p>Total de chamados nos últimos 30 dias: 
                                    <?php
                                    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM chamados WHERE data_abertura >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                                    if ($stmt) {
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        $totalUltimos30Dias = $result->fetch_assoc()['total'] ?? 0;
                                        echo htmlspecialchars($totalUltimos30Dias);
                                        $stmt->close();
                                    } else {
                                        error_log("Erro ao contar chamados dos últimos 30 dias: " . $conn->error);
                                        echo "Erro ao carregar total.";
                                    }
                                    ?>
                                </p>
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
                labels: ['Manutenção Geral', 'Informática', 'Almoxarifado', 'Casa da Merenda'],
                datasets: [{
                    label: 'Chamados por Tipo',
                    data: [<?= $statsType['geral'] ?? 0 ?>, <?= $statsType['informatica'] ?? 0 ?>, <?= $statsType['almoxarifado'] ?? 0 ?>, <?= $statsType['casa_da_merenda'] ?? 0 ?>],
                    backgroundColor: ['#6f42c1', '#fd7e14', '#20c997', '#6c757d']
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
                    borderColor: '#0d6efd',
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
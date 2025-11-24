<?php
session_start();
require_once 'connection.php';
require_once 'auth.php';
require_once 'helpers.php';
restrictAccess(['admin']);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$mensagemErro = $_GET['erro'] ?? '';
$mensagemSucesso = $_GET['sucesso'] ?? '';
$dadosChamados = [];
$totalChamados = 0;
$stats = ['aberto' => 0, 'aguardando_recebimento' => 0, 'em_andamento' => 0, 'concluido' => 0, 'cancelado' => 0];
$statsType = ['geral' => 0, 'informatica' => 0, 'almoxarifado' => 0, 'casa_da_merenda' => 0];
$statsUnit = [];

// Filtros
$filtroStatus = $_GET['status'] ?? 'todos';
$filtroTipo = $_GET['tipo'] ?? 'todos';

// Paginação
$itensPorPagina = 10;
$paginaAtual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// === Estatísticas por Status ===
$stmt = $conn->prepare("SELECT status, COUNT(*) as total FROM chamados GROUP BY status");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (array_key_exists($row['status'], $stats)) {
            $stats[$row['status']] = $row['total'];
        }
    }
    $stmt->close();
}

// === Estatísticas por Tipo ===
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
}

// === Top 5 Unidades ===
$stmt = $conn->prepare("SELECT ue.nome_unidade, COUNT(*) as total 
                        FROM chamados c 
                        LEFT JOIN unidades_escolares ue ON c.id_unidade_escolar = ue.id 
                        GROUP BY ue.id, ue.nome_unidade 
                        ORDER BY total DESC LIMIT 5");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $statsUnit[] = [
            'nome_unidade' => $row['nome_unidade'] ?: 'Não especificado',
            'total' => $row['total']
        ];
    }
    $stmt->close();
}

// === Gráfico de linha - últimos 30 dias ===
$lineData = ['labels' => [], 'data' => []];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $lineData['labels'][] = date('d/m', strtotime($date));
    $lineData['data'][$date] = 0;
}
$stmt = $conn->prepare("SELECT DATE(data_abertura) as dia, COUNT(*) as total 
                        FROM chamados WHERE data_abertura >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                        GROUP BY dia");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $lineData['data'][$row['dia']] = $row['total'];
    }
    $stmt->close();
}
$lineData['data'] = array_values($lineData['data']);

// === Consulta principal com filtros ===
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

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
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
    if (!empty($types)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $dadosChamados[] = $row;
    }
    $stmt->close();
}

// === Total para paginação ===
$totalQuery = "SELECT COUNT(*) as total FROM chamados c $whereClause";
$stmt = $conn->prepare($totalQuery);
if ($stmt) {
    if (!empty($types) && $types !== 'ii') {
        $stmt->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
    }
    $stmt->execute();
    $totalChamados = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();
}
$totalPaginas = ceil($totalChamados / $itensPorPagina);

// === Total nos últimos 30 dias (para o card de relatórios) ===
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM chamados WHERE data_abertura >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stmt->execute();
$totalUltimos30Dias = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();
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
    <style>
        .badge.bg-light { border: 1px solid #6c757d; }
    </style>
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Sistema de Chamados</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="gerenciar_unidades.php">Gerenciar Unidades</a></li>
                    <li class="nav-item"><a class="nav-link" href="gerenciar_usuarios.php">Gerenciar Usuários</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['user']['nome'] ?? 'Admin') ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-11">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h2 class="mb-0">Dashboard Administrativo - Todos os Chamados</h2>
                    </div>
                    <div class="card-body">

                        <?php if ($mensagemSucesso): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?= htmlspecialchars($mensagemSucesso) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        <?php if ($mensagemErro): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?= htmlspecialchars($mensagemErro) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Cards de Status -->
                        <div class="row row-cols-2 row-cols-md-5 g-4 mb-4">
                            <div class="col"><div class="card text-center h-100 border-warning"><div class="card-body"><h3 class="text-warning mb-0"><?= $stats['aberto'] ?></h3><small>Abertos</small></div></div></div>
                            <div class="col"><div class="card text-center h-100 border-secondary"><div class="card-body"><h3 class="text-secondary mb-0"><?= $stats['aguardando_recebimento'] ?? 0 ?></h3><small>Aguardando</small></div></div></div>
                            <div class="col"><div class="card text-center h-100 border-info"><div class="card-body"><h3 class="text-info mb-0"><?= $stats['em_andamento'] ?></h3><small>Em Andamento</small></div></div></div>
                            <div class="col"><div class="card text-center h-100 border-success"><div class="card-body"><h3 class="text-success mb-0"><?= $stats['concluido'] ?></h3><small>Concluídos</small></div></div></div>
                            <div class="col"><div class="card text-center h-100 border-danger"><div class="card-body"><h3 class="text-danger mb-0"><?= $stats['cancelado'] ?></h3><small>Cancelados</small></div></div></div>
                        </div>

                        <!-- Gráficos -->
                        <div class="row mb-4">
                            <div class="col-md-3"><canvas id="pieChart" height="180"></canvas></div>
                            <div class="col-md-3"><canvas id="barChart" height="180"></canvas></div>
                            <div class="col-md-3"><canvas id="lineChart" height="180"></canvas></div>
                            <div class="col-md-3"><canvas id="unitChart" height="180"></canvas></div>
                        </div>

                        <!-- Filtros -->
                        <form method="GET" class="row g-3 mb-4 align-items-end">
                            <div class="col-auto">
                                <label class="form-label">Status:</label>
                                <select name="status" class="form-select">
                                    <option value="todos" <?= $filtroStatus === 'todos' ? 'selected' : '' ?>>Todos</option>
                                    <option value="aberto" <?= $filtroStatus === 'aberto' ? 'selected' : '' ?>>Aberto</option>
                                    <option value="aguardando_recebimento" <?= $filtroStatus === 'aguardando_recebimento' ? 'selected' : '' ?>>Aguardando Recebimento</option>
                                    <option value="em_andamento" <?= $filtroStatus === 'em_andamento' ? 'selected' : '' ?>>Em Andamento</option>
                                    <option value="concluido" <?= $filtroStatus === 'concluido' ? 'selected' : '' ?>>Concluído</option>
                                    <option value="cancelado" <?= $filtroStatus === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                </select>
                            </div>
                            <div class="col-auto">
                                <label class="form-label">Tipo:</label>
                                <select name="tipo" class="form-select">
                                    <option value="todos" <?= $filtroTipo === 'todos' ? 'selected' : '' ?>>Todos</option>
                                    <option value="geral" <?= $filtroTipo === 'geral' ? 'selected' : '' ?>>Manutenção Geral</option>
                                    <option value="informatica" <?= $filtroTipo === 'informatica' ? 'selected' : '' ?>>Informática</option>
                                    <option value="almoxarifado" <?= $filtroTipo === 'almoxarifado' ? 'selected' : '' ?>>Almoxarifado</option>
                                    <option value="casa_da_merenda" <?= $filtroTipo === 'casa_da_merenda' ? 'selected' : '' ?>>Casa da Merenda</option>
                                </select>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary">Filtrar</button>
                            </div>
                        </form>

                        <!-- Tabela -->
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle">
                                <thead class="table-primary">
                                    <tr>
                                        <th>ID</th>
                                        <th>Unidade</th>
                                        <th>Tipo</th>
                                        <th>Descrição</th>
                                        <th>Solicitante</th>
                                        <th>Status</th>
                                        <th>Data Abertura</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($dadosChamados)): ?>
                                        <?php foreach ($dadosChamados as $chamado): ?>
                                            <tr>
                                                <td>#<?= $chamado['id'] ?></td>
                                                <td><?= htmlspecialchars($chamado['nome_unidade'] ?? 'Não especificado') ?></td>
                                                <td><?= htmlspecialchars(getNomeTipoManutencao($chamado['tipo_manutencao'])) ?></td>
                                                <td><?= htmlspecialchars(substr($chamado['descricao'], 0, 50)) . (strlen($chamado['descricao']) > 50 ? '...' : '') ?></td>
                                                <td><?= htmlspecialchars($chamado['nome_usuario']) ?></td>
                                                <td>
                                                    <?php
                                                    $status = $chamado['status'];
                                                    $classe = $status === 'aberto' ? 'bg-warning text-dark' :
                                                             ($status === 'aguardando_recebimento' ? 'bg-light text-dark border' :
                                                             ($status === 'em_andamento' ? 'bg-info' :
                                                             ($status === 'concluido' ? 'bg-success' : 'bg-danger')));
                                                    $texto = $status === 'aguardando_recebimento' ? 'Aguardando Recebimento' : ucfirst(str_replace('_', ' ', $status));
                                                    ?>
                                                    <span class="badge <?= $classe ?>"><?= $texto ?></span>
                                                </td>
                                                <td><?= date('d/m/Y H:i', strtotime($chamado['data_abertura'])) ?></td>
                                                <td>
                                                    <a href="ver_chamado.php?id=<?= $chamado['id'] ?>" class="btn btn-primary btn-sm">Ver</a>
                                                    <a href="gerenciar_chamados.php?id=<?= $chamado['id'] ?>" class="btn btn-warning btn-sm">Gerenciar</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="8" class="text-center py-4 text-muted">Nenhum chamado encontrado com os filtros aplicados.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginação -->
                        <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap gap-3 bg-white p-3 rounded border">
                            <div class="text-muted">
                                Mostrando <?= count($dadosChamados) ?> de <?= $totalChamados ?> chamados
                                <?= $totalChamados > 0 ? " • Página $paginaAtual de $totalPaginas" : '' ?>
                            </div>
                            <nav>
                                <ul class="pagination mb-0">
                                    <li class="page-item <?= $paginaAtual <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?status=<?= urlencode($filtroStatus) ?>&tipo=<?= urlencode($filtroTipo) ?>&pagina=<?= $paginaAtual - 1 ?>">Anterior</a>
                                    </li>
                                    <?php
                                    $inicio = max(1, $paginaAtual - 2);
                                    $fim = min($totalPaginas, $paginaAtual + 2);
                                    if ($inicio > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="?status='.urlencode($filtroStatus).'&tipo='.urlencode($filtroTipo).'&pagina=1">1</a></li>';
                                        if ($inicio > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    for ($i = $inicio; $i <= $fim; $i++) {
                                        $active = $i === $paginaAtual ? 'active' : '';
                                        echo "<li class='page-item $active'><a class='page-link' href='?status=".urlencode($filtroStatus)."&tipo=".urlencode($filtroTipo)."&pagina=$i'>$i</a></li>";
                                    }
                                    if ($fim < $totalPaginas) {
                                        if ($fim < $totalPaginas - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        echo "<li class='page-item'><a class='page-link' href='?status=".urlencode($filtroStatus)."&tipo=".urlencode($filtroTipo)."&pagina=$totalPaginas'>$totalPaginas</a></li>";
                                    }
                                    ?>
                                    <li class="page-item <?= $paginaAtual >= $totalPaginas ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?status=<?= urlencode($filtroStatus) ?>&tipo=<?= urlencode($filtroTipo) ?>&pagina=<?= $paginaAtual + 1 ?>">Próximo</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>

                        <!-- Relatórios Rápidos -->
                        <div class="card shadow-sm mt-4">
                            <div class="card-header bg-secondary text-white">
                                <h3 class="mb-0">Relatórios Rápidos</h3>
                            </div>
                            <div class="card-body">
                                <p>Total de chamados com filtros aplicados: <strong><?= $totalChamados ?></strong></p>
                                <p>Total de chamados nos últimos 30 dias: <strong><?= $totalUltimos30Dias ?></strong></p>
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
        // Gráficos
        new Chart(document.getElementById('pieChart'), {
            type: 'pie',
            data: {
                labels: ['Aberto', 'Aguardando', 'Em Andamento', 'Concluído', 'Cancelado'],
                datasets: [{ 
                    data: [<?= $stats['aberto'] ?>, <?= $stats['aguardando_recebimento']??0 ?>, <?= $stats['em_andamento'] ?>, <?= $stats['concluido'] ?>, <?= $stats['cancelado'] ?>],
                    backgroundColor: ['#ffc107', '#6c757d', '#0dcaf0', '#198754', '#dc3545']
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } }}
        });

        new Chart(document.getElementById('barChart'), {
            type: 'bar',
            data: {
                labels: ['Geral', 'Informática', 'Almoxarifado', 'Casa da Merenda'],
                datasets: [{ 
                    label: 'Chamados', 
                    data: [<?= $statsType['geral']??0 ?>, <?= $statsType['informatica']??0 ?>, <?= $statsType['almoxarifado']??0 ?>, <?= $statsType['casa_da_merenda']??0 ?>], 
                    backgroundColor: '#0d6efd' 
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }}
        });

        new Chart(document.getElementById('lineChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($lineData['labels']) ?>,
                datasets: [{ 
                    label: 'Chamados por dia', 
                    data: <?= json_encode($lineData['data']) ?>, 
                    borderColor: '#0d6efd', 
                    tension: 0.3, 
                    fill: true, 
                    backgroundColor: 'rgba(13,110,253,0.1)' 
                }]
            },
            options: { responsive: true }
        });

        new Chart(document.getElementById('unitChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($statsUnit, 'nome_unidade')) ?>,
                datasets: [{ 
                    label: 'Total', 
                    data: <?= json_encode(array_column($statsUnit, 'total')) ?>, 
                    backgroundColor: '#198754' 
                }]
            },
            options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }}
        });
    </script>
</body>
</html>
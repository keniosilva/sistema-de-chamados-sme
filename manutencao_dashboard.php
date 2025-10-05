<?php
session_start();
require_once 'connection.php';
require_once 'helpers.php';

// Proteção: só técnicos de manutenção podem acessar
verificarPermissao(['tecnico_geral']);

$mensagemErro = $_GET['erro'] ?? '';
$mensagemSucesso = $_GET['sucesso'] ?? '';
$dadosChamados = [];
$dadosChamadosOutrosSetores = [];
$totalChamados = 0;
$stats = ['aberto' => 0, 'em_andamento' => 0, 'concluido' => 0, 'cancelado' => 0];

// ID do técnico logado (para condicionar ações)
$id_tecnico = $_SESSION['user']['id'];

// Filtro por status
$filtroStatus = $_GET['status'] ?? 'todos';

// Consulta de estatísticas por status
$stmt = $conn->prepare("SELECT status, COUNT(*) as total FROM chamados WHERE tipo_manutencao = 'geral' GROUP BY status");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $stats[$row['status']] = $row['total'];
    }
    $stmt->close();
}

// Dados para gráfico de linha (chamados por dia nos últimos 30 dias)
$lineData = ['labels' => [], 'data' => []];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $lineData['labels'][] = date('d/m', strtotime($date));
    $lineData['data'][$date] = 0;
}
$stmt = $conn->prepare("SELECT DATE(data_abertura) as dia, COUNT(*) as total FROM chamados WHERE tipo_manutencao = 'geral' AND data_abertura >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY dia");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $lineData['data'][$row['dia']] = $row['total'];
    }
    $stmt->close();
}
$lineData['data'] = array_values($lineData['data']);

// Consulta principal com JOINs e filtro por status (chamados de manutenção geral)
$conditions = ["c.tipo_manutencao = 'geral'"];
$params = [];
$types = '';
if ($filtroStatus !== 'todos') {
    $conditions[] = "c.status = ?";
    $params[] = $filtroStatus;
    $types .= 's';
}
$whereClause = 'WHERE ' . implode(' AND ', $conditions);

$query = "SELECT c.id, c.tipo_manutencao, c.descricao, c.status, c.data_abertura, 
                 COALESCE(ue.nome_unidade, 'Secretaria de Educação') as nome_unidade, 
                 u.nome as nome_usuario, c.id_tecnico_responsavel 
          FROM chamados c 
          LEFT JOIN unidades_escolares ue ON c.id_unidade_escolar = ue.id 
          JOIN usuarios u ON c.id_usuario_abertura = u.id 
          $whereClause 
          ORDER BY c.data_abertura DESC";

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

    // Total para estatísticas
    $totalQuery = "SELECT COUNT(*) as total FROM chamados c $whereClause";
    $stmt = $conn->prepare($totalQuery);
    if ($stmt) {
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $totalChamados = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
    }
} else {
    $mensagemErro = "Erro ao preparar consulta de chamados: " . $conn->error;
}

// Consulta para chamados abertos pelo usuário para outros setores
$queryOutrosSetores = "SELECT c.id, c.tipo_manutencao, c.descricao, c.status, c.data_abertura, 
                              COALESCE(ue.nome_unidade, 'Secretaria de Educação') as nome_unidade, 
                              u.nome as nome_usuario 
                       FROM chamados c 
                       LEFT JOIN unidades_escolares ue ON c.id_unidade_escolar = ue.id 
                       JOIN usuarios u ON c.id_usuario_abertura = u.id 
                       WHERE c.id_usuario_abertura = ? AND c.tipo_manutencao != 'geral' 
                       ORDER BY c.data_abertura DESC";

$stmt = $conn->prepare($queryOutrosSetores);
if ($stmt) {
    $stmt->bind_param("i", $id_tecnico);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $dadosChamadosOutrosSetores[] = $row;
    }
    $stmt->close();
} else {
    $mensagemErro = "Erro ao preparar consulta de chamados para outros setores: " . $conn->error;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Manutenção Geral</title>
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
                        <a class="nav-link" href="#"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['user']['nome'] ?? 'Técnico', ENT_QUOTES, 'UTF-8') ?></a>
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
                        <h2 class="mb-0"><i class="bi bi-tools"></i> Dashboard Manutenção Geral - Todos os Chamados</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($mensagemSucesso): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($mensagemSucesso, ENT_QUOTES, 'UTF-8') ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <?php if ($mensagemErro): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8') ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <!--<div class="mb-4">
                            <a href="novo_chamado.php" class="btn btn-success"><i class="bi bi-plus-circle"></i> Abrir Novo Chamado</a>
                        </div>-->

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
                            <div class="col-md-6">
                                <canvas id="pieChart" height="200"></canvas>
                            </div>
                            <div class="col-md-6">
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
                                                <td>#<?= htmlspecialchars($chamado['id'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($chamado['nome_unidade'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars(getNomeTipoManutencao($chamado['tipo_manutencao']), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars(substr($chamado['descricao'], 0, 50), ENT_QUOTES, 'UTF-8') . (strlen($chamado['descricao']) > 50 ? '...' : '') ?></td>
                                                <td><?= htmlspecialchars($chamado['nome_usuario'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td>
                                                    <span class="badge 
                                                        <?= $chamado['status'] === 'aberto' ? 'bg-warning' : 
                                                            ($chamado['status'] === 'em_andamento' ? 'bg-info' : 
                                                            ($chamado['status'] === 'concluido' ? 'bg-success' : 'bg-danger')) ?>">
                                                        <?= ucfirst(str_replace('_', ' ', htmlspecialchars($chamado['status'], ENT_QUOTES, 'UTF-8'))) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('d/m/Y H:i', strtotime($chamado['data_abertura'])) ?></td>
                                                <td>
                                                    <a href="ver_chamado.php?id=<?= $chamado['id'] ?>" class="btn btn-primary btn-sm">
                                                        <i class="bi bi-eye"></i> Ver
                                                    </a>
                                                    <?php if ($chamado['id_tecnico_responsavel'] == $id_tecnico || !$chamado['id_tecnico_responsavel']): ?>
                                                        <a href="gerenciar_chamados.php?id=<?= $chamado['id'] ?>" class="btn btn-warning btn-sm">
                                                            <i class="bi bi-gear"></i> Gerenciar
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Nenhum chamado de manutenção geral encontrado.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!--<div class="table-responsive mt-4">
                            <h3>Chamados Abertos para Outros Setores</h3>
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
                                    <?php if (!empty($dadosChamadosOutrosSetores)): ?>
                                        <?php foreach ($dadosChamadosOutrosSetores as $chamado): ?>
                                            <tr>
                                                <td>#<?= htmlspecialchars($chamado['id'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($chamado['nome_unidade'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars(getNomeTipoManutencao($chamado['tipo_manutencao']), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars(substr($chamado['descricao'], 0, 50), ENT_QUOTES, 'UTF-8') . (strlen($chamado['descricao']) > 50 ? '...' : '') ?></td>
                                                <td><?= htmlspecialchars($chamado['nome_usuario'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td>
                                                    <span class="badge 
                                                        <?= $chamado['status'] === 'aberto' ? 'bg-warning' : 
                                                            ($chamado['status'] === 'em_andamento' ? 'bg-info' : 
                                                            ($chamado['status'] === 'concluido' ? 'bg-success' : 'bg-danger')) ?>">
                                                        <?= ucfirst(str_replace('_', ' ', htmlspecialchars($chamado['status'], ENT_QUOTES, 'UTF-8'))) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('d/m/Y H:i', strtotime($chamado['data_abertura'])) ?></td>
                                                <td>
                                                    <a href="ver_chamado.php?id=<?= $chamado['id'] ?>" class="btn btn-primary btn-sm">
                                                        <i class="bi bi-eye"></i> Ver
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Nenhum chamado aberto para outros setores encontrado.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>-->

                        <div class="card shadow-sm mt-4">
                            <div class="card-header bg-secondary text-white">
                                <h3 class="mb-0"><i class="bi bi-bar-chart"></i> Relatórios Rápidos</h3>
                            </div>
                            <div class="card-body">
                                <p>Total de chamados nos últimos 30 dias: 
                                    <?php
                                    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM chamados WHERE tipo_manutencao = 'geral' AND data_abertura >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                                    $stmt->execute();
                                    echo $stmt->get_result()->fetch_assoc()['total'];
                                    $stmt->close();
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
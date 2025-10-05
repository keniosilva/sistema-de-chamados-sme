<?php
session_start();
require_once 'connection.php';
require_once 'helpers.php';
verificarPermissao(['casa_da_merenda']);

$_SESSION['tipo_usuario'] = $_SESSION['user']['tipo_usuario'];
$_SESSION['usuario_id'] = $_SESSION['user']['id'];

$mensagemErro = $_GET['erro'] ?? '';
$mensagemSucesso = $_GET['sucesso'] ?? '';
$dadosChamados = [];
$totalChamados = 0;
$stats = ['aberto' => 0, 'em_andamento' => 0, 'concluido' => 0, 'cancelado' => 0];

// Consulta de estatísticas por status
$stmt = $conn->prepare("SELECT status, COUNT(*) as total FROM chamados WHERE tipo_manutencao = 'casa_da_merenda' GROUP BY status");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $stats[$row['status']] = $row['total'];
    }
    $stmt->close();
}

// Consulta de estatísticas por tipo (apenas um tipo aqui)
$statsType = ['casa_da_merenda' => array_sum($stats)];

// Dados para gráfico de linha (chamados por dia nos últimos 30 dias)
$lineData = ['labels' => [], 'data' => []];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $lineData['labels'][] = date('d/m', strtotime($date));
    $lineData['data'][$date] = 0;
}
$stmt = $conn->prepare("SELECT DATE(data_abertura) as dia, COUNT(*) as total FROM chamados WHERE tipo_manutencao = 'casa_da_merenda' AND data_abertura >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY dia");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $lineData['data'][$row['dia']] = $row['total'];
    }
    $stmt->close();
}
$lineData['data'] = array_values($lineData['data']);

// Filtro por status (opcional)
$filtroStatus = $_GET['status'] ?? 'todos';

// Paginação básica
$pagina = (int)($_GET['pagina'] ?? 1);
$limite = 10;
$offset = ($pagina - 1) * $limite;

// Consulta principal com JOINs, filtro e paginação
$whereClause = $filtroStatus === 'todos' ? 'WHERE c.tipo_manutencao = ?' : 'WHERE c.tipo_manutencao = ? AND c.status = ?';
$query = "SELECT c.id, c.tipo_manutencao, c.descricao, c.status, c.data_abertura, 
                 ue.nome_unidade, u.nome as nome_usuario, c.id_tecnico_responsavel, c.merenda_confirmacao_entrega
          FROM chamados c 
          JOIN unidades_escolares ue ON c.id_unidade_escolar = ue.id 
          JOIN usuarios u ON c.id_usuario_abertura = u.id 
          $whereClause 
          ORDER BY c.data_abertura DESC 
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if ($stmt) {
    if ($filtroStatus === 'todos') {
        $stmt->bind_param('sii', $tipo_manutencao, $limite, $offset);
        $tipo_manutencao = 'casa_da_merenda';
    } else {
        $stmt->bind_param('ssii', $tipo_manutencao, $status, $limite, $offset);
        $tipo_manutencao = 'casa_da_merenda';
        $status = $filtroStatus;
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $dadosChamados[] = $row;
    }
    $stmt->close();

    // Total para paginação
    $queryTotal = "SELECT COUNT(*) as total FROM chamados c WHERE c.tipo_manutencao = ?" . ($filtroStatus === 'todos' ? '' : ' AND c.status = ?');
    $stmt = $conn->prepare($queryTotal);
    if ($stmt) {
        if ($filtroStatus === 'todos') {
            $stmt->bind_param('s', $tipo_manutencao);
            $tipo_manutencao = 'casa_da_merenda';
        } else {
            $stmt->bind_param('ss', $tipo_manutencao, $status);
            $tipo_manutencao = 'casa_da_merenda';
            $status = $filtroStatus;
        }
        $stmt->execute();
        $totalChamados = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
    }
} else {
    $mensagemErro = "Erro ao preparar consulta de chamados: " . $conn->error;
}
$totalPaginas = ceil($totalChamados / $limite);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Casa da Merenda</title>
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
                    <div class="card-header bg-success text-white">
                        <h2 class="mb-0"><i class="bi bi-tools"></i> Dashboard - Casa da Merenda</h2>
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
                                    </select>
                                </div>
                            </form>
                        </div>

                        <!-- Tabela de Chamados -->
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle">
                                <thead class="table-success">
                                    <tr>
                                        <th scope="col">ID</th>
                                        <th scope="col">Unidade</th>
                                        <th scope="col">Usuário</th>
                                        <th scope="col">Tipo</th>
                                        <th scope="col">Descrição</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Data Abertura</th>
                                        <th scope="col">Entrega Merenda</th>
                                        <th scope="col">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($dadosChamados)): ?>
                                        <?php foreach ($dadosChamados as $chamado): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($chamado['id']) ?></td>
                                                <td><?= htmlspecialchars($chamado['nome_unidade']) ?></td>
                                                <td><?= htmlspecialchars($chamado['nome_usuario']) ?></td>
                                                <td><?= ucfirst(htmlspecialchars($chamado['tipo_manutencao'])) ?></td>
                                                <td><?= htmlspecialchars(substr($chamado['descricao'], 0, 50)) . (strlen($chamado['descricao']) > 50 ? '...' : '') ?></td>
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
                                                    <?php if ($chamado['status'] == 'concluido' || $chamado['status'] == 'aguardando_recebimento'): ?>
                                                        <?php if (($chamado['merenda_confirmacao_entrega'] ?? 0) == 1): ?>
                                                            <span class="badge bg-success">Entrega Confirmada</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">Aguardando Confirmação</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="ver_chamado.php?id=<?= $chamado["id"] ?>" class="btn btn-primary btn-sm">
                                                        <i class="bi bi-eye"></i> Ver
                                                    </a>
                                                    <?php if ($chamado["status"] == "em_andamento" && empty($chamado["merenda_confirmacao_entrega"])): ?>
                                                        <a href="gerar_oficio_entrega_merenda.php?id=<?= $chamado["id"] ?>" class="btn btn-info btn-sm">
                                                            <i class="bi bi-file-earmark-text"></i> Gerar Ofício
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($chamado["id_tecnico_responsavel"] == $_SESSION["user"]["id"] || !$chamado["id_tecnico_responsavel"]): ?>
                                                        <a href="gerenciar_chamados.php?id=<?= $chamado["id"] ?>" class="btn btn-warning btn-sm">
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
                        <?php if ($totalPaginas > 1): ?>
                            <nav aria-label="Paginação">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                        <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                                            <a class="page-link" href="?status=<?= urlencode($filtroStatus) ?>&pagina=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>

                        <!-- Seção de Relatórios -->
                        <div class="card shadow-sm mt-4">
                            <div class="card-header bg-secondary text-white">
                                <h3 class="mb-0"><i class="bi bi-bar-chart"></i> Relatórios Rápidos</h3>
                            </div>
                            <div class="card-body">
                                <p>Total de chamados nos últimos 30 dias: 
                                    <?php
                                    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM chamados WHERE tipo_manutencao = 'casa_da_merenda' AND data_abertura >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
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

        // Gráfico de Barras (Distribuição por Tipo)
        new Chart(document.getElementById('barChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Casa da Merenda'],
                datasets: [{
                    label: 'Chamados por Tipo',
                    data: [<?= $statsType['casa_da_merenda'] ?? 0 ?>],
                    backgroundColor: ['#198754']
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
                    borderColor: '#198754',
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
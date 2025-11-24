<?php
session_start();
require_once 'connection.php';
require_once 'auth.php';
require_once 'helpers.php';

// Acesso restrito a administradores ou usuários com permissão de relatório
// Assumindo que apenas 'admin' tem acesso por enquanto, seguindo o admin_dashboard.php
restrictAccess(['admin']);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$mensagemErro = $_GET['erro'] ?? '';
$mensagemSucesso = $_GET['sucesso'] ?? '';

// --- Lógica de Setor e Filtros ---

// Define o setor de destino padrão. O usuário pediu para ser "de acordo com o setor que for aberto o arquivo".
// Como este é um arquivo de relatório geral, vamos permitir que o admin filtre por setor, ou se for um usuário de setor,
// o setor seja pré-selecionado. Para o admin, vamos listar todos.
$setoresDisponiveis = [
    'todos' => 'Todos os Setores',
    'manutencao_geral' => 'Manutenção Geral',
    'informatica' => 'Informática',
    'casa_da_merenda' => 'Casa da Merenda',
    'almoxarifado' => 'Almoxarifado'
];

// Se o usuário logado tiver um setor específico (ex: 'informatica'), ele será o setor padrão.
// Caso contrário, o padrão é 'todos'.
$setorPadrao = $_SESSION['user']['setor'] ?? 'todos';
$filtroSetor = $_GET['setor'] ?? $setorPadrao;

// Filtros
$filtroUnidade = $_GET['unidade'] ?? 'todos';
// $filtroTipo = $_GET['tipo'] ?? 'todos'; // Removido a pedido do usuário
$filtroMes = $_GET['mes'] ?? date('Y-m'); // Formato YYYY-MM

// Obter lista de unidades escolares para o filtro
$unidades = [];
$stmt = $conn->prepare("SELECT id, nome_unidade FROM unidades_escolares ORDER BY nome_unidade");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $unidades[$row['id']] = $row['nome_unidade'];
    }
    $stmt->close();
}

// Obter lista de meses para o filtro (últimos 12 meses)
$mesesDisponiveis = [];
for ($i = 0; $i < 12; $i++) {
    $timestamp = strtotime("-$i months");
    $meses_pt = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$mes_num = (int)date('m', $timestamp);
$mesesDisponiveis[date('Y-m', $timestamp)] = $meses_pt[$mes_num - 1] . '/' . date('Y', $timestamp);
}

// --- Construção da Consulta SQL ---

$conditions = [];
$params = [];
$types = '';

// 1. Filtro por Setor de Destino
if ($filtroSetor !== 'todos' && isset($setoresDisponiveis[$filtroSetor])) {
    $conditions[] = "c.setor_destino = ?";
    $params[] = $filtroSetor;
    $types .= 's';
}

// 2. Filtro por Unidade Escolar
if ($filtroUnidade !== 'todos' && is_numeric($filtroUnidade)) {
    $conditions[] = "c.id_unidade_escolar = ?";
    $params[] = (int)$filtroUnidade;
    $types .= 'i';
}

// Filtro por Tipo de Manutenção removido a pedido do usuário

// 4. Filtro por Mês (data_abertura)
if (!empty($filtroMes)) {
    $ano = substr($filtroMes, 0, 4);
    $mes = substr($filtroMes, 5, 2);
    $conditions[] = "YEAR(c.data_abertura) = ? AND MONTH(c.data_abertura) = ?";
    $params[] = (int)$ano;
    $params[] = (int)$mes;
    $types .= 'ii';
}

$whereClause = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);

// --- Consultas de Dados ---

// Consulta principal para a tabela de chamados filtrados
$dadosChamados = [];
$query = "SELECT c.id, c.tipo_manutencao, c.descricao, c.status, c.data_abertura, 
                 ue.nome_unidade, u.nome as nome_usuario, c.setor_destino 
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
} else {
    $mensagemErro = "Erro ao preparar consulta de chamados: " . $conn->error;
    error_log("Erro na consulta principal: $query, Erro: " . $conn->error);
}

$totalChamados = count($dadosChamados);

// Estatísticas de Status para o gráfico (apenas dos chamados filtrados)
$statsStatus = ['aberto' => 0, 'em_andamento' => 0, 'concluido' => 0, 'cancelado' => 0];
foreach ($dadosChamados as $chamado) {
    if (isset($statsStatus[$chamado['status']])) {
        $statsStatus[$chamado['status']]++;
    }
}

// Estatísticas de Tipo para o gráfico (apenas dos chamados filtrados)
$statsType = ['geral' => 0, 'informatica' => 0, 'casa_da_merenda' => 0, 'almoxarifado' => 0];
foreach ($dadosChamados as $chamado) {
    if (isset($statsType[$chamado['tipo_manutencao']])) {
        $statsType[$chamado['tipo_manutencao']]++;
    }
}

// --- HTML de Apresentação (Estilo admin_dashboard.php) ---
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Sistema de Chamados</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Estilo para destacar o card de total */
        .card-total {
            background-color: #f8f9fa;
            border-left: 5px solid #0d6efd;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="admin_dashboard.php">Sistema de Chamados</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="relatorios.php"><i class="bi bi-bar-chart-line"></i> Relatórios</a>
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
            <div class="col-12 col-lg-11">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h2 class="mb-0"><i class="bi bi-bar-chart-line"></i> Relatórios Detalhados de Chamados</h2>
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

                        <!-- Formulário de Filtros -->
                        <div class="mb-4 p-3 border rounded bg-light">
                            <form method="GET" class="row g-3 align-items-end">
                                
                                <!-- Filtro por Setor -->
                                <div class="col-md-3">
                                    <label for="setor" class="form-label">Setor de Destino:</label>
                                    <select name="setor" id="setor" class="form-select">
                                        <?php foreach ($setoresDisponiveis as $key => $value): ?>
                                            <option value="<?= $key ?>" <?= $filtroSetor === $key ? 'selected' : '' ?>>
                                                <?= $value ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Filtro por Unidade -->
                                <div class="col-md-3">
                                    <label for="unidade" class="form-label">Unidade Escolar:</label>
                                    <select name="unidade" id="unidade" class="form-select">
                                        <option value="todos" <?= $filtroUnidade === 'todos' ? 'selected' : '' ?>>Todas as Unidades</option>
                                        <?php foreach ($unidades as $id => $nome): ?>
                                            <option value="<?= $id ?>" <?= (string)$filtroUnidade === (string)$id ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($nome) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Filtro por Mês -->
                                <div class="col-md-3">
                                    <label for="mes" class="form-label">Mês de Abertura:</label>
                                    <select name="mes" id="mes" class="form-select">
                                        <option value="" <?= empty($filtroMes) ? 'selected' : '' ?>>Todos os Meses</option>
                                        <?php foreach ($mesesDisponiveis as $key => $value): ?>
                                            <option value="<?= $key ?>" <?= $filtroMes === $key ? 'selected' : '' ?>>
                                                <?= $value ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                

                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Aplicar Filtros</button>
                                    <a href="relatorios.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i> Limpar Filtros</a>
                                    <a href="relatorio_pdf.php?<?= http_build_query($_GET) ?>" target="_blank" class="btn btn-danger"><i class="bi bi-file-pdf"></i> Exportar para PDF</a>
                                </div>
                            </form>
                        </div>

                        <!-- Estatísticas e Gráficos -->
                        <div class="row row-cols-1 row-cols-md-2 g-4 mb-4">
                            <div class="col-md-4">
                                <div class="card shadow-sm text-center card-total">
                                    <div class="card-body">
                                        <div class="h1 mb-0 text-primary"><?= $totalChamados ?></div>
                                        <div class="text-muted">Total de Chamados Filtrados</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card shadow-sm">
                                    <div class="card-header text-center bg-info text-white">
                                        <h5 class="mb-0">Distribuição por Status</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="statusChart" height="150"></canvas>
                                    </div>
                                </div>
                            </div>
                            
                        </div>

                        <!-- Tabela de Chamados Filtrados -->
                        <h4 class="mt-4 mb-3">Detalhes dos Chamados (<?= $totalChamados ?> encontrados)</h4>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle">
                                <thead class="table-primary">
                                    <tr>
                                        <th scope="col">ID</th>
                                        <th scope="col">Unidade</th>
                                        <th scope="col">Tipo</th>
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
                                                    <a href="ver_chamado.php?id=<?= $chamado['id'] ?>" class="btn btn-primary btn-sm" title="Ver Detalhes">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">Nenhum chamado encontrado com os filtros aplicados.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gráfico de Pizza (Distribuição por Status)
        new Chart(document.getElementById('statusChart').getContext('2d'), {
            type: 'pie',
            data: {
                labels: ['Aberto', 'Em Andamento', 'Concluído', 'Cancelado'],
                datasets: [{
                    data: [<?= $statsStatus['aberto'] ?? 0 ?>, <?= $statsStatus['em_andamento'] ?? 0 ?>, <?= $statsStatus['concluido'] ?? 0 ?>, <?= $statsStatus['cancelado'] ?? 0 ?>],
                    backgroundColor: ['#ffc107', '#0dcaf0', '#198754', '#dc3545']
                }]
            },
            options: { 
                responsive: true,
                plugins: { legend: { position: 'bottom' }, tooltip: { enabled: true } }
            }
        });

        
    </script>
</body>
</html>

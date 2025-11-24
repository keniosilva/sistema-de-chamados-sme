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
}

// --- Contar Total de Chamados para Paginação ---
$totalChamados = 0;
$stmtTotal = $conn->prepare("SELECT COUNT(*) as total FROM chamados WHERE id_unidade_escolar = ?");
if ($stmtTotal) {
    $stmtTotal->bind_param("i", $_SESSION['user']['id_unidade_escolar']);
    $stmtTotal->execute();
    $totalChamados = $stmtTotal->get_result()->fetch_assoc()['total'];
    $stmtTotal->close();
}
$totalPaginas = ceil($totalChamados / $limite);

// --- Buscar Chamados DA PÁGINA ATUAL ---
$chamados = [];
$stmt = $conn->prepare("
    SELECT c.*, u.nome as nome_tecnico, 
           c.almoxarifado_confirmacao_entrega, c.merenda_confirmacao_entrega, 
           c.confirmacao_recebimento_unidade, c.setor_destino,
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
}

// --- DETECTAR CHAMADOS COM ENTREGA PENDENTE DE CONFIRMAÇÃO ---
$temPendenciaRecebimento = false;
$chamadosPendentes = [];

foreach ($chamados as $chamado) {
    $is_almoxarifado = $chamado['setor_destino'] == 'almoxarifado';
    $is_merenda = $chamado['setor_destino'] == 'casa_da_merenda';
    $confirmacao_unidade = intval($chamado['confirmacao_recebimento_unidade'] ?? 0);

    if ($chamado['status'] === 'aguardando_recebimento' && $confirmacao_unidade == 0) {
        if (($is_almoxarifado && intval($chamado['almoxarifado_confirmacao_entrega'] ?? 0) == 1) ||
            ($is_merenda && intval($chamado['merenda_confirmacao_entrega'] ?? 0) == 1)) {
            $temPendenciaRecebimento = true;
            $chamadosPendentes[] = [
                'id' => $chamado['id'],
                'tipo' => getNomeTipoManutencao($chamado['tipo_manutencao']),
                'setor' => $is_almoxarifado ? 'Almoxarifado' : 'Casa da Merenda'
            ];
        }
    }
}

// Contar chamados concluídos (para o card)
$total_concluidos = 0;
$stmt_concluidos = $conn->prepare("SELECT COUNT(*) as total FROM chamados WHERE id_unidade_escolar = ? AND status = 'concluido'");
if ($stmt_concluidos) {
    $stmt_concluidos->bind_param("i", $_SESSION['user']['id_unidade_escolar']);
    $stmt_concluidos->execute();
    $total_concluidos = $stmt_concluidos->get_result()->fetch_assoc()['total'];
    $stmt_concluidos->close();
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
            --title-color: #00695C;
            --primary-color: #0288D1;
            --accent-color: #FF6B6B;
            --background-color: #F5F7FA;
            --card-bg: #FFFFFF;
            --table-header-bg: #37474F;
            --text-color: #263238;
        }
        body { background-color: var(--background-color); font-family: 'Roboto', Arial, sans-serif; color: var(--text-color); }
        .main-content { padding: 30px; }
        h1 { color: var(--title-color); font-weight: 700; font-size: 2.5rem; margin-bottom: 10px; }
        .lead { color: #455A64; font-size: 1.2rem; margin-bottom: 20px; }
        .card-stats { background: linear-gradient(135deg, var(--card-bg), #ECEFF1); border: none; border-radius: 12px; box-shadow: 0 6px 12px rgba(0,0,0,0.1); margin-bottom: 25px; text-align: center; transition: all 0.2s ease; }
        .card-stats:hover { transform: translateY(-5px); box-shadow: 0 8px 16px rgba(0,0,0,0.15); }
        .card-stats p { font-size: 2.2rem; font-weight: 700; color: var(--primary-color); }
        .card.bg-primary { background: linear-gradient(135deg, var(--primary-color), #0277BD) !important; color: #FFF; }
        .card.bg-warning { background: linear-gradient(135deg, var(--accent-color), #FF8A80) !important; color: #FFF; }
        .card.bg-success { background: linear-gradient(135deg, #2E7D32, #4CAF50) !important; color: #FFF; }
        .table { background-color: var(--card-bg); border-radius: 12px; overflow: hidden; box-shadow: 0 6px 12px rgba(0,0,0,0.1); }
        .table thead { background-color: var(--table-header-bg); color: #FFF; }
        .status-aberto, .status-em_andamento, .status-concluido, .status-cancelado, .status-aguardando_recebimento,
        .badge-entrega-pendente, .badge-entrega-confirmada, .tipo-badge { padding: 0.3em 0.7em; border-radius: 5px; font-size: 0.9rem; }
        .status-aberto, .status-aguardando_recebimento { background-color: var(--primary-color); color: #FFF; }
        .status-em_andamento, .badge-entrega-pendente { background-color: var(--accent-color); color: #FFF; }
        .status-concluido, .badge-entrega-confirmada { background-color: #2E7D32; color: #FFF; }
        .status-cancelado { background-color: #D32F2F; color: #FFF; }
        .btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); }
        .btn-primary:hover:not(.disabled) { background-color: #0277BD; border-color: #0277BD; }
        .btn-primary.disabled { opacity: 0.65; cursor: not-allowed; pointer-events: none; }
        hr { border-top: 2px solid rgba(2, 136, 209, 0.2); margin: 20px 0; }
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
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($_GET['erro'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_GET['erro']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white card-stats">
                            <div class="card-body">
                                <h5><i class="bi bi-journal-plus"></i> Abrir Novo Chamado</h5>
                                <?php if (!$temPendenciaRecebimento): ?>
                                    <p><a href="novo_chamado.php" class="btn btn-lg btn-light text-primary mt-2">NOVO</a></p>
                                <?php else: ?>
                                    <p>
                                        <button class="btn btn-lg btn-light text-primary mt-2 disabled" disabled>
                                            NOVO
                                        </button>
                                    </p>
                                    <small class="text-white opacity-75">
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                        <?= count($chamadosPendentes) ?> entrega(s) pendente(s) de confirmação
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-warning text-white card-stats">
                            <div class="card-body">
                                <h5><i class="bi bi-clock-history"></i> Aguardando Confirmação</h5>
                                <p><?php echo count($chamadosPendentes); ?></p>
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

                <!-- Mensagem de bloqueio abaixo dos cards -->
                <?php if ($temPendenciaRecebimento): ?>
                    <div class="alert alert-warning border border-danger mb-4">
                        <h5><i class="bi bi-exclamation-triangle-fill text-danger"></i> Atenção: Novo chamado bloqueado</h5>
                        <p class="mb-0">
                            Você possui <strong><?= count($chamadosPendentes) ?> entrega(s)</strong> de material (Almoxarifado ou Merenda) já realizada(s), mas <strong>ainda não confirmada(s)</strong>.
                            <br><strong>Confirme o recebimento na tabela abaixo para liberar a abertura de novos chamados.</strong>
                        </p>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h2 class="mb-0"><i class="bi bi-list-task"></i> Meus Chamados Recentes (Página <?= $pagina ?> de <?= $totalPaginas ?>)</h2>
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
                                                <td><?= $chamado['id'] ?></td>
                                                <td><span class="tipo-badge"><?= getNomeTipoManutencao($chamado['tipo_manutencao']) ?></span></td>
                                                <td><?= getNomeSetorDestino($chamado['setor_destino']) ?></td>
                                                <td><?= htmlspecialchars(substr($chamado['descricao'], 0, 50)) . (strlen($chamado['descricao']) > 50 ? '...' : '') ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($chamado['data_abertura'])) ?></td>
                                                <td><span class="status status-<?= $chamado['status'] ?>"><?= ucfirst(str_replace('_', ' ', $chamado['status'])) ?></span></td>
                                                <td><?= htmlspecialchars($chamado['nome_tecnico'] ?? 'Não atribuído') ?></td>
                                                <td>
                                                    <?php
                                                    $is_almoxarifado = $chamado['setor_destino'] == 'almoxarifado';
                                                    $is_merenda = $chamado['setor_destino'] == 'casa_da_merenda';
                                                    $confirmacao_almoxarifado = intval($chamado['almoxarifado_confirmacao_entrega'] ?? 0);
                                                    $confirmacao_merenda = intval($chamado['merenda_confirmacao_entrega'] ?? 0);
                                                    $confirmacao_unidade = intval($chamado['confirmacao_recebimento_unidade'] ?? 0);

                                                    if ($chamado["status"] == "aguardando_recebimento" && $confirmacao_unidade == 0) {
                                                        $setor_confirmou = false;
                                                        $setor_label = '';
                                                        $tipo_param = '';

                                                        if ($is_almoxarifado && $confirmacao_almoxarifado == 1) {
                                                            $setor_confirmou = true;
                                                            $setor_label = '(Almox.)';
                                                            $tipo_param = 'almoxarifado';
                                                        } elseif ($is_merenda && $confirmacao_merenda == 1) {
                                                            $setor_confirmou = true;
                                                            $setor_label = '(Merenda)';
                                                            $tipo_param = 'merenda';
                                                        }

                                                        if ($setor_confirmou) {
                                                            echo '<span class="badge badge-entrega-pendente">Aguardando Recebimento ' . $setor_label . '</span>';
                                                            echo '<br><a href="confirmar_recebimento_unidade.php?id=' . $chamado['id'] . '&tipo=' . $tipo_param . '" class="btn btn-sm btn-outline-success mt-2">Confirmar Recebimento</a>';
                                                        } else {
                                                            echo '<span class="badge bg-info text-white">Aguardando Setor</span>';
                                                        }
                                                    } elseif ($chamado["status"] == "concluido" && $confirmacao_unidade == 1) {
                                                        $setor_label = $is_almoxarifado ? '(Almox.)' : ($is_merenda ? '(Merenda)' : '');
                                                        echo '<span class="badge badge-entrega-confirmada">Recebido ' . $setor_label . '</span>';
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <a href="ver_chamado.php?id=<?= $chamado['id'] ?>" class="btn btn-sm btn-primary mb-1">
                                                        <i class="bi bi-search"></i> Ver
                                                    </a>
                                                    <?php if (!empty($chamado['numero_oficio']) && in_array($chamado['status'], ['aguardando_recebimento', 'concluido'])): ?>
                                                        <a href="gerar_pdf_oficio.php?id=<?= $chamado['id'] ?>&tipo=<?= $chamado['tipo_oficio'] ?>" 
                                                           class="btn btn-sm btn-info mt-1" target="_blank">
                                                            <i class="bi bi-file-earmark-pdf"></i> Ofício
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">
                                                Nenhum chamado encontrado.
                                                <?php if (!$temPendenciaRecebimento): ?>
                                                    <a href="novo_chamado.php" class="btn btn-link">Abrir o primeiro chamado</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPaginas > 1): ?>
                            <nav aria-label="Navegação de páginas">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?pagina=<?= $pagina - 1 ?>">Anterior</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                        <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                                            <a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?pagina=<?= $pagina + 1 ?>">Próxima</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Alerta - Só aparece se houver pendências -->
    <?php if ($temPendenciaRecebimento): ?>
    <div class="modal fade" id="modalPendenciaUnidade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle-fill"></i> Atenção: Entregas Pendentes!
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Você possui <strong><?= count($chamadosPendentes) ?> material(is)</strong> entregue(s) pelo setor, mas <strong>o recebimento ainda não foi confirmado</strong>.</p>
                    
                    <div class="alert alert-warning">
                        <strong>Chamados pendentes de confirmação:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($chamadosPendentes as $p): ?>
                                <li>Chamado #<?= $p['id'] ?> - <?= $p['tipo'] ?> (<?= $p['setor'] ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <p class="mb-0 text-danger fw-bold">
                        <i class="bi bi-lock-fill"></i> A abertura de novos chamados está bloqueada até a confirmação.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modal = new bootstrap.Modal(document.getElementById('modalPendenciaUnidade'), {
                backdrop: 'static',
                keyboard: false
            });
            modal.show();
        });
    </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                if (alert) new bootstrap.Alert(alert).close();
            });
        }, 6000);
    </script>
</body>
</html>
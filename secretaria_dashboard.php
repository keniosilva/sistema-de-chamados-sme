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

$pagina = (int)($_GET['pagina'] ?? 1);
$limite = 10;
$offset = ($pagina - 1) * $limite;

$filtroStatus = $_GET['status'] ?? 'todos';

$whereClause = 'WHERE c.id_usuario_abertura = ?';
$paramTypes = 'i';
$bindValues = [$_SESSION['user']['id']];

if ($filtroStatus !== 'todos') {
    $whereClause .= ' AND c.status = ?';
    $paramTypes .= 's';
    $bindValues[] = $filtroStatus;
}

// Contar total para paginação
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

// Consulta principal
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

// === DETECÇÃO DE PENDÊNCIAS DE CONFIRMAÇÃO DE RECEBIMENTO ===
$temPendenciaRecebimento = false;
$chamadosPendentes = [];

foreach ($todos_chamados as $chamado) {
    $is_almoxarifado = $chamado['setor_destino'] == 'almoxarifado';
    $is_merenda = $chamado['setor_destino'] == 'casa_da_merenda';
    $confirmacao_unidade = intval($chamado['confirmacao_recebimento_unidade'] ?? 0);

    if ($chamado['status'] === 'aguardando_recebimento' && $confirmacao_unidade == 0) {
        if (($is_almoxarifado && intval($chamado['almoxarifado_confirmacao_entrega'] ?? 0) == 1) ||
            ($is_merenda && intval($chamado['merenda_confirmacao_entrega'] ?? 0) == 1)) {
            $temPendenciaRecebimento = true;
            $chamadosPendentes[] = [
                'id' => $chamado['id'],
                'origem' => $chamado['origem'],
                'setor' => $is_almoxarifado ? 'Almoxarifado' : 'Casa da Merenda'
            ];
        }
    }
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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap">
    <link rel="stylesheet" href="css/style.css">
    <style>
        :root {
            --title-color: #2C3E50;
            --primary-color: #34495E;
            --accent-color: #FF8A80;
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
        .card-stats h5 { font-size: 1.3rem; color: var(--text-color); margin-bottom: 15px; font-weight: 500; }
        .card-stats p { font-size: 2.2rem; font-weight: 700; color: var(--primary-color); }
        .card.bg-info { background: linear-gradient(135deg, #0288D1, #0277BD) !important; color: #FFF; }
        .card.bg-primary { background: linear-gradient(135deg, var(--primary-color), #4B6A88) !important; color: #FFF; }
        .card.bg-warning { background: linear-gradient(135deg, var(--accent-color), #FFAB91) !important; color: #FFF; }
        .card.bg-success { background: linear-gradient(135deg, #2E7D32, #4CAF50) !important; color: #FFF; }
        .table { background-color: var(--card-bg); border-radius: 12px; overflow: hidden; box-shadow: 0 6px 12px rgba(0,0,0,0.1); }
        .table thead { background-color: var(--table-header-bg); color: #FFF; }
        .table-hover tbody tr:hover { background-color: rgba(52, 73, 94, 0.05); }
        .status-aberto, .status-em_andamento, .status-concluido, .status-cancelado, .status-aguardando_recebimento,
        .setor-badge, .badge-entrega-pendente, .badge-entrega-confirmada, .tipo-badge { padding: 0.3em 0.7em; border-radius: 5px; font-size: 0.9rem; }
        .status-aberto { background-color: var(--primary-color); color: #FFF; }
        .status-em_andamento { background-color: var(--accent-color); color: #FFF; }
        .status-concluido, .badge-entrega-confirmada { background-color: #2E7D32; color: #FFF; }
        .status-cancelado { background-color: #D32F2F; color: #FFF; }
        .status-aguardando_recebimento, .setor-badge[data-setor='manutencao_geral'] { background-color: #2C3E50; color: #FFF; }
        .setor-badge[data-setor='informatica'] { background-color: #F57C00; }
        .setor-badge[data-setor='almoxarifado'] { background-color: #26A69A; }
        .setor-badge[data-setor='casa_da_merenda'] { background-color: #78909C; }
        .badge-entrega-pendente { background-color: var(--accent-color); color: #FFF; }
        .btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); }
        .btn-primary:hover:not(.disabled) { background-color: #4B6A88; border-color: #4B6A88; }
        .btn-primary.disabled { opacity: 0.65; cursor: not-allowed; }
        .alert { border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        hr { border-top: 2px solid rgba(52, 73, 94, 0.2); margin: 20px 0; }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <div id="page-content-wrapper" class="main-content flex-grow-1">
            <div class="container-fluid">
                <h1 class="mt-4">Dashboard - Secretaria</h1>
                <p class="lead">Bem-vindo(a), <?php echo htmlspecialchars($_SESSION['user']['nome']); ?></p>
                <a href="logout.php" class="btn btn-danger float-end mb-3"><i class="bi bi-box-arrow-right"></i> Sair</a>
                <hr>

                <?php if (!empty($mensagemSucesso)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($mensagemSucesso); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($mensagemErro)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($mensagemErro); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
                        <div class="card bg-warning text-white card-stats">
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

                <!-- Botão Abrir Novo Chamado - BLOQUEADO se houver pendência -->
                <?php if (!$temPendenciaRecebimento): ?>
                    <a href="novo_chamado.php" class="btn btn-primary mb-4">
                        <i class="bi bi-plus-circle"></i> Abrir Novo Chamado
                    </a>
                <?php else: ?>
                    <button class="btn btn-primary mb-4 disabled" title="Existem entregas pendentes de confirmação" disabled>
                        <i class="bi bi-plus-circle"></i> Abrir Novo Chamado
                    </button>
                    <div class="text-danger ms-2">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <strong>Você possui <?= count($chamadosPendentes) ?> entrega(s) pendente(s) de confirmação.</strong><br>
                        <small>Confirme o recebimento na tabela abaixo antes de abrir novos chamados.</small>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h2 class="mb-0"><i class="bi bi-list-task"></i> Meus Chamados Abertos (Página <?php echo $pagina; ?> de <?php echo $total_paginas; ?>)</h2>
                        <small>Chamados que você iniciou ou que foram abertos pela Secretaria de Educação.</small>
                    </div>
                    <div class="card-body">
                        <?php if (empty($todos_chamados)): ?>
                            <p>N..
                            Nenhum chamado encontrado.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Origem</th>
                                            <th>Tipo</th>
                                            <th>Destino</th>
                                            <th>Descrição</th>
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
                                                <td><?= htmlspecialchars(substr($chamado['descricao'], 0, 50)) . (strlen($chamado['descricao']) > 50 ? '...' : '') ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($chamado['data_abertura'])) ?></td>
                                                <td><span class="status status-<?= $chamado['status'] ?>"><?= ucfirst(str_replace('_', ' ', $chamado['status'])) ?></span></td>
                                                <td><?= $chamado['tecnico_nome'] ? htmlspecialchars($chamado['tecnico_nome']) : 'Não atribuído' ?></td>
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
                                                            echo '<br><a href="confirmar_recebimento_unidade.php?id=' . $chamado['id'] . '&tipo=' . $tipo_param . '" class="btn btn-sm btn-outline-success mt-1">Confirmar Recebimento</a>';
                                                        } else {
                                                            echo '<span class="badge bg-info text-white">Aguardando Confirmação do Setor</span>';
                                                        }
                                                    } elseif ($chamado["status"] == "concluido" && $confirmacao_unidade == 1) {
                                                        $setor_label = $is_almoxarifado ? '(Almox.)' : ($is_merenda ? '(Merenda)' : '');
                                                        echo '<span class="badge badge-entrega-confirmada">Recebimento Confirmado ' . $setor_label . '</span>';
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <a href="ver_chamado.php?id=<?= $chamado['id'] ?>" class="btn btn-sm btn-primary mb-1" title="Detalhes">
                                                        <i class="bi bi-search"></i> Ver
                                                    </a>
                                                    <?php 
                                                    $has_oficio = !empty($chamado['numero_oficio']) && !empty($chamado['tipo_oficio']);
                                                    if (($chamado['status'] == 'aguardando_recebimento' || $chamado['status'] == 'concluido') && $has_oficio): 
                                                    ?>
                                                        <a href="gerar_pdf_oficio.php?id=<?= $chamado['id'] ?>&tipo=<?= $chamado['tipo_oficio'] ?>" 
                                                           class="btn btn-sm btn-info mt-1" target="_blank" title="Baixar Ofício">
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
                                        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?pagina=<?= $pagina - 1 ?>&status=<?= $filtroStatus ?>">Anterior</a>
                                        </li>
                                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                            <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                                                <a class="page-link" href="?pagina=<?= $i ?>&status=<?= $filtroStatus ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= $pagina >= $total_paginas ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?pagina=<?= $pagina + 1 ?>&status=<?= $filtroStatus ?>">Próxima</a>
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

    <!-- Modal de Alerta de Pendência de Recebimento -->
    <?php if ($temPendenciaRecebimento): ?>
    <div class="modal fade" id="modalPendenciaRecebimento" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
          <div class="modal-header bg-warning text-dark">
            <h5 class="modal-title">
              <i class="bi bi-exclamation-triangle-fill"></i> Atenção: Entregas Pendentes de Confirmação
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <p>Você possui <strong><?= count($chamadosPendentes) ?> chamado(s)</strong> com material já entregue pelo setor, mas <strong>o recebimento ainda não foi confirmado pela unidade</strong>.</p>
            
            <div class="alert alert-warning small">
              <strong>Chamados com entrega pendente:</strong>
              <ul class="mb-0 mt-2">
                <?php foreach ($chamadosPendentes as $p): ?>
                  <li>Chamado #<?= $p['id'] ?> - <?= htmlspecialchars($p['origem']) ?> (<?= $p['setor'] ?>)</li>
                <?php endforeach; ?>
              </ul>
            </div>

            <p class="mb-0"><strong>Por favor, confirme o recebimento na tabela acima para liberar a abertura de novos chamados.</strong></p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Entendido</button>
          </div>
        </div>
      </div>
    </div>

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var modal = new bootstrap.Modal(document.getElementById('modalPendenciaRecebimento'), {
          backdrop: 'static',
          keyboard: false
        });
        modal.show();
      });
    </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      // Auto-fechar alertas após 5 segundos
      setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
          if (alert) new bootstrap.Alert(alert).close();
        });
      }, 5000);
    </script>
</body>
</html>
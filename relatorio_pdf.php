<?php
// Tenta incluir o autoload do Dompdf, assumindo que pode estar na raiz ou via Composer
if (file_exists('dompdf/autoload.inc.php')) {
    require_once 'dompdf/autoload.inc.php';
} else {
    require 'vendor/autoload.php';
}
use Dompdf\Dompdf;
use Dompdf\Options;

// Incluir arquivos de configuração e helpers
session_start();
require_once 'connection.php';
require_once 'auth.php';
require_once 'helpers.php';

// Acesso restrito
restrictAccess(['admin']);

// --- Lógica de Setor e Filtros (Replicada do relatorios.php) ---

$setoresDisponiveis = [
    'todos' => 'Todos os Setores',
    'manutencao_geral' => 'Manutenção Geral',
    'informatica' => 'Informática',
    'casa_da_merenda' => 'Casa da Merenda',
    'almoxarifado' => 'Almoxarifado'
];

$setorPadrao = $_SESSION['user']['setor'] ?? 'todos';
$filtroSetor = $_GET['setor'] ?? $setorPadrao;

$filtroUnidade = $_GET['unidade'] ?? 'todos';
// $filtroTipo = $_GET['tipo'] ?? 'todos'; // Removido a pedido do usuário
$filtroMes = $_GET['mes'] ?? '';

// Obter lista de unidades escolares para o filtro (necessário para exibição)
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

// --- Construção da Consulta SQL (Replicada do relatorios.php) ---

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

// --- Consulta de Dados ---

$dadosChamados = [];
$query = "SELECT c.id, c.tipo_manutencao, c.descricao, c.status, c.data_abertura, c.setor_destino,
                 ue.nome_unidade, u.nome as nome_usuario 
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
}

$totalChamados = count($dadosChamados);

// --- Geração do HTML para o PDF ---

// Função para obter o nome do tipo de manutenção (assumindo que está em helpers.php ou connection.php)
if (!function_exists('getNomeTipoManutencao')) {
    function getNomeTipoManutencao($tipo) {
        $map = [
            'geral' => 'Manutenção Geral',
            'informatica' => 'Informática',
            'casa_da_merenda' => 'Casa da Merenda',
            'almoxarifado' => 'Almoxarifado'
        ];
        return $map[$tipo] ?? ucfirst(str_replace('_', ' ', $tipo));
    }
}

// Mapeamento de filtros para exibição no PDF
$filtrosAplicados = [
    'Setor de Destino' => $setoresDisponiveis[$filtroSetor] ?? 'Todos',
    'Unidade Escolar' => ($filtroUnidade !== 'todos' && isset($unidades[$filtroUnidade])) ? $unidades[$filtroUnidade] : 'Todas',
    // 'Tipo de Manutenção' removido a pedido do usuário
    'Mês de Abertura' => !empty($filtroMes) ? date('m/Y', strtotime($filtroMes)) : 'Todos',
];

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Relatório de Chamados</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 16pt; }
        .header h2 { margin: 5px 0 15px 0; font-size: 14pt; color: #333; }
        .filters { margin-bottom: 20px; border: 1px solid #ccc; padding: 10px; }
        .filters p { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-size: 10pt; }
        .status-aberto { color: orange; font-weight: bold; }
        .status-em_andamento { color: blue; font-weight: bold; }
        .status-concluido { color: green; font-weight: bold; }
        .status-cancelado { color: red; font-weight: bold; }
        .total { font-weight: bold; font-size: 12pt; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Prefeitura Municipal de Bayeux</h1>
        <h2>Relatório Detalhado de Chamados</h2>
    </div>

    <div class="filters">
        <h3>Filtros Aplicados:</h3>
        ' . implode('', array_map(function($key, $value) {
            return "<p><strong>{$key}:</strong> {$value}</p>";
        }, array_keys($filtrosAplicados), $filtrosAplicados)) . '
        <p class="total">Total de Chamados Encontrados: ' . $totalChamados . '</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Unidade</th>
                <th>Tipo</th>
                <th>Solicitante</th>
                <th>Status</th>
                <th>Data Abertura</th>
            </tr>
        </thead>
        <tbody>';

if (!empty($dadosChamados)) {
    foreach ($dadosChamados as $chamado) {
        $statusClass = 'status-' . $chamado['status'];
        $statusText = ucfirst(str_replace('_', ' ', htmlspecialchars($chamado['status'])));
        
        $html .= '
            <tr>
                <td>#' . htmlspecialchars($chamado['id']) . '</td>
                <td>' . htmlspecialchars($chamado['nome_unidade'] ?? 'Não especificado') . '</td>
                <td>' . htmlspecialchars(getNomeTipoManutencao($chamado['tipo_manutencao'])) . '</td>
                <td>' . htmlspecialchars($chamado['nome_usuario']) . '</td>
                <td class="' . $statusClass . '">' . $statusText . '</td>
                <td>' . date('d/m/Y H:i', strtotime($chamado['data_abertura'])) . '</td>
            </tr>';
    }
} else {
    $html .= '
        <tr>
            <td colspan="6" style="text-align: center;">Nenhum chamado encontrado com os filtros aplicados.</td>
        </tr>';
}

$html .= '
        </tbody>
    </table>

</body>
</html>';

// --- Configuração e Geração do Dompdf ---

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// (Opcional) Configurar o tamanho e orientação do papel
$dompdf->setPaper('A4', 'landscape'); // Usando paisagem para mais colunas

// Renderizar o HTML como PDF
$dompdf->render();

// Enviar o arquivo para o navegador
$filename = "relatorio_chamados_" . date('Ymd_His') . ".pdf";
$dompdf->stream($filename, ["Attachment" => true]);
?>

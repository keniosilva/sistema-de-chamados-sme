<?php
require_once 'connection.php';
require_once 'auth.php';
require_once 'helpers.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Proteção: só admins e técnicos podem acessar
verificarPermissao(['admin', 'tecnico', 'unidade_escolar', 'casa_da_merenda', 'almoxarifado', 'manutencao', 'tecnico_geral', 'tecnico_informatica', 'secretaria']);

$chamado_id = $_GET['id'] ?? 0;

if (!$chamado_id) {
    header("Location: " . ($_SESSION['user']['tipo_usuario'] == 'admin' ? 'admin_dashboard.php' : ($_SESSION['user']['tipo_usuario'] == 'tecnico' ? 'tecnico_dashboard.php' : 'unidade_dashboard.php')));
    exit();
}

// Buscar dados do chamado e ofício
$stmt = $conn->prepare("
    SELECT c.*, ue.nome_unidade, ue.endereco, ue.telefone, ue.email as email_unidade,
           u.nome as nome_usuario, u.email as email_usuario,
           t.nome as nome_tecnico, t.email as email_tecnico,
           o.numero_oficio, o.data_oficio, o.conteudo_oficio, o.hash_validacao
    FROM chamados c
    JOIN unidades_escolares ue ON c.id_unidade_escolar = ue.id
    JOIN usuarios u ON c.id_usuario_abertura = u.id
    LEFT JOIN usuarios t ON c.id_tecnico_responsavel = t.id
    LEFT JOIN oficios o ON c.id = o.id_chamado
    WHERE c.id = ?
");
$stmt->bind_param("i", $chamado_id);
$stmt->execute();
$chamado = $stmt->get_result()->fetch_assoc();

if (!$chamado || !$chamado['numero_oficio']) {
    header("Location: " . ($_SESSION['user']['tipo_usuario'] == 'admin' ? 'admin_dashboard.php' : ($_SESSION['user']['tipo_usuario'] == 'tecnico' ? 'tecnico_dashboard.php' : 'unidade_dashboard.php')) . "?erro=Chamado ou ofício não encontrado");
    exit();
}

// Verificar permissões para unidade escolar
if ($_SESSION['user']['tipo_usuario'] == 'unidade_escolar' && $chamado['id_unidade_escolar'] != ($_SESSION['user']['id_unidade_escolar'] ?? 0)) {
    header("Location: unidade_dashboard.php?erro=Acesso negado");
    exit();
}

// Função para formatar data em português
function formatarDataPortugues($data) {
    $meses = [
        'January' => 'Janeiro', 'February' => 'Fevereiro', 'March' => 'Março', 'April' => 'Abril',
        'May' => 'Maio', 'June' => 'Junho', 'July' => 'Julho', 'August' => 'Agosto',
        'September' => 'Setembro', 'October' => 'Outubro', 'November' => 'Novembro', 'December' => 'Dezembro'
    ];
    $dataFormatada = date('d \d\e F \d\e Y', strtotime($data));
    foreach ($meses as $en => $pt) {
        $dataFormatada = str_replace($en, $pt, $dataFormatada);
    }
    return $dataFormatada;
}

// Verificar se a imagem da logo existe
$logo_path = $_SERVER['DOCUMENT_ROOT'] . '/manutencao/images/logo.jpg';
$logo_url = '/manutencao/images/logo.jpg'; // Caminho relativo para o DomPDF
if (!file_exists($logo_path) || !is_readable($logo_path)) {
    error_log("Erro: Imagem da logo não encontrada ou não legível em $logo_path");
    $logo_url = 'https://via.placeholder.com/150x50?text=Logo+Prefeitura'; // Fallback para placeholder
}

// Configurar DomPDF
$options = new Options();
$options->set('defaultFont', 'Arial');
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);

// HTML do ofício
$html = '
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 1.5cm;
            size: A4;
        }
        
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.2;
            color: #000;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #000;
            padding-bottom: 10px;
        }
        
        .header img {
            width: 150px;
            height: 50px;
            margin-bottom: 10px;
        }
        
        .header h1 {
            font-size: 14pt;
            font-weight: bold;
            margin: 0 0 3px 0;
            text-transform: uppercase;
        }
        
        .header h2 {
            font-size: 12pt;
            font-weight: bold;
            margin: 0 0 5px 0;
            text-transform: uppercase;
        }
        
        .header .unidade {
            font-size: 11pt;
            font-weight: bold;
            margin: 5px 0;
        }
        
        .oficio-info {
            margin: 15px 0;
            text-align: right;
        }
        
        .oficio-info .numero {
            font-size: 12pt;
            font-weight: bold;
        }
        
        .oficio-info .data {
            font-size: 10pt;
            margin-top: 3px;
        }
        
        .content {
            margin: 20px 0;
            text-align: justify;
        }
        
        .content h3 {
            font-size: 11pt;
            font-weight: bold;
            margin: 15px 0 8px 0;
            text-transform: uppercase;
            text-align: center;
        }
        
        .content p {
            margin: 5px 0;
        }
        
        .descricao {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 10px;
            margin: 15px 0;
            border-radius: 4px;
        }
        
        .footer {
            margin-top: 30px;
            border-top: 1px solid #000;
            padding-top: 10px;
        }
        
        .assinatura {
            margin-top: 40px;
            text-align: center;
        }
        
        .assinatura .linha {
            border-top: 1px solid #000;
            width: 250px;
            margin: 0 auto 8px auto;
        }
        
        .assinatura p {
            margin: 3px 0;
        }
        
        .validacao {
            margin-top: 25px;
            font-size: 9pt;
            color: #666;
            border: 1px solid #ccc;
            padding: 8px;
            background: #f9f9f9;
        }
        
        .validacao h4 {
            margin: 0 0 5px 0;
            color: #000;
            font-size: 10pt;
        }
        
        .hash {
            font-family: monospace;
            word-break: break-all;
            font-size: 8pt;
        }
    </style>
</head>
<body>
    <div class="header">
        <!--<img src="' . $logo_url . '" alt="Logo Prefeitura">-->
        <h1>Prefeitura Municipal de Bayeux</h1>
        <h2>Secretaria Municipal de Educação</h2>
        <div class="unidade">' . htmlspecialchars($chamado['nome_unidade']) . '</div>
    </div>
    
    <div class="oficio-info">
        <div class="numero">OFÍCIO Nº ' . htmlspecialchars($chamado['numero_oficio']) . '</div>
        <div class="data">Bayeux, ' . formatarDataPortugues($chamado['data_oficio']) . '</div>
    </div>
    
    <div class="content">
        <h3>Solicitação - ' . strtoupper(htmlspecialchars($chamado['tipo_manutencao'])) . '</h3>
        <p></p>
        <p></p>
        <p><strong>Unidade Escolar:</strong> ' . htmlspecialchars($chamado['nome_unidade']) . '</p>
        <p><strong>Endereço:</strong> ' . htmlspecialchars($chamado['endereco']) . '</p>
        
        <p><strong>Solicitante:</strong> ' . htmlspecialchars($chamado['nome_usuario']) . '</p>
        <p><strong>Data da Solicitação:</strong> ' . date('d/m/Y \à\s H:i', strtotime($chamado['data_abertura'])) . '</p>
        <p></p>
        <h4 style="margin-top: 20px; margin-bottom: 10px;">DESCRIÇÃO DA SOLICITAÇÃO:</h4>
        <div class="descricao">
            ' . nl2br(htmlspecialchars($chamado['descricao'])) . '
        </div>
        
        <p style="margin-top: 15px;">
            Solicitamos a atenção da equipe responsável para o atendimento desta demanda, visando garantir o bom funcionamento das atividades.
            
        </p>
        
        <p style="margin-top: 10px;">
            Agradecemos a atenção e aguardamos o atendimento no menor prazo possível.
        </p>
    </div>
    
    <!--<div class="assinatura">
        <div class="linha"></div>
        <p><strong>' . htmlspecialchars($chamado['nome_usuario']) . '</strong></p>
        <p>Responsável pela Solicitação</p>
        <p>' . htmlspecialchars($chamado['nome_unidade']) . '</p>
    </div>-->
    <p></p>
    <p></p>
    <p></p>
    <p></p>
    <p></p>
    
    <div class="validacao">
        <h4>CERTIFICAÇÃO DE VALIDADE</h4>
        <p><strong>Chamado ID:</strong> #' . $chamado['id'] . '</p>
        <p><strong>Hash de Validação:</strong></p>
        <p class="hash">' . htmlspecialchars($chamado['hash_validacao']) . '</p>
        <p style="margin-top: 8px; font-size: 8pt;">
            Este ofício foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux. 
            O hash acima garante a autenticidade e integridade do documento.
        </p>
    </div>
</body>
</html>';

try {
    // Gerar PDF
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Definir nome do arquivo
    $filename = 'Oficio_' . str_replace('/', '_', $chamado['numero_oficio']) . '_Chamado_' . $chamado['id'] . '.pdf';

    // Enviar para download
    $dompdf->stream($filename, array("Attachment" => true));
} catch (Exception $e) {
    error_log("Erro ao gerar PDF: " . $e->getMessage());
    header("Location: ver_chamado.php?id=" . $chamado_id . "&erro=Erro ao gerar PDF: " . urlencode($e->getMessage()));
    exit();
}
?>
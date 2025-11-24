<?php
session_start();
require_once 'connection.php';
require_once 'auth.php';
require_once 'helpers.php';

// Permitir acesso a todos os tipos de usuário, mas gerenciar apenas para tipos específicos
restrictAccess(['admin', 'tecnico_informatica', 'tecnico_geral', 'almoxarifado', 'casa_da_merenda', 'secretaria', 'unidade_escolar']);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conn->set_charset("utf8mb4");

$chamado = null;
$mensagemErro = '';
$userType = getUserType();
$chamadoId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

// Consultar detalhes do chamado
if ($chamadoId > 0) {
    $query = "SELECT c.id, c.tipo_manutencao, c.descricao, c.status, c.data_abertura, c.data_fechamento, c.observacoes_tecnico, c.id_usuario_abertura, 
                     c.id_tecnico_responsavel, c.almoxarifado_confirmacao_entrega, c.confirmacao_recebimento_unidade, c.id_unidade_escolar, ue.nome_unidade, ue.endereco, ue.telefone, ue.email as email_unidade, 
                     u1.nome as nome_usuario_abertura, u1.email as email_usuario_abertura, 
                     u2.nome as nome_tecnico_responsavel, u2.email as email_tecnico_responsavel, 
                     o.numero_oficio, o.data_oficio, o.conteudo_oficio, o.hash_validacao
              FROM chamados c 
              LEFT JOIN unidades_escolares ue ON c.id_unidade_escolar = ue.id 
              JOIN usuarios u1 ON c.id_usuario_abertura = u1.id 
              LEFT JOIN usuarios u2 ON c.id_tecnico_responsavel = u2.id 
              LEFT JOIN oficios o ON c.id = o.id_chamado
              WHERE c.id = ?";
    
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("i", $chamadoId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $chamado = $result->fetch_assoc();
        } else {
            $mensagemErro = "Chamado não encontrado.";
        }
        $stmt->close();
    } else {
        $mensagemErro = "Erro ao consultar chamado: " . $conn->error;
        error_log("Erro na consulta do chamado ID $chamadoId: " . $conn->error);
    }
} else {
    $mensagemErro = "ID do chamado inválido.";
}

// Verificar permissões para unidade escolar
if ($userType == 'unidade_escolar' && $chamado['id_unidade_escolar'] != ($_SESSION['user']['id_unidade_escolar'] ?? 0)) {
    header("Location: unidade_dashboard.php");
    exit();
}

// Determinar se o usuário pode gerenciar o chamado
$canManage = in_array($userType, ['admin', 'tecnico_informatica', 'tecnico_geral', 'almoxarifado', 'casa_da_merenda']) &&
             in_array($chamado['tipo_manutencao'], [
                 'geral' => ['admin', 'tecnico_geral'],
                 'informatica' => ['admin', 'tecnico_informatica'],
                 'almoxarifado' => ['admin', 'almoxarifado'],
                 'casa_da_merenda' => ['admin', 'casa_da_merenda']
             ][$chamado['tipo_manutencao']] ?? []);

// Determinar a página de retorno com base no tipo de usuário
$returnPage = [
    'admin' => 'admin_dashboard.php',
    'tecnico_informatica' => 'tecnico_dashboard.php',
    'tecnico_geral' => 'manutencao_dashboard.php',
    'almoxarifado' => 'almoxarifado_dashboard.php',
    'casa_da_merenda' => 'merenda_dashboard.php',
    'secretaria' => 'secretaria_dashboard.php',
    'unidade_escolar' => 'unidade_dashboard.php'
][$userType] ?? 'login.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chamado #<?php echo htmlspecialchars($chamadoId); ?> - Sistema de Chamados</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.5rem;
        }
        
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .card h2 {
            color: #333;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .info-section h3 {
            color: #667eea;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        
        .info-item {
            margin-bottom: 0.75rem;
        }
        
        .info-label {
            font-weight: 600;
            color: #333;
            display: inline-block;
            width: 120px;
        }
        
        .info-value {
            color: #666;
        }
        
        .status {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .status.aberto {
            background: #fff3cd;
            color: #856404;
        }
        
        .status.em_atendimento {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status.aguardando_confirmacao {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status.concluido {
            background: #d4edda;
            color: #155724;
        }
        
        .status.cancelado {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            transition: transform 0.2s;
            cursor: pointer;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-warning {
            background: #ffc107;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .description-box {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 5px;
            border: 1px solid #e1e5e9;
            margin-bottom: 2rem;
            white-space: pre-wrap;
        }
        
        .oficio-box {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 5px;
            border: 1px solid #e1e5e9;
            margin: 1.5rem 0;
            white-space: pre-wrap;
            font-family: monospace;
        }
        
        .hash-validation {
            background: #e9ecef;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .actions a {
            flex: 1;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Sistema de Chamados - Detalhes do Chamado #<?php echo htmlspecialchars($chamadoId); ?></h1>
            <a href="<?php echo htmlspecialchars($returnPage); ?>" class="btn btn-secondary">Voltar para o Dashboard</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($mensagemErro): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($mensagemErro); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($chamado): ?>
            <div class="card">
                <h2>Informações do Chamado</h2>
                
                <div class="info-grid">
                    <div class="info-section">
                        <h3>Dados Gerais</h3>
                        <div class="info-item">
                            <span class="info-label">Status:</span>
                            <span class="status <?php echo htmlspecialchars($chamado['status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($chamado['status']))); ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Tipo:</span>
                            <span class="info-value"><?php echo htmlspecialchars(getNomeTipoManutencao($chamado['tipo_manutencao'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Data Abertura:</span>
                            <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($chamado['data_abertura'])); ?></span>
                        </div>
                        <?php if ($chamado["data_fechamento"]): ?>
                            <div class="info-item">
                                <span class="info-label">Data Fechamento:</span>
                                <span class="info-value"><?php echo date("d/m/Y H:i", strtotime($chamado["data_fechamento"])); ?></span>
                            </div>                        <?php endif; ?>
                    </div>
                    
                    <div class="info-section">
                        <h3>Unidade Escolar</h3>
                        <div class="info-item">
                            <span class="info-label">Nome:</span>
                            <span class="info-value"><?php echo htmlspecialchars($chamado['nome_unidade']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Endereço:</span>
                            <span class="info-value"><?php echo htmlspecialchars($chamado['endereco']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Telefone:</span>
                            <span class="info-value"><?php echo htmlspecialchars($chamado['telefone']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($chamado['email_unidade']); ?></span>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <h3>Solicitante</h3>
                        <div class="info-item">
                            <span class="info-label">Nome:</span>
                            <span class="info-value"><?php echo htmlspecialchars($chamado['nome_usuario_abertura']); ?></span>
                        </div>
                        <!--<div class="info-item">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php //echo htmlspecialchars($chamado['email_usuario_abertura']); ?></span>
                        </div>-->
                    </div>
                    
                    <div class="info-section">
                        <h3>Técnico Responsável</h3>
                        <?php if ($chamado["nome_tecnico_responsavel"]): ?>
                            <div class="info-item">
                                <span class="info-label">Nome:</span>
                                <span class="info-value"><?php echo htmlspecialchars($chamado["nome_tecnico_responsavel"]); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?php echo htmlspecialchars($chamado["email_tecnico_responsavel"]); ?></span>
                            </div>
                        <?php else: ?>
                            <p class="info-value">Não atribuído</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <h3 style="color: #667eea; margin: 2rem 0 1rem 0;">Descrição do Problema</h3>
                <div class="description-box">
                    <?php echo nl2br(htmlspecialchars($chamado['descricao'])); ?>
                </div>
                
                        <?php if ($chamado["observacoes_tecnico"]): ?>
                            <h3 style="color: #667eea; margin: 2rem 0 1rem 0;">Observações do Técnico</h3>
                            <div class="description-box">
                                <?php echo nl2br(htmlspecialchars($chamado["observacoes_tecnico"])); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($chamado["tipo_manutencao"] == "almoxarifado"): ?>
                            <h3 style="color: #667eea; margin: 2rem 0 1rem 0;">Status da Entrega</h3>
                            <div class="info-item">
                                <span class="info-label">Confirmação Almoxarifado:</span>
                                <span class="info-value">
                                    <?php echo ($chamado["almoxarifado_confirmacao_entrega"] ?? 0) == 1 ? 
                                        "<span style=\"color: green;\">Confirmado</span>" : 
                                        "<span style=\"color: orange;\">Pendente</span>"; 
                                    ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Confirmação Unidade:</span>
                                <span class="info-value">
                                    <?php echo ($chamado["confirmacao_recebimento_unidade"] ?? 0) == 1 ? 
                                        "<span style=\"color: green;\">Confirmado</span>" : 
                                        "<span style=\"color: orange;\">Pendente</span>"; 
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
            </div>
            
            <?php if ($chamado['numero_oficio']): ?>
                <div class="card">
                    <h2>Ofício Gerado</h2>
                    
                    <div class="info-item">
                        <span class="info-label">Número:</span>
                        <span class="info-value"><?php echo htmlspecialchars($chamado['numero_oficio']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Data:</span>
                        <span class="info-value"><?php echo date('d/m/Y', strtotime($chamado['data_oficio'])); ?></span>
                    </div>
                    
                    <div class="oficio-box">
                        <?php echo htmlspecialchars($chamado['conteudo_oficio']); ?>
                    </div>
                    
                    <div class="hash-validation">
                        <strong>Certificação de Validade:</strong><br>
                        Hash de Validação: <?php echo $chamado['hash_validacao']; ?><br>
                        <small>Este hash garante a autenticidade e integridade do ofício gerado pelo sistema.</small>
                    </div>
                    
                    <div class="actions">
                        <a href="gerar_pdf_oficio.php?id=<?php echo $chamado['id']; ?>" class="btn" target="_blank">Baixar PDF do Ofício</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($canManage): ?>
                <div class="card">
                    <h2>Ações do Técnico</h2>
                    <div class="actions">
                        <?php if ($chamado['status'] == 'aberto' && ($_SESSION['user']['tipo_usuario'] == 'admin' || $chamado['id_tecnico_responsavel'] == $_SESSION['user']['id'] || !$chamado['id_tecnico_responsavel'])): ?>
                            <?php if (!$chamado['id_tecnico_responsavel'] && $_SESSION['user']['tipo_usuario'] == 'tecnico'): ?>
                                <a href="atribuir_chamado.php?id=<?php echo $chamado['id']; ?>" class="btn btn-success">Assumir Chamado</a>
                            <?php endif; ?>
                            <?php if ($chamado['id_tecnico_responsavel'] == $_SESSION['user']['id'] || $_SESSION['user']['tipo_usuario'] == 'admin'): ?>
                                <a href="atualizar_chamado.php?id=<?php echo $chamado['id']; ?>&acao=iniciar" class="btn btn-warning">Iniciar Atendimento</a>
                            <?php endif; ?>
                        <?php elseif ($chamado['status'] == 'em_atendimento' && ($chamado['id_tecnico_responsavel'] == $_SESSION['user']['id'] || $_SESSION['user']['tipo_usuario'] == 'admin')): ?>
                            <a href="atualizar_chamado.php?id=<?php echo $chamado['id']; ?>&acao=concluir" class="btn btn-success">Concluir Chamado</a>
                            <a href="atualizar_chamado.php?id=<?php echo $chamado['id']; ?>&acao=cancelar" class="btn btn-danger">Cancelar Chamado</a>
                            <a href="adicionar_observacao.php?id=<?php echo $chamado['id']; ?>" class="btn">Adicionar Observação</a>
                        <?php endif; ?>
                        
                        <?php if ($_SESSION['user']['tipo_usuario'] == 'admin'): ?>
                            <a href="gerenciar_chamados.php?id=<?php echo $chamado['id']; ?>" class="btn">Gerenciar</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="d-flex gap-2 mt-3">
                <a href="<?php echo htmlspecialchars($returnPage); ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
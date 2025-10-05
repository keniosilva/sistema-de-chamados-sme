<?php
session_start();
require_once 'connection.php';
require_once 'helpers.php';

// Verificar permissão - agora permite todos os tipos de usuário abrir chamados
verificarPermissao(['unidade_escolar', 'secretaria', 'almoxarifado', 'casa_da_merenda', 'tecnico_geral', 'tecnico_informatica']);

$erro = '';
$sucesso = '';

// Buscar informações da unidade escolar ou setor do usuário
$origem_info = ['nome' => 'Usuário Não Identificado']; // Fallback
if (isset($_SESSION['user']['id_unidade_escolar']) && $_SESSION['user']['id_unidade_escolar']) {
    // Usuário de unidade escolar
    $stmt = $conn->prepare("SELECT nome_unidade as nome FROM unidades_escolares WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user']['id_unidade_escolar']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $origem_info = $row;
        }
        $stmt->close();
    }
} else {
    // Usuário de setor da secretaria
    $tipo_usuario = $_SESSION['user']['tipo_usuario'];
    $nomes_setores = [
        'secretaria' => 'Secretaria de Educação',
        'almoxarifado' => 'Almoxarifado',
        'casa_da_merenda' => 'Casa da Merenda',
        'tecnico_geral' => 'Manutenção Geral',
        'tecnico_informatica' => 'Informática'
    ];
    $origem_info['nome'] = $nomes_setores[$tipo_usuario] ?? 'Setor Não Identificado';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_manutencao = $_POST['tipo_manutencao'] ?? '';
    $setor_destino = $_POST['setor_destino'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    
    if ($tipo_manutencao && $setor_destino && $descricao) {
        $conn->begin_transaction();
        
        try {
            // Inserir chamado com setor_destino
            $stmt = $conn->prepare("INSERT INTO chamados (id_unidade_escolar, id_usuario_abertura, tipo_manutencao, setor_destino, descricao) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $id_unidade = $_SESSION['user']['id_unidade_escolar'] ?? null;
                $stmt->bind_param("iisss", $id_unidade, $_SESSION['user']['id'], $tipo_manutencao, $setor_destino, $descricao);
                $stmt->execute();
                $chamado_id = $conn->insert_id;
                $stmt->close();
            } else {
                throw new Exception("Erro ao preparar inserção de chamado: " . $conn->error);
            }
            
            // Gerar número do ofício
            $ano = date('Y');
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM oficios WHERE YEAR(data_oficio) = ?");
            if ($stmt) {
                $stmt->bind_param("i", $ano);
                $stmt->execute();
                $count = $stmt->get_result()->fetch_assoc()['total'];
                $stmt->close();
                $numero_oficio = sprintf("OF-%03d/%s", $count + 1, $ano);
            } else {
                throw new Exception("Erro ao preparar consulta de ofício: " . $conn->error);
            }
            
            // Mapear nomes dos setores para o ofício
            $nomes_setores_oficio = [
                'manutencao_geral' => 'MANUTENÇÃO GERAL',
                'informatica' => 'INFORMÁTICA',
                'casa_da_merenda' => 'CASA DA MERENDA',
                'almoxarifado' => 'ALMOXARIFADO'
            ];
            
            // Criar conteúdo do ofício
            $conteudo_oficio = "PREFEITURA MUNICIPAL DE BAYEUX\n";
            $conteudo_oficio .= "SECRETARIA MUNICIPAL DE EDUCAÇÃO\n\n";
            $conteudo_oficio .= "ORIGEM: " . $origem_info['nome'] . "\n";
            $conteudo_oficio .= "DESTINO: " . $nomes_setores_oficio[$setor_destino] . "\n";
            $conteudo_oficio .= "OFÍCIO Nº: " . $numero_oficio . "\n";
            $conteudo_oficio .= "DATA: " . date('d/m/Y') . "\n\n";
            $conteudo_oficio .= "SOLICITAÇÃO DE SERVIÇO - " . $nomes_setores_oficio[$setor_destino] . "\n\n";
            $conteudo_oficio .= "DESCRIÇÃO:\n" . $descricao . "\n\n";
            $conteudo_oficio .= "Este ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.";
            
            // Gerar hash de validação
            $hash_validacao = gerarHashValidacao($numero_oficio . $chamado_id . date('Y-m-d'));
            
            // Inserir ofício
            $stmt = $conn->prepare("INSERT INTO oficios (id_chamado, numero_oficio, data_oficio, conteudo_oficio, hash_validacao) VALUES (?, ?, CURDATE(), ?, ?)");
            if ($stmt) {
                $stmt->bind_param("isss", $chamado_id, $numero_oficio, $conteudo_oficio, $hash_validacao);
                $stmt->execute();
                $stmt->close();
            } else {
                throw new Exception("Erro ao preparar inserção de ofício: " . $conn->error);
            }
            
            $conn->commit();
            $sucesso = "Chamado aberto com sucesso! Ofício gerado: " . $numero_oficio;
            
            // Redirecionar para ver o chamado
            header("Location: ver_chamado.php?id=" . $chamado_id . "&sucesso=" . urlencode($sucesso));
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $erro = $e->getMessage();
            error_log("Erro ao criar chamado: " . $e->getMessage());
        }
    } else {
        $erro = "Por favor, preencha todos os campos obrigatórios.";
    }
}

// Determinar dashboard de retorno baseado no tipo de usuário
$dashboard_retorno = 'unidade_dashboard.php';
switch ($_SESSION['user']['tipo_usuario']) {
    case 'secretaria':
        $dashboard_retorno = 'secretaria_dashboard.php';
        break;
    case 'almoxarifado':
        $dashboard_retorno = 'almoxarifado_dashboard.php';
        break;
    case 'casa_da_merenda':
        $dashboard_retorno = 'merenda_dashboard.php';
        break;
    case 'tecnico_geral':
    case 'tecnico_informatica':
        $dashboard_retorno = 'tecnico_dashboard.php';
        break;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Chamado - Sistema de Chamados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        body {
            background: #f5f7fa;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6dd8 0%, #6a4292 100%);
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }
    </style>
</head>
<body>
    <header class="header py-3">
        <div class="container">
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <h1 class="fs-4 mb-0">Novo Chamado - <?= htmlspecialchars($origem_info['nome']) ?></h1>
                <a href="<?= $dashboard_retorno ?>" class="btn btn-secondary btn-sm">Voltar</a>
            </div>
        </div>
    </header>

    <main class="container py-4">
        <div class="card p-4">
            <h2 class="fs-4 mb-4 border-bottom border-primary pb-2">Abrir Nova Solicitação</h2>

            <div class="alert alert-info" role="alert">
                <h3 class="fs-5 mb-2">Informações Importantes</h3>
                <p class="mb-0">Ao abrir este chamado, será gerado automaticamente um ofício oficial da Secretaria Municipal de Educação com numeração sequencial e certificação de validade.</p>
            </div>

            <?php if ($erro): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label for="tipo_manutencao" class="form-label fw-medium">Tipo de Solicitação:</label>
                    <select id="tipo_manutencao" name="tipo_manutencao" class="form-select" required>
                        <option value="">Selecione o tipo de solicitação</option>
                        <option value="geral">Manutenção Geral</option>
                        <option value="informatica">Informática</option>
                        <option value="casa_da_merenda">Casa da Merenda</option>
                        <option value="almoxarifado">Almoxarifado</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="setor_destino" class="form-label fw-medium">Setor de Destino:</label>
                    <select id="setor_destino" name="setor_destino" class="form-select" required>
                        <option value="">Selecione o setor de destino</option>
                        <option value="manutencao_geral">Manutenção Geral</option>
                        <option value="informatica">Informática</option>
                        <option value="casa_da_merenda">Casa da Merenda</option>
                        <option value="almoxarifado">Almoxarifado</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="descricao" class="form-label fw-medium">Descrição da Solicitação:</label>
                    <textarea id="descricao" name="descricao" class="form-control" rows="5" placeholder="Descreva detalhadamente o problema que precisa ser resolvido ou sua solicitação..." required></textarea>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Abrir Chamado</button>
                    <a href="<?= $dashboard_retorno ?>" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // Sincronizar tipo_manutencao com setor_destino para facilitar o uso
        document.getElementById('tipo_manutencao').addEventListener('change', function() {
            const setorDestino = document.getElementById('setor_destino');
            const valor = this.value;
            
            if (valor === 'geral') {
                setorDestino.value = 'manutencao_geral';
            } else if (valor) {
                setorDestino.value = valor;
            }
        });
    </script>
</body>
</html>
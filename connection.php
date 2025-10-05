<?php
// Iniciar sessão apenas se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Credenciais do banco de dados (em um ambiente de produção, use variáveis de ambiente ou um arquivo de configuração externo)
$host = getenv("DB_HOST") ?: "localhost";
$user = getenv("DB_USER") ?: "root";
$pass = getenv("DB_PASS") ?: "adm123Info";
$db   = getenv("DB_NAME") ?: "sistema_chamados";

// Criar conexão
$conn = new mysqli($host, $user, $pass, $db);

// Verificar conexão
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

mysqli_set_charset($conn, "utf8mb4");



// Função para gerar hash de validação para ofícios
function gerarHashValidacao($dados) {
    return hash("sha256", $dados . "BAYEUX_SECRETARIA_EDUCACAO_2025");
}

// Função para mapear tipos de usuário para nomes amigáveis
function getNomeTipoUsuario($tipo) {
    $nomes = [
        "admin" => "Administrador",
        "tecnico_geral" => "Técnico - Manutenção Geral",
        "tecnico_informatica" => "Técnico - Informática",
        "unidade_escolar" => "Unidade Escolar",
        "almoxarifado" => "Almoxarifado",
        "casa_da_merenda" => "Casa da Merenda",
        "secretaria" => "Secretaria de Educação"
    ];
    return $nomes[$tipo] ?? "Tipo Desconhecido";
}

// Função para mapear setores de destino para nomes amigáveis
function getNomeSetorDestino($setor) {
    $nomes = [
        "manutencao_geral" => "Manutenção Geral",
        "informatica" => "Informática",
        "casa_da_merenda" => "Casa da Merenda",
        "almoxarifado" => "Almoxarifado"
    ];
    return $nomes[$setor] ?? "Setor Desconhecido";
}

// Função para mapear tipos de manutenção para nomes amigáveis
function getNomeTipoManutencao($tipo) {
    $nomes = [
        "geral" => "Manutenção Geral",
        "informatica" => "Informática",
        "casa_da_merenda" => "Casa da Merenda",
        "almoxarifado" => "Almoxarifado"
    ];
    return $nomes[$tipo] ?? "Tipo Desconhecido";
}

// Função para obter o tipo de usuário logado
function getUserType() {
    return $_SESSION["user"]["tipo_usuario"] ?? "guest";
}
?>

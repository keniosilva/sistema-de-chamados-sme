<?php
function verificarPermissao($tipos_permitidos) {
    if (!isset($_SESSION["user"]) || !isset($_SESSION["user"]["tipo_usuario"])) {
        header("Location: login.php");
        exit();
    }
    if (!in_array($_SESSION["user"]["tipo_usuario"], $tipos_permitidos)) {
        header("Location: acesso_negado.php"); // Redirecionar para uma página de acesso negado
        exit();
    }
}

function getDashboardUrl($userType) {
    switch ($userType) {
        case 'admin':
            return 'admin_dashboard.php';
        case 'tecnico':
        case 'tecnico_informatica':
            return 'tecnico_dashboard.php';
        case 'tecnico_geral':
            return 'manutencao_dashboard.php';
        case 'unidade_escolar':
            return 'unidade_dashboard.php';
        case 'almoxarifado':
            return 'almoxarifado_dashboard.php';
        case 'casa_da_merenda':
            return 'merenda_dashboard.php';
        case 'secretaria':
            return 'secretaria_dashboard.php';
        default:
            return 'index.php';
    }
}
?>


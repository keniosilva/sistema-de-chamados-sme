<?php
function restrictAccess($allowed_roles) {
    if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['tipo_usuario'], $allowed_roles)) {
        header('Location: login.php');
        exit();
    }
}
?>


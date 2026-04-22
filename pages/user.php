<?php
require_once __DIR__ . '/../includes/config.php';
http_response_code(301);
header('Location: ' . BASE_URL . 'admin/user.php');
exit;
?>

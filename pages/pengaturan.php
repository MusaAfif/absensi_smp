<?php
require_once __DIR__ . '/../includes/config.php';

// Legacy endpoint redirect to canonical settings module.
http_response_code(301);
header('Location: ' . BASE_URL . 'pengaturan/');
exit;
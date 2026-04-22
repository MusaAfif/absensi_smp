<?php
require_once 'includes/config.php';

// Hapus semua data session
session_unset();
session_destroy();

// Tendang kembali ke halaman login
header("Location: " . BASE_URL . "login.php");
exit;
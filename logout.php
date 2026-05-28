<?php
session_start();

// Hancurkan semua data session login di server
session_unset();
session_destroy();

// Tendang kembali ke halaman login router
header("Location: login.php");
exit;
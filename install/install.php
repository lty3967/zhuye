<?php
session_start();

// 定义安装状态
define('INSTALL_ROOT', dirname(__DIR__));
$lock_file = INSTALL_ROOT . '/config/install.lock';

// 检查是否已安装
if (file_exists($lock_file)) {
    header('Location: /');
    exit;
}

// 自动跳转到第一步
header('Location: step1.php');
exit;
?>
<?php
// 定义安装状态
define('INSTALL_ROOT', dirname(__DIR__));
$lock_file = INSTALL_ROOT . '/config/install.lock';
$config_file = INSTALL_ROOT . '/install/install_config.json';

// 检查是否已安装
if (file_exists($lock_file)) {
    header('Location: /');
    exit;
}

// 确保 config 目录存在
$config_dir = INSTALL_ROOT . '/config';
if (!is_dir($config_dir)) {
    if (!mkdir($config_dir, 0755, true)) {
        die('<div style="text-align:center;padding:50px;color:#ff4757;font-size:18px;">
            <h2>错误：无法创建 config 目录</h2>
            <p>请在网站根目录手动创建 config 目录，并设置权限为 755 或 777</p>
        </div>');
    }
}

// 加载已保存的配置
$saved_config = [];
if (file_exists($config_file)) {
    $config_content = file_get_contents($config_file);
    if ($config_content) {
        $saved_config = json_decode($config_content, true) ?: [];
    }
}

// 存储配置信息到文件
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step2'])) {
    $install_config = [
        'db_host' => $_POST['db_host'] ?? 'localhost',
        'db_name' => $_POST['db_name'] ?? '',
        'db_user' => $_POST['db_user'] ?? '',
        'db_pass' => $_POST['db_pass'] ?? '',
        'site_url' => $_POST['site_url'] ?? '',
        'admin_user' => $_POST['admin_user'] ?? '',
        'admin_pass' => $_POST['admin_pass'] ?? '',
        'admin_email' => $_POST['admin_email'] ?? '',
        'test_status' => 'pending',
        'test_error' => ''
    ];
    
    // 保存配置到文件
    file_put_contents($config_file, json_encode($install_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // 测试数据库连接
    $test_result = '';
    $test_success = false;
    
    try {
        $pdo = new PDO(
            "mysql:host={$install_config['db_host']}",
            $install_config['db_user'],
            $install_config['db_pass']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 尝试创建数据库
        $db_name = $install_config['db_name'];
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$db_name`");
        
        $test_success = true;
        $test_result = '数据库连接成功！';
        
    } catch (PDOException $e) {
        $test_result = '数据库连接失败: ' . $e->getMessage();
    }
    
    // 更新测试结果
    $install_config['test_status'] = $test_success ? 'success' : 'error';
    $install_config['test_error'] = $test_result;
    file_put_contents($config_file, json_encode($install_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // 重定向
    if ($test_success) {
        header('Location: step3.php');
    } else {
        header('Location: step2.php?error=' . urlencode($test_result));
    }
    exit;
}

// 获取错误信息
$error_message = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>安装向导 - 数据库配置</title>
    <link rel="shortcut icon" href="/assets/img/favicon.ico">
    <link rel="stylesheet" href="/assets/cucss/style.css" media="screen" type="text/css">
    <link rel="stylesheet" href="/assets/cucss/demo.css" type="text/css">
    <style>
        .install-wrapper {
            max-width: 720px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .install-header {
            text-align: center;
            padding: 3rem 0 2rem;
        }
        
        .install-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            color: #161209;
        }
        
        .dark-theme .install-header h1 {
            color: #a9a9b3;
        }
        
        .install-header p {
            font-size: 1.1rem;
            color: #666;
        }
        
        .dark-theme .install-header p {
            color: #888;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 2rem 0 3rem;
            position: relative;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f0f0f0;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            border: 2px solid #ddd;
            transition: all 0.3s ease;
        }
        
        .dark-theme .step-circle {
            background: #333;
            border-color: #444;
            color: #888;
        }
        
        .step-circle.active {
            background: #28df99;
            border-color: #28df99;
            color: white;
        }
        
        .step-circle.completed {
            background: #28df99;
            border-color: #28df99;
            color: white;
        }
        
        .step-text {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #666;
        }
        
        .dark-theme .step-text {
            color: #888;
        }
        
        .step.active .step-text {
            color: #161209;
            font-weight: 500;
        }
        
        .dark-theme .step.active .step-text {
            color: #fff;
        }
        
        .step-line {
            width: 150px;
            height: 2px;
            background: #ddd;
            margin: 0 10px;
        }
        
        .dark-theme .step-line {
            background: #444;
        }
        
        .step-line.completed {
            background: #28df99;
        }
        
        .install-content {
            background: #fff;
            border-radius: 12px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .dark-theme .install-content {
            background: #333;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        
        .install-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2rem 0;
            border-top: 1px solid #eee;
        }
        
        .dark-theme .install-footer {
            border-top-color: #444;
        }
        
        .btn {
            padding: 0.8rem 2rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-primary {
            background: #28df99;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1cc885;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #666;
        }
        
        .dark-theme .btn-secondary {
            background: #444;
            color: #ddd;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .dark-theme .btn-secondary:hover {
            background: #555;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn:disabled:hover {
            transform: none;
        }
        
        .config-form {
            margin-top: 2rem;
        }
        
        .form-group {
            margin-bottom: 2.5rem;
            position: relative;
        }
        
        .input-container {
            position: relative;
            background: #f8f9fa;
            border-radius: 12px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .dark-theme .input-container {
            background: #2a2a2a;
        }
        
        .input-container:hover {
            background: #f1f3f5;
        }
        
        .dark-theme .input-container:hover {
            background: #333;
        }
        
        .input-container:focus-within {
            background: #fff;
            border-color: #28df99;
        }
        
        .dark-theme .input-container:focus-within {
            background: #2a2a2a;
            border-color: #28df99;
        }
        
        .form-input {
            width: 100%;
            padding: 1.5rem 1.5rem 0.8rem;
            border: none;
            background: transparent;
            font-size: 16px;
            line-height: 1.5;
            transition: all 0.3s ease;
            color: #161209;
        }
        
        .dark-theme .form-input {
            color: #ddd;
        }
        
        .input-label {
            position: absolute;
            left: 1.5rem;
            top: 1.5rem;
            color: #666;
            pointer-events: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
        }
        
        .dark-theme .input-label {
            color: #888;
        }
        
        .icon {
            width: 20px;
            height: 20px;
            fill: #999;
            transition: fill 0.3s ease;
        }
        
        .dark-theme .icon {
            fill: #888;
        }
        
        .form-input:focus + .input-label,
        .form-input:not(:placeholder-shown) + .input-label {
            top: 0.8rem;
            transform: none;
            font-size: 12px;
            color: #28df99;
        }
        
        .dark-theme .form-input:focus + .input-label,
        .dark-theme .form-input:not(:placeholder-shown) + .input-label {
            color: #28df99;
        }
        
        .form-input:focus + .input-label .icon,
        .form-input:not(:placeholder-shown) + .input-label .icon {
            fill: #28df99;
        }
        
        .form-input:focus {
            outline: none;
            box-shadow: none;
        }
        
        .form-help {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.8rem;
            padding-left: 1.5rem;
            opacity: 0.8;
        }
        
        .dark-theme .form-help {
            color: #888;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
        
        .database-status {
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid;
        }
        
        .database-success {
            background: #e8f8f1;
            border-left-color: #28df99;
            color: #006442;
        }
        
        .dark-theme .database-success {
            background: rgba(40, 223, 153, 0.1);
            border-left-color: #28df99;
            color: #28df99;
        }
        
        .database-error {
            background: #ffeaea;
            border-left-color: #ff4757;
            color: #d63031;
        }
        
        .dark-theme .database-error {
            background: rgba(255, 71, 87, 0.1);
            border-left-color: #ff4757;
            color: #ff6b81;
        }
        
        .password-error {
            background: #ffeaea;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin: 1rem 0 2rem;
            color: #d63031;
            display: none;
        }
        
        .dark-theme .password-error {
            background: rgba(255, 71, 87, 0.1);
            color: #ff6b81;
        }
        
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top: 3px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .toggle-theme {
            position: fixed;
            top: 20px;
            right: 20px;
        }
        
        hr {
            border: none;
            border-top: 1px solid #eee;
            margin: 3rem 0 2rem;
        }
        
        .dark-theme hr {
            border-top-color: #444;
        }
        
        .section-title {
            font-size: 1.4rem;
            margin-bottom: 1.5rem;
            color: #161209;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #28df99;
        }
        
        .dark-theme .section-title {
            color: #ddd;
            border-bottom-color: #28df99;
        }
        
        /* 密码强度提示 */
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.8rem;
            display: none;
        }
        
        .strength-weak {
            color: #ff4757;
        }
        
        .strength-medium {
            color: #ffa502;
        }
        
        .strength-strong {
            color: #28df99;
        }
        
        .install-notice {
            background: #e8f8f1;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid #28df99;
        }
        
        .dark-theme .install-notice {
            background: rgba(40, 223, 153, 0.1);
            border-left-color: #28df99;
        }
        
        .install-notice h3 {
            color: #006442;
            margin-bottom: 0.8rem;
        }
        
        .dark-theme .install-notice h3 {
            color: #28df99;
        }
        
        .install-notice p {
            color: #666;
            line-height: 1.6;
        }
        
        .dark-theme .install-notice p {
            color: #888;
        }
        
        .reset-install {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 8px;
            background: #f8f9fa;
            text-align: center;
        }
        
        .dark-theme .reset-install {
            background: #2a2a2a;
        }
        
        .reset-install a {
            color: #ff4757;
            text-decoration: underline;
        }
        
        .dark-theme .reset-install a {
            color: #ff6b81;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <header>
            <nav class="navbar" id="mobile-toggle-theme">
                <div class="container">
                    <div class="menu navbar-right links">
                        <a class="menu-item" href="/">返回首页</a>
                    </div>
                </div>
            </nav>
        </header>
        
        <main class="main">
            <div class="container">
                <div class="install-wrapper">
                    <div class="install-header">
                        <h1>数据库配置</h1>
                        <p>请填写数据库连接信息和管理员账号</p>
                    </div>
                    
                    <div class="step-indicator">
                        <div class="step">
                            <div class="step-circle completed">✓</div>
                            <div class="step-text">系统检查</div>
                        </div>
                        <div class="step-line completed"></div>
                        <div class="step">
                            <div class="step-circle active">2</div>
                            <div class="step-text">数据库配置</div>
                        </div>
                        <div class="step-line"></div>
                        <div class="step">
                            <div class="step-circle inactive">3</div>
                            <div class="step-text">完成安装</div>
                        </div>
                    </div>
                    
                    <div class="install-content">
                        <div class="install-notice">
                            <h3>📋 重要提示：</h3>
                            <p>请确保您有创建数据库的权限。如果连接失败，请检查数据库用户是否有创建数据库的权限。</p>
                        </div>
                        
                        <?php if ($error_message): ?>
                        <div class="database-error database-status">
                            <strong>数据库连接失败：</strong><br>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php
                        // 检查是否已有成功的测试
                        if (file_exists($config_file)) {
                            $config_content = file_get_contents($config_file);
                            $current_config = json_decode($config_content, true);
                            if ($current_config && isset($current_config['test_status']) && $current_config['test_status'] === 'success'): ?>
                            <div class="database-success database-status">
                                <strong>✓ 数据库连接成功！</strong><br>
                                正在跳转到下一步...
                                <script>
                                setTimeout(function() {
                                    window.location.href = 'step3.php';
                                }, 1000);
                                </script>
                            </div>
                            <?php endif;
                        }
                        ?>
                        
                        <form method="post" class="config-form" id="installForm">
                            <h2 class="section-title">数据库信息</h2>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <div class="input-container">
                                        <input type="text" name="db_host" id="db_host" required 
                                               class="form-input" 
                                               value="<?php echo htmlspecialchars($saved_config['db_host'] ?? 'localhost'); ?>" 
                                               placeholder=" ">
                                        <label for="db_host" class="input-label">
                                            <svg class="icon" viewBox="0 0 24 24">
                                                <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V6h16v12zM6 10h2v2H6v-2zm0 4h2v2H6v-2zm4-4h8v2h-8v-2zm0 4h8v2h-8v-2z"/>
                                            </svg>
                                            <span>数据库主机 *</span>
                                        </label>
                                    </div>
                                    <div class="form-help">MySQL 数据库服务器地址，通常为 localhost</div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="input-container">
                                        <input type="text" name="db_name" id="db_name" required 
                                               class="form-input" 
                                               value="<?php echo htmlspecialchars($saved_config['db_name'] ?? ''); ?>" 
                                               placeholder=" ">
                                        <label for="db_name" class="input-label">
                                            <svg class="icon" viewBox="0 0 24 24">
                                                <path d="M12 4C7.9 4 5.1 4.8 4 5.5V7h16V5.5C18.9 4.8 16.1 4 12 4zM4 9v1.5c1.1.7 3.9 1.5 8 1.5s6.9-.8 8-1.5V9H4zm0 4v1.5c1.1.7 3.9 1.5 8 1.5s6.9-.8 8-1.5V13H4zm0 4v1.5c1.1.7 3.9 1.5 8 1.5s6.9-.8 8-1.5V17H4z"/>
                                            </svg>
                                            <span>数据库名称 *</span>
                                        </label>
                                    </div>
                                    <div class="form-help">如果数据库不存在，系统会尝试创建</div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <div class="input-container">
                                        <input type="text" name="db_user" id="db_user" required 
                                               class="form-input" 
                                               value="<?php echo htmlspecialchars($saved_config['db_user'] ?? ''); ?>" 
                                               placeholder=" ">
                                        <label for="db_user" class="input-label">
                                            <svg class="icon" viewBox="0 0 24 24">
                                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                            </svg>
                                            <span>数据库用户名 *</span>
                                        </label>
                                    </div>
                                    <div class="form-help">MySQL 用户名</div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="input-container">
                                        <input type="password" name="db_pass" id="db_pass" 
                                               class="form-input" 
                                               value="<?php echo htmlspecialchars($saved_config['db_pass'] ?? ''); ?>" 
                                               placeholder=" ">
                                        <label for="db_pass" class="input-label">
                                            <svg class="icon" viewBox="0 0 24 24">
                                                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                            </svg>
                                            <span>数据库密码</span>
                                        </label>
                                    </div>
                                    <div class="form-help">MySQL 密码</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="input-container">
                                    <input type="url" name="site_url" id="site_url" required 
                                           class="form-input" 
                                           value="<?php echo htmlspecialchars($saved_config['site_url'] ?? 'http://' . $_SERVER['HTTP_HOST']); ?>" 
                                           placeholder=" ">
                                    <label for="site_url" class="input-label">
                                        <svg class="icon" viewBox="0 0 24 24">
                                            <path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/>
                                        </svg>
                                        <span>网站URL *</span>
                                    </label>
                                </div>
                                <div class="form-help">请输入完整的网站地址，以http://或https://开头</div>
                            </div>
                            
                            <hr>
                            
                            <h2 class="section-title">管理员账号</h2>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <div class="input-container">
                                        <input type="text" name="admin_user" id="admin_user" required 
                                               class="form-input" 
                                               value="<?php echo htmlspecialchars($saved_config['admin_user'] ?? ''); ?>" 
                                               placeholder=" ">
                                        <label for="admin_user" class="input-label">
                                            <svg class="icon" viewBox="0 0 24 24">
                                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                            </svg>
                                            <span>管理员用户名 *</span>
                                        </label>
                                    </div>
                                    <div class="form-help">用于登录后台管理，2-20个字符</div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="input-container">
                                        <input type="email" name="admin_email" id="admin_email" required 
                                               class="form-input" 
                                               value="<?php echo htmlspecialchars($saved_config['admin_email'] ?? ''); ?>" 
                                               placeholder=" ">
                                        <label for="admin_email" class="input-label">
                                            <svg class="icon" viewBox="0 0 24 24">
                                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                                            </svg>
                                            <span>管理员邮箱 *</span>
                                        </label>
                                    </div>
                                    <div class="form-help">用于接收系统通知</div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <div class="input-container">
                                        <input type="password" name="admin_pass" id="admin_pass" required 
                                               class="form-input" 
                                               value="<?php echo htmlspecialchars($saved_config['admin_pass'] ?? ''); ?>" 
                                               placeholder=" ">
                                        <label for="admin_pass" class="input-label">
                                            <svg class="icon" viewBox="0 0 24 24">
                                                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                            </svg>
                                            <span>管理员密码 *</span>
                                        </label>
                                    </div>
                                    <div class="password-strength" id="passwordStrength"></div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="input-container">
                                        <input type="password" name="admin_pass2" id="admin_pass2" required 
                                               class="form-input" 
                                               placeholder=" ">
                                        <label for="admin_pass2" class="input-label">
                                            <svg class="icon" viewBox="0 0 24 24">
                                                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                            </svg>
                                            <span>确认密码 *</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="passwordError" class="password-error">
                                两次输入的密码不一致！
                            </div>
                            
                            <div class="reset-install">
                                <p>如果需要重新开始安装，请<a href="step2.php?reset=1">点击这里重置</a></p>
                            </div>
                            
                            <input type="hidden" name="step2" value="1">
                        </form>
                    </div>
                    
                    <div class="install-footer">
                        <a href="step1.php" class="btn btn-secondary">上一步</a>
                        <button type="button" onclick="submitForm()" class="btn btn-primary" id="submitBtn">
                            <span id="spinner" class="spinner" style="display:none;"></span>
                            <span id="btnText">测试并继续</span>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
    // 主题切换功能
    const themeToggle = document.createElement('div');
    themeToggle.className = 'toggle-theme';
    themeToggle.innerHTML = `
        <input id="themeSwitch" class="switch_default" type="checkbox" />
        <label for="themeSwitch"></label>
    `;
    document.body.appendChild(themeToggle);
    
    // 检查本地存储的主题设置
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-theme');
        document.getElementById('themeSwitch').checked = true;
    }
    
    // 主题切换事件
    const themeSwitch = document.getElementById('themeSwitch');
    if (themeSwitch) {
        themeSwitch.addEventListener('change', function() {
            if (this.checked) {
                document.body.classList.add('dark-theme');
                localStorage.setItem('theme', 'dark');
            } else {
                document.body.classList.remove('dark-theme');
                localStorage.setItem('theme', 'light');
            }
        });
    }
    
    // 密码强度检查函数
    function checkPasswordStrength(password) {
        let strength = 0;
        const strengthText = document.getElementById('passwordStrength');
        
        if (!password) {
            strengthText.style.display = 'none';
            return;
        }
        
        // 检查长度
        if (password.length >= 8) strength++;
        if (password.length >= 12) strength++;
        
        // 检查是否包含数字
        if (/\d/.test(password)) strength++;
        
        // 检查是否包含小写字母
        if (/[a-z]/.test(password)) strength++;
        
        // 检查是否包含大写字母
        if (/[A-Z]/.test(password)) strength++;
        
        // 检查是否包含特殊字符
        if (/[^a-zA-Z0-9]/.test(password)) strength++;
        
        // 显示强度提示
        strengthText.style.display = 'block';
        
        if (strength <= 2) {
            strengthText.innerHTML = '密码强度：<span class="strength-weak">弱</span>';
        } else if (strength <= 4) {
            strengthText.innerHTML = '密码强度：<span class="strength-medium">中</span>';
        } else {
            strengthText.innerHTML = '密码强度：<span class="strength-strong">强</span>';
        }
    }
    
    // 初始化输入框标签位置
    function initInputLabels() {
        document.querySelectorAll('.form-input').forEach(input => {
            const label = input.nextElementSibling;
            if (input.value) {
                label.style.top = '0.8rem';
                label.style.fontSize = '12px';
                label.style.color = '#28df99';
            }
            
            // 监听输入事件
            input.addEventListener('input', function() {
                if (this.value) {
                    label.style.top = '0.8rem';
                    label.style.fontSize = '12px';
                    label.style.color = '#28df99';
                } else {
                    label.style.top = '1.5rem';
                    label.style.fontSize = '16px';
                    label.style.color = '';
                }
                
                // 如果是密码输入框，检查强度
                if (this.id === 'admin_pass') {
                    checkPasswordStrength(this.value);
                }
            });
        });
    }
    
    // 初始化
    initInputLabels();
    
    function submitForm() {
        const form = document.getElementById('installForm');
        const password = document.getElementById('admin_pass').value;
        const password2 = document.getElementById('admin_pass2').value;
        const errorDiv = document.getElementById('passwordError');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const spinner = document.getElementById('spinner');
        
        // 验证用户名
        const adminUser = document.getElementById('admin_user').value;
        if (adminUser.length < 2 || adminUser.length > 20) {
            alert('用户名长度需在2-20个字符之间');
            return false;
        }
        
        // 验证密码
        if (password !== password2) {
            errorDiv.style.display = 'block';
            return false;
        } else {
            errorDiv.style.display = 'none';
        }
        
        // 验证密码强度
        if (password.length < 6) {
            alert('密码长度至少6位');
            return false;
        }
        
        // 验证网站URL
        const siteUrl = form.querySelector('input[name="site_url"]').value;
        if (!siteUrl.match(/^https?:\/\//i)) {
            alert('网站URL必须包含http://或https://');
            return false;
        }
        
        // 验证数据库名称
        const dbName = document.getElementById('db_name').value;
        if (!dbName) {
            alert('请输入数据库名称');
            return false;
        }
        
        // 验证数据库用户名
        const dbUser = document.getElementById('db_user').value;
        if (!dbUser) {
            alert('请输入数据库用户名');
            return false;
        }
        
        // 显示加载状态
        btnText.textContent = '正在连接数据库...';
        spinner.style.display = 'inline-block';
        submitBtn.disabled = true;
        
        // 提交表单
        form.submit();
    }
    
    // 监听密码输入框
    document.getElementById('admin_pass').addEventListener('input', function() {
        checkPasswordStrength(this.value);
    });
    
    // 重置功能
    if (window.location.search.includes('reset=1')) {
        // 清除配置文件和可能的session
        if (confirm('确定要重置安装配置吗？')) {
            // 尝试删除配置文件
            fetch('step2.php?action=reset', {method: 'POST'})
                .then(() => {
                    window.location.href = 'step2.php';
                });
        } else {
            // 移除reset参数
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }
    </script>
</body>
</html>
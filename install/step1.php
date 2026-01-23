<?php
// 定义安装状态
define('INSTALL_ROOT', dirname(__DIR__));
$lock_file = INSTALL_ROOT . '/config/install.lock';

// 检查是否已安装
if (file_exists($lock_file)) {
    header('Location: /');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>安装向导 - 龙腾云个人主页系统</title>
    <link rel="shortcut icon" href="/assets/img/favicon.ico">
    <link rel="stylesheet" href="/assets/cucss/style.css" media="screen" type="text/css">
    <link rel="stylesheet" href="/assets/cucss/demo.css" type="text/css">
    <style>
        .install-wrapper {
            max-width: 800px;
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
        
        .requirements-list {
            margin-top: 2rem;
        }
        
        .requirement-item {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            background: #f9f9f9;
            border-radius: 8px;
            margin-bottom: 0.8rem;
            border-left: 4px solid #ddd;
        }
        
        .dark-theme .requirement-item {
            background: #2a2a2a;
            border-left-color: #444;
        }
        
        .requirement-status {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 0.9rem;
        }
        
        .status-success {
            background: #28df99;
            color: white;
        }
        
        .status-error {
            background: #ff4757;
            color: white;
        }
        
        .requirement-info {
            flex: 1;
        }
        
        .requirement-title {
            font-weight: 500;
            margin-bottom: 0.3rem;
            color: #161209;
        }
        
        .dark-theme .requirement-title {
            color: #ddd;
        }
        
        .requirement-desc {
            font-size: 0.9rem;
            color: #666;
        }
        
        .dark-theme .requirement-desc {
            color: #888;
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
        
        .alert {
            padding: 1.2rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid;
        }
        
        .alert-error {
            background: #ffeaea;
            border-left-color: #ff4757;
            color: #d63031;
        }
        
        .dark-theme .alert-error {
            background: rgba(255, 71, 87, 0.1);
            border-left-color: #ff4757;
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
        
        .install-note {
            background: #fff8e1;
            padding: 1.2rem 1.5rem;
            border-radius: 8px;
            margin-top: 2rem;
            border-left: 4px solid #ffc107;
        }
        
        .dark-theme .install-note {
            background: rgba(255, 193, 7, 0.1);
            border-left-color: #ffc107;
        }
        
        .install-note h4 {
            color: #e65100;
            margin-bottom: 0.5rem;
        }
        
        .dark-theme .install-note h4 {
            color: #ffc107;
        }
        
        .install-note p {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .dark-theme .install-note p {
            color: #aaa;
        }
        
        .toggle-theme {
            position: fixed;
            top: 20px;
            right: 20px;
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
                        <h1>龙腾云个人主页安装向导</h1>
                        <p>欢迎使用龙腾云个人主页系统，请按照以下步骤完成安装</p>
                    </div>
                    
                    <div class="step-indicator">
                        <div class="step">
                            <div class="step-circle active">1</div>
                            <div class="step-text">系统检查</div>
                        </div>
                        <div class="step-line"></div>
                        <div class="step">
                            <div class="step-circle inactive">2</div>
                            <div class="step-text">数据库配置</div>
                        </div>
                        <div class="step-line"></div>
                        <div class="step">
                            <div class="step-circle inactive">3</div>
                            <div class="step-text">完成安装</div>
                        </div>
                    </div>
                    
                    <div class="install-content">
                        <h2 style="margin-bottom: 0.5rem; color: #161209;">系统环境检查</h2>
                        <p style="color: #666; margin-bottom: 1.5rem;">请确保以下系统要求已满足，然后继续安装</p>
                        
                        <div class="requirements-list">
                            <?php
                            $all_ok = true;
                            $config_dir = INSTALL_ROOT . '/config';
                            $config_file = $config_dir . '/config.php';
                            
                            $requirements = [
                                'php_version' => [
                                    'name' => 'PHP 版本',
                                    'required' => '7.2+',
                                    'current' => PHP_VERSION,
                                    'check' => function() { return version_compare(PHP_VERSION, '7.2.0', '>='); }
                                ],
                                'pdo_mysql' => [
                                    'name' => 'PDO MySQL 扩展',
                                    'required' => '已安装',
                                    'current' => extension_loaded('pdo_mysql') ? '已安装' : '未安装',
                                    'check' => function() { return extension_loaded('pdo_mysql'); }
                                ],
                                'config_writable' => [
                                    'name' => 'config 目录可写',
                                    'required' => '可写',
                                    'current' => is_writable($config_dir) ? '可写' : '不可写',
                                    'check' => function() use ($config_dir) { 
                                        if (!is_dir($config_dir)) {
                                            return mkdir($config_dir, 0755, true);
                                        }
                                        return is_writable($config_dir); 
                                    }
                                ],
                                'config_file' => [
                                    'name' => 'config.php 文件',
                                    'required' => '可写/可创建',
                                    'current' => file_exists($config_file) ? '已存在' : '可创建',
                                    'check' => function() use ($config_file, $config_dir) { 
                                        if (file_exists($config_file)) {
                                            return is_writable($config_file);
                                        }
                                        return is_writable($config_dir);
                                    }
                                ],
                                'json' => [
                                    'name' => 'JSON 支持',
                                    'required' => '已安装',
                                    'current' => function_exists('json_encode') ? '已安装' : '未安装',
                                    'check' => function() { return function_exists('json_encode'); }
                                ],
                                'mbstring' => [
                                    'name' => 'MBString 扩展',
                                    'required' => '已安装',
                                    'current' => extension_loaded('mbstring') ? '已安装' : '未安装',
                                    'check' => function() { return extension_loaded('mbstring'); }
                                ],
                                'file_uploads' => [
                                    'name' => '文件上传',
                                    'required' => '已启用',
                                    'current' => ini_get('file_uploads') ? '已启用' : '已禁用',
                                    'check' => function() { return ini_get('file_uploads'); }
                                ],
                                'session' => [
                                    'name' => 'Session 支持',
                                    'required' => '已启用',
                                    'current' => function_exists('session_start') ? '已启用' : '已禁用',
                                    'check' => function() { return function_exists('session_start'); }
                                ]
                            ];
                            
                            foreach ($requirements as $key => $req) {
                                $result = $req['check']();
                                $all_ok = $all_ok && $result;
                                $status_class = $result ? 'status-success' : 'status-error';
                                $status_icon = $result ? '✓' : '✗';
                            ?>
                            <div class="requirement-item">
                                <div class="requirement-status <?php echo $status_class; ?>">
                                    <?php echo $status_icon; ?>
                                </div>
                                <div class="requirement-info">
                                    <div class="requirement-title"><?php echo $req['name']; ?></div>
                                    <div class="requirement-desc">
                                        要求: <?php echo $req['required']; ?> | 
                                        当前: <?php echo is_callable($req['current']) ? $req['current']() : $req['current']; ?>
                                    </div>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                        
                        <?php if (!$all_ok): ?>
                        <div class="alert alert-error">
                            <strong>提示：</strong> 您的服务器环境不满足系统要求，请修复以上问题后刷新页面。
                        </div>
                        <?php endif; ?>
                        
                        <div class="install-note">
                            <h4>安装说明</h4>
                            <p>1. 请确保您已创建MySQL数据库<br>
                            2. 确保config目录有写入权限<br>
                            3. 建议在安装前备份现有数据</p>
                        </div>
                    </div>
                    
                    <div class="install-footer">
                        <a href="../" class="btn btn-secondary">返回首页</a>
                        <button onclick="location.reload()" class="btn btn-secondary">重新检查</button>
                        <?php if ($all_ok): ?>
                        <a href="step2.php" class="btn btn-primary">下一步</a>
                        <?php else: ?>
                        <button class="btn btn-primary" disabled>下一步</button>
                        <?php endif; ?>
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
    document.getElementById('themeSwitch').addEventListener('change', function() {
        if (this.checked) {
            document.body.classList.add('dark-theme');
            localStorage.setItem('theme', 'dark');
        } else {
            document.body.classList.remove('dark-theme');
            localStorage.setItem('theme', 'light');
        }
    });
    </script>
</body>
</html>

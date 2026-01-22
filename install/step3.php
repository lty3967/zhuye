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

// 检查是否有配置
if (!file_exists($config_file)) {
    header('Location: step2.php');
    exit;
}

// 加载配置
$config_content = file_get_contents($config_file);
$config = json_decode($config_content, true);

if (!$config || !isset($config['test_status']) || $config['test_status'] !== 'success') {
    header('Location: step2.php');
    exit;
}

// 处理安装请求
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        // 连接数据库
        $pdo = new PDO(
            "mysql:host={$config['db_host']}",
            $config['db_user'],
            $config['db_pass']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 选择数据库
        $pdo->exec("USE `{$config['db_name']}`");
        
        // 创建数据表
        $table_prefix = $config['db_prefix'];
        
        // 1. admin_users 表
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$table_prefix}admin_users` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) NOT NULL UNIQUE,
                `password_hash` VARCHAR(255) NOT NULL,
                `email` VARCHAR(100) NOT NULL,
                `status` TINYINT DEFAULT 1 COMMENT '1-正常 0-禁用',
                `last_login` DATETIME DEFAULT NULL,
                `failed_attempts` INT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci
        ");
        
        // 2. email_config 表
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$table_prefix}email_config` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `smtp_host` VARCHAR(100) DEFAULT '',
                `smtp_port` INT DEFAULT 465,
                `smtp_secure` VARCHAR(10) DEFAULT 'ssl' COMMENT 'ssl, tls',
                `smtp_username` VARCHAR(100) DEFAULT '',
                `smtp_password` VARCHAR(255) DEFAULT '',
                `from_email` VARCHAR(100) DEFAULT '',
                `from_name` VARCHAR(100) DEFAULT '',
                `is_active` TINYINT DEFAULT 0 COMMENT '1-启用 0-禁用',
                `test_email` VARCHAR(100) DEFAULT '',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci
        ");
        
        // 3. forbidden_words 表
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$table_prefix}forbidden_words` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `word` VARCHAR(50) NOT NULL UNIQUE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci
        ");
        
        // 4. friend_links 表
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$table_prefix}friend_links` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `site_name` VARCHAR(100) NOT NULL,
                `site_url` VARCHAR(255) NOT NULL,
                `description` VARCHAR(500) NOT NULL,
                `email` VARCHAR(100) NOT NULL,
                `status` TINYINT DEFAULT 0 COMMENT '0-待审核 1-已通过',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci
        ");
        
        // 5. messages 表
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$table_prefix}messages` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(50) NOT NULL,
                `contact` VARCHAR(100) NOT NULL,
                `content` TEXT NOT NULL,
                `status` TINYINT DEFAULT 0 COMMENT '0-待审核 1-已通过',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci
        ");
        
        // 6. sessions 表
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$table_prefix}sessions` (
                `id` VARCHAR(128) PRIMARY KEY,
                `data` TEXT,
                `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci
        ");
        
        // 插入初始数据
        $password_hash = password_hash($config['admin_pass'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO `{$table_prefix}admin_users` 
            (username, password_hash, email, status) 
            VALUES (?, ?, ?, 1)
        ");
        $stmt->execute([$config['admin_user'], $password_hash, $config['admin_email']]);
        
        // 插入禁用词
        $forbidden_words = [
            'sb', '你妈', '傻逼', '垃圾', '尼玛', '操', '擦', 
            '煞笔', '色情', '草', '诈骗', '赌博', '辣鸡'
        ];
        
        $stmt = $pdo->prepare("INSERT INTO `{$table_prefix}forbidden_words` (word) VALUES (?)");
        foreach ($forbidden_words as $word) {
            try {
                $stmt->execute([$word]);
            } catch (PDOException $e) {
                // 忽略重复词
            }
        }
        
        // 创建配置文件
        $config_content = "<?php\n";
        $config_content .= "// 数据库配置\n";
        $config_content .= "define('DB_HOST', '" . addslashes($config['db_host']) . "');\n";
        $config_content .= "define('DB_NAME', '" . addslashes($config['db_name']) . "');\n";
        $config_content .= "define('DB_USER', '" . addslashes($config['db_user']) . "');\n";
        $config_content .= "define('DB_PASS', '" . addslashes($config['db_pass']) . "');\n";
        $config_content .= "define('TABLE_PREFIX', '" . addslashes($config['db_prefix']) . "');\n";
        $config_content .= "\n";
        $config_content .= "// 网站配置\n";
        $config_content .= "define('SITE_URL', '" . addslashes(rtrim($config['site_url'], '/')) . "');\n";
        $config_content .= "define('SITE_NAME', '友情链接管理系统');\n";
        $config_content .= "\n";
        $config_content .= "// 数据库连接\n";
        $config_content .= "try {\n";
        $config_content .= "    \$pdo = new PDO(\n";
        $config_content .= "        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',\n";
        $config_content .= "        DB_USER,\n";
        $config_content .= "        DB_PASS,\n";
        $config_content .= "        [\n";
        $config_content .= "            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n";
        $config_content .= "            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n";
        $config_content .= "            PDO::ATTR_EMULATE_PREPARES => false\n";
        $config_content .= "        ]\n";
        $config_content .= "    );\n";
        $config_content .= "} catch (PDOException \$e) {\n";
        $config_content .= "    die('数据库连接失败: ' . \$e->getMessage());\n";
        $config_content .= "}\n";
        $config_content .= "?>";
        
        // 写入配置文件
        $config_main_file = INSTALL_ROOT . '/config/config.php';
        if (file_put_contents($config_main_file, $config_content) === false) {
            throw new Exception('无法写入配置文件，请检查config目录权限');
        }
        
        // 创建安装锁文件
        $lock_file = INSTALL_ROOT . '/config/install.lock';
        file_put_contents($lock_file, 'Installed at: ' . date('Y-m-d H:i:s') . "\n");
        file_put_contents($lock_file, 'Version: 1.0.0' . "\n", FILE_APPEND);
        
        $success = true;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>安装向导 - 完成安装</title>
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
            justify-content: center;
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
        
        .install-result {
            text-align: center;
        }
        
        .result-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
        }
        
        .success-icon {
            background: #28df99;
            color: white;
        }
        
        .error-icon {
            background: #ff4757;
            color: white;
        }
        
        .result-title {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            color: #161209;
        }
        
        .dark-theme .result-title {
            color: #ddd;
        }
        
        .result-message {
            color: #666;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        
        .dark-theme .result-message {
            color: #888;
        }
        
        .login-info {
            background: #f9f9f9;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1.5rem 0;
            text-align: left;
        }
        
        .dark-theme .login-info {
            background: #2a2a2a;
        }
        
        .login-info h3 {
            margin-bottom: 1rem;
            color: #161209;
        }
        
        .dark-theme .login-info h3 {
            color: #ddd;
        }
        
        .info-item {
            margin: 0.8rem 0;
            padding-left: 1.2rem;
            position: relative;
        }
        
        .info-item:before {
            content: '•';
            position: absolute;
            left: 0;
            color: #28df99;
        }
        
        .delete-status {
            margin-top: 2rem;
            padding: 1.5rem;
            border-radius: 8px;
            background: #f9f9f9;
            text-align: left;
        }
        
        .dark-theme .delete-status {
            background: #2a2a2a;
        }
        
        .delete-status h4 {
            margin-bottom: 1rem;
            color: #161209;
        }
        
        .dark-theme .delete-status h4 {
            color: #ddd;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f4f6;
            border-top: 4px solid #28df99;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 20px auto;
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
        
        .install-actions {
            margin-top: 2rem;
        }
        
        .install-actions .btn {
            margin: 0 0.5rem;
        }
        
        .install-notice {
            background: #fff8e1;
            padding: 1.2rem 1.5rem;
            border-radius: 8px;
            margin-top: 2rem;
            border-left: 4px solid #ffc107;
        }
        
        .dark-theme .install-notice {
            background: rgba(255, 193, 7, 0.1);
            border-left-color: #ffc107;
        }
        
        .install-notice h4 {
            color: #e65100;
            margin-bottom: 0.5rem;
        }
        
        .dark-theme .install-notice h4 {
            color: #ffc107;
        }
        
        .install-notice p {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .dark-theme .install-notice p {
            color: #aaa;
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
                        <h1>完成安装</h1>
                        <p>系统安装即将完成，请稍候...</p>
                    </div>
                    
                    <div class="step-indicator">
                        <div class="step">
                            <div class="step-circle completed">✓</div>
                            <div class="step-text">系统检查</div>
                        </div>
                        <div class="step-line completed"></div>
                        <div class="step">
                            <div class="step-circle completed">✓</div>
                            <div class="step-text">数据库配置</div>
                        </div>
                        <div class="step-line completed"></div>
                        <div class="step">
                            <div class="step-circle active">3</div>
                            <div class="step-text">完成安装</div>
                        </div>
                    </div>
                    
                    <div class="install-content">
                        <?php if ($error): ?>
                        <div class="install-result">
                            <div class="result-icon error-icon">✗</div>
                            <div class="result-title">安装失败</div>
                            <div class="result-message"><?php echo htmlspecialchars($error); ?></div>
                            <a href="step2.php" class="btn btn-primary">返回上一步</a>
                        </div>
                        <?php elseif ($success): ?>
                        <div class="install-result">
                            <div class="result-icon success-icon">✓</div>
                            <div class="result-title">安装成功！</div>
                            <div class="result-message">
                                恭喜！友情链接管理系统已成功安装。<br>
                                您可以通过以下信息登录后台管理：
                            </div>
                            
                            <div class="login-info">
                                <h3>管理员登录信息：</h3>
                                <div class="info-item">用户名：<strong><?php echo htmlspecialchars($config['admin_user']); ?></strong></div>
                                <div class="info-item">邮箱：<strong><?php echo htmlspecialchars($config['admin_email']); ?></strong></div>
                                <div class="info-item">密码：您在安装时设置的密码</div>
                            </div>
                            
                            <div class="install-notice">
                                <h4>重要提示：</h4>
                                <p>1. 请立即删除 install 目录，以防被恶意重装<br>
                                   2. 后台地址：<code>/admin/</code>（如果admin目录不存在，请创建）<br>
                                   3. 建议修改管理员密码以确保安全</p>
                            </div>
                            
                            <?php
                            // 自动删除安装文件
                            $files_to_delete = [
                                'install.php',
                                'step1.php', 
                                'step2.php',
                                'step3.php',
                                'install_config.json'
                            ];
                            
                            $delete_results = [];
                            foreach ($files_to_delete as $file) {
                                if (file_exists($file)) {
                                    if (unlink($file)) {
                                        $delete_results[$file] = '✓ 已删除';
                                    } else {
                                        $delete_results[$file] = '✗ 删除失败，请手动删除';
                                    }
                                } else {
                                    $delete_results[$file] = '⏺ 文件不存在';
                                }
                            }
                            ?>
                            
                            <div class="install-actions">
                                <a href="../" class="btn btn-primary">访问首页</a>
                                <a href="../admin/" class="btn btn-primary">进入后台</a>
                            </div>
                        </div>
                        <?php else: ?>
                        <form method="post" id="installForm">
                            <div class="install-result" id="installing">
                                <div class="spinner" id="loadingSpinner"></div>
                                <div class="result-title">正在安装...</div>
                                <div class="result-message" id="statusMessage">正在创建数据库表和配置文件，请稍候...</div>
                            </div>
                            
                            <div class="install-footer">
                                <button type="button" onclick="startInstall()" class="btn btn-primary" id="installBtn">
                                    开始安装
                                </button>
                            </div>
                            <input type="hidden" name="install" value="1">
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <?php if (!$error && !$success): ?>
    <script>
    function startInstall() {
        const form = document.getElementById('installForm');
        const installBtn = document.getElementById('installBtn');
        const loadingSpinner = document.getElementById('loadingSpinner');
        const statusMessage = document.getElementById('statusMessage');
        
        // 显示加载状态
        installBtn.disabled = true;
        installBtn.innerHTML = '正在安装...';
        
        // 提交表单
        form.submit();
    }
    
    // 如果是回退到本页，自动开始安装
    if (performance.navigation.type === 2) { // 2 表示从历史记录中重新加载
        startInstall();
    }
    </script>
    <?php endif; ?>
    
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
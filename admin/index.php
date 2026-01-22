<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/DBSessionHandler.php';

// 先创建会话处理器
$sessionHandler = new DBSessionHandler($pdo);

// 设置会话保存处理器
session_set_save_handler($sessionHandler, true);

// 设置会话cookie参数
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

// 启动会话
session_start();

// 先检查是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 验证会话安全
if (!isset($_SESSION['ip_address']) || $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
    error_log("IP地址不匹配: 会话IP-" . ($_SESSION['ip_address'] ?? '未设置') . ", 当前IP-" . $_SERVER['REMOTE_ADDR']);
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['user_agent']) || $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    error_log("User-Agent不匹配");
    header('Location: login.php');
    exit;
}

// 会话超时处理（30分钟）
if (time() - $_SESSION['last_activity'] > 1800) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// 更新最后活动时间
$_SESSION['last_activity'] = time();

// 初始化变量
$username = '管理员';
$counts = [
    'pending_links' => 0,
    'approved_links' => 0,
    'pending_messages' => 0,
    'approved_messages' => 0,
    'forbidden' => 0
];

try {
    // 获取管理员信息
    $stmt = $pdo->prepare("
        SELECT username 
        FROM " . TABLE_PREFIX . "admin_users 
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['admin_user_id']]);
    if ($user = $stmt->fetch()) {
        $username = $user['username'];
    }

    // 获取友情链接统计
    $stmt = $pdo->query("
        SELECT 
            SUM(status = 0) AS pending,
            SUM(status = 1) AS approved 
        FROM " . TABLE_PREFIX . "friend_links
    ");
    if ($result = $stmt->fetch()) {
        $counts['pending_links'] = (int)$result['pending'];
        $counts['approved_links'] = (int)$result['approved'];
    }

    // 获取留言统计
    $stmt = $pdo->query("
        SELECT 
            SUM(status = 0) AS pending,
            SUM(status = 1) AS approved 
        FROM " . TABLE_PREFIX . "messages
    ");
    if ($result = $stmt->fetch()) {
        $counts['pending_messages'] = (int)$result['pending'];
        $counts['approved_messages'] = (int)$result['approved'];
    }

    // 获取违禁词数量
    $stmt = $pdo->query("SELECT COUNT(*) FROM " . TABLE_PREFIX . "forbidden_words");
    $counts['forbidden'] = (int)$stmt->fetchColumn();

} catch (PDOException $e) {
    error_log("数据库查询错误: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>龙腾云个人主页后台管理中心</title>
    <link rel="stylesheet" href="/admin/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <!-- 左侧菜单 -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>


    <!-- 右侧内容 -->
    <div class="main-content">
        <!-- 欢迎提示 -->
        <div class="welcome-box">
            <h2 class="welcome-title">欢迎回来，<?= htmlspecialchars($username ?? '管理员') ?>！</h2>
            <p class="welcome-text">您已成功登录龙腾云个人主页系统，请开始管理您的网站内容吧！</p>
        </div>

        <!-- 统计信息 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-title">待审核友链</div>
                <div class="stat-number"><?= $counts['pending_links'] ?? 0 ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-title">已审核友链</div>
                <div class="stat-number"><?= $counts['approved_links'] ?? 0 ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-title">待审核留言</div>
                <div class="stat-number"><?= $counts['pending_messages'] ?? 0 ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-title">已审核留言</div>
                <div class="stat-number"><?= $counts['approved_messages'] ?? 0 ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-title">违禁词数量</div>
                <div class="stat-number"><?= $counts['forbidden'] ?? 0 ?></div>
            </div>
        </div>
        
        <div class="sysinfo-box">
            <h3 class="sysinfo-title">系统信息</h3>
            <div class="sysinfo-grid">
                <?php
                $sysinfo = [
                    '系统程序' => '龙腾云个人主页系统',
                    '程序版本' => 'V1.0.0',
                    '服务器软件' => $_SERVER['SERVER_SOFTWARE'] ?? '未知',
                    '服务器语言' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '未知',
                    '服务器IP' => $_SERVER['SERVER_ADDR'] ?? '未知',
                    'PHP版本' => phpversion(),
                    'POST许可' => ini_get('post_max_size'),
                    '文件上传许可' => ini_get('upload_max_filesize'),
                    '程序最大运行时间' => ini_get('max_execution_time') . 's',
                ];
                
                foreach ($sysinfo as $title => $value): ?>
                    <div class="sysinfo-item">
                        <span class="sysinfo-label"><?= htmlspecialchars($title) ?>：</span>
                        <span class="sysinfo-value"><?= htmlspecialchars($value) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<script src="js/mobile-sidebar.js"></script>
</body>
</html>
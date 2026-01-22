<?php
// 包含配置文件
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/DBSessionHandler.php';

// 初始化会话处理器
$sessionHandler = new DBSessionHandler($pdo);
session_set_save_handler($sessionHandler, true);

// 会话安全设置
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();

// 验证登录状态
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['admin_user_id'])) {
    header('Location: login.php');
    exit;
}

// 会话超时处理（30分钟）
if (time() - $_SESSION['last_activity'] > 1800) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$_SESSION['last_activity'] = time();

// 会话劫持检测
if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
    session_regenerate_id(true);
    session_destroy();
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    session_regenerate_id(true);
    session_destroy();
    header('Location: login.php');
    exit;
}

// 包含功能文件
require_once __DIR__ . '/includes/functions.php';

$page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$search = trim($_GET['search'] ?? '');
$limit = 15;
$offset = ($page - 1) * $limit;

// 处理删除操作
if (isset($_GET['action']) && $_GET['action'] === 'delete_link' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM " . TABLE_PREFIX . "friend_links WHERE id = ?");
        $stmt->execute([$id]);
        
        $redirect_url = "links.php";
        if ($page > 1) $redirect_url .= "?page=" . $page;
        if ($search) $redirect_url .= ($page > 1 ? "&" : "?") . "search=" . urlencode($search);
        
        header("Location: " . $redirect_url);
        exit;
    } catch (PDOException $e) {
        error_log("删除友链失败: " . $e->getMessage());
        $_SESSION['error'] = '删除友链失败';
        header("Location: links.php");
        exit;
    }
}

try {
    $params = [':status' => 1];
    $search_condition = generateSearchCondition($search, ['site_name', 'site_url', 'description'], $params);

    $base_sql = "FROM " . TABLE_PREFIX . "friend_links WHERE status = :status";
    if ($search_condition) $base_sql .= " AND $search_condition";

    $sql = "SELECT * $base_sql ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $links = $stmt->fetchAll();

    $count_sql = "SELECT COUNT(*) $base_sql";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log("数据库查询错误: " . $e->getMessage());
    $_SESSION['error'] = '数据库错误: ' . $e->getMessage();
    $links = [];
    $total = 0;
}

$total_pages = ceil($total / $limit);

// 获取当前管理员用户名
$username = '管理员';
try {
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
} catch (PDOException $e) {
    error_log("获取管理员信息失败: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>已审核友链管理 - 龙腾云个人主页后台管理系统</title>
    <link rel="stylesheet" href="/admin/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .mail-message {
            margin: 12px 0;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            max-width: 100%;
        }
        .mail-message.success {
            background: #ECFDF5;
            color: #065F46;
            border: 1px solid #6EE7B7;
        }
        .mail-message.error {
            background: #FEF2F2;
            color: #991B1B;
            border: 1px solid #FCA5A5;
        }
        .mail-message.warning {
            background: #FFFBEB;
            color: #92400E;
            border: 1px solid #FCD34D;
        }
        .mail-message.info {
            background: #EFF6FF;
            color: #1E40AF;
            border: 1px solid #93C5FD;
        }
    </style>
</head>
<body>
    <!-- 左侧菜单 -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- 右侧内容 -->
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-check-circle"></i> 已审核友链</h1>
            <div class="total-badge">共 <?= $total ?> 条记录</div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
        <div class="mail-message error">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- 操作栏 -->
        <div class="action-bar">
            <form class="search-form" method="get">
                <div class="search-input-group">
                    <input type="text" 
                           name="search" 
                           placeholder="搜索名称、网址或描述..."
                           value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn-search">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <?php if($search): ?>
                <a href="links.php" class="btn-reset">重置筛选</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- 数据表格 -->
        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th width="25%">网站名称</th>
                        <th width="30%">网址</th>
                        <th width="35%">描述</th>
                        <th width="10%">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($links)): ?>
                    <tr>
                        <td colspan="4" class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>当前没有已审核的友情链接</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($links as $link): ?>
                    <tr>
                        <td>
                            <div class="text-ellipsis">
                                <?= htmlspecialchars($link['site_name']) ?>
                            </div>
                        </td>
                        <td>
                            <a href="<?= htmlspecialchars($link['site_url']) ?>" 
                               target="_blank" 
                               class="url-link">
                                <i class="fas fa-external-link-alt"></i>
                                <?= htmlspecialchars($link['site_url']) ?>
                            </a>
                        </td>
                        <td>
                            <div class="text-ellipsis">
                                <?= htmlspecialchars($link['description']) ?>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="?action=delete_link&id=<?= $link['id'] ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>" 
                                   class="btn-icon danger"
                                   title="删除友链"
                                   onclick="return confirm('确定要永久删除该友链吗？')">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- 分页 -->
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <?php if($page > 1): ?>
                <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>" class="page-prev">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>

                <?php for($i=max(1, $page-2); $i<=min($page+2, $total_pages); $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" 
                   class="<?= $i==$page?'active':'' ?>">
                   <?= $i ?>
                </a>
                <?php endfor; ?>

                <?php if($page < $total_pages): ?>
                <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>" class="page-next">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // 操作确认
        document.querySelectorAll('[onclick*="confirm"]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if(!confirm('确定要永久删除该友链吗？')) {
                    e.preventDefault();
                }
            });
        });
    </script>
    
    <script src="js/mobile-sidebar.js"></script>
</body>
</html>
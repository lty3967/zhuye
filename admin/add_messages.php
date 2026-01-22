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

// 处理操作
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    
    try {
        if ($action === 'approve_message') {
            $stmt = $pdo->prepare("UPDATE " . TABLE_PREFIX . "messages SET status = 1 WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = '留言审核通过';
        } elseif ($action === 'delete_message') {
            $stmt = $pdo->prepare("DELETE FROM " . TABLE_PREFIX . "messages WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = '留言已删除';
        }
        
        header("Location: add_messages.php");
        exit;
    } catch (PDOException $e) {
        error_log("操作失败: " . $e->getMessage());
        $_SESSION['error'] = '操作失败: ' . $e->getMessage();
        header("Location: add_messages.php");
        exit;
    }
}

// 分页和搜索
$page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$search = trim($_GET['search'] ?? '');
$limit = 15;
$offset = ($page - 1) * $limit;

try {
    $params = [':status' => 0];
    $search_condition = generateSearchCondition($search, ['name', 'content', 'contact'], $params);

    $base_sql = "FROM " . TABLE_PREFIX . "messages WHERE status = :status";
    if ($search_condition) $base_sql .= " AND $search_condition";

    $sql = "SELECT * $base_sql ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll();

    $count_sql = "SELECT COUNT(*) $base_sql";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log("数据库查询错误: " . $e->getMessage());
    $_SESSION['error'] = '数据库错误: ' . $e->getMessage();
    $messages = [];
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
    <title>待审留言管理 - 龙腾云个人主页后台管理系统</title>
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
    </style>
</head>
<body>
    <!-- 左侧菜单 -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- 右侧内容 -->
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-comment-dots"></i> 待审核留言</h1>
            <div class="total-badge">共 <?= $total ?> 条记录</div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
        <div class="mail-message success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        <div class="mail-message error">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="action-bar">
            <form class="search-form" method="get">
                <div class="search-input-group">
                    <input type="text" 
                           name="search" 
                           placeholder="搜索姓名、内容或联系方式..."
                           value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn-search">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <?php if($search): ?>
                <a href="add_messages.php" class="btn-reset">重置筛选</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th width="20%">提交时间</th>
                        <th width="45%">留言内容</th>
                        <th width="20%">联系方式</th>
                        <th width="15%">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($messages)): ?>
                    <tr>
                        <td colspan="4" class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>当前没有待审核的留言</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($messages as $msg): ?>
                    <tr>
                        <td>
                            <?= date('Y-m-d H:i', strtotime($msg['created_at'])) ?>
                        </td>
                        <td>
                            <div class="text-ellipsis" title="<?= htmlspecialchars($msg['content']) ?>">
                                <?= nl2br(htmlspecialchars(mb_strimwidth($msg['content'], 0, 100, '...'))) ?>
                            </div>
                        </td>
                        <td>
                            <div class="text-ellipsis">
                                <?= htmlspecialchars($msg['contact']) ?>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="?action=approve_message&id=<?= $msg['id'] ?>" 
                                   class="btn-icon success"
                                   title="通过审核"
                                   onclick="return confirm('确定通过该留言？')">
                                    <i class="fas fa-check"></i>
                                </a>
                                <a href="?action=delete_message&id=<?= $msg['id'] ?>" 
                                   class="btn-icon danger"
                                   title="删除留言"
                                   onclick="return confirm('确定永久删除该留言？')">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

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
                if(!confirm(this.getAttribute('data-confirm') || '确定执行此操作？')) {
                    e.preventDefault();
                }
            });
        });
        
        // 自动隐藏消息
        setTimeout(() => {
            document.querySelectorAll('.mail-message').forEach(msg => {
                msg.style.transition = 'opacity 0.5s ease';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);
    </script>
    
<script src="js/mobile-sidebar.js"></script>
</body>
</html>
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
require_once __DIR__ . '/includes/EmailSender.php';

// 处理拒绝操作（带原因）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_link') {
    $id = (int)$_POST['id'];
    $reject_reason = trim($_POST['reject_reason'] ?? '');
    
    if (empty($reject_reason)) {
        $_SESSION['mail_error'] = '请填写拒绝原因';
        header("Location: apply.php");
        exit;
    }
    
    $emailSender = new EmailSender($pdo);
    
    try {
        // 先获取友链信息
        $stmt = $pdo->prepare("
            SELECT * 
            FROM " . TABLE_PREFIX . "friend_links 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $link = $stmt->fetch();
        
        if ($link) {
            // 删除记录
            $stmt = $pdo->prepare("
                DELETE FROM " . TABLE_PREFIX . "friend_links 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            
            // 发送邮件通知用户（包含拒绝原因）
            if ($emailSender->isEnabled() && !empty($link['email'])) {
                $result = $emailSender->sendLinkRejectionNotification($link, $reject_reason);
                if ($result && $result['success']) {
                    $_SESSION['mail_success'] = '友链已拒绝，并已发送邮件通知用户';
                } else {
                    $_SESSION['mail_error'] = '友链已拒绝，但邮件发送失败: ' . ($result['message'] ?? '未知错误');
                }
            } else {
                $_SESSION['mail_warning'] = '友链已拒绝，但邮件功能未启用或用户未提供邮箱';
            }
            
            header("Location: apply.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log("拒绝友链失败: " . $e->getMessage());
        $_SESSION['mail_error'] = '操作失败: ' . $e->getMessage();
        header("Location: apply.php");
        exit;
    }
}

// GET方式处理（用于通过审核）
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    
    $emailSender = new EmailSender($pdo);
    
    try {
        if ($action === 'approve_link') {
            // 先获取友链信息
            $stmt = $pdo->prepare("
                SELECT * 
                FROM " . TABLE_PREFIX . "friend_links 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            $link = $stmt->fetch();
            
            if ($link) {
                // 更新状态
                $stmt = $pdo->prepare("
                    UPDATE " . TABLE_PREFIX . "friend_links 
                    SET status = 1 
                    WHERE id = ?
                ");
                $stmt->execute([$id]);
                
                // 发送邮件通知用户
                if ($emailSender->isEnabled() && !empty($link['email'])) {
                    $result = $emailSender->sendLinkApprovalNotification($link);
                    if ($result && $result['success']) {
                        $_SESSION['mail_success'] = '友链已审核通过，并已发送邮件通知用户';
                    } else {
                        $_SESSION['mail_error'] = '友链审核通过，但邮件发送失败: ' . ($result['message'] ?? '未知错误');
                    }
                } else {
                    $_SESSION['mail_warning'] = '友链审核通过，但邮件功能未启用或用户未提供邮箱';
                }
                
                header("Location: apply.php");
                exit;
            }
        }
    } catch (PDOException $e) {
        error_log("通过友链失败: " . $e->getMessage());
        $_SESSION['mail_error'] = '操作失败: ' . $e->getMessage();
        header("Location: apply.php");
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
    die("数据库错误: " . $e->getMessage());
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
    <title>待审友链管理 - 龙腾云个人主页后台管理系统</title>
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
        .mail-status {
            margin: 15px 0;
        }
        .mail-status i {
            font-size: 16px;
        }
        
        /* 模态框样式 */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            margin: 0;
            color: #374151;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #6b7280;
        }
        .modal-body {
            padding: 20px;
        }
        .modal-footer {
            padding: 16px 20px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            resize: vertical;
            min-height: 100px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary {
            background-color: #3b82f6;
            color: white;
        }
        .btn-secondary {
            background-color: #6b7280;
            color: white;
        }
        .btn-danger {
            background-color: #ef4444;
            color: white;
        }
    </style>
</head>
<body>
    <!-- 左侧菜单 -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- 右侧内容 -->
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-hourglass-half"></i> 待审核友链</h1>
            <div class="total-badge">共 <?= $total ?> 条记录</div>
        </div>

        <div class="mail-status">
            <?php
            // 显示邮件发送状态
            if (isset($_SESSION['mail_success'])): ?>
                <div class="mail-message success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($_SESSION['mail_success']) ?>
                </div>
                <?php unset($_SESSION['mail_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['mail_error'])): ?>
                <div class="mail-message error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($_SESSION['mail_error']) ?>
                </div>
                <?php unset($_SESSION['mail_error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['mail_warning'])): ?>
                <div class="mail-message warning">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($_SESSION['mail_warning']) ?>
                </div>
                <?php unset($_SESSION['mail_warning']); ?>
            <?php endif; ?>
            
            <?php
            // 检查邮件功能是否启用
            $emailSender = new EmailSender($pdo);
            if (!$emailSender->isEnabled()): ?>
                <div class="mail-message info">
                    <i class="fas fa-info-circle"></i>
                    邮件通知功能未启用，审核结果将不会通过邮件通知用户。请前往<a href="email_config.php" style="color: #1E40AF; text-decoration: underline; margin-left: 5px;">发信功能配置</a>启用邮件功能。
                </div>
            <?php endif; ?>
        </div>

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
                <a href="apply.php" class="btn-reset">重置筛选</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th width="20%">网站名称</th>
                        <th width="25%">网址</th>
                        <th width="25%">描述</th>
                        <th width="20%">联系邮箱</th>
                        <th width="10%">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($links)): ?>
                    <tr>
                        <td colspan="5" class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>当前没有待审核的友情链接</p>
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
                            <div class="text-ellipsis">
                                <?= !empty($link['email']) ? htmlspecialchars($link['email']) : '<span style="color:#999;">未提供</span>' ?>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="?action=approve_link&id=<?= $link['id'] ?>" 
                                   class="btn-icon success"
                                   title="通过审核"
                                   onclick="return confirm('确定通过该友链？')">
                                    <i class="fas fa-check"></i>
                                </a>
                                <button type="button" 
                                        class="btn-icon danger reject-btn"
                                        title="拒绝申请"
                                        data-id="<?= $link['id'] ?>"
                                        data-name="<?= htmlspecialchars($link['site_name']) ?>"
                                    style="border: none;">
                                    <i class="fas fa-times"></i>
                                </button>
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

    <!-- 拒绝原因模态框 -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-times-circle"></i> 拒绝友链申请</h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <form method="post" id="rejectForm">
                <input type="hidden" name="action" value="reject_link">
                <input type="hidden" name="id" id="rejectId">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="siteInfo">申请信息</label>
                        <div id="siteInfo" style="padding: 8px 12px; background: #f3f4f6; border-radius: 4px;">
                            <!-- 动态显示网站信息 -->
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="reject_reason">拒绝原因 <span style="color: #ef4444;">*</span></label>
                        <textarea name="reject_reason" id="reject_reason" required 
                                  placeholder="请详细说明拒绝的原因，这将通过邮件发送给申请人..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close">取消</button>
                    <button type="submit" class="btn btn-danger">确认拒绝</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // 拒绝按钮点击事件
        document.querySelectorAll('.reject-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                // 设置表单数据
                document.getElementById('rejectId').value = id;
                document.getElementById('siteInfo').textContent = `网站名称: ${name} (ID: ${id})`;
                
                // 显示模态框
                document.getElementById('rejectModal').style.display = 'block';
            });
        });
        
        // 关闭模态框
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('rejectModal').style.display = 'none';
            });
        });
        
        // 点击模态框外部关闭
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('rejectModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
        
        // 表单提交验证
        document.getElementById('rejectForm').addEventListener('submit', function(e) {
            const reason = document.getElementById('reject_reason').value.trim();
            if (!reason) {
                e.preventDefault();
                alert('请填写拒绝原因');
                return false;
            }
            return true;
        });
        
        // 自动隐藏邮件消息
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
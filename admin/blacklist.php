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

$message = null;
$last_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['word'])) {
    $word = trim($_POST['word']);
    $last_input = htmlspecialchars($word);
    
    try {
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM " . TABLE_PREFIX . "forbidden_words 
            WHERE word = ?
        ");
        $checkStmt->execute([$word]);
        if ($checkStmt->fetchColumn() > 0) {
            throw new PDOException("DUPLICATE:".$word);
        }

        $pdo->prepare("
            INSERT INTO " . TABLE_PREFIX . "forbidden_words (word) 
            VALUES (?)
        ")->execute([$word]);
        
        $message = ['type' => 'success', 'text' => '违禁词添加成功'];
        $last_input = '';
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'DUPLICATE:') === 0) {
            $dupWord = substr($e->getMessage(), 10);
            $message = ['type' => 'error', 'text' => "添加失败：“ {$dupWord} ”已存在，请勿重复提交！"];
        }
        elseif ($e->errorInfo[1] == 1062) {
            preg_match("/'(.+?)'/", $e->errorInfo[2], $matches);
            $dupWord = $matches[1] ?? $word;
            $message = ['type' => 'error', 'text' => "添加失败：'{$dupWord}'已存在"];
        }
        else {
            $message = ['type' => 'error', 'text' => '添加失败：数据库异常'];
        }
    }
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    try {
        if ($_GET['action'] === 'delete_word') {
            $stmt = $pdo->prepare("
                DELETE FROM " . TABLE_PREFIX . "forbidden_words 
                WHERE id = ?
            ");
            $stmt->execute([(int)$_GET['id']]);
            $_SESSION['success'] = '违禁词已删除';
            header("Location: forbidden.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log("删除违禁词失败: " . $e->getMessage());
        $_SESSION['error'] = '操作失败: ' . $e->getMessage();
        header("Location: forbidden.php");
        exit;
    }
}

try {
    $stmt = $pdo->query("
        SELECT * 
        FROM " . TABLE_PREFIX . "forbidden_words 
        ORDER BY word ASC
    ");
    $words = $stmt->fetchAll();
    $total = count($words);
} catch (PDOException $e) {
    error_log("数据库查询错误: " . $e->getMessage());
    $_SESSION['error'] = '数据库错误: ' . $e->getMessage();
    $words = [];
    $total = 0;
}

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
    <title>违禁词管理 - 龙腾云个人主页后台管理系统</title>
    <link rel="stylesheet" href="/admin/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/admin.css">
    <style>
    .form-message {
        margin-top: 12px;
        padding: 12px 16px;
        border-radius: 8px;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
        max-width: 500px;
    }
    .form-message.success {
        background: #ECFDF5;
        color: #065F46;
        border: 1px solid #6EE7B7;
    }
    .form-message.error {
        background: #FEF2F2;
        color: #991B1B;
        border: 1px solid #FCA5A5;
    }
    .form-message i {
        font-size: 16px;
    }
    .form-input-group {
        max-width: 500px;
    }
    .add-form {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .styled-input {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #E5E7EB;
        border-radius: 8px;
        font-size: 14px;
    }
    .btn {
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        border: none;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-primary {
        background: #3B82F6;
        color: white;
    }
    .btn-primary:hover {
        background: #2563EB;
    }
    .with-icon {
        display: inline-flex;
        align-items: center;
    }
    </style>
</head>
<body>
    <!-- 左侧菜单 -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- 右侧内容 -->
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-ban"></i> 违禁词管理</h1>
            <div class="total-badge">共 <?= $total ?> 条记录</div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
        <div class="form-message success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        <div class="form-message error">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="action-bar">
            <form method="post" class="add-form">
                <div class="form-input-group">
                    <input type="text" 
                           name="word" 
                           required
                           placeholder="输入新违禁词（1-20个字符）"
                           pattern=".{1,20}"
                           title="长度1-20个字符"
                           class="styled-input"
                           value="<?= $last_input ?>">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary with-icon">
                        <i class="fas fa-plus-circle"></i>
                        <span>添加</span>
                    </button>
                </div>
                
                <?php if ($message): ?>
                <div class="form-message <?= $message['type'] ?>">
                    <i class="fas fa-<?= $message['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                    <?= htmlspecialchars($message['text']) ?>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th width="60%">违禁词</th>
                        <th width="40%">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($words)): ?>
                    <tr>
                        <td colspan="2" class="empty-state">
                            <i class="fas fa-shield-alt"></i>
                            <p>没有违禁词</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($words as $word): ?>
                    <tr>
                        <td>
                            <div class="text-ellipsis">
                                <?= htmlspecialchars($word['word']) ?>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="?action=delete_word&id=<?= $word['id'] ?>" 
                                   class="btn-icon danger"
                                   title="删除"
                                   onclick="return confirm('确定删除？')">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // 自动隐藏消息
        setTimeout(() => {
            document.querySelectorAll('.form-message').forEach(msg => {
                msg.style.transition = 'opacity 0.5s ease';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);
    </script>
    
<script src="js/mobile-sidebar.js"></script>
</body>
</html>
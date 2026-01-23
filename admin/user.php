<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/DBSessionHandler.php';

$sessionHandler = new DBSessionHandler($pdo);
session_set_save_handler($sessionHandler, true);
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

$error = '';
$success = '';
$userInfo = [];
$currentUsername = '';

try {
    $stmt = $pdo->prepare("
        SELECT id, username, email 
        FROM " . TABLE_PREFIX . "admin_users 
        WHERE id = ? 
        AND status = 1
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['admin_user_id']]);
    $userInfo = $stmt->fetch();
    $currentUsername = $userInfo['username'] ?? '';
} catch (PDOException $e) {
    error_log("管理员信息查询失败: " . $e->getMessage());
    $error = '获取管理员信息失败';
}

// 处理密码修改请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword)) {
        $error = '请输入当前密码';
    } elseif (empty($newPassword)) {
        $error = '请输入新密码';
    } elseif (strlen($newPassword) < 8) {
        $error = '新密码长度至少8位';
    } elseif ($newPassword !== $confirmPassword) {
        $error = '两次输入的新密码不一致';
    } else {
        try {
            // 验证当前密码
            $stmt = $pdo->prepare("
                SELECT password_hash 
                FROM " . TABLE_PREFIX . "admin_users 
                WHERE id = ? 
                AND status = 1
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['admin_user_id']]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = '用户不存在或已被禁用';
            } elseif (!password_verify($currentPassword, $user['password_hash'])) {
                $error = '当前密码输入错误';
            } else {
                // 更新密码
                $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE " . TABLE_PREFIX . "admin_users 
                    SET password_hash = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$newPasswordHash, $_SESSION['admin_user_id']])) {
                    $success = '密码修改成功！请使用新密码登录';
                    
                    // 清除当前密码，防止重复提交
                    unset($_POST['current_password']);
                    unset($_POST['new_password']);
                    unset($_POST['confirm_password']);
                } else {
                    $error = '密码更新失败，请稍后再试';
                }
            }
        } catch (PDOException $e) {
            error_log("密码更新失败: " . $e->getMessage());
            $error = '系统错误，请稍后再试';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员信息 - 龙腾云个人主页后台管理系统</title>
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
    
    .btn.with-icon {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.25rem;
        border: none;
        border-radius: 0.5rem;
        font-weight: 500;
        transition: all 0.2s;
        cursor: pointer;
        text-decoration: none;
        font-size: 14px;
    }
    
    .btn.with-icon:hover {
        transform: translateY(-1px);
    }
    
    .btn.with-icon i {
        font-size: 1.1em;
    }
    
    .btn-primary {
        background: var(--primary-color);
        color: white;
    }
    
    .btn-primary:hover {
        background: var(--secondary-color);
    }
    
    .styled-input[type="number"] {
        -moz-appearance: textfield;
    }
    
    .styled-input[type="number"]::-webkit-outer-spin-button,
    .styled-input[type="number"]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    
    .form-actions.grid-layout {
        align-items: center;
    }
    
    .button-group {
        display: flex;
        gap: 10px;
    }
    
    .loading-spinner {
        display: none;
        width: 20px;
        height: 20px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid var(--primary-color);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    </style>
</head>
<body>
    <!-- 左侧菜单 -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <!-- 右侧内容 -->
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-user-cog"></i> 账号信息管理</h1>
        </div>

        <div class="action-bar">
            <div class="form-container">
                <?php if ($success): ?>
                <div class="form-message success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="form-message error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <form method="POST" id="passwordForm" autocomplete="off">
                    <input type="hidden" name="update_password" value="1">
                    
                    <!-- 管理员账号 -->
                    <div class="form-input-group grid-layout">
                        <label class="form-label" for="username">管理员账号</label>
                        <div class="input-wrapper">
                            <i class="input-icon fas fa-user-shield"></i>
                            <input type="text" 
                                   id="username"
                                   class="styled-input"
                                   value="<?= htmlspecialchars($currentUsername) ?>"
                                   placeholder="管理员账号"
                                   readonly
                                   style="background: #F9FAFB;">
                        </div>
                    </div>

                    <!-- 邮箱 -->
                    <div class="form-input-group grid-layout">
                        <label class="form-label" for="email">邮箱地址</label>
                        <div class="input-wrapper">
                            <i class="input-icon fas fa-envelope"></i>
                            <input type="text" 
                                   id="email"
                                   class="styled-input"
                                   value="<?= htmlspecialchars($userInfo['email'] ?? '未设置') ?>"
                                   placeholder="邮箱地址"
                                   readonly
                                   style="background: #F9FAFB;">
                        </div>
                    </div>

                    <!-- 当前密码 -->
                    <div class="form-input-group grid-layout">
                        <label class="form-label" for="current_password">当前密码 <span style="color: #ef4444;">*</span></label>
                        <div class="input-container">
                            <div class="input-wrapper">
                                <i class="input-icon fas fa-lock"></i>
                                <input type="password" 
                                       id="current_password"
                                       name="current_password" 
                                       class="styled-input"
                                       required
                                       placeholder="请输入当前密码"
                                       value="<?= isset($_POST['current_password']) ? htmlspecialchars($_POST['current_password']) : '' ?>"
                                       autocomplete="new-password">
                                <button type="button" class="password-toggle" onclick="togglePassword('current_password')" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #9CA3AF; cursor: pointer;">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 新密码 -->
                    <div class="form-input-group grid-layout">
                        <label class="form-label" for="new_password">新密码 <span style="color: #ef4444;">*</span></label>
                        <div class="input-container">
                            <div class="input-wrapper">
                                <i class="input-icon fas fa-key"></i>
                                <input type="password" 
                                       id="new_password"
                                       name="new_password"
                                       class="styled-input"
                                       required
                                       placeholder="请输入新密码（至少8位）"
                                       value="<?= isset($_POST['new_password']) ? htmlspecialchars($_POST['new_password']) : '' ?>"
                                       autocomplete="new-password">
                                <button type="button" class="password-toggle" onclick="togglePassword('new_password')" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #9CA3AF; cursor: pointer;">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-hint">
                                <i class="fas fa-info-circle"></i> 密码长度至少8位，建议包含字母、数字和特殊字符
                            </div>
                        </div>
                    </div>

                    <!-- 确认新密码 -->
                    <div class="form-input-group grid-layout">
                        <label class="form-label" for="confirm_password">确认新密码 <span style="color: #ef4444;">*</span></label>
                        <div class="input-container">
                            <div class="input-wrapper">
                                <i class="input-icon fas fa-key"></i>
                                <input type="password" 
                                       id="confirm_password"
                                       name="confirm_password"
                                       class="styled-input"
                                       required
                                       placeholder="请再次输入新密码"
                                       value="<?= isset($_POST['confirm_password']) ? htmlspecialchars($_POST['confirm_password']) : '' ?>"
                                       autocomplete="new-password">
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #9CA3AF; cursor: pointer;">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 操作按钮 -->
                    <div class="form-actions grid-layout">
                        <div class="placeholder"></div>
                        <div class="button-group">
                            <button type="submit" class="btn with-icon btn-primary">
                                <i class="fas fa-save"></i>
                                <span>修改密码</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // 密码显示/隐藏切换
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        // 表单提交处理
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // 验证密码强度
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('密码长度至少8位');
                return;
            }
            
            // 验证两次密码是否一致
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('两次输入的密码不一致');
                return;
            }
            
            // 显示加载状态
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner"></i> 正在修改...';
        });

        // 5秒后自动隐藏消息
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

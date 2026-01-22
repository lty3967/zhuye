<?php
// 包含配置文件
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/DBSessionHandler.php';
require_once __DIR__ . '/includes/EmailSender.php';

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

$error = '';
$success = '';
$currentUsername = '';

try {
    $stmt = $pdo->prepare("SELECT username FROM " . TABLE_PREFIX . "admin_users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['admin_user_id']]);
    if ($user = $stmt->fetch()) {
        $currentUsername = $user['username'] ?? '';
    }
} catch (PDOException $e) {
    error_log("管理员信息查询失败: " . $e->getMessage());
}

// 创建邮件发送器实例
$emailSender = new EmailSender($pdo);

// 获取当前配置
$emailConfig = $emailSender->getConfig();
if (empty($emailConfig)) {
    $emailConfig = [
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_secure' => 'tls',
        'smtp_username' => '',
        'smtp_password' => '',
        'from_email' => '',
        'from_name' => '网站管理员',
        'is_active' => 0,
        'test_email' => ''
    ];
}

// 处理发送测试邮件请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 检查是发送测试邮件还是保存配置
    if (isset($_POST['action']) && $_POST['action'] === 'send_test_email') {
        $test_email = trim($_POST['test_email'] ?? '');
        
        if (empty($test_email)) {
            echo json_encode(['success' => false, 'message' => '请填写测试邮箱地址']);
            exit;
        }
        
        if (!filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => '邮箱格式不正确']);
            exit;
        }
        
        // 发送测试邮件
        $result = $emailSender->sendTestEmail($test_email);
        echo json_encode($result);
        exit;
    }
    
    // 保存配置的表单处理
    $smtp_host = trim($_POST['smtp_host'] ?? '');
    $smtp_port = intval($_POST['smtp_port'] ?? 587);
    $smtp_secure = trim($_POST['smtp_secure'] ?? 'tls');
    $smtp_username = trim($_POST['smtp_username'] ?? '');
    $smtp_password = trim($_POST['smtp_password'] ?? '');
    $from_email = trim($_POST['from_email'] ?? '');
    $from_name = trim($_POST['from_name'] ?? '网站管理员');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $test_email = trim($_POST['test_email'] ?? '');
    
    // 验证必填项
    if (empty($smtp_host) || empty($smtp_username) || empty($from_email)) {
        $_SESSION['error'] = '请填写SMTP主机、用户名和发件人邮箱';
    } elseif (!filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = '发件人邮箱格式不正确';
    } elseif (!empty($test_email) && !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = '测试邮箱格式不正确';
    } elseif ($smtp_port < 1 || $smtp_port > 65535) {
        $_SESSION['error'] = '端口号必须在1-65535之间';
    } else {
        try {
            // 检查是否已有配置
            $stmt = $pdo->query("SELECT id FROM " . TABLE_PREFIX . "email_config LIMIT 1");
            if ($stmt->rowCount() > 0) {
                // 更新配置
                // 如果密码为空，则不更新密码字段
                if (empty($smtp_password)) {
                    $sql = "UPDATE " . TABLE_PREFIX . "email_config SET 
                            smtp_host = ?, 
                            smtp_port = ?, 
                            smtp_secure = ?, 
                            smtp_username = ?, 
                            from_email = ?, 
                            from_name = ?, 
                            is_active = ?, 
                            test_email = ?,
                            updated_at = NOW()";
                    
                    $params = [
                        $smtp_host,
                        $smtp_port,
                        $smtp_secure,
                        $smtp_username,
                        $from_email,
                        $from_name,
                        $is_active,
                        $test_email
                    ];
                } else {
                    $sql = "UPDATE " . TABLE_PREFIX . "email_config SET 
                            smtp_host = ?, 
                            smtp_port = ?, 
                            smtp_secure = ?, 
                            smtp_username = ?, 
                            smtp_password = ?, 
                            from_email = ?, 
                            from_name = ?, 
                            is_active = ?, 
                            test_email = ?,
                            updated_at = NOW()";
                    
                    $params = [
                        $smtp_host,
                        $smtp_port,
                        $smtp_secure,
                        $smtp_username,
                        $smtp_password,
                        $from_email,
                        $from_name,
                        $is_active,
                        $test_email
                    ];
                }
                
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute($params)) {
                    $_SESSION['success'] = 'SMTP配置更新成功！';
                    
                    echo '<script>window.location.href = "email.php?refresh=" + Date.now();</script>';
                    exit;
                } else {
                    $_SESSION['error'] = '更新SMTP配置失败';
                }
            } else {
                // 插入新配置
                $sql = "INSERT INTO " . TABLE_PREFIX . "email_config (
                    smtp_host, 
                    smtp_port, 
                    smtp_secure, 
                    smtp_username, 
                    smtp_password, 
                    from_email, 
                    from_name, 
                    is_active, 
                    test_email
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([
                    $smtp_host,
                    $smtp_port,
                    $smtp_secure,
                    $smtp_username,
                    $smtp_password,
                    $from_email,
                    $from_name,
                    $is_active,
                    $test_email
                ])) {
                    $_SESSION['success'] = 'SMTP配置保存成功！';
                    
                    echo '<script>window.location.href = "email.php?refresh=" + Date.now();</script>';
                    exit;
                } else {
                    $_SESSION['error'] = '保存SMTP配置失败';
                }
            }
        } catch (PDOException $e) {
            error_log("保存SMTP配置失败: " . $e->getMessage());
            $_SESSION['error'] = '保存配置时发生错误：' . $e->getMessage();
        }
    }
    
    // 如果有错误，重新显示页面
    if (isset($_SESSION['error'])) {
        $error = $_SESSION['error'];
    } elseif (isset($_SESSION['success'])) {
        $success = $_SESSION['success'];
    }
} else {
    // 如果是GET请求，检查是否有Session消息
    if (isset($_SESSION['error'])) {
        $error = $_SESSION['error'];
        unset($_SESSION['error']);
    }
    if (isset($_SESSION['success'])) {
        $success = $_SESSION['success'];
        unset($_SESSION['success']);
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>发信功能配置 - 龙腾云个人主页后台管理系统</title>
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
    .form-message.warning {
        background: #FFFBEB;
        color: #92400E;
        border: 1px solid #FCD34D;
    }
    .form-message i {
        font-size: 16px;
    }
    
    .toggle-container {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
    }
    
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #D1D5DB;
        transition: .4s;
        border-radius: 24px;
    }
    
    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    
    input:checked + .toggle-slider {
        background-color: var(--primary-color);
    }
    
    input:checked + .toggle-slider:before {
        transform: translateX(20px);
    }
    
    .toggle-label {
        font-size: 14px;
        color: var(--text-primary);
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
    
    .btn-success {
        background: var(--success-color);
        color: white;
    }
    
    .btn-success:hover {
        background: #059669;
    }
    
    .btn-success:disabled {
        background: #9CA3AF;
        cursor: not-allowed;
        transform: none;
    }
    
    .select-wrapper {
        position: relative;
    }
    
    .select-wrapper:after {
        content: '\f078';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
        pointer-events: none;
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
    
    .test-status {
        margin-top: 10px;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 13px;
    }
    
    .test-status.success {
        background: #ECFDF5;
        color: #065F46;
        border: 1px solid #6EE7B7;
    }
    
    .test-status.error {
        background: #FEF2F2;
        color: #991B1B;
        border: 1px solid #FCA5A5;
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
            <h1><i class="fas fa-envelope"></i> 发信功能配置</h1>
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
                
                <?php if (!$emailSender->isEnabled()): ?>
                <div class="form-message warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    发信功能当前未启用，请填写配置并启用
                </div>
                <?php endif; ?>

                <form method="POST" id="emailConfigForm" autocomplete="off">
                    <!-- SMTP服务器 -->
                    <div class="form-input-group grid-layout">
                        <label class="form-label" for="smtp_host">SMTP服务器</label>
                        <div class="input-wrapper">
                            <i class="input-icon fas fa-server"></i>
                            <input type="text" 
                                   id="smtp_host"
                                   name="smtp_host" 
                                   class="styled-input"
                                   value="<?= htmlspecialchars($emailConfig['smtp_host']) ?>"
                                   placeholder="例如：smtp.qq.com 或 smtp.gmail.com"
                                   required>
                        </div>
                    </div>

                    <!-- 端口 -->
                    <div class="form-input-group grid-layout">
                        <label class="form-label" for="smtp_port">端口</label>
                        <div class="input-wrapper">
                            <i class="input-icon fas fa-plug"></i>
                            <input type="number" 
                                   id="smtp_port"
                                   name="smtp_port" 
                                   class="styled-input"
                                   value="<?= htmlspecialchars($emailConfig['smtp_port']) ?>"
                                   min="1" 
                                   max="65535"
                                   placeholder="587(推荐) 或 465"
                                   required>
                        </div>
                    </div>

                    <!-- 加密方式 -->
                    <div class="form-input-group grid-layout">
                        <label class="form-label" for="smtp_secure">加密方式</label>
                        <div class="input-wrapper">
                            <i class="input-icon fas fa-lock"></i>
                            <select id="smtp_secure" name="smtp_secure" class="styled-input">
                                <option value="tls" <?= $emailConfig['smtp_secure'] === 'tls' ? 'selected' : '' ?>>TLS (推荐)</option>
                                <option value="ssl" <?= $emailConfig['smtp_secure'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                <option value="" <?= empty($emailConfig['smtp_secure']) ? 'selected' : '' ?>>无加密</option>
                            </select>
                        </div>
                    </div>

                    <!-- 用户名 -->
                    <div class="form-input-group grid-layout">
                        <label class="form-label" for="smtp_username">用户名</label>
                        <div class="input-wrapper">
                            <i class="input-icon fas fa-user"></i>
                            <input type="text" 
                                   id="smtp_username"
                                   name="smtp_username" 
                                   class="styled-input"
                                   value="<?= htmlspecialchars($emailConfig['smtp_username']) ?>"
                                   placeholder="您的邮箱账号"
                                   required>
                        </div>
                    </div>

                    <!-- 密码 -->
                    <div class="form-input-group grid-layout">
                        <label class="form-label" for="smtp_password">密码</label>
                        <div class="input-container">
                            <div class="input-wrapper">
                                <i class="input-icon fas fa-key"></i>
                                <input type="password" 
                                       id="smtp_password"
                                       name="smtp_password" 
                                       class="styled-input"
                                       value=""
                                       placeholder="邮箱密码或授权码（留空则不修改）"
                                       autocomplete="new-password">
                            </div>
                            <div class="form-hint">
                                <i class="fas fa-info-circle"></i> 
                                <?php if (strpos($emailConfig['smtp_host'] ?? '', 'qq.com') !== false): ?>
                                    腾讯邮箱需要使用授权码，请在邮箱设置中生成
                                <?php elseif (strpos($emailConfig['smtp_host'] ?? '', 'gmail.com') !== false): ?>
                                    Gmail需要使用应用专用密码
                                <?php else: ?>
                                    注意：密码以明文存储，建议使用专用邮箱账号
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- 发件人邮箱 -->
                    <div class="form-input-group grid-layout">
                        <label class="form-label" for="from_email">发件人邮箱</label>
                        <div class="input-wrapper">
                            <i class="input-icon fas fa-envelope"></i>
                            <input type="email" 
                                   id="from_email"
                                   name="from_email" 
                                   class="styled-input"
                                   value="<?= htmlspecialchars($emailConfig['from_email']) ?>"
                                   placeholder="发件人邮箱地址"
                                   required>
                        </div>
                    </div>

                    <!-- 发件人名称 -->
                    <div class="form-input-group grid-layout">
                        <label class="form-label" for="from_name">发件人名称</label>
                        <div class="input-wrapper">
                            <i class="input-icon fas fa-user-tag"></i>
                            <input type="text" 
                                   id="from_name"
                                   name="from_name" 
                                   class="styled-input"
                                   value="<?= htmlspecialchars($emailConfig['from_name']) ?>"
                                   placeholder="例如：网站管理员">
                        </div>
                    </div>

                    <!-- 启用状态 -->
                    <div class="form-input-group grid-layout">
                        <label class="form-label">启用发信</label>
                        <div class="toggle-container">
                            <label class="toggle-switch">
                                <input type="checkbox" 
                                       name="is_active" 
                                       value="1" 
                                       <?= $emailConfig['is_active'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="toggle-label"><?= $emailConfig['is_active'] ? '已启用' : '已禁用' ?></span>
                        </div>
                    </div>

                    <!-- 测试邮箱 -->
                    <div class="form-input-group grid-layout">
                        <label class="form-label" for="test_email">测试邮箱</label>
                        <div class="input-container">
                            <div class="input-wrapper">
                                <i class="input-icon fas fa-vial"></i>
                                <input type="email" 
                                       id="test_email"
                                       name="test_email" 
                                       class="styled-input"
                                       value="<?= htmlspecialchars($emailConfig['test_email']) ?>"
                                       placeholder="用于测试发信功能的邮箱地址">
                            </div>
                            <div class="form-hint">
                                <i class="fas fa-info-circle"></i> 保存配置后，可在此发送测试邮件验证配置是否正确
                            </div>
                        </div>
                    </div>
                    
                    <!-- 测试结果区域 -->
                    <div id="testResult" style="display: none;"></div>

                    <!-- 操作按钮 -->
                    <div class="form-actions grid-layout">
                        <div class="placeholder"></div>
                        <div class="button-group">
                            <button type="button" 
                                    class="btn with-icon btn-success" 
                                    onclick="sendTestEmail()"
                                    id="testEmailBtn"
                                    <?= empty($emailConfig['test_email']) || !$emailSender->isEnabled() ? 'disabled' : '' ?>>
                                <i class="fas fa-paper-plane"></i>
                                <span id="testBtnText">发送测试邮件</span>
                                <div class="loading-spinner" id="testSpinner"></div>
                            </button>
                            <button type="submit" class="btn with-icon btn-primary">
                                <i class="fas fa-save"></i>
                                <span>保存配置</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
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
        
        // 更新开关状态文本
        const toggleCheckbox = document.querySelector('input[name="is_active"]');
        const toggleLabel = document.querySelector('.toggle-label');
        
        if (toggleCheckbox) {
            toggleCheckbox.addEventListener('change', function() {
                if (toggleLabel) {
                    toggleLabel.textContent = this.checked ? '已启用' : '已禁用';
                }
            });
        }
        
        // 发送测试邮件函数
        async function sendTestEmail() {
            const testEmailInput = document.getElementById('test_email');
            const testBtn = document.getElementById('testEmailBtn');
            const testBtnText = document.getElementById('testBtnText');
            const testSpinner = document.getElementById('testSpinner');
            const testResult = document.getElementById('testResult');
            
            if (!testEmailInput || !testBtn) return;
            
            const email = testEmailInput.value.trim();
            
            if (!email) {
                alert('请先填写测试邮箱地址');
                return;
            }
            
            if (!validateEmail(email)) {
                alert('邮箱格式不正确');
                return;
            }
            
            if (!confirm('确定要向 ' + email + ' 发送测试邮件吗？')) {
                return;
            }
            
            // 显示加载状态
            testBtn.disabled = true;
            testBtnText.textContent = '发送中...';
            if (testSpinner) testSpinner.style.display = 'inline-block';
            if (testResult) testResult.style.display = 'none';
            
            try {
                const formData = new FormData();
                formData.append('action', 'send_test_email');
                formData.append('test_email', email);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    if (testResult) {
                        testResult.innerHTML = `
                            <div class="test-status success">
                                <i class="fas fa-check-circle"></i> ${result.message}
                            </div>
                        `;
                        testResult.style.display = 'block';
                    }
                } else {
                    if (testResult) {
                        testResult.innerHTML = `
                            <div class="test-status error">
                                <i class="fas fa-exclamation-triangle"></i> ${result.message}
                            </div>
                        `;
                        testResult.style.display = 'block';
                    }
                }
                
            } catch (error) {
                if (testResult) {
                    testResult.innerHTML = `
                        <div class="test-status error">
                            <i class="fas fa-exclamation-triangle"></i> 请求失败: ${error.message}
                        </div>
                    `;
                    testResult.style.display = 'block';
                }
            } finally {
                // 恢复按钮状态
                testBtn.disabled = false;
                testBtnText.textContent = '发送测试邮件';
                if (testSpinner) testSpinner.style.display = 'none';
            }
        }
        
        // 邮箱验证函数
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
        
        // 监听测试邮箱输入变化
        const testEmailInput = document.getElementById('test_email');
        const testBtn = document.getElementById('testEmailBtn');
        
        if (testEmailInput && testBtn) {
            testEmailInput.addEventListener('input', function() {
                const email = this.value.trim();
                const isActive = document.querySelector('input[name="is_active"]')?.checked || false;
                
                testBtn.disabled = !email || !isActive;
            });
        }
        
        // 监听启用开关变化
        if (toggleCheckbox && testBtn) {
            toggleCheckbox.addEventListener('change', function() {
                const email = testEmailInput ? testEmailInput.value.trim() : '';
                testBtn.disabled = !email || !this.checked;
            });
        }
        
        // 页面加载时检查按钮状态
        window.addEventListener('load', function() {
            const email = testEmailInput ? testEmailInput.value.trim() : '';
            const isActive = toggleCheckbox ? toggleCheckbox.checked : false;
            if (testBtn) {
                testBtn.disabled = !email || !isActive;
            }
        });
    </script>
    
<script src="js/mobile-sidebar.js"></script>
</body>
</html>
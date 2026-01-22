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

$error = '';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        $stmt = $pdo->prepare("
            SELECT id, username, password_hash, email
            FROM " . TABLE_PREFIX . "admin_users 
            WHERE username = ?
            AND status = 1
            LIMIT 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user) {
            if (password_verify($password, $user['password_hash'])) {
                
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE " . TABLE_PREFIX . "admin_users SET password_hash = ? WHERE id = ?")
                       ->execute([$newHash, $user['id']]);
                }

                session_regenerate_id(true);
                
                // 设置会话变量
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_email'] = $user['email'];
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                $_SESSION['last_activity'] = time();
                
                // 立即保存会话
                session_write_close();
                
                // 更新最后登录时间
                $pdo->prepare("UPDATE " . TABLE_PREFIX . "admin_users SET last_login = NOW() WHERE id = ?")
                   ->execute([$user['id']]);
                
                header('Location: index.php');
                exit;
            } else {
                $error = '密码错误';
            }
        } else {
            $error = '用户不存在或账号被禁用';
        }
        
    } catch (PDOException $e) {
        error_log("登录错误: " . $e->getMessage());
        $error = '系统暂时不可用，请稍后再试';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>龙腾云个人主页后台管理系统登录</title>
    <style>
        :root {
            --sidebar-width: 260px;
            --primary-color: #3B82F6;
            --secondary-color: #2563EB;
            --success-color: #10B981;
            --danger-color: #EF4444;
            --warning-color: #F59E0B;
            --bg-color: #F8FAFC;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-color);
            padding: 20px;
        }

        .login-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            border: 1px solid #E2E8F0;
        }

        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .login-header h1 {
            color: var(--text-primary);
            font-size: 24px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .login-header h1 i {
            color: var(--primary-color);
            font-size: 28px;
        }

        .login-header p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .error-message {
            background: #FEF2F2;
            color: var(--danger-color);
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            border: 1px solid #FECACA;
            display: <?= !empty($error) ? 'flex' : 'none' ?>;
            align-items: center;
            gap: 8px;
        }

        .error-message i {
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .input-container {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 16px;
            z-index: 2;
        }

        .styled-input {
            width: 100%;
            padding: 12px 16px 12px 40px;
            border: 2px solid #E2E8F0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            color: var(--text-primary);
            transition: all 0.2s;
        }

        .styled-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-secondary);
            background: none;
            border: none;
            font-size: 16px;
            z-index: 2;
        }

        .password-toggle:hover {
            color: var(--primary-color);
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px 16px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-1px);
        }

        .btn-primary i {
            font-size: 16px;
        }

        .login-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #E2E8F0;
            color: var(--text-secondary);
            font-size: 12px;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 24px;
                margin: 0 16px;
            }
            
            body {
                padding: 16px;
                align-items: flex-start;
                padding-top: 60px;
            }
        }

        .login-container {
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .btn-primary.loading {
            position: relative;
            color: transparent;
        }

        .btn-primary.loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            border: 2px solid transparent;
            border-top: 2px solid white;
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
    <div class="login-container">
        <div class="login-header">
            <h1>
                <i class="fas fa-cube"></i>
                龙腾云个人主页后台管理系统
            </h1>
            <p>请输入管理员凭证登录系统</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <div class="input-container">
                    <i class="fas fa-user input-icon"></i>
                    <input 
                        type="text" 
                        name="username" 
                        class="styled-input"
                        placeholder="请输入用户名"
                        autocomplete="username"
                        value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <div class="input-container">
                    <i class="fas fa-lock input-icon"></i>
                    <input 
                        type="password" 
                        name="password" 
                        id="password"
                        class="styled-input"
                        placeholder="请输入密码"
                        autocomplete="current-password"
                        required
                    >
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-primary" id="submitBtn">
                <i class="fas fa-sign-in-alt"></i>
                登录系统
            </button>
        </form>

        <div class="login-footer">
            <p>© <?= date('Y') ?> 龙腾云个人主页后台管理系统</p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                passwordField.type = 'password';
                toggleIcon.className = 'fas fa-eye';
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fas fa-spinner"></i>登录中...';
        });

        document.querySelectorAll('.styled-input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentNode.querySelector('.input-icon').style.color = 'var(--primary-color)';
            });
            
            input.addEventListener('blur', function() {
                this.parentNode.querySelector('.input-icon').style.color = 'var(--text-secondary)';
            });
        });

        document.querySelector('input[name="username"]').focus();
    </script>

    <!-- Font Awesome 图标 -->
    <link rel="stylesheet" href="/admin/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>
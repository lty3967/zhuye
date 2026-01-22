<?php
include 'config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_name = trim($_POST['site_name'] ?? '');
    $site_url_input = trim($_POST['site_url'] ?? '');
    $site_url = filter_var($site_url_input, FILTER_VALIDATE_URL);
    $description = trim($_POST['description'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($site_name) || empty($site_url_input) || empty($description) || empty($email)) {
        die("<script>alert('请填写所有必填字段');history.back();</script>");
    }

    if (!$site_url) {
        die("<script>alert('URL格式不正确，必须包含http://或https://');history.back();</script>");
    }

    if (mb_strlen($site_name, 'UTF-8') < 2 || mb_strlen($site_name, 'UTF-8') > 50) {
        die("<script>alert('网站名称需在2-50个字符之间');history.back();</script>");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("<script>alert('邮箱格式不正确');history.back();</script>");
    }

    $descLength = mb_strlen($description, 'UTF-8');
    if ($descLength < 10 || $descLength > 100) {
        die("<script>alert('网站描述需在10-100个字符之间（当前：{$descLength}字）');history.back();</script>");
    }

    try {
        $checkStmt = $pdo->prepare("SELECT status FROM friend_links WHERE site_url = ?");
        $checkStmt->execute([$site_url]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            if ($existing['status'] == 0) {
                $error = "您的站点正在审核中，请勿重复提交！";
            } else {
                $error = "您的站点已审核通过，请勿重复提交！";
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO friend_links (site_name, site_url, description, email, status) VALUES (?, ?, ?, ?, 0)");
            $stmt->execute([$site_name, $site_url, $description, $email]);
            $link_id = $pdo->lastInsertId();
            $success = true;
            
            // 发送邮件通知管理员
            try {
                // 引入邮件发送类
                require_once __DIR__ . '/admin/includes/EmailSender.php';
                $emailSender = new EmailSender($pdo);
                
                if ($emailSender->isEnabled()) {
            // 发送新的友情链接申请通知
            $result = $emailSender->sendNewFriendLinkNotification([
            'site_name' => $site_name,
            'site_url' => $site_url,
            'description' => $description,
            'email' => $email,
            'created_at' => date('Y-m-d H:i:s')
        ]);
                    
                    if ($result && $result['success']) {
                        // 邮件发送成功
                        $mail_success = true;
                    } else {
                        // 邮件发送失败，但不影响主流程
                        $mail_error = "申请提交成功，但邮件通知发送失败";
                    }
                } else {
                    $mail_warning = "申请提交成功，但邮件通知功能未启用";
                }
            } catch (Exception $e) {
                // 邮件发送失败不影响主流程
                error_log("发送友链申请通知邮件失败: " . $e->getMessage());
            }
        }
    } catch (PDOException $e) {
        die("系统错误：" . $e->getMessage());
    }
}

try {
    $total_approved = $pdo->query("SELECT COUNT(*) FROM friend_links WHERE status=1")->fetchColumn();
    $pending_approved = $pdo->query("SELECT COUNT(*) FROM friend_links WHERE status=0")->fetchColumn();
} catch (PDOException $e) {
    die("系统错误：" . $e->getMessage());
}

$stmt = $pdo->query("SELECT * FROM friend_links WHERE status=1 ORDER BY created_at DESC");
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="keywords" content="友情链接, 友链申请, 网站合作">
    <meta name="description" content="欢迎申请友情链接，互利共赢">
    <title>友情链接 - 每天努力奋斗</title>
    <link rel="shortcut icon" href="/assets/img/favicon.ico">
    <link rel="stylesheet" href="/assets/cucss/style.css" media="screen" type="text/css">
    <link rel="stylesheet" href="/assets/cucss/demo.css" type="text/css">
    <style>
        .friend-form {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2.5rem;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 2rem;
            position: relative;
        }

        .input-container {
            position: relative;
            background: #f8f9fa;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .input-container:hover {
            background: #f1f3f5;
        }

        .input-container:focus-within {
            background: #fff;
            box-shadow: 0 0 0 2px #409EFF;
        }

        .form-input {
            width: 100%;
            padding: 1.5rem 1.5rem 1rem;
            border: none;
            background: transparent;
            font-size: 16px;
            line-height: 1.5;
            transition: all 0.3s ease;
        }

        .textarea {
            resize: vertical;
            min-height: 150px;
        }

        .input-label {
            position: absolute;
            left: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            pointer-events: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
        }

        .icon {
            width: 20px;
            height: 20px;
            fill: #999;
            transition: fill 0.3s ease;
        }

        .form-input:focus + .input-label,
        .form-input:not(:placeholder-shown) + .input-label {
            top: 12px;
            transform: none;
            font-size: 12px;
            color: #409EFF;
        }

        .form-input:focus + .input-label .icon,
        .form-input:not(:placeholder-shown) + .input-label .icon {
            fill: #409EFF;
        }

        .form-input:focus {
          outline: none;
          box-shadow: none;
        }

        .char-counter {
            position: absolute;
            right: 1rem;
            bottom: 1rem;
            font-size: 12px;
            color: #999;
            background: rgba(255,255,255,0.9);
            padding: 2px 8px;
            border-radius: 4px;
        }

        .submit-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 1.2rem;
            background: linear-gradient(135deg, #409EFF, #66b3ff);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(64, 158, 255, 0.3);
        }

        .friend-links {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .link-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .link-card:hover {
            transform: translateY(-2px);
        }

        .link-card a {
            text-decoration: none;
            color: #333;
        }

        .link-name {
            font-size: 1.2rem;
            font-weight: 500;
        }

        .link-url {
            color: #666;
            font-size: 0.9rem;
            margin: 0.5rem 0;
        }

        .link-desc {
            color: #444;
            line-height: 1.6;
        }

        .message-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 2rem 0;
            padding: 1rem 2rem;
            background: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }
        .stat-item {
            display: flex;
            align-items: center;
            color: #666;
            font-size: 14px;
        }
        .stat-item svg {
            width: 18px;
            height: 18px;
            fill: #999;
            margin-right: 8px;
            transition: fill 0.3s ease;
        }
        .stat-item:hover svg {
            fill: #409eff;
        }
        .stat-count {
            font-weight: 500;
            color: #333;
        }
        
        .mail-notice {
            margin: 1rem 0;
            padding: 1rem;
            border-radius: 6px;
            font-size: 14px;
            line-height: 1.5;
        }
        .mail-notice.success {
            background: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }
        .mail-notice.warning {
            background: #fcf8e3;
            color: #8a6d3b;
            border: 1px solid #faebcc;
        }
        .mail-notice.info {
            background: #d9edf7;
            color: #31708f;
            border: 1px solid #bce8f1;
        }
</style>
</head>
<body>
<div class="wrapper">
    <header>
        <nav class="navbar" id="mobile-toggle-theme">
            <div class="container">
                <div class="menu navbar-right links">
                    <a class="menu-item" href="/">我的主页</a> · 
                    <a href="/liuyan.html">留言板块</a> · 
                    <a href="/friends.html">友链申请</a>
                </div>
            </div>
        </nav>
    </header>

    <main class="main">
        <div class="container">
            <h1 style="text-align: center; margin: 2rem 0">友情链接申请</h1>

            <div class="message-stats">
                <div class="stat-item">
                    <svg viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <span class="stat-count"><?= $pending_approved ?></span>
                    <small style="color: #999">条待审核友链</small>
                </div>
                <div class="stat-item">
                    <svg viewBox="0 0 24 24"><path d="M12 2c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    <span class="stat-count"><?= $total_approved ?></span>
                    <small style="color: #999">条已审核友链</small>
                </div>
            </div>

            <?php if(isset($success)): ?>
            <div class="mail-notice success">
                <strong>✓ 申请已提交！</strong><br>
                管理员已收到通知，审核通过后会通过邮件通知您。
            </div>
            <?php endif; ?>

            <?php if(isset($mail_success)): ?>
            <div class="mail-notice info">
                <strong>✓ 邮件通知已发送！</strong><br>
                管理员已收到您的申请通知邮件。
            </div>
            <?php endif; ?>

            <?php if(isset($mail_error)): ?>
            <div class="mail-notice warning">
                <strong>⚠ <?= $mail_error ?></strong>
            </div>
            <?php endif; ?>

            <?php if(isset($mail_warning)): ?>
            <div class="mail-notice warning">
                <strong>⚠ <?= $mail_warning ?></strong>
            </div>
            <?php endif; ?>

            <?php if(isset($error)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 4px; margin: 1rem 0">
                <?= $error ?>
            </div>
            <?php endif; ?>

            <form method="post" class="friend-form" onsubmit="return validateForm()">
                <div class="form-group">
                    <div class="input-container">
                        <input type="text" name="site_name" id="site_name" required 
                               class="form-input" 
                               placeholder=" ">
                        <label for="site_name" class="input-label">
                            <svg class="icon" viewBox="0 0 24 24">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                            <span>网站名称（2-50个字符）</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-container">
                        <input type="url" name="site_url" id="site_url" required 
                               class="form-input" 
                               placeholder=" ">
                        <label for="site_url" class="input-label">
                            <svg class="icon" viewBox="0 0 24 24">
                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8l8 5 8-5v10zm-8-7L4 6h16l-8 5z"/>
                            </svg>
                            <span>网站地址（必填，包含http://或https://）</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-container">
                        <input type="email" name="email" id="email" required 
                               class="form-input" 
                               placeholder=" ">
                        <label for="email" class="input-label">
                            <svg class="icon" viewBox="0 0 24 24">
                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                            </svg>
                            <span>联系邮箱（审核结果将通过此邮箱通知您）</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-container">
                        <textarea name="description" id="description" required
                                  class="form-input textarea" 
                                  rows="5"
                                  placeholder=" "></textarea>
                        <label for="description" class="input-label">
                            <svg class="icon" viewBox="0 0 24 24">
                                <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 14H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                            </svg>
                            <span>网站描述（10-100字）</span>
                        </label>
                        <div class="char-counter"><span>0</span>/100</div>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    <svg class="icon" viewBox="0 0 24 24">
                        <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                    </svg>
                    提交申请
                </button>
            </form>

            <div class="friend-links">
                <?php if (empty($links)): ?>
                    <div class="link-card">
                        <p style="text-align: center; color: #999;">暂无友链，快来第一个申请吧！</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($links as $link): ?>
                    <div class="link-card">
                        <a href="<?= $link['site_url'] ?>" target="_blank" rel="nofollow">
                            <div class="link-name"><?= $link['site_name'] ?></div>
                            <div class="link-url"><?= parse_url($link['site_url'], PHP_URL_HOST) ?></div>
                            <div class="link-desc"><?= $link['description'] ?></div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer id="footer" class="footer">
        <p><?php
    // 引入 fk.php 文件
    require 'fk.php';
    
    // 获取统计数据
    $stats = get_stats();
    
    // 显示统计数据
    echo "当前在线人数: <strong><font color='red'>" . $stats['online'] . "</font></strong> - ";
    echo "今日访问人数: <strong><font color='red'>" . $stats['today'] . "</font></strong> - ";
    echo "昨日访问人数: <strong><font color='red'>" . $stats['yesterday'] . "</font></strong> - ";
    echo "总访问人数: <strong><font color='red'>" . $stats['total'] . "</font></strong>";
    ?></p>
        <img style="width:32px;height:32px;margin-bottom:0px" src="/assets/img/icp.svg">
        <a href="http://www.beian.miit.gov.cn/" rel="nofollow" target="_blank">粤ICP备2020124673号-1</a> | 
        <img style="width:32px;height:32px;margin-bottom:0px" src="/assets/img/beian.png">
        <a href="https://beian.mps.gov.cn/#/query/webSearch?code=44010502002900" rel="noreferrer" target="_blank">粤公网安备44010502002900</a>
    </footer>
</div>

<script>
function validateForm() {
    const siteName = document.getElementById('site_name').value.trim();
    const siteUrl = document.getElementById('site_url').value.trim();
    const email = document.getElementById('email').value.trim();
    const description = document.getElementById('description').value.trim();

    const nameLength = Array.from(siteName).length;
    if (nameLength < 2 || nameLength > 50) {
        alert('网站名称需在2-50个字符之间');
        return false;
    }

    if (!/^https?:\/\//i.test(siteUrl)) {
        alert('URL必须包含http://或https://');
        return false;
    }
    try {
        new URL(siteUrl);
    } catch (e) {
        alert('请输入有效的URL地址');
        return false;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        alert('请输入有效的邮箱地址');
        return false;
    }

    const descLength = Array.from(description).length;
    if (descLength < 10 || descLength > 100) {
        alert(`网站描述需在10-100个字符之间（当前：${descLength}字）`);
        return false;
    }

    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    const descriptionArea = document.getElementById('description');
    const counter = descriptionArea.parentElement.querySelector('.char-counter span');
    
    function updateCounter() {
        const content = descriptionArea.value;
        const length = Array.from(content).length;
        counter.textContent = length;
        
        const container = descriptionArea.parentElement;
        if (length < 10 || length > 100) {
            counter.style.color = '#ff4d4f';
            container.classList.add('error-border');
        } else {
            counter.style.color = '#999';
            container.classList.remove('error-border');
        }
    }

    descriptionArea.addEventListener('input', updateCounter);
    
    let isComposing = false;
    descriptionArea.addEventListener('compositionstart', () => isComposing = true);
    descriptionArea.addEventListener('compositionend', () => {
        isComposing = false;
        updateCounter();
    });

    descriptionArea.addEventListener('paste', e => {
        setTimeout(() => {
            if (!isComposing) updateCounter();
        }, 0);
    });

    updateCounter();

    const form = document.querySelector('.friend-form');
    form.addEventListener('submit', () => {
        sessionStorage.setItem('formScrollPos', window.scrollY);
    });

    const savedScroll = sessionStorage.getItem('formScrollPos');
    if (savedScroll) window.scrollTo(0, savedScroll);
    sessionStorage.removeItem('formScrollPos');
    
    // 自动隐藏消息通知
    setTimeout(() => {
        document.querySelectorAll('.mail-notice').forEach(notice => {
            notice.style.transition = 'opacity 0.5s ease';
            notice.style.opacity = '0';
            setTimeout(() => notice.remove(), 500);
        });
    }, 5000);
});
</script>
</body>
</html>
<?php
include 'config/config.php';

$per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $per_page;

// 初始化邮件状态变量
$mail_success = false;
$mail_error = '';
$mail_warning = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (!empty($name) && !empty($content)) {

        try {
            $stmt = $pdo->query("SELECT word FROM forbidden_words");
            $forbiddenWords = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            die("系统错误：" . $e->getMessage());
        }

        $containsForbiddenWord = false;
        $detectedWord = '';
        foreach ($forbiddenWords as $word) {
            if (stripos($content, $word) !== false || stripos($name, $word) !== false) {
                $containsForbiddenWord = true;
                $detectedWord = $word;
                break;
            }
        }

        if ($containsForbiddenWord) {
            die("<script>alert('提交失败：包含违禁词【{$detectedWord}】');history.back();</script>");
        }

        $safeName = htmlspecialchars($name);
        $safeContent = htmlspecialchars($content);
        $safeEmail = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';

        if (!empty($safeEmail) && !filter_var($safeEmail, FILTER_VALIDATE_EMAIL)) {
            die("<script>alert('邮箱格式不正确');history.back();</script>");
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO messages (name, contact, content, status) VALUES (?, ?, ?, 0)");
            $stmt->execute([$safeName, $safeEmail, $safeContent]);
            $message_id = $pdo->lastInsertId();
            
            // 发送邮件通知管理员
            try {
                // 引入邮件发送类
                require_once __DIR__ . '/admin/includes/EmailSender.php';
                $emailSender = new EmailSender($pdo);
                
                if ($emailSender->isEnabled()) {
                    // 发送新留言通知
                    $result = $emailSender->sendNewMessageNotification([
                        'username' => $safeName,
                        'email' => $safeEmail,
                        'content' => $safeContent,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    if ($result && $result['success']) {
                        // 邮件发送成功
                        $mail_success = true;
                    } else {
                        // 邮件发送失败，但不影响主流程
                        $mail_error = "留言提交成功，但邮件通知发送失败";
                    }
                } else {
                    $mail_warning = "留言提交成功，但邮件通知功能未启用";
                }
            } catch (Exception $e) {
                // 邮件发送失败不影响主流程
                error_log("发送留言通知邮件失败: " . $e->getMessage());
                $mail_error = "留言提交成功，但邮件通知发送失败";
            }
            
            // 显示成功消息
            if ($mail_success) {
                echo "<script>alert('留言提交成功，管理员已收到通知');location.href='".strtok($_SERVER["REQUEST_URI"], '?')."';</script>";
            } else if ($mail_error) {
                echo "<script>alert('留言提交成功，但邮件通知发送失败');location.href='".strtok($_SERVER["REQUEST_URI"], '?')."';</script>";
            } else if ($mail_warning) {
                echo "<script>alert('留言提交成功，等待审核通过后显示');location.href='".strtok($_SERVER["REQUEST_URI"], '?')."';</script>";
            } else {
                echo "<script>alert('留言提交成功，等待审核通过后显示');location.href='".strtok($_SERVER["REQUEST_URI"], '?')."';</script>";
            }
            exit();
        } catch (PDOException $e) {
            die("留言提交失败：" . $e->getMessage());
        }
    }
}

try {
    $total_messages = $pdo->query("SELECT COUNT(*) FROM messages WHERE status = 1")->fetchColumn();
    $pending_messages = $pdo->query("SELECT COUNT(*) FROM messages WHERE status = 0")->fetchColumn();
} catch (PDOException $e) {
    die("系统错误：" . $e->getMessage());
}

$total_pages = ceil($total_messages / $per_page);

try {
    $stmt = $pdo->prepare("SELECT name, contact, content, created_at 
                          FROM messages 
                          WHERE status = 1
                          ORDER BY created_at DESC 
                          LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("无法获取留言列表：" . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="keywords" content="留言板, 用户留言, 互动交流">
    <meta name="description" content="欢迎留下您的宝贵意见和建议">
    <title>留言板块 - 每天努力奋斗</title>
    <link rel="shortcut icon" href="/assets/img/favicon.ico">
    <link rel="stylesheet" href="/assets/cucss/style.css" media="screen" type="text/css">
    <link rel="stylesheet" href="/assets/cucss/demo.css" type="text/css">
    <script defer src="https://umami.ltywl.top/script.js" data-website-id="76a93bf3-7e16-4300-8264-c638d77c01c2"></script>
    <style>
        .message-form {
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

        .message-item {
            background: #fff;
            padding: 1.5rem;
            margin: 1rem 0;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .message-item:hover {
            transform: translateY(-2px);
        }

        /* 邮件状态消息样式 */
        .mail-notice {
            margin: 1rem auto;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            font-size: 14px;
            max-width: 800px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .mail-notice.success {
            background: #ECFDF5;
            color: #065F46;
            border: 1px solid #6EE7B7;
        }
        .mail-notice.error {
            background: #FEF2F2;
            color: #991B1B;
            border: 1px solid #FCA5A5;
        }
        .mail-notice.warning {
            background: #FFFBEB;
            color: #92400E;
            border: 1px solid #FCD34D;
        }
        .mail-notice.info {
            background: #EFF6FF;
            color: #1E40AF;
            border: 1px solid #93C5FD;
        }

        /* 新增统计框样式 */
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
        /* 其他样式优化 */
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .message-form {
            margin: 2rem auto;
        }
        .char-counter {
            right: 1.5rem;
            bottom: 1.5rem;
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
            <h1 style="text-align: center; margin: 2rem 0">留言板块</h1>
            
            <?php
            // 检查邮件功能是否启用
            try {
                require_once __DIR__ . '/admin/includes/EmailSender.php';
                $emailSender = new EmailSender($pdo);
                if (!$emailSender->isEnabled()): ?>
                    <div class="mail-notice info">
                        <i class="fas fa-info-circle"></i>
                        邮件通知功能未启用，审核结果将不会通过邮件通知用户。
                    </div>
                <?php endif;
            } catch (Exception $e) {
                // 忽略错误，不显示邮件状态
            }
            ?>

            <div class="message-stats">
                <div class="stat-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                         stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
                        <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <span class="stat-count"><?= $pending_messages ?></span>
                    <small style="color: #999">条待审核留言</small>
                </div>
                <div class="stat-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                         stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
                        <path d="M12 2c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                    <span class="stat-count"><?= $total_messages ?></span>
                    <small style="color: #999">条已审核留言</small>
                </div>
            </div>

            <form method="post" class="message-form">
                <div class="form-group">
                    <div class="input-container">
                        <input type="text" name="name" id="name" required 
                               class="form-input" pattern=".{2,20}"
                               placeholder=" ">
                        <label for="name" class="input-label">
                            <svg class="icon" viewBox="0 0 24 24">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                            <span>您的昵称（2-20个字符）</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-container">
                        <input type="email" name="email" id="email" 
                               class="form-input" 
                               placeholder=" ">
                        <label for="email" class="input-label">
                            <svg class="icon" viewBox="0 0 24 24">
                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8l8 5 8-5v10zm-8-7L4 6h16l-8 5z"/>
                            </svg>
                            <span>电子邮箱（选填）</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-container">
                        <textarea name="content" id="content" required
                                  class="form-input textarea" 
                                  rows="5" minlength="10" maxlength="500"
                                  placeholder=" "></textarea>
                        <label for="content" class="input-label">
                            <svg class="icon" viewBox="0 0 24 24">
                                <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 14H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                            </svg>
                            <span>留言内容（10-500字）</span>
                        </label>
                        <div class="char-counter"><span>0</span>/500</div>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    <svg class="icon" viewBox="0 0 24 24">
                        <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                    </svg>
                    提交留言
                </button>
            </form>

            <div class="message-list">
                <?php if (empty($messages)): ?>
                    <div class="message-item">
                        <p style="text-align: center; color: #999;">暂无留言，快来第一个发言吧！</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                    <div class="message-item">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <h3 style="margin: 0; color: var(--primary-color);"><?= htmlspecialchars($msg['name']) ?></h3>
                            <small style="color: #666"><?= date('Y-m-d H:i', strtotime($msg['created_at'])) ?></small>
                        </div>
                        <p style="margin: 0; line-height: 1.6; color: #333;"><?= nl2br(htmlspecialchars($msg['content'])) ?></p>
                        <?php if (!empty($msg['contact'])): ?>
                        <p style="margin-top: 0.5rem; color: #666;">
                            <svg style="width:16px;height:16px;vertical-align:-2px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <?= htmlspecialchars($msg['contact']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="?page=<?= $current_page - 1 ?>" class="page-link">上一页</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>" class="page-link <?= $i == $current_page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?= $current_page + 1 ?>" class="page-link">下一页</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
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
        
        <img style="width:32px;height:32px;margin-bottom:0px" src="/assets/img/icp.svg"><a href="http://www.beian.miit.gov.cn/" rel="nofollow" target="_blank">粤ICP备2020124673号-1</a> | 
  <img style="width:32px;height:32px;margin-bottom:0px" src="https://www.ltywl.top/assets/img/beian.png"><a href="https://beian.mps.gov.cn/#/query/webSearch?code=44010502002900" rel="noreferrer" target="_blank">粤公网安备44010502002900</a>
    </footer>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const contentArea = document.getElementById('content');
    const counter = contentArea.parentElement.querySelector('.char-counter span');
    
    function updateCounter() {
        counter.textContent = contentArea.value.length;
    }
    
    contentArea.addEventListener('input', updateCounter);
    updateCounter(); 

    const form = document.querySelector('.message-form');
    form.addEventListener('submit', function() {
        sessionStorage.setItem('formScrollPos', window.scrollY);
    });

    const savedScroll = sessionStorage.getItem('formScrollPos');
    if (savedScroll) {
        window.scrollTo(0, savedScroll);
        sessionStorage.removeItem('formScrollPos');
    }
    
    // 自动隐藏邮件消息
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
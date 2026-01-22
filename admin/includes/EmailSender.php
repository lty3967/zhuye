<?php
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailSender {
    private $pdo;
    private $config = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadConfig();
    }
    
    private function loadConfig() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM email_config WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
            if ($config = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->config = $config;
                return true;
            }
        } catch (PDOException $e) {
            error_log("加载邮件配置失败: " . $e->getMessage());
        }
        return false;
    }
    
    public function isEnabled() {
        return !empty($this->config) && $this->config['is_active'] == 1;
    }
    
    public function getConfig() {
        return $this->config;
    }
    
    public function sendEmail($to, $subject, $body, $isHTML = true) {
        if (!$this->isEnabled()) {
            throw new Exception("邮件功能未启用或未配置");
        }
        
        $mail = new PHPMailer(true);
        
        try {
            // 服务器设置
            $mail->isSMTP();
            $mail->CharSet = 'UTF-8';
            $mail->SMTPDebug = 0; // 0=不显示调试信息, 1=显示错误和消息, 2=显示完整调试
            $mail->Debugoutput = 'error_log';
            
            $mail->Host = $this->config['smtp_host'];
            $mail->Port = $this->config['smtp_port'];
            
            // 加密方式
            if ($this->config['smtp_secure'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($this->config['smtp_secure'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
            }
            
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['smtp_username'];
            $mail->Password = $this->config['smtp_password'];
            
            // 发件人
            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $mail->addReplyTo($this->config['from_email'], $this->config['from_name']);
            
            // 收件人
            if (is_array($to)) {
                foreach ($to as $email) {
                    $mail->addAddress($email);
                }
            } else {
                $mail->addAddress($to);
            }
            
            // 内容
            $mail->isHTML($isHTML);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            if (!$isHTML) {
                $mail->AltBody = strip_tags($body);
            }
            
            // 发送邮件
            if ($mail->send()) {
                return ['success' => true, 'message' => '邮件发送成功'];
            } else {
                throw new Exception("邮件发送失败: " . $mail->ErrorInfo);
            }
            
        } catch (Exception $e) {
            error_log("邮件发送异常: " . $e->getMessage());
            return ['success' => false, 'message' => '邮件发送失败: ' . $e->getMessage()];
        }
    }
    
    public function sendTestEmail($toEmail) {
        if (empty($toEmail)) {
            return ['success' => false, 'message' => '收件人邮箱不能为空'];
        }
        
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => '邮箱格式不正确'];
        }
        
        $subject = '龙腾云个人主页 - 发信功能测试邮件';
        $timestamp = date('Y-m-d H:i:s');
        
        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e1e1e1; border-top: none; }
                .test-info { background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #4CAF50; margin: 20px 0; }
                .success { color: #4CAF50; font-weight: bold; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🎉 发信功能测试成功！</h1>
                </div>
                <div class="content">
                    <p>恭喜您！您的龙腾云个人主页系统发信功能已配置成功。</p>
                    
                    <div class="test-info">
                        <p><strong>测试时间：</strong> ' . $timestamp . '</p>
                        <p><strong>发信服务器：</strong> ' . htmlspecialchars($this->config['smtp_host']) . '</p>
                        <p><strong>发信邮箱：</strong> ' . htmlspecialchars($this->config['from_email']) . '</p>
                        <p class="success">✓ 系统发信功能一切正常！</p>
                    </div>
                    
                    <p>现在您可以：</p>
                    <ul>
                        <li>接收网站留言通知</li>
                        <li>接收友情链接申请通知</li>
                        <li>发送密码重置邮件</li>
                        <li>其他系统通知邮件</li>
                    </ul>
                    
                    <p>如果您收到此邮件，说明您的邮件服务器配置完全正确。</p>
                    
                    <div class="footer">
                        <p>此邮件为系统自动发送，请勿回复。</p>
                        <p>© ' . date('Y') . ' 龙腾云个人主页系统 - 邮件测试</p>
                    </div>
                </div>
            </div>
        </body>
        </html>';
        
        $textBody = "发信功能测试成功！\n\n" .
                   "测试时间：" . $timestamp . "\n" .
                   "发信服务器：" . $this->config['smtp_host'] . "\n" .
                   "发信邮箱：" . $this->config['from_email'] . "\n\n" .
                   "恭喜您！您的龙腾云个人主页系统发信功能已配置成功。\n\n" .
                   "此邮件为系统自动发送，请勿回复。";
        
        return $this->sendEmail($toEmail, $subject, $htmlBody, true);
    }
    
    public function sendNewFriendLinkNotification($linkData) {
    if (!$this->isEnabled()) {
        return false;
    }
    
    $subject = '新的友情链接申请 - 龙腾云个人主页';
    
    $htmlBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #3B82F6; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; border: 1px solid #e1e1e1; border-top: none; }
            .link-box { background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #3B82F6; margin: 15px 0; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            .btn { display: inline-block; padding: 10px 20px; background: #3B82F6; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>🔗 新的友情链接申请</h2>
            </div>
            <div class="content">
                <p>管理员您好，您的网站收到了新的友情链接申请，请及时处理。</p>
                
                <div class="link-box">
                    <p><strong>网站名称：</strong> ' . htmlspecialchars($linkData['site_name']) . '</p>
                    <p><strong>网站地址：</strong> <a href="' . htmlspecialchars($linkData['site_url']) . '">' . htmlspecialchars($linkData['site_url']) . '</a></p>
                    <p><strong>网站描述：</strong></p>
                    <p>' . nl2br(htmlspecialchars($linkData['description'])) . '</p>
                    <p><strong>申请邮箱：</strong> ' . htmlspecialchars($linkData['email']) . '</p>
                    <p><strong>申请时间：</strong> ' . $linkData['created_at'] . '</p>
                </div>
                
                <p>请登录后台管理系统查看和处理：</p>
                <a href="' . (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/admin/apply.php" class="btn">审核友情链接</a>
                
                <div class="footer">
                    <p>此邮件为系统自动发送，请勿回复。</p>
                    <p>© ' . date('Y') . ' 龙腾云个人主页系统</p>
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    return $this->sendEmail($this->config['from_email'], $subject, $htmlBody, true);
}
    
    public function sendNewMessageNotification($messageData) {
        if (!$this->isEnabled()) {
            return false;
        }
        
        $subject = '您收到新的留言 - 龙腾云个人主页';
        
        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #3B82F6; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; border: 1px solid #e1e1e1; border-top: none; }
                .message-box { background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #3B82F6; margin: 15px 0; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
                .btn { display: inline-block; padding: 10px 20px; background: #3B82F6; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>📨 您收到新的留言</h2>
                </div>
                <div class="content">
                    <p>管理员您好，您的网站收到了新的留言，请及时处理。</p>
                    
                    <div class="message-box">
                        <p><strong>留言人：</strong> ' . htmlspecialchars($messageData['username']) . '</p>
                        <p><strong>联系邮箱：</strong> ' . htmlspecialchars($messageData['email']) . '</p>
                        <p><strong>留言内容：</strong></p>
                        <p>' . nl2br(htmlspecialchars($messageData['content'])) . '</p>
                        <p><strong>留言时间：</strong> ' . $messageData['created_at'] . '</p>
                    </div>
                    
                    <p>请登录后台管理系统查看和处理：</p>
                    <a href="' . (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/admin/add_messages.php" class="btn">前往审核留言</a>
                    
                    <div class="footer">
                        <p>此邮件为系统自动发送，请勿回复。</p>
                        <p>© ' . date('Y') . ' 龙腾云个人主页系统</p>
                    </div>
                </div>
            </div>
        </body>
        </html>';
        
        return $this->sendEmail($this->config['from_email'], $subject, $htmlBody, true);
    }
    
    public function sendLinkApprovalNotification($linkData) {
    if (!$this->isEnabled()) {
        return false;
    }
    
    $subject = '您的友情链接申请已通过 - 龙腾云个人主页';
    
    $htmlBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #10B981; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; border: 1px solid #e1e1e1; border-top: none; }
            .info-box { background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #10B981; margin: 15px 0; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>✓ 友情链接申请已通过</h2>
            </div>
            <div class="content">
                <p>亲爱的网站管理员，</p>
                
                <div class="info-box">
                    <p><strong>网站名称：</strong> ' . htmlspecialchars($linkData['site_name']) . '</p>
                    <p><strong>网站地址：</strong> <a href="' . htmlspecialchars($linkData['site_url']) . '">' . htmlspecialchars($linkData['site_url']) . '</a></p>
                    <p><strong>申请状态：</strong> <span style="color: #10B981; font-weight: bold;">已通过</span></p>
                    <p><strong>处理时间：</strong> ' . date('Y-m-d H:i:s') . '</p>
                </div>
                
                <p>您的友情链接申请已通过审核，感谢您的申请！您的网站现已在我们的友情链接页面展示。</p>
                <p>如果您也愿意将我们的网站添加到您的友情链接中，我们将不胜感激。</p>
                
                <div class="footer">
                    <p>此邮件为系统自动发送，请勿回复。</p>
                    <p>© ' . date('Y') . ' 龙腾云个人主页系统</p>
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    if (!empty($linkData['email'])) {
        return $this->sendEmail($linkData['email'], $subject, $htmlBody, true);
    }
    
    return false;
}

    public function sendLinkRejectionNotification($linkData, $rejectReason) {
    if (!$this->isEnabled()) {
        return false;
    }
    
    $subject = '您的友情链接申请需要修改 - 龙腾云个人主页';
    
    $htmlBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #F59E0B; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; border: 1px solid #e1e1e1; border-top: none; }
            .info-box { background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #F59E0B; margin: 15px 0; }
            .reason-box { background: #fff3cd; padding: 15px; border-radius: 6px; border: 1px solid #ffeaa7; margin: 15px 0; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>⚠ 友情链接申请需要修改</h2>
            </div>
            <div class="content">
                <p>亲爱的网站管理员，</p>
                
                <div class="info-box">
                    <p><strong>网站名称：</strong> ' . htmlspecialchars($linkData['site_name']) . '</p>
                    <p><strong>网站地址：</strong> <a href="' . htmlspecialchars($linkData['site_url']) . '">' . htmlspecialchars($linkData['site_url']) . '</a></p>
                    <p><strong>申请状态：</strong> <span style="color: #F59E0B; font-weight: bold;">需要修改</span></p>
                    <p><strong>处理时间：</strong> ' . date('Y-m-d H:i:s') . '</p>
                </div>
                
                <div class="reason-box">
                    <h4>拒绝原因：</h4>
                    <p>' . nl2br(htmlspecialchars($rejectReason)) . '</p>
                </div>
                
                <p>请根据上述原因修改您的申请信息，然后重新提交。如有疑问，请联系我们。</p>
                <p>您可以在我们的网站上重新提交友情链接申请。</p>
                
                <div class="footer">
                    <p>此邮件为系统自动发送，请勿回复。</p>
                    <p>© ' . date('Y') . ' 龙腾云个人主页系统</p>
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    if (!empty($linkData['email'])) {
        return $this->sendEmail($linkData['email'], $subject, $htmlBody, true);
    }
    
    return false;
}
}
?>
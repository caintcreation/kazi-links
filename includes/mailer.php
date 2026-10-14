<?php
// includes/mailer.php - Production-grade Mailer supporting Brevo/SendGrid HTTPS APIs, SMTP fallback, and on-screen code displays

require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/Exception.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Universal mail dispatch function:
 * 1. Attempts Brevo or Resend/SendGrid API if key is present
 * 2. Attempts the configured SMTP provider
 * 3. Falls back to standard PHP mail()
 * 4. Logs every email payload to disk (logs/sent_emails.log)
 */
function sendPlatformEmail(string $toEmail, string $subject, string $htmlBody, string $textBody = ''): bool {
    $apiKey = getenv('BREVO_API_KEY') ?: (defined('BREVO_API_KEY') ? BREVO_API_KEY : '');
    $fromEmail = SMTP_FROM_EMAIL;
    $fromName = SMTP_FROM_NAME;
    $sent = false;

    // 1. Try Brevo HTTPS API if an API key is configured
    if (!empty($apiKey) && $apiKey !== 'YOUR_BREVO_API_KEY') {
        $payload = [
            'sender' => ['name' => $fromName, 'email' => $fromEmail],
            'to' => [['email' => $toEmail]],
            'subject' => $subject,
            'htmlContent' => $htmlBody,
            'textContent' => $textBody ?: strip_tags($htmlBody)
        ];

        $ch = curl_init("https://api.brevo.com/v3/smtp/email");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'api-key: ' . $apiKey,
            'content-type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 200 && $code < 300) {
            $sent = true;
        }

    }

    // 2. Try the configured SMTP provider through PHPMailer.
    if (!$sent) {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->Port = SMTP_PORT;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = str_replace(' ', '', SMTP_PASS);
            $mail->Timeout = 15;
            $mail->SMTPDebug = 0;
            $mail->AuthType = 'LOGIN';
            $mail->SMTPSecure = SMTP_ENCRYPTION === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody ?: strip_tags($htmlBody);
            $sent = $mail->send();
        } catch (MailException $e) {
            error_log('Platform email failed: ' . $e->getMessage());
        }
    }

    // 3. Fallback to standard PHP mail()
    if (!$sent) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . $fromName . " <" . $fromEmail . ">" . "\r\n";
        $sent = @mail($toEmail, $subject, $htmlBody, $headers) ? true : false;
    }

    // 4. Archive into logs directory for verification & inspection
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/sent_emails.log';
    $statusText = $sent ? 'DELIVERED_EXTERNAL' : 'SAVED_LOCAL_AND_FALLBACK';
    $logEntry = "[" . date('Y-m-d H:i:s') . "] TO: {$toEmail} | SUBJECT: {$subject} | STATUS: {$statusText}\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND);

    // Save individual HTML snapshot
    $snapshotFile = $logDir . '/latest_' . preg_replace('/[^a-zA-Z0-9]/', '_', $toEmail) . '.html';
    @file_put_contents($snapshotFile, $htmlBody);

    return $sent;
}

/**
 * Send a customer-care message to the configured support inbox.
 */
function sendCustomerCareEmail(
    string $senderEmail,
    string $senderName,
    string $subject,
    string $message
): bool {
    $safeName = htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($senderEmail, ENT_QUOTES, 'UTF-8');
    $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Customer care message</title></head>
<body style="font-family:Arial,sans-serif;color:#0f172a;line-height:1.6;">
  <h2>New KaziLink customer-care message</h2>
  <p><strong>From:</strong> {$safeName} ({$safeEmail})</p>
  <p><strong>Subject:</strong> {$safeSubject}</p>
  <hr>
  <p>{$safeMessage}</p>
</body>
</html>
HTML;
    $text = "New KaziLink customer-care message\n\n"
        . "From: {$senderName} ({$senderEmail})\n"
        . "Subject: {$subject}\n\n{$message}";

    return sendPlatformEmail(
        SUPPORT_EMAIL,
        '[KaziLink Customer Care] ' . $subject,
        $html,
        $text
    );
}

/**
 * Send Account Verification & Welcome email immediately after signup
 * Includes greetings, welcome message, activation code, earning tips, referral details, and support contacts.
 */
function sendAccountVerificationEmail(string $email, string $verifyCode, string $verifyToken, string $referralCode, float $welcomeBonus = 100.0, string $name = ''): bool {
    $verifyUrl = BASE_URL . "/verify.php?token=" . urlencode($verifyToken);
    $referralUrl = BASE_URL . "/register.php?ref=" . urlencode($referralCode);
    $websiteUrl = "https://kazilinks.kesug.com/";
    $bonusFormatted = number_format($welcomeBonus, 2);

    $username = trim($name);
    if ($username === '') {
        $username = ucfirst(explode('@', $email)[0]);
    }
    $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safeReferralCode = htmlspecialchars($referralCode, ENT_QUOTES, 'UTF-8');

    $subject = "Welcome to KaziLink Jobs — Verify Your Account & Start Earning 🇰🇪";

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Welcome to KaziLink Jobs</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 0; color: #1e293b; line-height: 1.6; }
    .email-container { max-width: 620px; margin: 24px auto; background: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .email-header { background: linear-gradient(135deg, #082F49 0%, #0F2D4A 100%); padding: 36px 28px; text-align: center; color: #ffffff; }
    .email-header h1 { margin: 0; font-size: 26px; font-weight: 800; letter-spacing: -0.5px; }
    .email-header p { margin: 6px 0 0; font-size: 14px; opacity: 0.9; color: #bae6fd; font-weight: 500; }
    .email-body { padding: 32px 28px; font-size: 15px; }
    .greeting { font-size: 18px; font-weight: 700; color: #082F49; margin-top: 0; margin-bottom: 8px; }
    .welcome-title { font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 0; margin-bottom: 12px; }
    .code-box { background: #f0fdf4; border: 2px dashed #16a34a; border-radius: 12px; padding: 22px; text-align: center; margin: 24px 0; }
    .code-title { font-size: 12px; font-weight: 800; text-transform: uppercase; color: #15803d; letter-spacing: 1.5px; margin-bottom: 6px; }
    .code-number { font-size: 34px; font-weight: 800; color: #166534; letter-spacing: 8px; font-family: monospace, Consolas; margin: 6px 0; }
    .cta-button { display: inline-block; background: #082F49; color: #ffffff !important; text-decoration: none; padding: 13px 32px; border-radius: 9999px; font-weight: 700; font-size: 14px; margin: 12px 0 4px; text-align: center; box-shadow: 0 2px 6px rgba(8,47,73,0.25); }
    .spam-notice { background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 12px 16px; margin: 20px 0; font-size: 13px; color: #92400e; line-height: 1.5; }
    .section-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; margin: 18px 0; }
    .section-heading { font-size: 16px; font-weight: 700; color: #082F49; margin-top: 0; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
    .section-text { margin: 0; color: #334155; font-size: 14px; line-height: 1.6; }
    .referral-box { background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 14px; margin-top: 10px; font-size: 13px; }
    .contact-card { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 18px 20px; margin: 20px 0; }
    .contact-heading { font-size: 16px; font-weight: 700; color: #0369a1; margin-top: 0; margin-bottom: 8px; }
    .contact-item { font-size: 14px; color: #0f172a; margin-bottom: 6px; }
    .contact-item strong { color: #0369a1; }
    .next-step-card { text-align: center; background: linear-gradient(135deg, #f0fdf4 0%, #e0f2fe 100%); border-radius: 12px; padding: 18px 20px; margin: 24px 0; border: 1px solid #bae6fd; }
    .email-signoff { margin-top: 28px; padding-top: 18px; border-top: 1px solid #e2e8f0; font-size: 14px; color: #475569; }
    .email-footer { background: #f8fafc; padding: 22px 28px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
  </style>
</head>
<body>
  <div class="email-container">
    <div class="email-header">
      <h1>🇰🇪 KaziLink Jobs</h1>
      <p>Kazi rahisi. Pesa halisi.</p>
    </div>

    <div class="email-body">
      <div class="greeting">Hello {$safeUsername},</div>
      <div class="welcome-title">🎉 Welcome to KaziLink Jobs!</div>
      <p class="section-text" style="font-size: 15px; margin-bottom: 18px;">
        Thank you for joining our platform. We are happy to have you as a member of our growing community!
      </p>

      <!-- Account Activation Box -->
      <div class="code-box">
        <div class="code-title">Your 6-Digit Activation Code</div>
        <div class="code-number">{$verifyCode}</div>
        <p style="margin: 6px 0 12px; font-size: 13px; color: #166534;">
          Enter this code on the activation screen or click below to verify automatically:
        </p>
        <div>
          <a href="{$verifyUrl}" class="cta-button">Verify Email &amp; Activate Account</a>
        </div>
      </div>

      <!-- Spam Reminder -->
      <div class="spam-notice">
        <strong>⚠️ Important Reminder:</strong> If you ever have trouble finding our emails in your inbox, please make sure to check your <strong>Spam / Junk folder</strong> and mark KaziLink Jobs as <em>"Not Spam"</em> so you never miss earnings or withdrawal notices!
      </div>

      <!-- Start Earning -->
      <div class="section-card">
        <div class="section-heading">💼 Start Earning</div>
        <p class="section-text">
          Log in to your account, visit the <strong>Available Jobs</strong> section, and complete the tasks according to the instructions provided. Check your dashboard regularly for new opportunities and updates on your earnings.
        </p>
      </div>

      <!-- Invite Friends & Earn Rewards -->
      <div class="section-card">
        <div class="section-heading">🤝 Invite Friends &amp; Earn Rewards</div>
        <p class="section-text">
          Share your referral link with friends and family. When they join and meet the applicable referral requirements, you may earn rewards. Help us grow the KaziLink Jobs community!
        </p>
        <div class="referral-box">
          <div style="margin-bottom: 4px;"><strong>Your Referral Code:</strong> <span style="font-family: monospace; font-size: 14px; font-weight: 700; color: #082F49;">{$safeReferralCode}</span></div>
          <div><strong>Your Referral Link:</strong> <a href="{$referralUrl}" style="color: #0284c7; word-break: break-all;">{$referralUrl}</a></div>
        </div>
      </div>

      <!-- Welcome Bonus -->
      <div class="section-card">
        <div class="section-heading">🎁 Welcome Bonus</div>
        <p class="section-text">
          If your account qualifies for a welcome bonus, please check your dashboard and the applicable bonus terms. Your account has been credited with an initial <strong>Sh {$bonusFormatted}</strong> welcome bonus!
        </p>
      </div>

      <!-- Customer Care Support -->
      <div class="contact-card">
        <div class="contact-heading">📞 Need Help or Have a Complaint?</div>
        <p style="margin: 0 0 10px; font-size: 14px; color: #334155;">Our customer care team is available to assist you.</p>
        <div class="contact-item"><strong>Email:</strong> <a href="mailto:growflow56@gmail.com" style="color: #0284c7;">growflow56@gmail.com</a></div>
        <div class="contact-item"><strong>Phone:</strong> <a href="tel:0182435685" style="color: #0284c7;">0182435685</a></div>
        <p style="margin: 10px 0 0; font-size: 13px; color: #64748b; line-height: 1.5;">
          If you experience any problems with your account, tasks, referrals, or withdrawals, please contact our support team.
        </p>
      </div>

      <!-- Next Step -->
      <div class="next-step-card">
        <div style="font-weight: 800; font-size: 15px; color: #082F49; margin-bottom: 6px;">
          🚀 Your next step: Log in to KaziLink Jobs today and explore the available tasks!
        </div>
        <div style="font-size: 14px;">
          🌐 Website: <a href="{$websiteUrl}" style="color: #0284c7; font-weight: 700; text-decoration: none;">{$websiteUrl}</a>
        </div>
      </div>

      <!-- Signoff -->
      <div class="email-signoff">
        <p style="margin: 0 0 6px;">Thank you for being part of KaziLink Jobs.</p>
        <p style="margin: 0; line-height: 1.4;">
          <strong>Best regards,</strong><br>
          <strong style="color: #082F49;">KaziLink Jobs Administration Team</strong>
        </p>
      </div>
    </div>

    <div class="email-footer">
      &copy; 2026 KaziLink Jobs Kenya. All rights reserved.<br>
      Automated account notification &middot; M-Pesa is a registered trademark of Safaricom PLC.
    </div>
  </div>
</body>
</html>
HTML;

    $text = "Hello {$username},\n\n"
          . "🎉 Welcome to KaziLink Jobs!\n"
          . "Thank you for joining our platform. We are happy to have you as a member of our growing community!\n\n"
          . "Your 6-Digit Activation Code: {$verifyCode}\n"
          . "Or verify directly: {$verifyUrl}\n\n"
          . "Important: If this email arrived in your Spam folder, please mark it as Not Spam.\n\n"
          . "💼 Start Earning\n"
          . "Log in to your account, visit the Available Jobs section, and complete the tasks according to the instructions provided. Check your dashboard regularly for new opportunities and updates on your earnings.\n\n"
          . "🤝 Invite Friends & Earn Rewards\n"
          . "Share your referral link with friends and family. When they join and meet the applicable referral requirements, you may earn rewards. Help us grow the KaziLink Jobs community!\n"
          . "Your Referral Code: {$referralCode}\n"
          . "Your Referral Link: {$referralUrl}\n\n"
          . "🎁 Welcome Bonus\n"
          . "If your account qualifies for a welcome bonus, please check your dashboard and the applicable bonus terms. Your account has received Sh {$bonusFormatted} welcome bonus!\n\n"
          . "📞 Need Help or Have a Complaint?\n"
          . "Our customer care team is available to assist you.\n"
          . "Email: growflow56@gmail.com\n"
          . "Phone: 0182435685\n"
          . "If you experience any problems with your account, tasks, referrals, or withdrawals, please contact our support team.\n\n"
          . "🚀 Your next step: Log in to KaziLink Jobs today and explore the available tasks!\n"
          . "🌐 Website: {$websiteUrl}\n\n"
          . "Thank you for being part of KaziLink Jobs.\n\n"
          . "Best regards,\n"
          . "KaziLink Jobs Administration Team";

    return sendPlatformEmail($email, $subject, $html, $text);
}

/**
 * Send Password Reset email with 6-digit reset code and reset URL.
 */
function sendPasswordResetEmail(string $email, string $resetCode, string $resetToken): bool {
    $resetUrl = BASE_URL . "/reset-password.php?token=" . urlencode($resetToken);
    $subject = "Reset Your KaziLink Password — 6-Digit Code 🇰🇪";

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f8fafc; margin: 0; padding: 0; color: #0f172a; }
    .email-container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 14px; overflow: hidden; border: 1px solid #e2e8f0; }
    .email-header { background: linear-gradient(160deg, #082F49 0%, #0F2D4A 100%); padding: 30px 24px; text-align: center; color: #ffffff; }
    .email-header h1 { margin: 0; font-size: 24px; font-weight: 800; }
    .email-body { padding: 32px 24px; font-size: 15px; line-height: 1.6; }
    .code-box { background: #fef2f2; border: 2px dashed #ef4444; border-radius: 12px; padding: 20px; text-align: center; margin: 24px 0; }
    .code-number { font-size: 32px; font-weight: 800; color: #991b1b; letter-spacing: 6px; font-family: monospace; }
    .cta-button { display: inline-block; background: #082F49; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 9999px; font-weight: 700; font-size: 15px; margin: 16px 0; }
    .email-footer { background: #f1f5f9; padding: 20px 24px; text-align: center; font-size: 12px; color: #64748b; }
  </style>
</head>
<body>
  <div class="email-container">
    <div class="email-header">
      <h1>🇰🇪 KaziLink Security</h1>
      <p>Password Reset Request</p>
    </div>

    <div class="email-body">
      <h2 style="font-size: 20px; font-weight: 700; color: #082F49; margin-top: 0;">Password Reset Request</h2>
      <p>We received a request to reset the password for your KaziLink account (<strong>{$email}</strong>).</p>
      
      <p>Use the 6-digit security code below or click the button to set a new password:</p>

      <div class="code-box">
        <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #991b1b; margin-bottom: 4px;">Password Reset Code</div>
        <div class="code-number">{$resetCode}</div>
      </div>

      <div style="text-align: center;">
        <a href="{$resetUrl}" class="cta-button">Reset My Password Online</a>
      </div>

      <p style="font-size: 13px; color: #64748b; margin-top: 24px;">
        This code is valid for 1 hour. If you did not request this password reset, please ignore this email and your account password will remain unchanged.
      </p>
    </div>

    <div class="email-footer">
      &copy; 2026 KaziLink Kenya. All rights reserved.
    </div>
  </div>
</body>
</html>
HTML;

    $text = "KaziLink Password Reset\n\nYour 6-digit reset code is: {$resetCode}\n\nReset link: {$resetUrl}\n\nThis code expires in 1 hour.";

    return sendPlatformEmail($email, $subject, $html, $text);
}

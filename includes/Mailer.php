<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

class Mailer {
    private function build(): PHPMailer {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = env('MAIL_HOST', 'localhost');
        $mail->SMTPAuth   = true;
        $mail->Username   = env('MAIL_USERNAME', '');
        $mail->Password   = env('MAIL_PASSWORD', '');
        $mail->SMTPSecure = env('MAIL_ENCRYPTION', 'tls') === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) env('MAIL_PORT', 587);
        $mail->setFrom(env('MAIL_FROM_ADDRESS', 'noreply@ventdepot.com'), env('MAIL_FROM_NAME', 'VentDepot'));
        $mail->isHTML(true);

        return $mail;
    }

    public function sendOrderConfirmation(string $toEmail, string $toName, array $order): bool {
        try {
            $mail = $this->build();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = "Order Confirmation #" . $order['id'] . " — VentDepot";
            $mail->Body    = $this->orderConfirmationHtml($order);
            $mail->AltBody = "Thank you for your order #" . $order['id'] . ". Total: $" . number_format($order['total_amount'], 2);
            $mail->send();
            return true;
        } catch (MailerException $e) {
            error_log('Mailer::sendOrderConfirmation failed: ' . $e->getMessage());
            return false;
        }
    }

    public function sendPasswordReset(string $toEmail, string $toName, string $resetLink): bool {
        try {
            $mail = $this->build();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = "Reset Your VentDepot Password";
            $mail->Body    = $this->passwordResetHtml($toName, $resetLink);
            $mail->AltBody = "Reset your password: $resetLink\nThis link expires in 1 hour.";
            $mail->send();
            return true;
        } catch (MailerException $e) {
            error_log('Mailer::sendPasswordReset failed: ' . $e->getMessage());
            return false;
        }
    }

    public function sendMerchantStatusUpdate(string $toEmail, string $toName, string $status, string $reason = ''): bool {
        try {
            $mail = $this->build();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = "Your VentDepot Merchant Application — " . ucfirst($status);
            $mail->Body    = $this->merchantStatusHtml($toName, $status, $reason);
            $mail->AltBody = "Your merchant application status has been updated to: $status." . ($reason ? " Reason: $reason" : '');
            $mail->send();
            return true;
        } catch (MailerException $e) {
            error_log('Mailer::sendMerchantStatusUpdate failed: ' . $e->getMessage());
            return false;
        }
    }

    private function orderConfirmationHtml(array $order): string {
        $orderId = htmlspecialchars($order['id']);
        $total   = number_format($order['total_amount'], 2);
        $appName = htmlspecialchars(env('APP_NAME', 'VentDepot'));
        $appUrl  = htmlspecialchars(env('APP_URL', ''));

        return <<<HTML
        <div style="font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px">
            <h1 style="color:#1d4ed8">{$appName}</h1>
            <h2>Order Confirmed</h2>
            <p>Thank you for your order! We've received order <strong>#{$orderId}</strong>.</p>
            <table style="width:100%;border-collapse:collapse;margin:20px 0">
                <tr style="background:#f3f4f6">
                    <td style="padding:10px;border:1px solid #e5e7eb">Order Total</td>
                    <td style="padding:10px;border:1px solid #e5e7eb"><strong>\${$total}</strong></td>
                </tr>
            </table>
            <p><a href="{$appUrl}/order-confirmation.php?order_id={$orderId}"
                  style="background:#1d4ed8;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px">
                View Order
            </a></p>
            <p style="color:#6b7280;font-size:12px">If you didn't place this order, please contact us immediately.</p>
        </div>
        HTML;
    }

    private function passwordResetHtml(string $name, string $resetLink): string {
        $appName   = htmlspecialchars(env('APP_NAME', 'VentDepot'));
        $safeName  = htmlspecialchars($name);
        $safeLink  = htmlspecialchars($resetLink);

        return <<<HTML
        <div style="font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px">
            <h1 style="color:#1d4ed8">{$appName}</h1>
            <h2>Password Reset Request</h2>
            <p>Hi {$safeName},</p>
            <p>We received a request to reset your password. Click the button below to choose a new one.</p>
            <p><a href="{$safeLink}"
                  style="background:#1d4ed8;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px">
                Reset Password
            </a></p>
            <p>This link expires in <strong>1 hour</strong>.</p>
            <p style="color:#6b7280;font-size:12px">If you didn't request a password reset, you can safely ignore this email.</p>
        </div>
        HTML;
    }

    private function merchantStatusHtml(string $name, string $status, string $reason): string {
        $appName  = htmlspecialchars(env('APP_NAME', 'VentDepot'));
        $appUrl   = htmlspecialchars(env('APP_URL', ''));
        $safeName = htmlspecialchars($name);
        $statusLabel = ucfirst(htmlspecialchars($status));
        $color = $status === 'approved' ? '#16a34a' : ($status === 'rejected' ? '#dc2626' : '#1d4ed8');

        $reasonBlock = $reason
            ? '<p><strong>Note from our team:</strong> ' . htmlspecialchars($reason) . '</p>'
            : '';

        return <<<HTML
        <div style="font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px">
            <h1 style="color:#1d4ed8">{$appName}</h1>
            <h2>Merchant Application Update</h2>
            <p>Hi {$safeName},</p>
            <p>Your merchant application status has been updated to:
               <strong style="color:{$color}">{$statusLabel}</strong>
            </p>
            {$reasonBlock}
            <p><a href="{$appUrl}/merchant/dashboard.php"
                  style="background:#1d4ed8;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px">
                Go to Merchant Dashboard
            </a></p>
        </div>
        HTML;
    }
}

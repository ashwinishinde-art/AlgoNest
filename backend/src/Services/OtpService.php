<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/mail.php';

class OtpService {
    private $db;

    // Max verification attempts before OTP is locked
    const MAX_ATTEMPTS   = 3;
    // OTP validity in minutes
    const EXPIRY_MINUTES = 10;

    public function __construct() {
        $database  = new Database();
        $this->db  = $database->getConnection();
    }

    /**
     * Generate, store, and email a 6-digit OTP.
     *
     * @param string $email  Recipient email address
     * @param string $type   'register' | 'reset'
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendOtp(string $email, string $type): array {
        // --- Invalidate previous unused OTPs for this email+type ---
        $this->db->prepare(
            "UPDATE otp_verifications SET used = 1 WHERE email = :email AND type = :type AND used = 0"
        )->execute([':email' => $email, ':type' => $type]);

        // --- Generate 6-digit OTP ---
        $otp     = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpHash = password_hash($otp, PASSWORD_BCRYPT);
        $expires = date('Y-m-d H:i:s', time() + (self::EXPIRY_MINUTES * 60));

        // --- Store in DB ---
        $insert = $this->db->prepare(
            "INSERT INTO otp_verifications (email, otp_hash, type, expires_at)
             VALUES (:email, :otp_hash, :type, :expires_at)"
        );
        $insert->execute([
            ':email'      => $email,
            ':otp_hash'   => $otpHash,
            ':type'       => $type,
            ':expires_at' => $expires,
        ]);

        // --- Send email ---
        $sent = $this->sendEmail($email, $otp, $type);
        if (!$sent['success']) {
            return $sent;
        }

        return ['success' => true, 'message' => 'OTP sent successfully. Please check your email.'];
    }

    /**
     * Check a submitted OTP without consuming it (marks attempts but not used).
     * Used to validate before proceeding to password reset screen.
     */
    public function checkOtp(string $email, string $otp, string $type): array {
        $stmt = $this->db->prepare(
            "SELECT id, otp_hash, attempts, expires_at FROM otp_verifications
             WHERE email = :email AND type = :type AND used = 0
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([':email' => $email, ':type' => $type]);
        $record = $stmt->fetch();

        if (!$record) {
            return ['success' => false, 'message' => 'No active OTP found. Please request a new code.'];
        }
        if ($record['attempts'] >= self::MAX_ATTEMPTS) {
            return ['success' => false, 'message' => 'Too many incorrect attempts. Please request a new code.'];
        }
        if (strtotime($record['expires_at']) < time()) {
            return ['success' => false, 'message' => 'OTP has expired. Please request a new code.'];
        }

        // Increment attempt counter
        $this->db->prepare(
            "UPDATE otp_verifications SET attempts = attempts + 1 WHERE id = :id"
        )->execute([':id' => $record['id']]);

        if (!password_verify($otp, $record['otp_hash'])) {
            $remaining = self::MAX_ATTEMPTS - ($record['attempts'] + 1);
            if ($remaining <= 0) {
                return ['success' => false, 'message' => 'Incorrect code. No attempts remaining — please request a new code.'];
            }
            return ['success' => false, 'message' => "Incorrect code. {$remaining} attempt(s) remaining."];
        }

        return ['success' => true, 'message' => 'OTP valid.'];
    }

    /**
     * Verify a submitted OTP.
     *
     * @param string $email
     * @param string $otp   Plain-text OTP entered by the user
     * @param string $type  'register' | 'reset'
     * @return array ['success' => bool, 'message' => string]
     */
    public function verifyOtp(string $email, string $otp, string $type): array {
        // Fetch the latest active OTP record
        $stmt = $this->db->prepare(
            "SELECT id, otp_hash, attempts, expires_at FROM otp_verifications
             WHERE email = :email AND type = :type AND used = 0
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([':email' => $email, ':type' => $type]);
        $record = $stmt->fetch();

        if (!$record) {
            return ['success' => false, 'message' => 'No active OTP found. Please request a new code.'];
        }

        // Check if already exceeded attempts
        if ($record['attempts'] >= self::MAX_ATTEMPTS) {
            return ['success' => false, 'message' => 'Too many incorrect attempts. Please request a new code.'];
        }

        // Check expiry
        if (strtotime($record['expires_at']) < time()) {
            return ['success' => false, 'message' => 'OTP has expired. Please request a new code.'];
        }

        // Increment attempt counter
        $this->db->prepare(
            "UPDATE otp_verifications SET attempts = attempts + 1 WHERE id = :id"
        )->execute([':id' => $record['id']]);

        // Verify the code
        if (!password_verify($otp, $record['otp_hash'])) {
            $remaining = self::MAX_ATTEMPTS - ($record['attempts'] + 1);
            if ($remaining <= 0) {
                return ['success' => false, 'message' => 'Incorrect code. No attempts remaining — please request a new code.'];
            }
            return ['success' => false, 'message' => "Incorrect code. {$remaining} attempt(s) remaining."];
        }

        // Mark OTP as used
        $this->db->prepare(
            "UPDATE otp_verifications SET used = 1 WHERE id = :id"
        )->execute([':id' => $record['id']]);

        return ['success' => true, 'message' => 'OTP verified successfully.'];
    }

    /**
     * Send the OTP email via Brevo transactional email API.
     */
    private function sendEmail(string $toEmail, string $otp, string $type): array {
        $subject   = $type === 'register'
            ? 'Your AlgoNest Registration Code'
            : 'Your AlgoNest Password Reset Code';

        $typeLabel  = $type === 'register' ? 'complete your registration' : 'reset your password';
        $expiryMins = self::EXPIRY_MINUTES;

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#060d1f;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#060d1f;padding:40px 0;">
    <tr>
      <td align="center">
        <table width="480" cellpadding="0" cellspacing="0" style="background:#0c1630;border:1px solid rgba(99,130,200,0.2);border-radius:16px;padding:40px;max-width:480px;width:100%;">
          <tr>
            <td align="center" style="padding-bottom:28px;border-bottom:1px solid rgba(99,130,200,0.12);">
              <span style="font-size:22px;font-weight:800;background:linear-gradient(135deg,#60a5fa,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;letter-spacing:-0.5px;">AlgoNest</span>
            </td>
          </tr>
          <tr>
            <td style="padding-top:32px;">
              <p style="margin:0 0 8px;font-size:20px;font-weight:700;color:#f1f5f9;">Verification Code</p>
              <p style="margin:0 0 28px;font-size:14px;color:#7a8fa8;line-height:1.6;">Use the code below to {$typeLabel}. It expires in <strong style="color:#cbd5e1;">{$expiryMins} minutes</strong>.</p>

              <div style="background:#060d1f;border:1px solid rgba(99,130,200,0.25);border-radius:12px;padding:24px;text-align:center;margin-bottom:28px;">
                <span style="font-size:42px;font-weight:800;letter-spacing:12px;color:#60a5fa;font-family:'Courier New',monospace;">{$otp}</span>
              </div>

              <p style="margin:0 0 6px;font-size:13px;color:#475569;line-height:1.6;">If you didn't request this, you can safely ignore this email. Your account is not affected.</p>
              <p style="margin:0;font-size:13px;color:#475569;">For security, never share this code with anyone.</p>
            </td>
          </tr>
          <tr>
            <td style="padding-top:32px;border-top:1px solid rgba(99,130,200,0.1);margin-top:28px;">
              <p style="margin:0;font-size:11px;color:#334155;text-align:center;">© 2026 AlgoNest. All rights reserved.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

        $payload = json_encode([
            'sender'     => ['name' => MAIL_FROM_NAME, 'email' => MAIL_FROM_EMAIL],
            'to'         => [['email' => $toEmail]],
            'subject'    => $subject,
            'htmlContent' => $html,
            'textContent' => "Your AlgoNest verification code is: {$otp}\nIt expires in {$expiryMins} minutes.",
        ]);

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'api-key: ' . BREVO_API_KEY,
                'content-type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        @curl_close($ch);

        if ($curlError) {
            error_log('OtpService curl error: ' . $curlError);
            return ['success' => false, 'message' => 'Failed to send email. Please try again.'];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('OtpService Brevo API error: HTTP ' . $httpCode . ' — ' . $response);
            return ['success' => false, 'message' => 'Failed to send email. Please try again.'];
        }

        return ['success' => true];
    }
}

<?php
namespace Ironcrest\Signature;

/**
 * Email Service - Mailgun Integration
 */
class EmailService {
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    /**
     * Send email via Mailgun API
     */
    public function send($to, $subject, $textBody, $htmlBody = null, $replyTo = null) {
        $url = "https://api.mailgun.net/v3/{$this->config['domain']}/messages";
        
        $postData = [
            'from' => "{$this->config['from_name']} <{$this->config['from_email']}>",
            'to' => $to,
            'subject' => $subject,
            'text' => $textBody,
        ];
        
        if ($htmlBody) {
            $postData['html'] = $htmlBody;
        }
        
        if ($replyTo) {
            $postData['h:Reply-To'] = $replyTo;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, 'api:' . $this->config['api_key']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("Mailgun cURL Error: " . $curlError);
            return ['success' => false, 'error' => $curlError];
        }
        
        if ($httpCode !== 200) {
            error_log("Mailgun API Error (HTTP {$httpCode}): " . $response);
            return ['success' => false, 'error' => "HTTP {$httpCode}"];
        }
        
        return ['success' => true, 'response' => json_decode($response, true)];
    }
    
    /**
     * Send signature delivery email
     */
    public function sendSignatureDelivery($email, $name, $signatureHtml, $publicUuid) {
        $firstName = explode(' ', $name)[0];
        $editUrl = $this->config['app_url'] . '/public/?edit=' . $publicUuid;
        
        $subject = 'Your Email Signature (HTML + Install Guide)';
        
        $textBody = "Hi {$firstName},\n\n";
        $textBody .= "Your professional email signature is ready!\n\n";
        $textBody .= "COPY YOUR SIGNATURE HTML:\n";
        $textBody .= "Visit: {$editUrl}\n\n";
        $textBody .= "INSTALL GUIDES:\n\n";
        $textBody .= "Gmail:\n";
        $textBody .= "1. Settings → See all settings → General → Signature\n";
        $textBody .= "2. Create new → Paste HTML → Save\n\n";
        $textBody .= "Outlook:\n";
        $textBody .= "1. File/Settings → Mail → Signatures\n";
        $textBody .= "2. New → Paste HTML → Save\n\n";
        $textBody .= "Apple Mail:\n";
        $textBody .= "1. Preferences → Signatures → +\n";
        $textBody .= "2. Paste as rich text\n\n";
        $textBody .= "Need to edit? Visit: {$editUrl}\n\n";
        $textBody .= "Questions? Reply to this email.\n\n";
        $textBody .= "Best,\nIroncrest Software Team\n";
        $textBody .= "https://ironcrestsoftware.com";
        
        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 20px;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <!-- Header -->
                            <tr>
                                <td style="padding: 30px; text-align: center; background-color: #2B68C1 !important; background: linear-gradient(135deg, #2B68C1 0%, #2A3B8F 100%); border-radius: 8px 8px 0 0;">
                                    <h1 style="margin: 0; color: #ffffff !important; font-size: 24px; mso-line-height-rule: exactly;">Your Email Signature is Ready! 🎉</h1>
                                </td>
                            </tr>
                            
                            <!-- Content -->
                            <tr>
                                <td style="padding: 30px;">
                                    <p style="margin: 0 0 20px; color: #334155; font-size: 16px; line-height: 1.6;">Hi ' . htmlspecialchars($firstName) . ',</p>
                                    <p style="margin: 0 0 20px; color: #334155; font-size: 16px; line-height: 1.6;">Your professional email signature is ready! Here\'s a preview:</p>
                                    
                                    <h2 style="margin: 0 0 15px; color: #1a1a1a; font-size: 18px;">📧 Your Signature Preview</h2>
                                    <div style="background: #fef3c7; padding: 12px; border-radius: 8px; border-left: 4px solid #f59e0b; margin-bottom: 15px;">
                                        <p style="margin: 0; color: #92400e; font-size: 13px;"><strong>⚠️ Can\'t see images?</strong> Click "Show images" or "Display images" in your email client to view the signature preview below.</p>
                                    </div>
                                    <div style="background: #ffffff; padding: 20px; border-radius: 8px; border: 2px dashed #cbd5e1; margin-bottom: 30px;">
                                        ' . $signatureHtml . '
                                    </div>
                                    
                                    <h2 style="margin: 20px 0 15px; color: #1a1a1a; font-size: 18px;">📋 How to Install</h2>
                                    <div style="background: #f0f9ff; padding: 20px; border-radius: 8px; border-left: 4px solid #2B68C1; margin-bottom: 20px;">
                                        <p style="margin: 0 0 15px; color: #1e40af; font-weight: bold;">Best Method: Copy Visual</p>
                                        <ol style="margin: 0; padding-left: 20px; color: #1e3a8a; line-height: 1.8;">
                                            <li>Select the signature preview above</li>
                                            <li>Copy it (Ctrl+C or Cmd+C)</li>
                                            <li>Go to your email settings → Signature</li>
                                            <li>Paste directly (Ctrl+V or Cmd+V)</li>
                                        </ol>
                                    </div>
                                    
                                    <div style="background: #dcfce7; padding: 15px; border-radius: 8px; border-left: 4px solid #16a34a; margin-bottom: 20px;">
                                        <p style="margin: 0 0 10px; color: #166534; font-size: 14px;"><strong>✓ Don\'t worry about images!</strong> Even if images don\'t show in this email preview, they WILL work perfectly once you install the signature.</p>
                                        <p style="margin: 0; color: #166534; font-size: 13px;"><strong>Why?</strong> The image HTML code IS included when you copy - it just doesn\'t display in received emails for security. Once installed as your signature, images will show perfectly in all emails you send.</p>
                                    </div>
                                    
                                    <div style="background: #fef3c7; padding: 15px; border-radius: 8px; border-left: 4px solid #f59e0b; margin-bottom: 20px;">
                                        <p style="margin: 0; color: #92400e; font-size: 14px;"><strong>💡 Tip:</strong> If copying doesn\'t work, click the button below to access the HTML code and other export options.</p>
                                    </div>
                                    
                                    <div style="text-align: center; margin-top: 30px;">
                                        <a href="' . htmlspecialchars($editUrl) . '" style="display: inline-block; background-color: #2B68C1 !important; background: linear-gradient(135deg, #2B68C1 0%, #2A3B8F 100%); color: #ffffff !important; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); mso-padding-alt: 14px 32px;">View & Copy Signature</a>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td style="padding: 20px; text-align: center; background-color: #f8fafc; border-radius: 0 0 8px 8px; border-top: 1px solid #e2e8f0;">
                                    <p style="margin: 0; color: #64748b; font-size: 12px;">
                                        Questions? Reply to this email.<br>
                                        <a href="https://ironcrestsoftware.com" style="color: #2B68C1; text-decoration: none;">Ironcrest Software</a>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ';
        
        return $this->send($email, $subject, $textBody, $htmlBody);
    }
    
    /**
     * Send magic link email
     */
    public function sendMagicLink($email, $token, $redirectPath = null) {
        $magicUrl = $this->config['app_url'] . '/api/auth/verify.php?token=' . $token;
        if ($redirectPath) {
            $magicUrl .= '&redirect=' . urlencode($redirectPath);
        }
        
        $subject = 'Sign in to Ironcrest Email Signatures';
        
        $textBody = "Click the link below to sign in:\n\n";
        $textBody .= $magicUrl . "\n\n";
        $textBody .= "This link expires in 1 hour.\n\n";
        $textBody .= "If you didn't request this, you can safely ignore this email.\n\n";
        $textBody .= "Ironcrest Software\n";
        $textBody .= "https://ironcrestsoftware.com";
        
        require_once __DIR__ . '/../../../../httpdocs/includes/EmailTemplate.php';
        
        $content = \EmailTemplate::section(
            'Sign In',
            '<p style="font-size: 15px; line-height: 1.7; color: #334155;">Click the button below to securely sign in to your account. This link expires in 1 hour.</p>'
        );
        
        $content .= \EmailTemplate::button('Sign In Now', $magicUrl, '🔐');
        
        $content .= \EmailTemplate::metadata([
            'Expires' => '1 hour from now',
            'Security' => 'This is a one-time use link'
        ]);
        
        $htmlBody = \EmailTemplate::generate(
            'Sign In to Your Account',
            $content,
            ['alert_message' => 'Click the button below to sign in securely']
        );
        
        return $this->send($email, $subject, $textBody, $htmlBody);
    }
}

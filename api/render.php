<?php
/**
 * Render API
 * POST /api/render.php - Generate signature HTML
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    error_log('Render API - Input received: ' . json_encode($input));
    
    if (!isset($input['templateKey']) || !isset($input['config'])) {
        error_log('Render API - Missing fields. templateKey: ' . (isset($input['templateKey']) ? 'YES' : 'NO') . ', config: ' . (isset($input['config']) ? 'YES' : 'NO'));
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing required fields: templateKey, config',
        ]);
        exit;
    }
    
    $templateKey = $input['templateKey'];
    $configData = $input['config'];
    
    // Handle config as either string (JSON) or already parsed object
    if (is_string($configData)) {
        $signatureConfig = json_decode($configData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Render API - Invalid JSON in config: ' . json_last_error_msg());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid JSON in config field',
            ]);
            exit;
        }
    } else {
        $signatureConfig = $configData;
    }
    
    error_log('Render API - Parsed config: ' . json_encode($signatureConfig));
    
    // Extract ALL data from config
    $identity = $signatureConfig['identity'] ?? [];
    $company = $signatureConfig['company'] ?? [];
    $contact = $signatureConfig['contact'] ?? [];
    $links = $signatureConfig['links'] ?? [];
    $branding = $signatureConfig['branding'] ?? [];
    $addons = $signatureConfig['addons'] ?? [];
    
    // Debug: Log the addons data specifically
    error_log('Render API - Addons data: ' . json_encode($addons));
    error_log('Render API - CTA data: ' . json_encode($addons['cta'] ?? 'NOT FOUND'));
    
    // Prepare all data for rendering
    $data = [
        // Identity
        'name' => htmlspecialchars($identity['name'] ?? ''),
        'title' => htmlspecialchars($identity['title'] ?? ''),
        'pronouns' => htmlspecialchars($identity['pronouns'] ?? ''),
        
        // Company
        'company_name' => htmlspecialchars($company['name'] ?? ''),
        'company_slogan' => nl2br(htmlspecialchars($company['slogan'] ?? '')),
        'logo_url' => htmlspecialchars($company['logoUrl'] ?? ''),
        
        // Contact
        'email' => htmlspecialchars($contact['email'] ?? ''),
        'phone' => htmlspecialchars($contact['phone'] ?? ''),
        'website' => htmlspecialchars($contact['website'] ?? ''),
        'calendly' => htmlspecialchars($contact['calendly'] ?? ''),
        
        // Links
        'linkedin' => htmlspecialchars($links['linkedin'] ?? ''),
        'x' => htmlspecialchars($links['x'] ?? ''),
        'github' => htmlspecialchars($links['github'] ?? ''),
        'facebook' => htmlspecialchars($links['facebook'] ?? ''),
        'instagram' => htmlspecialchars($links['instagram'] ?? ''),
        'youtube' => htmlspecialchars($links['youtube'] ?? ''),
        
        // Branding
        'accent_color' => htmlspecialchars($branding['accent'] ?? '#2B68C1'),
        'logo_size' => intval($branding['logoSize'] ?? 80),
        'font_size' => htmlspecialchars($branding['fontSize'] ?? 'medium'),
        'line_height' => htmlspecialchars($branding['lineHeight'] ?? 'normal'),
        'spacing' => htmlspecialchars($branding['spacing'] ?? 'normal'),
        'letter_spacing' => htmlspecialchars($branding['letterSpacing'] ?? 'normal'),
        'icon_style' => htmlspecialchars($branding['iconStyle'] ?? 'outline'),
        'corner_radius' => htmlspecialchars($branding['cornerRadius'] ?? 'medium'),
        
        // Addons - Handle both old single CTA and new multiple CTAs
        'cta_buttons' => processCTAButtons($addons['cta'] ?? []),
        'cta_label' => htmlspecialchars($addons['cta']['label'] ?? ''),
        'cta_url' => htmlspecialchars($addons['cta']['url'] ?? ''),
        'cta_corner_radius' => htmlspecialchars($addons['cta']['cornerRadius'] ?? 'medium'),
        'disclaimer' => nl2br(htmlspecialchars($addons['disclaimer'] ?? '')),
    ];
    
    // Generate tracking links if we have signature ID and user ID
    $signatureId = $input['signatureId'] ?? null;
    $userId = $input['userId'] ?? null;
    $trackingLinks = [];
    
    if ($signatureId && $userId) {
        $trackingLinks = generateTrackingLinks($pdo, $signatureId, $userId, $data);
    }
    
    // Generate comprehensive HTML based on template
    $html = generateComprehensiveSignatureHtml($templateKey, $data, $trackingLinks);
    
    // Add tracking pixel if we have signature ID and user ID
    if ($signatureId && $userId) {
        $trackingPixel = getTrackingPixel($signatureId, $userId);
        $html .= $trackingPixel;
    }
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
    
} catch (Exception $e) {
    error_log('Render API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to render signature: ' . $e->getMessage()
    ]);
}

// Process multiple CTA buttons
function processCTAButtons($ctaData) {
    $buttons = [];
    
    // Handle new format with indexed buttons (0, 1, 2, etc.)
    if (is_array($ctaData)) {
        foreach ($ctaData as $key => $value) {
            if (is_numeric($key) && is_array($value)) {
                if (!empty($value['label']) && !empty($value['url'])) {
                    $buttons[] = [
                        'label' => htmlspecialchars($value['label']),
                        'url' => htmlspecialchars($value['url']),
                        'cornerRadius' => htmlspecialchars($value['cornerRadius'] ?? 'medium')
                    ];
                }
            }
        }
    }
    
    // Fallback to old format if no indexed buttons found
    if (empty($buttons) && isset($ctaData['label']) && isset($ctaData['url'])) {
        $buttons[] = [
            'label' => htmlspecialchars($ctaData['label']),
            'url' => htmlspecialchars($ctaData['url']),
            'cornerRadius' => htmlspecialchars($ctaData['cornerRadius'] ?? 'medium')
        ];
    }
    
    return $buttons;
}

// Generate CTA buttons HTML
function generateCTAButtonsHtml($buttons, $accentColor) {
    if (empty($buttons)) return '';
    
    $html = '<div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">';
    
    $borderRadiusMap = ['none' => '0px', 'small' => '4px', 'medium' => '8px', 'large' => '20px'];
    
    foreach ($buttons as $button) {
        $borderRadius = $borderRadiusMap[$button['cornerRadius']] ?? '8px';
        $html .= "<a href='{$button['url']}' style='background: {$accentColor}; color: white; padding: 10px 18px; text-decoration: none; border-radius: {$borderRadius}; font-size: 13px; font-weight: 600; display: inline-block;'>{$button['label']}</a>";
    }
    
    $html .= '</div>';
    return $html;
}

function generateComprehensiveSignatureHtml($templateKey, $data, $trackingLinks = []) {
    // Helper function to generate social links with tracking
    $socialLinks = '';
    if ($data['linkedin']) {
        $url = $trackingLinks['linkedin'] ?? $data['linkedin'];
        $socialLinks .= "<a href='{$url}' style='color: {$data['accent_color']}; text-decoration: none; margin-right: 8px;'>LinkedIn</a>";
    }
    if ($data['x']) {
        $url = $trackingLinks['x'] ?? $data['x'];
        $socialLinks .= "<a href='{$url}' style='color: {$data['accent_color']}; text-decoration: none; margin-right: 8px;'>X</a>";
    }
    if ($data['github']) {
        $url = $trackingLinks['github'] ?? $data['github'];
        $socialLinks .= "<a href='{$url}' style='color: {$data['accent_color']}; text-decoration: none; margin-right: 8px;'>GitHub</a>";
    }
    if ($data['facebook']) {
        $url = $trackingLinks['facebook'] ?? $data['facebook'];
        $socialLinks .= "<a href='{$url}' style='color: {$data['accent_color']}; text-decoration: none; margin-right: 8px;'>Facebook</a>";
    }
    if ($data['instagram']) {
        $url = $trackingLinks['instagram'] ?? $data['instagram'];
        $socialLinks .= "<a href='{$url}' style='color: {$data['accent_color']}; text-decoration: none; margin-right: 8px;'>Instagram</a>";
    }
    if ($data['youtube']) {
        $url = $trackingLinks['youtube'] ?? $data['youtube'];
        $socialLinks .= "<a href='{$url}' style='color: {$data['accent_color']}; text-decoration: none; margin-right: 8px;'>YouTube</a>";
    }
    
    // Helper function to generate contact info with tracking
    $contactInfo = '';
    if ($data['email']) {
        $url = $trackingLinks['email'] ?? 'mailto:' . $data['email'];
        $contactInfo .= "<div>📧 <a href='{$url}' style='color: {$data['accent_color']}; text-decoration: none;'>{$data['email']}</a></div>";
    }
    if ($data['phone']) {
        $url = $trackingLinks['phone'] ?? 'tel:' . $data['phone'];
        $contactInfo .= "<div>📞 <a href='{$url}' style='color: {$data['accent_color']}; text-decoration: none;'>{$data['phone']}</a></div>";
    }
    if ($data['website']) {
        $url = $trackingLinks['website'] ?? $data['website'];
        $contactInfo .= "<div>🌐 <a href='{$url}' style='color: {$data['accent_color']}; text-decoration: none;'>{$data['website']}</a></div>";
    }
    if ($data['calendly']) {
        $url = $trackingLinks['calendly'] ?? $data['calendly'];
        $contactInfo .= "<div>📅 <a href='{$url}' style='color: {$data['accent_color']}; text-decoration: none;'>Book a Meeting</a></div>";
    }
    
    // Logo HTML if provided
    $logoHtml = $data['logo_url'] ? "<img src='{$data['logo_url']}' alt='Logo' style='width: {$data['logo_size']}px; height: auto; margin-right: 15px; vertical-align: top;'>" : '';
    
    // Helper function to generate CTA button with tracking
    function generateCTAButton($data, $trackingLinks, $style = 'default') {
        if (!$data['cta_label'] || !$data['cta_url']) return '';
        
        $url = $trackingLinks['custom'] ?? $data['cta_url'];
        $borderRadiusMap = ['none' => '0px', 'small' => '4px', 'medium' => '8px', 'large' => '20px'];
        $borderRadius = $borderRadiusMap[$data['cta_corner_radius']] ?? '8px';
        
        $styles = [
            'default' => "background: {$data['accent_color']}; color: white; padding: 8px 16px; text-decoration: none; border-radius: {$borderRadius}; font-size: 12px; font-weight: 600; display: inline-block;",
            'minimal' => "background: {$data['accent_color']}; color: white; padding: 10px 18px; text-decoration: none; border-radius: {$borderRadius}; font-size: 13px; font-weight: 600; display: inline-block; box-shadow: 0 1px 3px rgba(0,0,0,0.12);",
            'corporate' => "background: {$data['accent_color']}; color: white; padding: 10px 20px; text-decoration: none; border-radius: {$borderRadius}; font-size: 13px; font-weight: 600; display: inline-block;",
            'badge' => "background: {$data['accent_color']}; color: white; padding: 10px 18px; text-decoration: none; border-radius: {$borderRadius}; font-size: 13px; font-weight: 600; display: inline-block; box-shadow: 0 2px 4px rgba(0,0,0,0.1);",
            'text' => "color: {$data['accent_color']}; text-decoration: underline; font-weight: 600;",
            'executive' => "background: {$data['accent_color']}; color: white; padding: 12px 24px; text-decoration: none; border-radius: {$borderRadius}; font-size: 14px; font-weight: 600; display: inline-block; box-shadow: 0 3px 6px rgba(0,0,0,0.15); font-family: Arial, sans-serif;"
        ];
        
        $buttonStyle = $styles[$style] ?? $styles['default'];
        return "<a href='{$url}' style='{$buttonStyle}'>{$data['cta_label']}</a>";
    }
    
    // Font size mapping
    $fontSizes = ['small' => '12px', 'medium' => '14px', 'large' => '16px'];
    $fontSize = $fontSizes[$data['font_size']] ?? '14px';
    
    // Line height mapping  
    $lineHeights = ['tight' => '1.2', 'normal' => '1.4', 'relaxed' => '1.6'];
    $lineHeight = $lineHeights[$data['line_height']] ?? '1.4';
    
    // Letter spacing mapping
    $letterSpacings = ['tight' => '-0.5px', 'normal' => '0px', 'loose' => '1px'];
    $letterSpacing = $letterSpacings[$data['letter_spacing']] ?? '0px';
    
    switch ($templateKey) {
        case 'minimal-line':
            return "
            <table style='font-family: Arial, sans-serif; font-size: {$fontSize}; line-height: {$lineHeight}; letter-spacing: {$letterSpacing}; border-collapse: collapse; max-width: 400px;'>
                <tr>
                    <td style='border-left: 3px solid {$data['accent_color']}; padding-left: 15px; vertical-align: top;'>
                        " . ($logoHtml ? "<div style='margin-bottom: 12px;'>{$logoHtml}</div>" : "") . "
                        <div style='font-weight: bold; color: #1f2937; font-size: 16px; margin-bottom: 4px;'>{$data['name']}" . 
                        ($data['pronouns'] ? " <span style='color: #6b7280; font-weight: normal; font-size: 14px;'>({$data['pronouns']})</span>" : "") . "</div>
                        " . ($data['title'] ? "<div style='color: {$data['accent_color']}; font-weight: 600; margin-bottom: 2px;'>{$data['title']}</div>" : "") . "
                        " . ($data['company_name'] ? "<div style='color: #6b7280; margin-bottom: 12px; font-size: 14px;'>{$data['company_name']}" . 
                        ($data['company_slogan'] ? " <span style='color: #9ca3af;'>• {$data['company_slogan']}</span>" : "") . "</div>" : "") . "
                        <div style='font-size: 13px; color: #6b7280; line-height: 1.5;'>
                            {$contactInfo}
                        </div>
                        " . ($socialLinks ? "<div style='margin-top: 12px; font-size: 12px;'>{$socialLinks}</div>" : "") . "
                        " . (generateCTAButton($data, $trackingLinks, 'minimal') ? "<div style='margin-top: 15px;'>" . generateCTAButton($data, $trackingLinks, 'minimal') . "</div>" : "") . "
                        " . ($data['disclaimer'] ? "<div style='font-size: 10px; color: #9ca3af; margin-top: 12px; line-height: 1.3; border-top: 1px solid #e5e7eb; padding-top: 8px;'>{$data['disclaimer']}</div>" : "") . "
                    </td>
                </tr>
            </table>";
            
        case 'corporate-block':
            return "
            <table style='font-family: Arial, sans-serif; font-size: {$fontSize}; line-height: {$lineHeight}; letter-spacing: {$letterSpacing}; border-collapse: collapse; max-width: 500px;'>
                <tr>
                    <td style='background: {$data['accent_color']}; padding: 1px;'>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='background: white; padding: 20px;'>
                                    " . ($logoHtml ? "<div style='margin-bottom: 15px;'>{$logoHtml}</div>" : "") . "
                                    <div style='font-weight: bold; color: {$data['accent_color']}; font-size: 18px; margin-bottom: 2px;'>{$data['name']}" . 
                                    ($data['pronouns'] ? " <span style='color: #6b7280; font-weight: normal; font-size: 14px;'>({$data['pronouns']})</span>" : "") . "</div>
                                    " . ($data['title'] ? "<div style='color: #1f2937; font-weight: 600; margin-bottom: 2px;'>{$data['title']}</div>" : "") . "
                                    " . ($data['company_name'] ? "<div style='color: #6b7280; margin-bottom: 12px; font-size: 14px;'>{$data['company_name']}" . 
                                    ($data['company_slogan'] ? "<br/><span style='color: #9ca3af; font-size: 12px;'>{$data['company_slogan']}</span>" : "") . "</div>" : "") . "
                                    <div style='border-top: 2px solid {$data['accent_color']}; padding-top: 12px; margin-top: 12px; font-size: 13px; color: #4b5563;'>
                                        {$contactInfo}
                                    </div>
                                    " . ($socialLinks ? "<div style='margin-top: 12px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 12px;'>{$socialLinks}</div>" : "") . "
                                    " . (count($data['cta_buttons'] ?? []) > 0 ? "<div style='margin-top: 15px;'>" . generateCTAButtonsHtml($data['cta_buttons'], $data['accent_color']) . "</div>" : "") . "
                                    " . ($data['disclaimer'] ? "<div style='font-size: 10px; color: #9ca3af; margin-top: 12px; border-top: 1px solid #e5e7eb; padding-top: 8px;'>{$data['disclaimer']}</div>" : "") . "
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>";
            
        case 'badge':
            return "
            <div style='font-family: Arial, sans-serif; font-size: {$fontSize}; line-height: {$lineHeight}; background: linear-gradient(135deg, {$data['accent_color']}22, {$data['accent_color']}11); padding: 20px; border-radius: 12px; border: 1px solid {$data['accent_color']}33;'>
                <div style='display: flex; align-items: center; gap: 15px;'>
                    {$logoHtml}
                    <div>
                        <div style='font-weight: bold; color: #1f2937; font-size: 18px; margin-bottom: 4px;'>{$data['name']}" . 
                        ($data['pronouns'] ? " ({$data['pronouns']})" : "") . "</div>
                        " . ($data['title'] ? "<div style='color: {$data['accent_color']}; font-weight: 600; margin-bottom: 2px;'>{$data['title']}</div>" : "") . "
                        " . ($data['company_name'] ? "<div style='color: #6b7280; margin-bottom: 12px;'>{$data['company_name']}" . 
                        ($data['company_slogan'] ? " - {$data['company_slogan']}" : "") . "</div>" : "") . "
                        <div style='font-size: 12px; color: #6b7280;'>
                            {$contactInfo}
                        </div>
                        " . ($socialLinks ? "<div style='margin-top: 10px; font-size: 12px;'>{$socialLinks}</div>" : "") . "
                        " . ($data['cta_label'] && $data['cta_url'] ? 
                        "<div style='margin-top: 12px;'><a href='{$data['cta_url']}' style='background: {$data['accent_color']}; color: white; padding: 10px 18px; text-decoration: none; border-radius: " . 
                        ($data['cta_corner_radius'] === 'none' ? '0px' : ($data['cta_corner_radius'] === 'small' ? '4px' : '8px')) . 
                        "; font-size: 13px; font-weight: 600; display: inline-block; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>{$data['cta_label']}</a></div>" : "") . "
                    </div>
                </div>
                " . ($data['disclaimer'] ? "<div style='font-size: 10px; color: #9ca3af; margin-top: 15px; text-align: center;'>{$data['disclaimer']}</div>" : "") . "
            </div>";
            
        case 'simple-text':
            // Plain text professional signature (like example #1)
            return "
            <div style='font-family: Arial, sans-serif; font-size: {$fontSize}; line-height: {$lineHeight}; color: #1f2937;'>
                <div style='font-weight: bold; margin-bottom: 2px;'>{$data['name']}" . 
                ($data['pronouns'] ? " ({$data['pronouns']})" : "") . "</div>
                " . ($data['title'] && $data['company_name'] ? "<div style='margin-bottom: 2px;'>{$data['title']} | {$data['company_name']}" . 
                ($data['company_slogan'] ? " - {$data['company_slogan']}" : "") . "</div>" : 
                ($data['title'] ? "<div style='margin-bottom: 2px;'>{$data['title']}</div>" : "") . 
                ($data['company_name'] ? "<div style='margin-bottom: 2px;'>{$data['company_name']}" . 
                ($data['company_slogan'] ? " - {$data['company_slogan']}" : "") . "</div>" : "")) . "
                " . ($data['phone'] ? "<div>Phone: {$data['phone']}</div>" : "") . "
                " . ($data['email'] ? "<div>Email: <a href='mailto:{$data['email']}' style='color: {$data['accent_color']}; text-decoration: none;'>{$data['email']}</a></div>" : "") . "
                " . ($data['website'] ? "<div>Website: <a href='{$data['website']}' style='color: {$data['accent_color']}; text-decoration: none;'>{$data['website']}</a></div>" : "") . "
                " . ($data['calendly'] ? "<div>Schedule: <a href='{$data['calendly']}' style='color: {$data['accent_color']}; text-decoration: none;'>Book a Meeting</a></div>" : "") . "
                " . ($socialLinks ? "<div style='margin-top: 8px;'>{$socialLinks}</div>" : "") . "
                " . ($data['cta_label'] && $data['cta_url'] ? 
                "<div style='margin-top: 10px;'><a href='{$data['cta_url']}' style='color: {$data['accent_color']}; text-decoration: underline; font-weight: 600;'>{$data['cta_label']}</a></div>" : "") . "
                " . ($data['disclaimer'] ? "<div style='font-size: 10px; color: #6b7280; margin-top: 8px; border-top: 1px solid #e5e7eb; padding-top: 6px;'>{$data['disclaimer']}</div>" : "") . "
            </div>";
            
        case 'professional-headshot':
            // Professional signature with headshot (like example #4)
            $headshotHtml = $data['logo_url'] ? "<img src='{$data['logo_url']}' alt='{$data['name']}' style='width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-right: 20px; border: 2px solid #e5e7eb;'>" : '';
            return "
            <table style='font-family: Arial, sans-serif; font-size: {$fontSize}; border-collapse: collapse; max-width: 500px;'>
                <tr>
                    <td style='vertical-align: top; padding-right: 20px;'>
                        {$headshotHtml}
                    </td>
                    <td style='vertical-align: top;'>
                        <div style='font-weight: bold; color: #1f2937; font-size: 18px; margin-bottom: 4px;'>{$data['name']}" . 
                        ($data['pronouns'] ? " <span style='color: #6b7280; font-weight: normal; font-size: 14px;'>({$data['pronouns']})</span>" : "") . "</div>
                        " . ($data['title'] ? "<div style='color: {$data['accent_color']}; font-weight: 600; margin-bottom: 2px; font-size: 15px;'>{$data['title']}</div>" : "") . "
                        " . ($data['company_name'] ? "<div style='color: #6b7280; margin-bottom: 12px; font-size: 14px;'>{$data['company_name']}" . 
                        ($data['company_slogan'] ? " • {$data['company_slogan']}" : "") . "</div>" : "") . "
                        <div style='font-size: 13px; color: #6b7280; line-height: 1.6;'>
                            {$contactInfo}
                        </div>
                        " . ($socialLinks ? "<div style='margin-top: 12px; font-size: 12px;'>{$socialLinks}</div>" : "") . "
                        " . ($data['cta_label'] && $data['cta_url'] ? 
                        "<div style='margin-top: 15px;'><a href='{$data['cta_url']}' style='background: {$data['accent_color']}; color: white; padding: 10px 20px; text-decoration: none; border-radius: " . 
                        ($data['cta_corner_radius'] === 'none' ? '0px' : ($data['cta_corner_radius'] === 'small' ? '4px' : '8px')) . 
                        "; font-size: 13px; font-weight: 600; display: inline-block; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>{$data['cta_label']}</a></div>" : "") . "
                        " . ($data['disclaimer'] ? "<div style='font-size: 10px; color: #9ca3af; margin-top: 12px; line-height: 1.3; border-top: 1px solid #e5e7eb; padding-top: 8px;'>{$data['disclaimer']}</div>" : "") . "
                    </td>
                </tr>
            </table>";
            
        case 'executive':
            // Executive/CEO style signature (like example #8)
            return "
            <table style='font-family: 'Times New Roman', serif; font-size: {$fontSize}; border-collapse: collapse; max-width: 450px;'>
                <tr>
                    <td style='vertical-align: top; border-left: 2px solid {$data['accent_color']}; padding-left: 20px;'>
                        " . ($logoHtml ? "<div style='margin-bottom: 15px;'>{$logoHtml}</div>" : "") . "
                        <div style='font-weight: bold; color: #1f2937; font-size: 20px; margin-bottom: 6px;'>{$data['name']}" . 
                        ($data['pronouns'] ? " <span style='color: #6b7280; font-weight: normal; font-size: 16px;'>({$data['pronouns']})</span>" : "") . "</div>
                        " . ($data['title'] ? "<div style='color: {$data['accent_color']}; font-weight: 600; margin-bottom: 4px; font-size: 16px; font-style: italic;'>{$data['title']}</div>" : "") . "
                        " . ($data['company_name'] ? "<div style='color: #1f2937; margin-bottom: 15px; font-size: 15px; font-weight: 600;'>{$data['company_name']}" . 
                        ($data['company_slogan'] ? "<br><span style='font-size: 13px; color: #6b7280; font-weight: normal; font-style: italic;'>\"{$data['company_slogan']}\"</span>" : "") . "</div>" : "") . "
                        <div style='font-size: 13px; color: #6b7280; line-height: 1.6; border-top: 1px solid #e5e7eb; padding-top: 12px;'>
                            {$contactInfo}
                        </div>
                        " . ($socialLinks ? "<div style='margin-top: 15px; font-size: 12px; padding-top: 10px; border-top: 1px solid #f3f4f6;'>{$socialLinks}</div>" : "") . "
                        " . ($data['cta_label'] && $data['cta_url'] ? 
                        "<div style='margin-top: 18px;'><a href='{$data['cta_url']}' style='background: {$data['accent_color']}; color: white; padding: 12px 24px; text-decoration: none; border-radius: " . 
                        ($data['cta_corner_radius'] === 'none' ? '0px' : ($data['cta_corner_radius'] === 'small' ? '4px' : '8px')) . 
                        "; font-size: 14px; font-weight: 600; display: inline-block; box-shadow: 0 3px 6px rgba(0,0,0,0.15); font-family: Arial, sans-serif;'>{$data['cta_label']}</a></div>" : "") . "
                        " . ($data['disclaimer'] ? "<div style='font-size: 9px; color: #9ca3af; margin-top: 15px; line-height: 1.3; border-top: 1px solid #e5e7eb; padding-top: 10px; font-family: Arial, sans-serif;'>{$data['disclaimer']}</div>" : "") . "
                    </td>
                </tr>
            </table>";
            
        default:
            return "
            <div style='font-family: Arial, sans-serif; font-size: {$fontSize}; line-height: {$lineHeight};'>
                <div style='display: flex; align-items: flex-start; gap: 15px;'>
                    {$logoHtml}
                    <div>
                        <div style='font-weight: bold; color: #1f2937; margin-bottom: 4px;'>{$data['name']}" . 
                        ($data['pronouns'] ? " ({$data['pronouns']})" : "") . "</div>
                        " . ($data['title'] ? "<div style='color: #6b7280; margin-bottom: 2px;'>{$data['title']}</div>" : "") . "
                        " . ($data['company_name'] ? "<div style='color: #6b7280; margin-bottom: 8px;'>{$data['company_name']}" . 
                        ($data['company_slogan'] ? " - {$data['company_slogan']}" : "") . "</div>" : "") . "
                        <div style='font-size: 12px; color: #6b7280;'>
                            {$contactInfo}
                        </div>
                        " . ($socialLinks ? "<div style='margin-top: 8px; font-size: 12px;'>{$socialLinks}</div>" : "") . "
                        " . ($data['cta_label'] && $data['cta_url'] ? 
                        "<div style='margin-top: 10px;'><a href='{$data['cta_url']}' style='background: {$data['accent_color']}; color: white; padding: 8px 16px; text-decoration: none; border-radius: " . 
                        ($data['cta_corner_radius'] === 'none' ? '0px' : ($data['cta_corner_radius'] === 'small' ? '4px' : '8px')) . 
                        "; font-size: 12px; font-weight: 600; display: inline-block;'>{$data['cta_label']}</a></div>" : "") . "
                        " . ($data['disclaimer'] ? "<div style='font-size: 10px; color: #9ca3af; margin-top: 8px;'>{$data['disclaimer']}</div>" : "") . "
                    </div>
                </div>
            </div>";
    }
}

/**
 * Generate tracking links for signature
 */
function generateTrackingLinks($pdo, $signatureId, $userId, $data) {
    $trackingLinks = [];
    $baseUrl = 'https://apps.ironcrestsoftware.com/email-signature/api/click.php?c=';
    
    // Generate tracking links for each type of link
    $linkTypes = [
        'email' => $data['email'],
        'phone' => $data['phone'] ? 'tel:' . $data['phone'] : '',
        'website' => $data['website'],
        'calendly' => $data['calendly'],
        'linkedin' => $data['linkedin'],
        'x' => $data['x'],
        'github' => $data['github'],
        'facebook' => $data['facebook'],
        'instagram' => $data['instagram'],
        'youtube' => $data['youtube'],
        'custom' => $data['cta_url']
    ];
    
    foreach ($linkTypes as $type => $url) {
        if (!empty($url)) {
            // Generate short code
            $shortCode = generateShortCode();
            
            // Store tracking link in database
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO sig_tracking_links 
                    (signature_id, user_id, short_code, link_type, destination_url, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ');
                $stmt->execute([$signatureId, $userId, $shortCode, $type, $url]);
                
                // Store the tracking URL
                $trackingLinks[$type] = $baseUrl . $shortCode;
            } catch (Exception $e) {
                // If tracking link creation fails, use original URL
                $trackingLinks[$type] = $url;
                error_log('Failed to create tracking link: ' . $e->getMessage());
            }
        }
    }
    
    return $trackingLinks;
}

/**
 * Generate a unique short code for tracking links
 */
function generateShortCode($length = 8) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $shortCode = '';
    for ($i = 0; $i < $length; $i++) {
        $shortCode .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $shortCode;
}

/**
 * Generate tracking pixel HTML for analytics
 */
function getTrackingPixel($signatureId, $userId) {
    $pixelUrl = 'https://apps.ironcrestsoftware.com/email-signature/api/pixel.php?s=' . urlencode($signatureId) . '&u=' . urlencode($userId);
    return "\n<!-- Email Signature Analytics -->\n<img src=\"{$pixelUrl}\" width=\"1\" height=\"1\" style=\"display: none;\" alt=\"\" />";
}

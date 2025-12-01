<?php
namespace Ironcrest\Signature;

/**
 * HTML Signature Renderer
 * Generates email-safe HTML from config + template
 */
class Renderer {
    private $signatureId;
    private $userId;
    private $trackingLinks = [];
    
    /**
     * Render signature HTML
     */
    public function render($config, $templateKey, $signatureId = null, $userId = null) {
        $this->signatureId = $signatureId;
        $this->userId = $userId;
        $this->trackingLinks = $config['_trackingLinks'] ?? [];
        
        $method = 'render' . str_replace('-', '', ucwords($templateKey, '-'));
        
        if (method_exists($this, $method)) {
            $html = $this->$method($config);
        } else {
            // Fallback to minimal-line
            $html = $this->renderMinimalLine($config);
        }
        
        // Add tracking pixel if we have signature ID and user ID
        if ($this->signatureId && $this->userId) {
            $html .= $this->getTrackingPixel();
        }
        
        return $html;
    }
    
    /**
     * Wrap a link with tracking if available
     */
    private function wrapLink($url, $linkType, $text) {
        // If we have a tracking link for this type, use it
        if (isset($this->trackingLinks[$linkType])) {
            $trackingUrl = 'https://apps.ironcrestsoftware.com/email-signature/api/click.php?c=' . $this->trackingLinks[$linkType];
            return '<a href="' . htmlspecialchars($trackingUrl) . '" style="color: inherit; text-decoration: none;">' . htmlspecialchars($text) . '</a>';
        }
        
        // Otherwise, use direct link
        return '<a href="' . htmlspecialchars($url) . '" style="color: inherit; text-decoration: none;">' . htmlspecialchars($text) . '</a>';
    }
    
    /**
     * Get tracking pixel HTML
     */
    private function getTrackingPixel() {
        $pixelUrl = 'https://apps.ironcrestsoftware.com/email-signature/api/pixel.php?s=' . $this->signatureId . '&u=' . $this->userId;
        return '<img src="' . htmlspecialchars($pixelUrl) . '" width="1" height="1" style="display:none;" alt="" />';
    }
    
    /**
     * Minimal Line Template
     */
    private function renderMinimalLine($c) {
        $accent = $c['branding']['accent'] ?? '#2B68C1';
        $logoSize = $c['branding']['logoSize'] ?? 60;
        
        $html = '<table cellpadding="0" cellspacing="0" style="font-family: Arial, sans-serif; color: #333; line-height: 1.4; font-size: 14px;">';
        
        // Logo (if exists)
        if (!empty($c['company']['logoUrl'])) {
            $html .= '<tr><td style="padding-bottom: 12px;">';
            $html .= '<img src="' . htmlspecialchars($c['company']['logoUrl']) . '" width="' . $logoSize . '" height="' . $logoSize . '" style="border-radius: 4px; display: block; object-fit: contain;" alt="Logo">';
            $html .= '</td></tr>';
        }
        
        // Name & Title
        $html .= '<tr><td style="padding-bottom: 8px;">';
        $html .= '<div style="font-weight: 700; font-size: 16px; color: #1a1a1a;">' . htmlspecialchars($c['identity']['name']);
        if (!empty($c['identity']['pronouns'])) {
            $html .= ' <span style="font-weight: 400; color: #666; font-size: 14px;">(' . htmlspecialchars($c['identity']['pronouns']) . ')</span>';
        }
        $html .= '</div>';
        if (!empty($c['identity']['title'])) {
            $html .= '<div style="color: #555; font-size: 14px;">' . htmlspecialchars($c['identity']['title']) . '</div>';
        }
        if (!empty($c['company']['name'])) {
            $html .= '<div style="color: #555; font-size: 14px;">' . htmlspecialchars($c['company']['name']) . '</div>';
        }
        $html .= '</td></tr>';
        
        // Divider
        $html .= '<tr><td style="padding: 8px 0;"><div style="height: 1px; background: #e0e0e0; width: 200px;"></div></td></tr>';
        
        // Contact Row
        $html .= '<tr><td style="padding-top: 8px; font-size: 13px;">';
        $contacts = [];
        if (!empty($c['contact']['email'])) {
            $contacts[] = '<a href="mailto:' . htmlspecialchars($c['contact']['email']) . '" style="color: ' . $accent . '; text-decoration: none;">' . htmlspecialchars($c['contact']['email']) . '</a>';
        }
        if (!empty($c['contact']['phone'])) {
            $contacts[] = '<a href="tel:' . preg_replace('/[^0-9+]/', '', $c['contact']['phone']) . '" style="color: ' . $accent . '; text-decoration: none;">' . htmlspecialchars($c['contact']['phone']) . '</a>';
        }
        if (!empty($c['contact']['website'])) {
            $contacts[] = '<a href="' . htmlspecialchars($c['contact']['website']) . '" style="color: ' . $accent . '; text-decoration: none;">' . htmlspecialchars(parse_url($c['contact']['website'], PHP_URL_HOST)) . '</a>';
        }
        $html .= implode(' • ', $contacts);
        $html .= '</td></tr>';
        
        // Social Icons
        if (!empty($c['links'])) {
            $html .= '<tr><td style="padding-top: 10px;">';
            $html .= $this->renderSocialIcons($c['links'], 16);
            $html .= '</td></tr>';
        }
        
        // CTA
        if (!empty($c['addons']['cta']['url'])) {
            $html .= '<tr><td style="padding-top: 12px;">';
            $html .= '<a href="' . htmlspecialchars($c['addons']['cta']['url']) . '" style="display: inline-block; padding: 8px 16px; background-color: ' . $accent . ' !important; color: #ffffff !important; text-decoration: none; border-radius: 4px; font-size: 13px; font-weight: 600; mso-padding-alt: 8px 16px; background: ' . $accent . ';">' . htmlspecialchars($c['addons']['cta']['label'] ?? 'Get in Touch') . '</a>';
            $html .= '</td></tr>';
        }
        
        // Disclaimer
        if (!empty($c['addons']['disclaimer'])) {
            $html .= '<tr><td style="padding-top: 12px; font-size: 11px; color: #888; border-top: 1px solid #e0e0e0; padding-top: 8px; margin-top: 8px;">';
            $html .= htmlspecialchars($c['addons']['disclaimer']);
            $html .= '</td></tr>';
        }
        
        // Powered by
        $html .= '<tr><td style="padding-top: 12px; font-size: 10px; color: #999;">';
        $html .= 'Powered by <a href="https://ironcrestsoftware.com" style="color: #999; text-decoration: none;">Ironcrest Software</a>';
        $html .= '</td></tr>';
        
        $html .= '</table>';
        
        return $html;
    }
    
    /**
     * Corporate Block Template
     */
    private function renderCorporateBlock($c) {
        $accent = $c['branding']['accent'] ?? '#2B68C1';
        $logoSize = $c['branding']['logoSize'] ?? 80;
        
        $html = '<table cellpadding="0" cellspacing="0" style="font-family: Arial, sans-serif; color: #333; line-height: 1.4; font-size: 14px;">';
        $html .= '<tr>';
        
        // Photo/Logo column (if exists)
        if (!empty($c['company']['logoUrl'])) {
            $html .= '<td style="padding-right: 16px; vertical-align: top;">';
            $html .= '<img src="' . htmlspecialchars($c['company']['logoUrl']) . '" width="' . $logoSize . '" height="' . $logoSize . '" style="border-radius: 50%; display: block; object-fit: cover;" alt="Logo">';
            $html .= '</td>';
        }
        
        // Info column
        $html .= '<td style="vertical-align: top;">';
        
        // Name & Title
        $html .= '<div style="font-weight: 700; font-size: 16px; color: #1a1a1a;">' . htmlspecialchars($c['identity']['name']);
        if (!empty($c['identity']['pronouns'])) {
            $html .= ' <span style="font-weight: 400; color: #666; font-size: 14px;">(' . htmlspecialchars($c['identity']['pronouns']) . ')</span>';
        }
        $html .= '</div>';
        if (!empty($c['identity']['title'])) {
            $html .= '<div style="color: #555; font-size: 14px; margin-top: 2px;">' . htmlspecialchars($c['identity']['title']) . '</div>';
        }
        if (!empty($c['company']['name'])) {
            $html .= '<div style="color: #555; font-size: 14px;">' . htmlspecialchars($c['company']['name']) . '</div>';
        }
        
        // Contact
        $html .= '<div style="margin-top: 8px; font-size: 13px;">';
        $contacts = [];
        if (!empty($c['contact']['email'])) {
            $contacts[] = '<a href="mailto:' . htmlspecialchars($c['contact']['email']) . '" style="color: ' . $accent . '; text-decoration: none;">' . htmlspecialchars($c['contact']['email']) . '</a>';
        }
        if (!empty($c['contact']['phone'])) {
            $contacts[] = htmlspecialchars($c['contact']['phone']);
        }
        $html .= implode(' • ', $contacts);
        $html .= '</div>';
        
        // Social
        if (!empty($c['links'])) {
            $html .= '<div style="margin-top: 8px;">' . $this->renderSocialIcons($c['links'], 16) . '</div>';
        }
        
        // CTA
        if (!empty($c['addons']['cta']['url'])) {
            $html .= '<div style="margin-top: 10px;">';
            $html .= '<a href="' . htmlspecialchars($c['addons']['cta']['url']) . '" style="display: inline-block; padding: 8px 16px; background: ' . $accent . '; color: #ffffff; text-decoration: none; border-radius: 4px; font-size: 13px; font-weight: 600;">' . htmlspecialchars($c['addons']['cta']['label'] ?? 'Connect') . '</a>';
            $html .= '</div>';
        }
        
        // Disclaimer
        if (!empty($c['addons']['disclaimer'])) {
            $html .= '<div style="margin-top: 10px; font-size: 11px; color: #888; padding-top: 8px; border-top: 1px solid #e0e0e0;">' . htmlspecialchars($c['addons']['disclaimer']) . '</div>';
        }
        
        $html .= '</td>';
        $html .= '</tr>';
        $html .= '</table>';
        
        return $html;
    }
    
    /**
     * Render social icons as text links
     * Most reliable approach for email clients
     */
    private function renderSocialIcons($links, $size = 16) {
        $icons = [
            'linkedin' => ['label' => 'LinkedIn', 'color' => '#0A66C2'],
            'x' => ['label' => 'X', 'color' => '#000000'],
            'twitter' => ['label' => 'Twitter', 'color' => '#1DA1F2'],
            'facebook' => ['label' => 'Facebook', 'color' => '#1877F2'],
            'instagram' => ['label' => 'Instagram', 'color' => '#E4405F'],
            'github' => ['label' => 'GitHub', 'color' => '#181717'],
            'youtube' => ['label' => 'YouTube', 'color' => '#FF0000'],
        ];
        
        $html = '';
        foreach ($links as $platform => $url) {
            if (empty($url) || !isset($icons[$platform])) continue;
            
            $icon = $icons[$platform];
            $html .= '<a href="' . htmlspecialchars($url) . '" style="display: inline-block; margin-right: 8px; padding: 4px 8px; background: ' . $icon['color'] . '; color: white; text-decoration: none; border-radius: 3px; font-size: 11px; font-weight: 600;">';
            $html .= $icon['label'];
            $html .= '</a>';
        }
        
        return $html;
    }
    
    /**
     * Badge Template
     */
    private function renderBadge($c) {
        return $this->renderCorporateBlock($c); // Similar layout
    }
    
    /**
     * Stripe Template
     */
    private function renderStripe($c) {
        $accent = $c['branding']['accent'] ?? '#2B68C1';
        $logoSize = $c['branding']['logoSize'] ?? 60;
        
        $html = '<table cellpadding="0" cellspacing="0" style="font-family: Arial, sans-serif; color: #333; line-height: 1.4; font-size: 14px;">';
        $html .= '<tr>';
        
        // Accent stripe
        $html .= '<td style="width: 4px; background: ' . $accent . '; border-radius: 2px;"></td>';
        $html .= '<td style="padding-left: 16px;">';
        
        // Logo (if exists)
        if (!empty($c['company']['logoUrl'])) {
            $html .= '<div style="margin-bottom: 12px;">';
            $html .= '<img src="' . htmlspecialchars($c['company']['logoUrl']) . '" width="' . $logoSize . '" height="' . $logoSize . '" style="border-radius: 4px; display: block; object-fit: contain;" alt="Logo">';
            $html .= '</div>';
        }
        
        // Name & Pronouns
        $html .= '<div style="font-weight: 700; font-size: 16px; color: #1a1a1a;">' . htmlspecialchars($c['identity']['name']);
        if (!empty($c['identity']['pronouns'])) {
            $html .= ' <span style="font-weight: 400; color: #666; font-size: 14px;">(' . htmlspecialchars($c['identity']['pronouns']) . ')</span>';
        }
        $html .= '</div>';
        
        if (!empty($c['identity']['title'])) {
            $html .= '<div style="color: #555; font-size: 14px;">' . htmlspecialchars($c['identity']['title']) . '</div>';
        }
        if (!empty($c['company']['name'])) {
            $html .= '<div style="color: #555; font-size: 14px;">' . htmlspecialchars($c['company']['name']) . '</div>';
        }
        
        // Contact
        $html .= '<div style="margin-top: 8px; font-size: 13px;">';
        $contacts = [];
        if (!empty($c['contact']['email'])) {
            $contacts[] = '<a href="mailto:' . htmlspecialchars($c['contact']['email']) . '" style="color: ' . $accent . '; text-decoration: none;">' . htmlspecialchars($c['contact']['email']) . '</a>';
        }
        if (!empty($c['contact']['phone'])) {
            $contacts[] = htmlspecialchars($c['contact']['phone']);
        }
        if (!empty($c['contact']['website'])) {
            $contacts[] = '<a href="' . htmlspecialchars($c['contact']['website']) . '" style="color: ' . $accent . '; text-decoration: none;">' . htmlspecialchars(parse_url($c['contact']['website'], PHP_URL_HOST)) . '</a>';
        }
        $html .= implode(' • ', $contacts);
        $html .= '</div>';
        
        // Social
        if (!empty($c['links'])) {
            $html .= '<div style="margin-top: 8px;">' . $this->renderSocialIcons($c['links'], 16) . '</div>';
        }
        
        // CTA
        if (!empty($c['addons']['cta']['url'])) {
            $html .= '<div style="margin-top: 10px;">';
            $html .= '<a href="' . htmlspecialchars($c['addons']['cta']['url']) . '" style="display: inline-block; padding: 8px 16px; background: ' . $accent . '; color: #ffffff; text-decoration: none; border-radius: 4px; font-size: 13px; font-weight: 600;">' . htmlspecialchars($c['addons']['cta']['label'] ?? 'Connect') . '</a>';
            $html .= '</div>';
        }
        
        // Disclaimer
        if (!empty($c['addons']['disclaimer'])) {
            $html .= '<div style="margin-top: 10px; font-size: 11px; color: #888;">' . htmlspecialchars($c['addons']['disclaimer']) . '</div>';
        }
        
        $html .= '</td>';
        $html .= '</tr>';
        $html .= '</table>';
        
        return $html;
    }
    
    /**
     * Monoline Template
     */
    private function renderMonoline($c) {
        $accent = $c['branding']['accent'] ?? '#2B68C1';
        $logoSize = $c['branding']['logoSize'] ?? 40;
        
        $html = '<table cellpadding="0" cellspacing="0" style="font-family: Arial, sans-serif; font-size: 13px; color: #333;">';
        
        // Logo (if exists) - small inline version
        if (!empty($c['company']['logoUrl'])) {
            $html .= '<tr><td style="padding-bottom: 8px;">';
            $html .= '<img src="' . htmlspecialchars($c['company']['logoUrl']) . '" width="' . $logoSize . '" height="' . $logoSize . '" style="border-radius: 4px; display: block; object-fit: contain;" alt="Logo">';
            $html .= '</td></tr>';
        }
        
        // Main line
        $html .= '<tr><td>';
        $parts = [];
        
        // Name with pronouns
        $namePart = '<strong>' . htmlspecialchars($c['identity']['name']) . '</strong>';
        if (!empty($c['identity']['pronouns'])) {
            $namePart .= ' (' . htmlspecialchars($c['identity']['pronouns']) . ')';
        }
        $parts[] = $namePart;
        
        if (!empty($c['identity']['title'])) {
            $parts[] = htmlspecialchars($c['identity']['title']);
        }
        
        if (!empty($c['company']['name'])) {
            $parts[] = htmlspecialchars($c['company']['name']);
        }
        
        if (!empty($c['contact']['email'])) {
            $parts[] = '<a href="mailto:' . htmlspecialchars($c['contact']['email']) . '" style="color: ' . $accent . '; text-decoration: none;">' . htmlspecialchars($c['contact']['email']) . '</a>';
        }
        
        if (!empty($c['contact']['phone'])) {
            $parts[] = htmlspecialchars($c['contact']['phone']);
        }
        
        if (!empty($c['contact']['website'])) {
            $parts[] = '<a href="' . htmlspecialchars($c['contact']['website']) . '" style="color: ' . $accent . '; text-decoration: none;">' . htmlspecialchars(parse_url($c['contact']['website'], PHP_URL_HOST)) . '</a>';
        }
        
        $html .= implode(' • ', $parts);
        $html .= '</td></tr>';
        
        // Social links (if any)
        if (!empty($c['links'])) {
            $html .= '<tr><td style="padding-top: 6px;">';
            $html .= $this->renderSocialIcons($c['links'], 14);
            $html .= '</td></tr>';
        }
        
        // CTA (if any)
        if (!empty($c['addons']['cta']['url'])) {
            $html .= '<tr><td style="padding-top: 8px;">';
            $html .= '<a href="' . htmlspecialchars($c['addons']['cta']['url']) . '" style="display: inline-block; padding: 6px 12px; background: ' . $accent . '; color: #ffffff; text-decoration: none; border-radius: 3px; font-size: 12px; font-weight: 600;">' . htmlspecialchars($c['addons']['cta']['label'] ?? 'Connect') . '</a>';
            $html .= '</td></tr>';
        }
        
        // Disclaimer (if any)
        if (!empty($c['addons']['disclaimer'])) {
            $html .= '<tr><td style="padding-top: 8px; font-size: 10px; color: #888;">';
            $html .= htmlspecialchars($c['addons']['disclaimer']);
            $html .= '</td></tr>';
        }
        
        $html .= '</table>';
        
        return $html;
    }
    
    /**
     * Card Template
     */
    private function renderCard($c) {
        $accent = $c['branding']['accent'] ?? '#2B68C1';
        $logoSize = $c['branding']['logoSize'] ?? 70;
        
        $html = '<table cellpadding="0" cellspacing="0" style="font-family: Arial, sans-serif; color: #333; line-height: 1.4; font-size: 14px; border: 2px solid #e5e7eb; border-radius: 8px; padding: 20px; max-width: 400px;">';
        
        $html .= '<tr><td>';
        
        // Logo (if exists)
        if (!empty($c['company']['logoUrl'])) {
            $html .= '<div style="margin-bottom: 16px; text-align: center;">';
            $html .= '<img src="' . htmlspecialchars($c['company']['logoUrl']) . '" width="' . $logoSize . '" height="' . $logoSize . '" style="border-radius: 8px; display: block; margin: 0 auto; object-fit: contain;" alt="Logo">';
            $html .= '</div>';
        }
        
        // Name & Pronouns
        $html .= '<div style="font-weight: 700; font-size: 16px; color: #1a1a1a; text-align: center;">' . htmlspecialchars($c['identity']['name']);
        if (!empty($c['identity']['pronouns'])) {
            $html .= ' <span style="font-weight: 400; color: #666; font-size: 14px;">(' . htmlspecialchars($c['identity']['pronouns']) . ')</span>';
        }
        $html .= '</div>';
        
        if (!empty($c['identity']['title'])) {
            $html .= '<div style="color: #555; font-size: 14px; margin-top: 4px; text-align: center;">' . htmlspecialchars($c['identity']['title']) . '</div>';
        }
        if (!empty($c['company']['name'])) {
            $html .= '<div style="color: #555; font-size: 14px; text-align: center;">' . htmlspecialchars($c['company']['name']) . '</div>';
        }
        
        // Contact
        $html .= '<div style="margin-top: 12px; font-size: 13px; text-align: center;">';
        $contacts = [];
        if (!empty($c['contact']['email'])) {
            $contacts[] = '<a href="mailto:' . htmlspecialchars($c['contact']['email']) . '" style="color: ' . $accent . '; text-decoration: none;">' . htmlspecialchars($c['contact']['email']) . '</a>';
        }
        if (!empty($c['contact']['phone'])) {
            $contacts[] = htmlspecialchars($c['contact']['phone']);
        }
        if (!empty($c['contact']['website'])) {
            $contacts[] = '<a href="' . htmlspecialchars($c['contact']['website']) . '" style="color: ' . $accent . '; text-decoration: none;">' . htmlspecialchars(parse_url($c['contact']['website'], PHP_URL_HOST)) . '</a>';
        }
        $html .= implode(' • ', $contacts);
        $html .= '</div>';
        
        // Social
        if (!empty($c['links'])) {
            $html .= '<div style="margin-top: 12px; text-align: center;">' . $this->renderSocialIcons($c['links'], 16) . '</div>';
        }
        
        // CTA
        if (!empty($c['addons']['cta']['url'])) {
            $html .= '<div style="margin-top: 14px; text-align: center;">';
            $html .= '<a href="' . htmlspecialchars($c['addons']['cta']['url']) . '" style="display: inline-block; padding: 10px 20px; background: ' . $accent . '; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 600;">' . htmlspecialchars($c['addons']['cta']['label'] ?? 'Connect') . '</a>';
            $html .= '</div>';
        }
        
        // Disclaimer
        if (!empty($c['addons']['disclaimer'])) {
            $html .= '<div style="margin-top: 12px; font-size: 11px; color: #888; text-align: center; padding-top: 12px; border-top: 1px solid #e5e7eb;">' . htmlspecialchars($c['addons']['disclaimer']) . '</div>';
        }
        
        $html .= '</td></tr>';
        $html .= '</table>';
        
        return $html;
    }
    
    /**
     * Sidebar Template
     */
    private function renderSidebar($c) {
        return $this->renderStripe($c); // Similar to stripe
    }
    
    /**
     * Logo First Template
     */
    private function renderLogofirst($c) {
        return $this->renderMinimalLine($c); // Fallback to minimal
    }
    
    /**
     * Accent Tag Template
     */
    private function renderAccenttag($c) {
        return $this->renderStripe($c); // Similar to stripe
    }
    
    /**
     * Hero CTA Template
     */
    private function renderHerocta($c) {
        $accent = $c['branding']['accent'] ?? '#2B68C1';
        $logoSize = $c['branding']['logoSize'] ?? 80;
        
        $html = '<table cellpadding="0" cellspacing="0" style="font-family: Arial, sans-serif; color: #333; line-height: 1.4; font-size: 14px; text-align: center;">';
        
        // Logo (if exists)
        if (!empty($c['company']['logoUrl'])) {
            $html .= '<tr><td style="padding-bottom: 16px;">';
            $html .= '<img src="' . htmlspecialchars($c['company']['logoUrl']) . '" width="' . $logoSize . '" height="' . $logoSize . '" style="border-radius: 8px; display: block; margin: 0 auto; object-fit: contain;" alt="Logo">';
            $html .= '</td></tr>';
        }
        
        // Name & Pronouns
        $html .= '<tr><td style="padding-bottom: 12px;">';
        $html .= '<div style="font-weight: 800; font-size: 18px; color: #1a1a1a;">' . htmlspecialchars($c['identity']['name']);
        if (!empty($c['identity']['pronouns'])) {
            $html .= ' <span style="font-weight: 400; color: #666; font-size: 15px;">(' . htmlspecialchars($c['identity']['pronouns']) . ')</span>';
        }
        $html .= '</div>';
        
        if (!empty($c['identity']['title'])) {
            $html .= '<div style="color: #555; font-size: 14px; font-weight: 600; margin-top: 4px;">' . htmlspecialchars($c['identity']['title']) . '</div>';
        }
        if (!empty($c['company']['name'])) {
            $html .= '<div style="color: #555; font-size: 14px; font-weight: 600;">' . htmlspecialchars($c['company']['name']) . '</div>';
        }
        $html .= '</td></tr>';
        
        // CTA Button (prominent)
        if (!empty($c['addons']['cta']['url'])) {
            $html .= '<tr><td style="padding: 12px 0;">';
            $html .= '<a href="' . htmlspecialchars($c['addons']['cta']['url']) . '" style="display: inline-block; padding: 12px 24px; background: ' . $accent . '; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 700;">' . htmlspecialchars($c['addons']['cta']['label'] ?? 'Get in Touch') . '</a>';
            $html .= '</td></tr>';
        }
        
        // Contact
        $html .= '<tr><td style="padding-top: 12px; font-size: 13px;">';
        $contacts = [];
        if (!empty($c['contact']['email'])) {
            $contacts[] = '<a href="mailto:' . htmlspecialchars($c['contact']['email']) . '" style="color: ' . $accent . '; text-decoration: none;">' . htmlspecialchars($c['contact']['email']) . '</a>';
        }
        if (!empty($c['contact']['phone'])) {
            $contacts[] = htmlspecialchars($c['contact']['phone']);
        }
        if (!empty($c['contact']['website'])) {
            $contacts[] = '<a href="' . htmlspecialchars($c['contact']['website']) . '" style="color: ' . $accent . '; text-decoration: none;">' . htmlspecialchars(parse_url($c['contact']['website'], PHP_URL_HOST)) . '</a>';
        }
        $html .= implode(' • ', $contacts);
        $html .= '</td></tr>';
        
        // Social
        if (!empty($c['links'])) {
            $html .= '<tr><td style="padding-top: 10px;">';
            $html .= $this->renderSocialIcons($c['links'], 16);
            $html .= '</td></tr>';
        }
        
        // Disclaimer
        if (!empty($c['addons']['disclaimer'])) {
            $html .= '<tr><td style="padding-top: 12px; font-size: 11px; color: #888;">';
            $html .= htmlspecialchars($c['addons']['disclaimer']);
            $html .= '</td></tr>';
        }
        
        $html .= '</table>';
        
        return $html;
    }
    
    /**
     * Professional Left Logo Template (Kevin's Signature Style)
     * Two-column layout with logo on left, vertical divider, content on right
     */
    private function renderProfessionalLeftLogo($c) {
        $accent = $c['branding']['accent'] ?? '#2B68C1';
        $buttonGradient = 'linear-gradient(135deg, #2A3B8F 0%, ' . $accent . ' 100%)';
        $dividerColor = '#1a1a1a';
        $companyColor = $accent;
        
        // Font size mapping
        $fontSizes = [
            'small' => ['name' => '14px', 'title' => '12px', 'tagline' => '11px', 'contact' => '12px', 'button' => '11px'],
            'medium' => ['name' => '16px', 'title' => '13px', 'tagline' => '12px', 'contact' => '13px', 'button' => '12px'],
            'large' => ['name' => '18px', 'title' => '14px', 'tagline' => '13px', 'contact' => '14px', 'button' => '13px']
        ];
        $fontSize = $c['branding']['fontSize'] ?? 'medium';
        $sizes = $fontSizes[$fontSize] ?? $fontSizes['medium'];
        
        // Line height mapping
        $lineHeights = ['tight' => '1.2', 'normal' => '1.4', 'relaxed' => '1.6'];
        $lineHeight = $lineHeights[$c['branding']['lineHeight'] ?? 'normal'] ?? '1.4';
        
        // Spacing mapping
        $spacings = ['compact' => '6px', 'normal' => '10px', 'spacious' => '14px'];
        $spacing = $spacings[$c['branding']['spacing'] ?? 'normal'] ?? '10px';
        
        // Corner radius mapping
        $radiusMap = ['none' => '0', 'small' => '2px', 'medium' => '4px', 'large' => '8px'];
        $radius = $radiusMap[$c['branding']['cornerRadius'] ?? 'medium'] ?? '4px';
        
        // Logo size
        $logoSize = $c['branding']['logoSize'] ?? 70;
        $logoColumnWidth = $logoSize + 10; // Add padding
        
        $html = '<table cellpadding="0" cellspacing="0" style="font-family: Arial, sans-serif; color: #333; line-height: ' . $lineHeight . '; font-size: 14px; max-width: 600px;">';
        $html .= '<tr><td>';
        $html .= '<table cellpadding="0" cellspacing="0" style="width: 100%;"><tr>';
        
        // Left Column - Logo
        $html .= '<td style="width: ' . $logoColumnWidth . 'px; padding-right: ' . $spacing . '; border-right: 2px solid ' . $dividerColor . '; vertical-align: top;">';
        if (!empty($c['company']['logoUrl'])) {
            $html .= '<img src="' . htmlspecialchars($c['company']['logoUrl']) . '" alt="Logo" style="width: ' . $logoSize . 'px; height: ' . $logoSize . 'px; display: block; border-radius: ' . $radius . '; object-fit: contain;" />';
        } else {
            // Fallback logo placeholder using table for email compatibility
            $initials = '';
            if (!empty($c['company']['name'])) {
                $words = explode(' ', $c['company']['name']);
                $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
            }
            $html .= '<table cellpadding="0" cellspacing="0" style="width: ' . $logoSize . 'px; height: ' . $logoSize . 'px; background: ' . $buttonGradient . '; border-radius: ' . $radius . ';"><tr><td style="text-align: center; vertical-align: middle; color: white; font-weight: 800; font-size: 24px;">' . $initials . '</td></tr></table>';
        }
        $html .= '</td>';
        
        // Right Column - Content
        $html .= '<td style="padding-left: ' . $spacing . '; vertical-align: top;">';
        
        // Name
        $html .= '<div style="font-weight: 700; font-size: ' . $sizes['name'] . '; color: #1a1a1a; line-height: 1.2;">';
        $html .= htmlspecialchars($c['identity']['name']);
        if (!empty($c['identity']['pronouns'])) {
            $html .= ' <span style="font-weight: 400; color: #666; font-size: ' . $sizes['title'] . ';">(' . htmlspecialchars($c['identity']['pronouns']) . ')</span>';
        }
        $html .= '</div>';
        
        // Title & Company
        if (!empty($c['identity']['title']) || !empty($c['company']['name'])) {
            $html .= '<div style="font-size: ' . $sizes['title'] . '; color: #666; margin-top: 2px;">';
            if (!empty($c['identity']['title'])) {
                $html .= htmlspecialchars($c['identity']['title']);
            }
            if (!empty($c['identity']['title']) && !empty($c['company']['name'])) {
                $html .= ' | ';
            }
            if (!empty($c['company']['name'])) {
                $html .= '<span style="color: ' . $companyColor . '; font-weight: 600;">' . htmlspecialchars($c['company']['name']) . '</span>';
            }
            $html .= '</div>';
        }
        
        // Contact Info
        $html .= '<div style="font-size: ' . $sizes['contact'] . '; margin-top: 8px;">';
        if (!empty($c['contact']['phone'])) {
            $html .= '<div style="color: ' . $accent . '; margin-bottom: 2px;">';
            $html .= '<strong>T</strong> <a href="tel:' . preg_replace('/[^0-9+]/', '', $c['contact']['phone']) . '" style="color: ' . $accent . '; text-decoration: none;">' . htmlspecialchars($c['contact']['phone']) . '</a>';
            $html .= '</div>';
        }
        if (!empty($c['contact']['email'])) {
            $html .= '<div style="color: ' . $accent . '; margin-bottom: 2px;">';
            $html .= '<strong>E</strong> <a href="mailto:' . htmlspecialchars($c['contact']['email']) . '" style="color: ' . $accent . '; text-decoration: none;">' . htmlspecialchars($c['contact']['email']) . '</a>';
            $html .= '</div>';
        }
        if (!empty($c['contact']['website'])) {
            $html .= '<div style="color: ' . $accent . '; margin-bottom: 2px;">';
            $html .= '<strong>W</strong> <a href="' . htmlspecialchars($c['contact']['website']) . '" style="color: ' . $accent . '; text-decoration: none;">' . htmlspecialchars(parse_url($c['contact']['website'], PHP_URL_HOST)) . '</a>';
            $html .= '</div>';
        }
        if (!empty($c['contact']['calendly'])) {
            $html .= '<div style="color: ' . $accent . ';">';
            $html .= '<strong>📅</strong> <a href="' . htmlspecialchars($c['contact']['calendly']) . '" style="color: ' . $accent . '; text-decoration: none;">Book a Meeting</a>';
            $html .= '</div>';
        }
        $html .= '</div>';
        
        // CTA Button
        if (!empty($c['addons']['cta']['url'])) {
            $html .= '<div style="margin-top: 10px;">';
            $html .= '<a href="' . htmlspecialchars($c['addons']['cta']['url']) . '" style="display: inline-block; padding: 8px 16px; background: ' . $buttonGradient . '; color: #ffffff; text-decoration: none; border-radius: ' . $radius . '; font-size: ' . $sizes['button'] . '; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
            $html .= htmlspecialchars($c['addons']['cta']['label'] ?? 'Schedule a Consultation');
            $html .= '</a>';
            $html .= '</div>';
        }
        
        // Social Icons
        if (!empty($c['links'])) {
            $html .= '<div style="margin-top: 10px;">';
            $html .= $this->renderSocialIcons($c['links'], 16);
            $html .= '</div>';
        }
        
        $html .= '</td>';
        $html .= '</tr></table>';
        $html .= '</td></tr>';
        
        // Legal Disclaimer / Confidentiality Notice (if provided)
        if (!empty($c['addons']['disclaimer'])) {
            $html .= '<tr><td style="padding-top: 12px; font-size: 10px; color: #888; border-top: 1px solid #e0e0e0; margin-top: 12px;">';
            $html .= htmlspecialchars($c['addons']['disclaimer']);
            $html .= '</td></tr>';
        }
        
        $html .= '</table>';
        
        return $html;
    }
    
    /**
     * Sanitize and validate URL
     */
    private function sanitizeUrl($url) {
        $url = filter_var($url, FILTER_SANITIZE_URL);
        $parsed = parse_url($url);
        
        if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https', 'mailto', 'tel'])) {
            return null;
        }
        
        return $url;
    }
}

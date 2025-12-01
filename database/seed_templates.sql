-- ============================================
-- Ironcrest Email Signature Generator
-- Template Seed Data
-- ============================================

-- Clear existing templates
DELETE FROM sig_templates;

-- ============================================
-- 10 PREMIUM TEMPLATES
-- ============================================

-- 1. MINIMAL LINE
INSERT INTO sig_templates (template_key, name, version, description, meta_json, default_json, sort_order) VALUES (
  'minimal-line',
  'Minimal Line',
  1,
  'Clean single column with subtle divider',
  JSON_OBJECT(
    'atoms', JSON_ARRAY('NameTitle', 'ContactRow', 'SocialIcons', 'CTA', 'Disclaimer'),
    'layout', 'single_column_divided',
    'supports', JSON_ARRAY('logo', 'cta', 'disclaimer')
  ),
  JSON_OBJECT(
    'accent', '#2B68C1',
    'divider', true,
    'dividerStyle', 'thin'
  ),
  1
);

-- 2. CORPORATE BLOCK
INSERT INTO sig_templates (template_key, name, version, description, meta_json, default_json, sort_order) VALUES (
  'corporate-block',
  'Corporate Block',
  1,
  'Professional two-column layout with photo',
  JSON_OBJECT(
    'atoms', JSON_ARRAY('Avatar', 'NameTitle', 'CompanyLogo', 'ContactRow', 'SocialIcons', 'CTA', 'Disclaimer'),
    'layout', 'two_column_media_left',
    'supports', JSON_ARRAY('photo', 'logo', 'cta', 'disclaimer')
  ),
  JSON_OBJECT(
    'accent', '#2B68C1',
    'mediaWidth', 120,
    'gap', 16
  ),
  2
);

-- 3. BADGE
INSERT INTO sig_templates (template_key, name, version, description, meta_json, default_json, sort_order) VALUES (
  'badge',
  'Badge',
  1,
  'Compact badge-style signature',
  JSON_OBJECT(
    'atoms', JSON_ARRAY('Avatar', 'NameTitle', 'CompanyLogo', 'ContactRow', 'SocialIcons', 'CTA', 'Disclaimer'),
    'layout', 'two_row_avatar_top',
    'supports', JSON_ARRAY('photo', 'logo', 'cta', 'disclaimer')
  ),
  JSON_OBJECT(
    'accent', '#2B68C1',
    'iconStyle', 'outline'
  ),
  3
);

-- 4. STRIPE
INSERT INTO sig_templates (template_key, name, version, description, meta_json, default_json, sort_order) VALUES (
  'stripe',
  'Stripe',
  1,
  'Horizontal stripe with accent color',
  JSON_OBJECT(
    'atoms', JSON_ARRAY('NameTitle', 'ContactRow', 'SocialIcons', 'CTA'),
    'layout', 'horizontal_stripe',
    'supports', JSON_ARRAY('logo', 'cta')
  ),
  JSON_OBJECT(
    'accent', '#2B68C1',
    'stripePosition', 'left',
    'stripeWidth', 4
  ),
  4
);

-- 5. CARD
INSERT INTO sig_templates (template_key, name, version, description, meta_json, default_json, sort_order) VALUES (
  'card',
  'Card',
  1,
  'Card-like container with border',
  JSON_OBJECT(
    'atoms', JSON_ARRAY('Avatar', 'NameTitle', 'CompanyLogo', 'ContactRow', 'SocialIcons', 'CTA', 'Disclaimer'),
    'layout', 'card_container',
    'supports', JSON_ARRAY('photo', 'logo', 'cta', 'disclaimer')
  ),
  JSON_OBJECT(
    'accent', '#2B68C1',
    'borderRadius', 8,
    'padding', 20
  ),
  5
);

-- 6. SIDEBAR
INSERT INTO sig_templates (template_key, name, version, description, meta_json, default_json, sort_order) VALUES (
  'sidebar',
  'Sidebar',
  1,
  'Left sidebar with accent background',
  JSON_OBJECT(
    'atoms', JSON_ARRAY('Avatar', 'NameTitle', 'CompanyLogo', 'ContactRow', 'SocialIcons', 'CTA'),
    'layout', 'sidebar_left',
    'supports', JSON_ARRAY('photo', 'logo', 'cta')
  ),
  JSON_OBJECT(
    'accent', '#2B68C1',
    'sidebarWidth', 100
  ),
  6
);

-- 7. MONOLINE
INSERT INTO sig_templates (template_key, name, version, description, meta_json, default_json, sort_order) VALUES (
  'monoline',
  'Monoline',
  1,
  'Ultra-minimal single line signature',
  JSON_OBJECT(
    'atoms', JSON_ARRAY('NameTitle', 'ContactRow'),
    'layout', 'single_line',
    'supports', JSON_ARRAY()
  ),
  JSON_OBJECT(
    'accent', '#2B68C1',
    'separator', '•'
  ),
  7
);

-- 8. LOGO FIRST
INSERT INTO sig_templates (template_key, name, version, description, meta_json, default_json, sort_order) VALUES (
  'logo-first',
  'Logo First',
  1,
  'Company logo takes center stage',
  JSON_OBJECT(
    'atoms', JSON_ARRAY('CompanyLogo', 'NameTitle', 'ContactRow', 'SocialIcons', 'CTA'),
    'layout', 'logo_prominent',
    'supports', JSON_ARRAY('logo', 'cta')
  ),
  JSON_OBJECT(
    'accent', '#2B68C1',
    'logoSize', 'large'
  ),
  8
);

-- 9. ACCENT TAG
INSERT INTO sig_templates (template_key, name, version, description, meta_json, default_json, sort_order) VALUES (
  'accent-tag',
  'Accent Tag',
  1,
  'Colored accent bar on the side',
  JSON_OBJECT(
    'atoms', JSON_ARRAY('NameTitle', 'ContactRow', 'SocialIcons', 'CTA', 'Disclaimer'),
    'layout', 'accent_bar_left',
    'supports', JSON_ARRAY('logo', 'cta', 'disclaimer')
  ),
  JSON_OBJECT(
    'accent', '#2B68C1',
    'barWidth', 6
  ),
  9
);

-- 10. HERO CTA
INSERT INTO sig_templates (template_key, name, version, description, meta_json, default_json, sort_order) VALUES (
  'hero-cta',
  'Hero CTA',
  1,
  'CTA-focused design with prominent button',
  JSON_OBJECT(
    'atoms', JSON_ARRAY('NameTitle', 'ContactRow', 'SocialIcons', 'CTA', 'Disclaimer'),
    'layout', 'stacked_hero',
    'supports', JSON_ARRAY('photo', 'logo', 'cta', 'disclaimer')
  ),
  JSON_OBJECT(
    'accent', '#2B68C1',
    'ctaVariant', 'button',
    'ctaRadius', 6
  ),
  10
);

-- 11. PROFESSIONAL LEFT LOGO (Kevin's Signature Style)
INSERT INTO sig_templates (template_key, name, version, description, meta_json, default_json, sort_order) VALUES (
  'professional-left-logo',
  'Professional Left Logo',
  1,
  'Clean professional design with left-aligned logo and vertical divider',
  JSON_OBJECT(
    'atoms', JSON_ARRAY('Logo', 'NameTitle', 'Tagline', 'ContactInfo', 'CTA', 'Disclaimer'),
    'layout', 'two_column_left_logo',
    'supports', JSON_ARRAY('logo', 'tagline', 'phone', 'email', 'website', 'cta', 'disclaimer', 'gradient_button', 'custom_fonts')
  ),
  JSON_OBJECT(
    'accent', '#2B68C1',
    'buttonGradient', 'linear-gradient(135deg, #2A3B8F 0%, #2B68C1 100%)',
    'buttonRadius', 4,
    'dividerColor', '#1a1a1a',
    'taglineStyle', 'italic',
    'companyNameColor', '#2B68C1'
  ),
  11
);

-- ============================================
-- VERIFY INSERTION
-- ============================================
SELECT COUNT(*) as template_count FROM sig_templates WHERE is_active = 1;

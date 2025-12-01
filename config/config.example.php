<?php
/**
 * Ironcrest Email Signature Generator
 * Configuration Template
 * 
 * Copy this file to config.php and update with your values
 */

return [
    // Database Configuration
    'database' => [
        'host' => 'localhost',
        'name' => 'ironcrest_db',
        'username' => 'dbuser_ic',
        'password' => 'your_password_here',
        'charset' => 'utf8mb4',
    ],

    // Mailgun Configuration
    'mailgun' => [
        'api_key' => 'your_mailgun_api_key',
        'domain' => 'mg.815media.com',
        'from_email' => 'noreply@ironcrestsoftware.com',
        'from_name' => 'Ironcrest Email Signatures',
    ],

    // Application Settings
    'app' => [
        'name' => 'Ironcrest Email Signature Generator',
        'url' => 'https://apps.ironcrestsoftware.com/email-signature',
        'debug' => false, // Set to false in production
        'timezone' => 'America/Chicago',
    ],

    // Upload Settings
    'upload' => [
        'max_size' => 5242880, // 5MB in bytes
        'allowed_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'path' => __DIR__ . '/../public/uploads/',
        'url' => '/email-signature/public/uploads/',
    ],

    // Security Settings
    'security' => [
        'ip_salt' => 'change_this_random_salt_string', // For IP hashing
        'magic_link_expiry' => 3600, // 1 hour in seconds
        'session_lifetime' => 86400, // 24 hours
    ],

    // Rate Limiting
    'rate_limit' => [
        'render' => 200, // requests per hour per IP
        'export' => 40,  // requests per hour per IP
        'upload' => 20,  // requests per hour per IP
    ],

    // Email Templates
    'email' => [
        'signature_delivery' => [
            'subject' => 'Your Email Signature (HTML + Install Guide)',
            'template' => 'signature_delivery',
        ],
        'magic_link' => [
            'subject' => 'Sign in to Ironcrest Email Signatures',
            'template' => 'magic_link',
        ],
    ],

    // Feature Flags
    'features' => [
        'email_capture' => true,
        'magic_links' => true,
        'analytics' => true,
        'drip_campaign' => false, // Phase 2
        'teams' => false, // Phase 2
    ],

    // Branding
    'branding' => [
        'company' => 'Ironcrest Software',
        'tagline' => 'Modern Strength. Intelligent Systems.',
        'website' => 'https://ironcrestsoftware.com',
        'support_email' => 'contact@ironcrestsoftware.com',
        'colors' => [
            'primary' => '#2B68C1',
            'gradient_start' => '#2A3B8F',
            'gradient_end' => '#2B68C1',
        ],
    ],
];

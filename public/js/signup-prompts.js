/**
 * Signup Prompts & Reminders
 * Shows benefits throughout the user journey
 */

let formInteractionCount = 0;
let hasSeenStickyBanner = false;

// Check if user is logged in
function isUserLoggedIn() {
    // Check if user data exists in state (from app.js)
    if (typeof state !== 'undefined' && state.user) return true;
    
    // Fallback: check for session cookie
    return document.cookie.includes('session_token=');
}

// Show sticky banner after user starts filling form
function showStickySignupBanner() {
    // Don't show if user is logged in
    if (isUserLoggedIn()) {
        console.log('User is logged in - skipping signup banner');
        return;
    }
    
    if (hasSeenStickyBanner) return;
    
    const banner = document.createElement('div');
    banner.id = 'sticky-signup-banner';
    banner.className = 'fixed bottom-0 left-0 right-0 bg-gradient-to-r from-purple-600 to-pink-600 text-white py-4 px-6 shadow-2xl z-40 transform translate-y-full transition-transform duration-500';
    banner.innerHTML = `
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-save text-xl"></i>
                </div>
                <div>
                    <p class="font-bold text-lg">💡 Don't lose your work!</p>
                    <p class="text-sm opacity-90">Create a FREE account to save your info forever</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="closeStickyBanner()" class="text-white hover:text-gray-200 px-3 py-2 text-sm">
                    Maybe Later
                </button>
                <button onclick="showQuickSignup(); closeStickyBanner();" class="bg-white text-purple-700 font-bold px-6 py-2 rounded-lg hover:bg-gray-100 transition-all shadow-lg">
                    <i class="fas fa-rocket"></i> Save Now (10 sec)
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(banner);
    
    // Animate in
    setTimeout(() => {
        banner.style.transform = 'translateY(0)';
    }, 100);
    
    hasSeenStickyBanner = true;
}

// Close sticky banner
function closeStickyBanner() {
    const banner = document.getElementById('sticky-signup-banner');
    if (banner) {
        banner.style.transform = 'translateY(100%)';
        setTimeout(() => banner.remove(), 500);
    }
}

// Track form interactions
function trackFormInteraction() {
    formInteractionCount++;
    
    // Show sticky banner after 3 form interactions
    if (formInteractionCount === 3 && !hasSeenStickyBanner) {
        setTimeout(showStickySignupBanner, 2000);
    }
}

// Add tooltip to template switcher
function addTemplateSwitcherTooltip() {
    const btn = document.getElementById('change-template-btn');
    if (btn && !btn.dataset.tooltipAdded) {
        btn.dataset.tooltipAdded = 'true';
        btn.title = '💡 With a free account, you can switch templates anytime without re-entering data!';
    }
}

// Show benefits in console for developers
function showDevMessage() {
    console.log('%c🎉 Email Signature Generator', 'font-size: 20px; font-weight: bold; color: #8b5cf6;');
    console.log('%cFree Account Benefits:', 'font-size: 14px; font-weight: bold; color: #059669;');
    console.log('✓ Save your info forever');
    console.log('✓ Switch between 11 templates instantly');
    console.log('✓ Access from any device');
    console.log('✓ 100% free - no credit card');
    console.log('%cCreate account: Just click "Save My Info" button!', 'font-size: 12px; color: #3b82f6;');
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    // Track form field interactions
    const form = document.getElementById('signature-form');
    if (form) {
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('focus', trackFormInteraction, { once: true });
        });
    }
    
    // Add tooltip to template switcher
    setTimeout(addTemplateSwitcherTooltip, 2000);
    
    // Show dev message
    showDevMessage();
    
    // Show reminder after 2 minutes if not signed up
    setTimeout(() => {
        if (!hasSeenStickyBanner && formInteractionCount > 0) {
            showStickySignupBanner();
        }
    }, 120000); // 2 minutes
});

// Export functions for global use
window.closeStickyBanner = closeStickyBanner;
window.showStickySignupBanner = showStickySignupBanner;

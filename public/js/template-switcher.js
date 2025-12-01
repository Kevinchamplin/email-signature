/**
 * Template Switcher
 * Allows users to change templates without losing their data
 */

// Show template switcher modal
function showTemplateSwitcher() {
    const modal = document.getElementById('template-switcher-modal');
    if (modal) {
        modal.classList.remove('hidden');
        
        // Highlight current template
        const currentTemplate = state.selectedTemplate;
        document.querySelectorAll('.template-option').forEach(option => {
            if (option.dataset.template === currentTemplate) {
                option.classList.add('ring-4', 'ring-purple-500');
            } else {
                option.classList.remove('ring-4', 'ring-purple-500');
            }
        });
    }
}

// Close template switcher modal
function closeTemplateSwitcher() {
    const modal = document.getElementById('template-switcher-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Switch to a different template
async function switchTemplate(templateKey) {
    // Update state
    state.selectedTemplate = templateKey;
    
    // Update config from form to preserve current data
    updateConfigFromForm();
    
    // Re-render preview with new template
    await updatePreview();
    
    // Close modal
    closeTemplateSwitcher();
    
    // Show success message
    showToast(`✓ Switched to ${getTemplateName(templateKey)} template!`, 'success');
    
    // Track event
    trackEvent('template_switched', { 
        template: templateKey,
        from_step: 2
    });
}

// Get friendly template name
function getTemplateName(key) {
    const names = {
        'minimal-line': 'Minimal Line',
        'corporate-block': 'Corporate Block',
        'badge': 'Badge',
        'stripe': 'Stripe',
        'card': 'Card',
        'sidebar': 'Sidebar',
        'monoline': 'Monoline',
        'logo-first': 'Logo First',
        'accent-tag': 'Accent Tag',
        'hero-cta': 'Hero CTA',
        'professional-left-logo': 'Professional Left Logo'
    };
    return names[key] || key;
}

// Initialize template switcher
document.addEventListener('DOMContentLoaded', () => {
    // Close modal when clicking outside
    const modal = document.getElementById('template-switcher-modal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeTemplateSwitcher();
            }
        });
    }
    
    // Close button
    const closeBtn = document.getElementById('template-switcher-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeTemplateSwitcher);
    }
    
    // Template option clicks
    document.querySelectorAll('.template-option').forEach(option => {
        option.addEventListener('click', () => {
            const templateKey = option.dataset.template;
            if (templateKey) {
                switchTemplate(templateKey);
            }
        });
    });
});

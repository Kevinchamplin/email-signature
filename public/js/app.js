// Ironcrest Email Signature Generator - Main App
// API_BASE is defined in app.html

// State with placeholder data for live preview
let state = {
    currentStep: 1,
    selectedTemplate: null,
    config: {
        identity: { 
            name: 'Jordan Alvarez', 
            title: 'Senior Account Executive', 
            pronouns: '' 
        },
        company: { 
            name: 'IRONCREST Software', 
            slogan: '',
            logoUrl: '' 
        },
        contact: { 
            email: 'jordan@company.com', 
            phone: '+1 (312) 555-1234', 
            website: 'https://yourcompany.com', 
            calendly: '' 
        },
        links: { 
            linkedin: 'https://linkedin.com/in/yourname', 
            x: '', 
            github: '', 
            facebook: '', 
            instagram: '', 
            youtube: '' 
        },
        branding: { 
            accent: '#2B68C1', 
            iconStyle: 'outline',
            fontSize: 'medium',
            lineHeight: 'normal',
            spacing: 'normal',
            letterSpacing: 'normal',
            cornerRadius: 'medium',
            logoSize: 80
        },
        addons: { cta: { label: '', url: '' }, disclaimer: '' },
        options: { darkModePreview: false, compactMode: false }
    },
    signatureHtml: '',
    signatureTitle: null, // Custom name for the signature
    publicUuid: null,
    sessionId: generateSessionId(),
    startTime: Date.now(),
    user: null // Will be populated if logged in
};

// Generate session ID
function generateSessionId() {
    return 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
}

// Check if user can access premium templates
function canAccessPremiumTemplates() {
    // Check if user data is available
    if (!state.user) {
        console.log('canAccessPremiumTemplates: No user data');
        return false;
    }
    
    console.log('canAccessPremiumTemplates check:', {
        user: state.user,
        is_grandfathered: state.user.is_grandfathered,
        account_tier: state.user.account_tier
    });
    
    // Grandfathered users get everything
    if (state.user.is_grandfathered) {
        console.log('✅ User is grandfathered - unlocking all templates');
        return true;
    }
    
    // Check tier (basic, pro, enterprise get premium templates)
    const tier = state.user.account_tier;
    const hasAccess = tier === 'basic' || tier === 'pro' || tier === 'enterprise';
    console.log(`Tier check: ${tier} -> ${hasAccess ? 'UNLOCKED' : 'LOCKED'}`);
    return hasAccess;
}

// Load user preferences (if logged in)
async function loadUserPreferences() {
    try {
        const response = await fetch(`${API_BASE}/preferences.php`, {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success && data.preferences) {
            const prefs = data.preferences;
            
            // Map preferences to config structure
            state.config = {
                identity: {
                    name: prefs.name || '',
                    title: prefs.title || '',
                    pronouns: prefs.pronouns || ''
                },
                company: {
                    name: prefs.company_name || '',
                    slogan: prefs.company_slogan || '',
                    logoUrl: prefs.logo_url || ''
                },
                contact: {
                    email: prefs.email || '',
                    phone: prefs.phone || '',
                    website: prefs.website || '',
                    calendly: prefs.calendly || ''
                },
                links: prefs.social_links || {},
                branding: {
                    ...state.config.branding,
                    ...prefs.branding_preferences
                },
                addons: prefs.addons || { cta: { label: '', url: '' }, disclaimer: '' },
                options: state.config.options
            };
            
            // Populate form with loaded data
            populateFormFromState();
            
            console.log('✓ User preferences loaded');
            return true;
        }
        return false;
    } catch (error) {
        console.log('No saved preferences or not logged in');
        return false;
    }
}

// Save user preferences (database if logged in, show signup modal if not)
async function saveUserPreferences() {
    // First, update config from form
    updateConfigFromForm();
    
    // Validate required fields
    if (!state.config.identity.name || !state.config.contact.email) {
        showToast('Name and email are required to save preferences', 'error');
        return false;
    }
    
    // Try to save to database (if logged in)
    try {
        const response = await fetch(`${API_BASE}/preferences.php`, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: state.config.identity.name,
                title: state.config.identity.title,
                pronouns: state.config.identity.pronouns,
                company_name: state.config.company.name,
                company_slogan: state.config.company.slogan,
                logo_url: state.config.company.logoUrl,
                email: state.config.contact.email,
                phone: state.config.contact.phone,
                website: state.config.contact.website,
                calendly: state.config.contact.calendly,
                social_links: state.config.links,
                branding_preferences: state.config.branding,
                addons: state.config.addons
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Saved to database successfully
            showToast('✓ Preferences saved to your account! Switch templates anytime.', 'success');
            return true;
        } else if (response.status === 401) {
            // Not logged in - show quick signup modal
            if (typeof showQuickSignup === 'function') {
                showQuickSignup();
            } else {
                // Fallback to localStorage if modal not available
                saveToLocalStorage();
                showToast('✓ Saved to this browser! Create a free account to sync across devices.', 'success');
            }
            return false;
        } else {
            // Other error
            console.error('Failed to save preferences:', data.error);
            showToast('Error saving preferences. Please try again.', 'error');
            return false;
        }
    } catch (error) {
        // Network error or not logged in - show quick signup modal
        console.log('Not logged in - showing signup modal');
        if (typeof showQuickSignup === 'function') {
            showQuickSignup();
        } else {
            // Fallback to localStorage if modal not available
            saveToLocalStorage();
            showToast('✓ Saved to this browser! Create a free account to sync across devices.', 'success');
        }
        return false;
    }
}

// Save preferences to browser localStorage
function saveToLocalStorage() {
    try {
        const prefsData = {
            name: state.config.identity.name,
            title: state.config.identity.title,
            pronouns: state.config.identity.pronouns,
            company_name: state.config.company.name,
            logo_url: state.config.company.logoUrl,
            email: state.config.contact.email,
            phone: state.config.contact.phone,
            website: state.config.contact.website,
            calendly: state.config.contact.calendly,
            social_links: state.config.links,
            branding_preferences: state.config.branding,
            addons: state.config.addons,
            saved_at: new Date().toISOString()
        };
        
        localStorage.setItem('sig_preferences', JSON.stringify(prefsData));
        console.log('✓ Saved to localStorage');
        return true;
    } catch (error) {
        console.error('Failed to save to localStorage:', error);
        return false;
    }
}

// Load preferences from localStorage
function loadFromLocalStorage() {
    try {
        const saved = localStorage.getItem('sig_preferences');
        if (!saved) return false;
        
        const prefs = JSON.parse(saved);
        
        // Map to config structure
        state.config = {
            identity: {
                name: prefs.name || '',
                title: prefs.title || '',
                pronouns: prefs.pronouns || ''
            },
            company: {
                name: prefs.company_name || '',
                slogan: prefs.company_slogan || '',
                logoUrl: prefs.logo_url || ''
            },
            contact: {
                email: prefs.email || '',
                phone: prefs.phone || '',
                website: prefs.website || '',
                calendly: prefs.calendly || ''
            },
            links: prefs.social_links || {},
            branding: {
                ...state.config.branding,
                ...prefs.branding_preferences
            },
            addons: prefs.addons || { cta: { label: '', url: '' }, disclaimer: '' },
            options: state.config.options
        };
        
        populateFormFromState();
        console.log('✓ Loaded from localStorage');
        return true;
    } catch (error) {
        console.error('Failed to load from localStorage:', error);
        return false;
    }
}

// Populate form from current state
function populateFormFromState() {
    const form = document.getElementById('signature-form');
    if (!form) return;
    
    // Identity
    setFormValue('identity.name', state.config.identity.name);
    setFormValue('identity.title', state.config.identity.title);
    setFormValue('identity.pronouns', state.config.identity.pronouns);
    
    // Company
    setFormValue('company.name', state.config.company.name);
    setFormValue('company.slogan', state.config.company.slogan);
    setFormValue('company.logoUrl', state.config.company.logoUrl);
    
    // Contact
    setFormValue('contact.email', state.config.contact.email);
    setFormValue('contact.phone', state.config.contact.phone);
    setFormValue('contact.website', state.config.contact.website);
    setFormValue('contact.calendly', state.config.contact.calendly);
    
    // Social Links
    setFormValue('links.linkedin', state.config.links.linkedin);
    setFormValue('links.x', state.config.links.x);
    setFormValue('links.github', state.config.links.github);
    
    // Branding
    setFormValue('branding.accent', state.config.branding.accent);
    setFormValue('branding.fontSize', state.config.branding.fontSize);
    setFormValue('branding.lineHeight', state.config.branding.lineHeight);
    setFormValue('branding.spacing', state.config.branding.spacing);
    setFormValue('branding.cornerRadius', state.config.branding.cornerRadius);
    setFormValue('branding.logoSize', state.config.branding.logoSize);
    
    // Update logo size display
    const logoSizeValue = document.getElementById('logoSizeValue');
    if (logoSizeValue) {
        logoSizeValue.textContent = state.config.branding.logoSize + 'px';
    }
    
    // Add-ons
    setFormValue('addons.cta.label', state.config.addons.cta?.label);
    setFormValue('addons.cta.url', state.config.addons.cta?.url);
    setFormValue('addons.disclaimer', state.config.addons.disclaimer);
}

function setFormValue(path, value) {
    const form = document.getElementById('signature-form');
    const input = form.querySelector(`[name="${path}"]`);
    if (input && value) {
        input.value = value;
    }
}

// Track analytics event
async function trackEvent(eventType, meta = {}) {
    try {
        await fetch(`${API_BASE}/track.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                event_type: eventType,
                meta: {
                    ...meta,
                    session_id: state.sessionId,
                    time_on_page: Math.floor((Date.now() - state.startTime) / 1000),
                    current_step: state.currentStep,
                    template: state.selectedTemplate,
                    referrer: document.referrer || 'direct',
                    screen_width: window.innerWidth,
                    screen_height: window.innerHeight
                }
            })
        });
    } catch (error) {
        console.error('Tracking failed:', error);
    }
}

// NOTE: Initialization moved to main DOMContentLoaded at bottom of file
// Templates will load AFTER user data is loaded

// Check URL for edit parameter
function checkUrlParams() {
    const params = new URLSearchParams(window.location.search);
    const editUuid = params.get('edit');
    
    if (editUuid) {
        loadSignatureForEdit(editUuid);
    }
}

// Load signature for editing
async function loadSignatureForEdit(uuid) {
    try {
        console.log('Loading signature for edit:', uuid);
        const response = await fetch(`${API_BASE}/signatures.php?uuid=${uuid}`);
        const data = await response.json();
        
        console.log('Signature API response:', data);
        
        if (data.success && data.signature) {
            console.log('Raw signature data:', data.signature);
            
            // Parse config_json if it's a string
            let configData = data.signature.config_json || data.signature.config;
            if (typeof configData === 'string') {
                try {
                    configData = JSON.parse(configData);
                    console.log('Parsed config data:', configData);
                } catch (parseError) {
                    console.error('Failed to parse config JSON:', parseError);
                    showToast('Error loading signature configuration', 'error');
                    return;
                }
            }
            
            state.config = configData;
            state.selectedTemplate = data.signature.template_key;
            state.publicUuid = uuid;
            
            console.log('State updated:', { config: state.config, template: state.selectedTemplate });
            
            // Check if form exists
            const form = document.getElementById('signature-form');
            console.log('Form element found:', !!form);
            
            // Populate form
            console.log('About to populate form with config:', state.config);
            populateForm(state.config);
            console.log('Form populated');
            
            // Go to step 2
            console.log('About to go to step 2');
            goToStep(2);
            console.log('Went to step 2');
            
            showToast('Signature loaded for editing');
        } else {
            console.error('Failed to load signature:', data.error || 'Unknown error');
            showToast('Failed to load signature: ' + (data.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Failed to load signature:', error);
        showToast('Failed to load signature', 'error');
    }
}

// Load templates from API
async function loadTemplates() {
    try {
        const response = await fetch(`${API_BASE}/templates.php`);
        const data = await response.json();
        
        if (data.success) {
            renderTemplateGrid(data.templates);
        }
    } catch (error) {
        console.error('Failed to load templates:', error);
        document.getElementById('template-grid').innerHTML = `
            <div class="col-span-full text-center py-12">
                <i class="fas fa-exclamation-triangle text-4xl text-red-500 mb-4"></i>
                <p class="text-gray-600">Failed to load templates. Please refresh the page.</p>
            </div>
        `;
    }
}

// Render template grid
function renderTemplateGrid(templates) {
    const grid = document.getElementById('template-grid');
    const isLoggedIn = !!localStorage.getItem('session_token');
    
    grid.innerHTML = templates.map(template => {
        const preview = getTemplatePreview(template.template_key);
        const isPremium = template.is_premium;
        
        // Check if template is locked based on user tier
        const isLocked = isPremium && !canAccessPremiumTemplates();
        
        return `
        <div class="template-card ${state.selectedTemplate === template.template_key ? 'selected' : ''} ${isLocked ? 'opacity-75 cursor-not-allowed' : 'cursor-pointer'}" 
             onclick="${isLocked ? 'event.stopPropagation(); selectTemplate(\'' + template.template_key + '\', ' + isPremium + ')' : 'selectTemplate(\'' + template.template_key + '\', ' + isPremium + ')'}"
             style="${isLocked ? 'pointer-events: auto;' : ''}"
             title="${isLocked ? 'Sign up to unlock this template' : ''}"
             >
            ${isPremium ? `
                <div class="absolute top-3 right-3 bg-gradient-to-r from-yellow-400 to-yellow-600 text-white text-xs font-bold px-3 py-1 rounded-full shadow-lg z-10 flex items-center gap-1">
                    ${isLocked ? '<i class="fas fa-lock"></i>' : '<i class="fas fa-star"></i>'}
                    PREMIUM
                </div>
            ` : ''}
            <div class="check-badge">
                <i class="fas fa-check"></i>
            </div>
            <div class="mb-4 relative">
                <div class="w-full h-40 bg-white rounded-lg border-2 border-gray-200 p-3 overflow-hidden flex items-center justify-center" style="font-size: 10px;">
                    ${preview}
                </div>
                ${isLocked ? `
                    <div class="absolute inset-0 bg-black bg-opacity-40 rounded-lg flex items-center justify-center">
                        <div class="text-center text-white">
                            <i class="fas fa-lock text-3xl mb-2"></i>
                            <p class="text-xs font-semibold">Sign up to unlock</p>
                        </div>
                    </div>
                ` : ''}
            </div>
            <h4 class="font-display font-bold text-lg mb-2">${template.name}</h4>
            <p class="text-sm text-gray-600 mb-4">${template.description || ''}</p>
            <button class="btn-primary w-full text-sm py-2">
                ${isLocked ? '<i class="fas fa-lock mr-2"></i>Sign Up to Use' : 
                  state.selectedTemplate === template.template_key ? 'Selected' : 'Choose Template'}
            </button>
        </div>
    `}).join('');
}

// Get template preview HTML
function getTemplatePreview(templateKey) {
    const previews = {
        'minimal-line': `
            <div style="font-family: Arial, sans-serif; font-size: 10px; line-height: 1.4;">
                <div style="font-weight: 700; font-size: 12px; color: #1a1a1a;">John Doe</div>
                <div style="color: #666; font-size: 9px; margin-top: 2px;">Senior Developer • IRONCREST</div>
                <div style="height: 1px; background: linear-gradient(90deg, #2B68C1, transparent); width: 120px; margin: 6px 0;"></div>
                <div style="font-size: 9px; color: #2B68C1;">📧 john@company.com</div>
                <div style="font-size: 9px; color: #666; margin-top: 2px;">📱 +1 (555) 123-4567</div>
            </div>
        `,
        'corporate-block': `
            <div style="font-family: Arial, sans-serif; font-size: 10px; display: flex; gap: 10px; align-items: center;">
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='150' height='150'%3E%3Crect fill='%232B68C1' width='150' height='150'/%3E%3Ctext fill='white' font-size='40' font-weight='bold' x='50%25' y='50%25' text-anchor='middle' dy='.3em'%3ELOGO%3C/text%3E%3C/svg%3E" style="width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid #e3f2fd;" alt="Photo">
                <div>
                    <div style="font-weight: 700; font-size: 12px; color: #1a1a1a;">John Doe</div>
                    <div style="color: #666; font-size: 9px;">Senior Developer • IRONCREST</div>
                    <div style="font-size: 9px; color: #2B68C1; margin-top: 3px;">john@company.com</div>
                    <div style="margin-top: 3px; display: flex; gap: 4px;">
                        <span style="font-size: 8px;">🔗</span>
                        <span style="font-size: 8px;">💼</span>
                    </div>
                </div>
            </div>
        `,
        'badge': `
            <div style="font-family: Arial, sans-serif; font-size: 10px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 10px; border-radius: 8px; display: flex; gap: 8px; align-items: center;">
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='150' height='150'%3E%3Crect fill='%232B68C1' width='150' height='150'/%3E%3Ctext fill='white' font-size='40' font-weight='bold' x='50%25' y='50%25' text-anchor='middle' dy='.3em'%3ELOGO%3C/text%3E%3C/svg%3E" style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover;" alt="Photo">
                <div>
                    <div style="font-weight: 700; font-size: 11px; color: #1a1a1a;">John Doe</div>
                    <div style="color: #666; font-size: 8px;">Senior Developer</div>
                    <div style="background: #2B68C1; color: white; padding: 2px 6px; border-radius: 3px; font-size: 7px; margin-top: 2px; display: inline-block;">IRONCREST</div>
                </div>
            </div>
        `,
        'stripe': `
            <div style="font-family: Arial, sans-serif; font-size: 10px; display: flex; gap: 10px;">
                <div style="width: 4px; background: linear-gradient(180deg, #2A3B8F 0%, #2B68C1 100%); border-radius: 2px;"></div>
                <div>
                    <div style="font-weight: 700; font-size: 12px; color: #1a1a1a;">John Doe</div>
                    <div style="color: #666; font-size: 9px;">Senior Developer</div>
                    <div style="font-size: 9px; color: #2B68C1; margin-top: 3px;">📧 john@company.com</div>
                    <div style="font-size: 8px; color: #999; margin-top: 2px;">🌐 ironcrestsoftware.com</div>
                </div>
            </div>
        `,
        'card': `
            <div style="font-family: Arial, sans-serif; font-size: 10px; border: 2px solid #2B68C1; border-radius: 8px; padding: 12px; background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);">
                <div style="font-weight: 700; font-size: 12px; color: #1a1a1a;">John Doe</div>
                <div style="color: #666; font-size: 9px; margin-top: 2px;">Senior Developer • IRONCREST</div>
                <div style="height: 1px; background: #e5e7eb; margin: 6px 0;"></div>
                <div style="font-size: 9px; color: #2B68C1;">john@company.com</div>
                <div style="font-size: 8px; color: #999; margin-top: 2px;">+1 (555) 123-4567</div>
            </div>
        `,
        'sidebar': `
            <div style="font-family: Arial, sans-serif; font-size: 10px; display: flex; gap: 10px; align-items: center;">
                <div style="background: linear-gradient(180deg, #2A3B8F 0%, #2B68C1 100%); padding: 8px; border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                    <img src="https://ironcrestsoftware.com/assets/images/Ironcrest-software-stacked.png" style="width: 35px; height: auto;" alt="Logo">
                </div>
                <div>
                    <div style="font-weight: 700; font-size: 12px; color: #1a1a1a;">John Doe</div>
                    <div style="color: #666; font-size: 9px;">Senior Developer • IRONCREST</div>
                    <div style="font-size: 9px; color: #2B68C1; margin-top: 3px;">📧 john@company.com</div>
                </div>
            </div>
        `,
        'monoline': `
            <div style="font-family: Arial, sans-serif; font-size: 9px; color: #333; border-bottom: 1px solid #e0e0e0; padding-bottom: 4px;">
                <strong style="color: #1a1a1a;">John Doe</strong> • Senior Developer • 📧 john@company.com • 📱 +1 (555) 123-4567
            </div>
        `,
        'logo-first': `
            <div style="font-family: Arial, sans-serif; font-size: 10px; text-align: center; background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); padding: 10px; border-radius: 8px;">
                <img src="https://ironcrestsoftware.com/assets/images/Ironcrest-software-stacked.png" style="width: 65px; height: auto; margin: 0 auto 6px; display: block;" alt="Logo">
                <div style="font-weight: 700; font-size: 11px; color: #1a1a1a;">John Doe</div>
                <div style="color: #666; font-size: 9px; margin-top: 2px;">Senior Developer • IRONCREST</div>
                <div style="font-size: 9px; color: #2B68C1; margin-top: 3px;">john@company.com</div>
            </div>
        `,
        'accent-tag': `
            <div style="font-family: Arial, sans-serif; font-size: 10px; display: flex; gap: 10px;">
                <div style="width: 6px; background: #2B68C1; border-radius: 3px; box-shadow: 0 2px 4px rgba(43,104,193,0.3);"></div>
                <div>
                    <div style="font-weight: 700; font-size: 12px; color: #1a1a1a;">John Doe</div>
                    <div style="color: #666; font-size: 9px;">Senior Developer • IRONCREST</div>
                    <div style="font-size: 9px; color: #2B68C1; margin-top: 3px;">📧 john@company.com</div>
                    <div style="margin-top: 4px; display: flex; gap: 3px;">
                        <span style="font-size: 8px;">🔗</span>
                        <span style="font-size: 8px;">💼</span>
                        <span style="font-size: 8px;">🐦</span>
                    </div>
                </div>
            </div>
        `,
        'hero-cta': `
            <div style="font-family: Arial, sans-serif; font-size: 10px; text-align: center; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 12px; border-radius: 8px;">
                <div style="font-weight: 800; font-size: 13px; color: #1a1a1a;">John Doe</div>
                <div style="color: #666; font-size: 9px; font-weight: 600; margin-top: 2px;">Senior Developer • IRONCREST</div>
                <div style="background: linear-gradient(135deg, #2A3B8F 0%, #2B68C1 100%); color: white; padding: 5px 14px; border-radius: 5px; font-size: 9px; margin-top: 6px; display: inline-block; box-shadow: 0 2px 6px rgba(43,104,193,0.3);">📅 Schedule a Call</div>
                <div style="font-size: 8px; color: #2B68C1; margin-top: 4px;">john@company.com</div>
            </div>
        `,
        'professional-left-logo': `
            <div style="font-family: Arial, sans-serif; font-size: 10px; display: flex; gap: 10px; align-items: flex-start;">
                <div style="width: 50px; text-align: center; padding-right: 10px; border-right: 2px solid #1a1a1a;">
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='150' height='150'%3E%3Crect fill='%232B68C1' width='150' height='150'/%3E%3Ctext fill='white' font-size='40' font-weight='bold' x='50%25' y='50%25' text-anchor='middle' dy='.3em'%3ELOGO%3C/text%3E%3C/svg%3E" alt="Logo" style="width: 45px; height: 45px; border-radius: 4px; object-fit: contain;" />
                </div>
                <div style="flex: 1;">
                    <div style="font-weight: 700; font-size: 12px; color: #1a1a1a; line-height: 1.2;">John Doe</div>
                    <div style="font-size: 9px; color: #666; margin-top: 1px;">Founder & Lead Engineer | <span style="color: #2B68C1; font-weight: 600;">IRONCREST</span> Software</div>
                    <div style="font-size: 8px; color: #666; font-style: italic; margin-top: 2px;">Modern Strength. Intelligent Systems.</div>
                    <div style="font-size: 9px; margin-top: 4px;">
                        <div style="color: #2B68C1; margin-bottom: 1px;">T (815) 277-1127</div>
                        <div style="color: #2B68C1; margin-bottom: 1px;">E john@company.com</div>
                        <div style="color: #2B68C1;">W company.com</div>
                    </div>
                    <div style="margin-top: 5px;">
                        <div style="background: linear-gradient(135deg, #2A3B8F 0%, #2B68C1 100%); color: white; padding: 4px 10px; border-radius: 4px; font-size: 8px; display: inline-block; font-weight: 600;">Schedule a Consultation</div>
                    </div>
                </div>
            </div>
        `
    };
    
    return previews[templateKey] || previews['minimal-line'];
}

// Select template
function selectTemplate(templateKey, isPremium = false) {
    // Check if template is locked
    if (isPremium && !canAccessPremiumTemplates()) {
        // Show upgrade message
        if (!state.user) {
            // Not logged in - show signup
            if (typeof showQuickSignup === 'function') {
                showQuickSignup();
            }
        } else {
            // Logged in but no access - show upgrade
            window.location.href = 'upgrade.html';
        }
        trackEvent('premium_template_blocked', { template: templateKey });
        return;
    }
    
    state.selectedTemplate = templateKey;
    
    // Track template selection
    trackEvent('template_selected', { template: templateKey, is_premium: isPremium });
    
    // Re-fetch templates to re-render with selection
    loadTemplates();
    
    // Auto-advance after short delay
    setTimeout(() => goToStep(2), 500);
}

// Setup form listeners
function setupFormListeners() {
    const form = document.getElementById('signature-form');
    if (!form) return;
    
    // Listen to all input changes (for text inputs)
    form.addEventListener('input', (e) => {
        updateConfigFromForm();
        updatePreview();
        triggerAutoSave(); // Auto-save after changes
    });
    
    // Listen to change events (for textareas, selects, etc.)
    form.addEventListener('change', (e) => {
        updateConfigFromForm();
        updatePreview();
        triggerAutoSave(); // Auto-save after changes
    });
    
    // Color picker sync
    const colorInput = form.querySelector('input[name="branding.accent"]');
    const colorHex = form.querySelector('input[name="branding.accentHex"]');
    
    if (colorInput && colorHex) {
        colorInput.addEventListener('input', (e) => {
            colorHex.value = e.target.value;
        });
        
        colorHex.addEventListener('input', (e) => {
            if (/^#[0-9A-F]{6}$/i.test(e.target.value)) {
                colorInput.value = e.target.value;
            }
        });
    }
}

// Update config from form
function updateConfigFromForm() {
    const form = document.getElementById('signature-form');
    const formData = new FormData(form);
    
    // Start with placeholder data as base
    const formConfig = JSON.parse(JSON.stringify(state.config)); // Deep clone to preserve structure
    
    formData.forEach((value, key) => {
        const keys = key.split('.');
        let obj = formConfig;
        
        for (let i = 0; i < keys.length - 1; i++) {
            if (!obj[keys[i]]) obj[keys[i]] = {};
            obj = obj[keys[i]];
        }
        
        // Always update with form value (even if empty)
        // This ensures the preview matches what's in the form
        obj[keys[keys.length - 1]] = value;
    });
    
    // Update state with form config
    state.config = formConfig;
}

// Populate form from config
function populateForm(config) {
    console.log('populateForm called with config:', config);
    const form = document.getElementById('signature-form');
    console.log('Form found in populateForm:', !!form);
    
    if (!form) {
        console.error('No form found with id "signature-form"');
        return;
    }
    
    function setValue(path, value) {
        const input = form.querySelector(`[name="${path}"]`);
        console.log(`Setting ${path} = ${value}, input found:`, !!input);
        if (input) {
            input.value = value || '';
            console.log(`Set ${path} to "${input.value}"`);
        } else {
            console.warn(`No input found for path: ${path}`);
        }
    }
    
    // Identity
    setValue('identity.name', config.identity?.name);
    setValue('identity.title', config.identity?.title);
    setValue('identity.pronouns', config.identity?.pronouns);
    
    // Company
    setValue('company.name', config.company?.name);
    setValue('company.slogan', config.company?.slogan);
    setValue('company.logoUrl', config.company?.logoUrl);
    
    // Contact
    setValue('contact.email', config.contact?.email);
    setValue('contact.phone', config.contact?.phone);
    setValue('contact.website', config.contact?.website);
    setValue('contact.calendly', config.contact?.calendly);
    
    // Links
    setValue('links.linkedin', config.links?.linkedin);
    setValue('links.x', config.links?.x);
    setValue('links.github', config.links?.github);
    setValue('links.facebook', config.links?.facebook);
    setValue('links.instagram', config.links?.instagram);
    setValue('links.youtube', config.links?.youtube);
    
    // Branding
    setValue('branding.accent', config.branding?.accent);
    setValue('branding.accentHex', config.branding?.accentHex);
    setValue('branding.logoSize', config.branding?.logoSize);
    setValue('branding.fontSize', config.branding?.fontSize);
    setValue('branding.lineHeight', config.branding?.lineHeight);
    setValue('branding.spacing', config.branding?.spacing);
    
    // CTA
    setValue('addons.cta.label', config.addons?.cta?.label);
    setValue('addons.cta.url', config.addons?.cta?.url);
    setValue('addons.cta.cornerRadius', config.addons?.cta?.cornerRadius);
}

// Update preview
async function updatePreview() {
    if (!state.selectedTemplate) return;
    
    try {
        const response = await fetch(`${API_BASE}/render.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                templateKey: state.selectedTemplate,
                config: state.config,
                signatureId: state.signatureId || null,
                userId: state.user?.id || null
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.signatureHtml = data.html;
            document.getElementById('signature-preview').innerHTML = data.html;
            document.getElementById('final-preview').innerHTML = data.html;
        }
    } catch (error) {
        console.error('Preview update failed:', error);
    }
}

// Navigation
function goToStep(step) {
    // Validation
    if (step === 2 && !state.selectedTemplate) {
        showToast('Please select a template first', 'error');
        return;
    }
    
    if (step === 3 && !state.config.identity.name) {
        showToast('Please fill in your name', 'error');
        return;
    }
    
    // Track step navigation
    trackEvent('step_changed', { from_step: state.currentStep, to_step: step });
    
    // Hide all steps
    document.querySelectorAll('.step-content').forEach(el => el.classList.add('hidden'));
    
    // Show target step
    document.getElementById(`step-${step}`).classList.remove('hidden');
    
    // Update progress
    document.querySelectorAll('.step').forEach((el, i) => {
        el.classList.toggle('active', i + 1 <= step);
    });
    
    state.currentStep = step;
    
    // Show Save Signature button on Step 3 if logged in
    if (step === 3) {
        console.log('📍 Step 3 reached');
        console.log('User logged in?', !!state.user);
        console.log('User data:', state.user);
        
        const saveSection = document.getElementById('save-signature-section');
        console.log('Save section element found?', !!saveSection);
        
        if (saveSection && state.user) {
            console.log('✅ Showing Save Signature button');
            saveSection.classList.remove('hidden');
            
            // Make it more visible with a highlight animation
            saveSection.style.animation = 'pulse 2s ease-in-out 3';
        } else if (!state.user) {
            console.log('ℹ️ User not logged in - Save button hidden');
        } else if (!saveSection) {
            console.error('❌ Save section element not found in DOM!');
        }
    }
    
    // Update preview when going to step 2 or 3
    if (step === 2 || step === 3) {
        updatePreview();
    }
    
    // Additional preview update for step 3
    if (step === 3) {
        trackEvent('signature_completed', {
            has_name: !!state.config.identity.name,
            has_title: !!state.config.identity.title,
            has_company: !!state.config.company.name,
            has_phone: !!state.config.contact.phone,
            has_website: !!state.config.contact.website,
            has_social: !!(state.config.links.linkedin || state.config.links.x),
            has_cta: !!state.config.addons.cta.url
        });
    }
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Export functions
async function copyHtml() {
    trackEvent('export_copy_html', { template: state.selectedTemplate });
    try {
        await navigator.clipboard.writeText(state.signatureHtml);
        showToast('HTML copied to clipboard!');
    } catch (error) {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = state.signatureHtml;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showToast('HTML copied to clipboard!');
    }
}

// Copy visual (formatted) signature - works better for Gmail, Outlook web, etc.
async function copyVisual() {
    trackEvent('export_copy_visual', { template: state.selectedTemplate });
    
    try {
        // Get the preview element
        const previewElement = document.getElementById('final-preview');
        if (!previewElement) {
            showToast('Preview not found', 'error');
            return;
        }
        
        // Create a temporary container with the signature HTML
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = state.signatureHtml;
        tempDiv.style.position = 'absolute';
        tempDiv.style.left = '-9999px';
        document.body.appendChild(tempDiv);
        
        // Select the content
        const range = document.createRange();
        range.selectNodeContents(tempDiv);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        
        // Copy to clipboard
        document.execCommand('copy');
        
        // Clean up
        selection.removeAllRanges();
        document.body.removeChild(tempDiv);
        
        showToast('✓ Visual signature copied! Now paste it directly into your email client.', 'success');
    } catch (error) {
        console.error('Copy visual error:', error);
        showToast('Failed to copy. Try selecting the preview and pressing Ctrl+C (Cmd+C on Mac)', 'error');
    }
}

function downloadHtml() {
    trackEvent('export_download', { template: state.selectedTemplate });
    const blob = new Blob([state.signatureHtml], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'email-signature.html';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    
    showToast('Signature downloaded!');
}

function showEmailModal() {
    trackEvent('export_email_modal_opened', { template: state.selectedTemplate });
    document.getElementById('email-modal').classList.remove('hidden');
    document.getElementById('export-email').value = state.config.contact.email || '';
}

function closeEmailModal() {
    document.getElementById('email-modal').classList.add('hidden');
}

async function sendEmail(event) {
    event.preventDefault();
    
    const email = document.getElementById('export-email').value;
    const btnText = document.getElementById('send-btn-text');
    const btnSpinner = document.getElementById('send-btn-spinner');
    
    // Show loading
    btnText.textContent = 'Sending...';
    btnSpinner.classList.remove('hidden');
    
    try {
        const response = await fetch(`${API_BASE}/export.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email: email,
                templateKey: state.selectedTemplate,
                config: state.config,
                uuid: state.publicUuid
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.publicUuid = data.uuid;
            closeEmailModal();
            showToast('Signature sent! Check your email.');
        } else {
            showToast(data.error || 'Failed to send email', 'error');
        }
    } catch (error) {
        console.error('Send email failed:', error);
        showToast('Failed to send email. Please try again.', 'error');
    } finally {
        btnText.textContent = 'Send Signature';
        btnSpinner.classList.add('hidden');
    }
}

// Auto-save signature for logged-in users (only updates existing, doesn't create new)
async function autoSaveSignature() {
    // Only auto-save if user is logged in
    if (!state.user) {
        console.log('Not logged in - skipping auto-save');
        return;
    }
    
    // Only auto-save if we're editing an existing signature
    if (!state.publicUuid) {
        console.log('No signature to update - use "Save Signature" to create one');
        return;
    }
    
    // Don't save if no content
    if (!state.config.identity.name && !state.config.contact.email) {
        console.log('No content to save yet');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/signatures.php?uuid=${state.publicUuid}`, {
            method: 'PUT',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                templateKey: state.selectedTemplate,
                config: state.config,
                title: state.signatureTitle || `${state.config.identity.name || 'Untitled'} - ${state.selectedTemplate}`
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            console.log('✅ Signature auto-saved:', state.publicUuid);
            
            // Show subtle success indicator
            const indicator = document.createElement('div');
            indicator.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg text-sm z-50';
            indicator.innerHTML = '<i class="fas fa-check mr-2"></i>Saved';
            document.body.appendChild(indicator);
            setTimeout(() => indicator.remove(), 2000);
        }
    } catch (error) {
        console.error('Auto-save failed:', error);
    }
}

// Manually save signature with custom name
async function saveSignature() {
    if (!state.user) {
        showToast('Please log in to save signatures', 'error');
        return;
    }
    
    // Prompt for signature name
    const name = prompt('Name this signature:', state.signatureTitle || `${state.config.identity.name || 'My Signature'} - ${state.selectedTemplate}`);
    
    if (!name) return; // User cancelled
    
    try {
        const response = await fetch(`${API_BASE}/signatures.php`, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email: state.user.email,
                templateKey: state.selectedTemplate,
                config: state.config,
                title: name
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.publicUuid = data.uuid;
            state.signatureTitle = name;
            console.log('✅ Signature saved:', data.uuid);
            
            // Show success with option to view signatures
            if (confirm(`✅ Signature "${name}" saved!\n\nWould you like to view all your saved signatures?`)) {
                window.location.href = 'signatures.html';
            } else {
                showToast(`✅ Signature "${name}" saved!`);
            }
        } else {
            showToast('Failed to save signature', 'error');
        }
    } catch (error) {
        console.error('Save failed:', error);
        showToast('Failed to save signature', 'error');
    }
}

// Debounced auto-save (wait 2 seconds after last change)
let autoSaveTimeout;
function triggerAutoSave() {
    clearTimeout(autoSaveTimeout);
    autoSaveTimeout = setTimeout(autoSaveSignature, 2000);
}

// Logo file upload handler
function handleLogoFileUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    // Validate file size (2MB max)
    const maxSize = 2 * 1024 * 1024;
    if (file.size > maxSize) {
        showToast('File size must be less than 2MB', 'error');
        return;
    }
    
    // Validate file type
    if (!file.type.startsWith('image/')) {
        showToast('Please select a valid image file', 'error');
        return;
    }
    
    // Read file and convert to base64
    const reader = new FileReader();
    reader.onload = (e) => {
        const base64Data = e.target.result;
        
        // Store in state
        state.config.company.logoUrl = base64Data;
        
        // Show preview
        const preview = document.getElementById('logoPreview');
        const previewImg = document.getElementById('logoPreviewImg');
        const fileName = document.getElementById('logoFileName');
        const fileSize = document.getElementById('logoFileSize');
        
        previewImg.src = base64Data;
        fileName.textContent = file.name;
        fileSize.textContent = (file.size / 1024).toFixed(1) + ' KB';
        preview.classList.remove('hidden');
        
        // Clear URL input
        document.querySelector('input[name="company.logoUrl"]').value = '';
        
        // Update preview
        updatePreview();
        showToast('Logo uploaded successfully', 'success');
    };
    reader.readAsDataURL(file);
}

// Clear logo upload
function clearLogoUpload() {
    document.getElementById('logoFileInput').value = '';
    document.getElementById('logoPreview').classList.add('hidden');
    state.config.company.logoUrl = '';
    updatePreview();
    showToast('Logo removed', 'success');
}

// Validate social link
function validateSocialLink(input) {
    const value = input.value.trim();
    const platform = input.dataset.platform;
    const errorDiv = input.nextElementSibling;
    
    // If empty, no error
    if (!value) {
        errorDiv.classList.add('hidden');
        return true;
    }
    
    // Check if it's a valid URL
    try {
        new URL(value);
    } catch (e) {
        errorDiv.textContent = '❌ Please enter a valid URL';
        errorDiv.classList.remove('hidden');
        return false;
    }
    
    // Platform-specific validation
    const platformRules = {
        linkedin: {
            domains: ['linkedin.com'],
            patterns: [/linkedin\.com\/(in|company|school)\//],
            message: 'LinkedIn URL should be like: https://linkedin.com/in/yourname'
        },
        x: {
            domains: ['x.com', 'twitter.com'],
            patterns: [/x\.com\//, /twitter\.com\//],
            message: 'X/Twitter URL should be like: https://x.com/yourname'
        },
        github: {
            domains: ['github.com'],
            patterns: [/github\.com\/[a-zA-Z0-9-]+\/?$/],
            message: 'GitHub URL should be like: https://github.com/yourname'
        },
        facebook: {
            domains: ['facebook.com', 'fb.com'],
            patterns: [/facebook\.com\//, /fb\.com\//],
            message: 'Facebook URL should be like: https://facebook.com/yourname'
        },
        instagram: {
            domains: ['instagram.com'],
            patterns: [/instagram\.com\//],
            message: 'Instagram URL should be like: https://instagram.com/yourname'
        },
        youtube: {
            domains: ['youtube.com', 'youtu.be'],
            patterns: [/youtube\.com\/@/, /youtube\.com\/c\//, /youtube\.com\/user\//],
            message: 'YouTube URL should be like: https://youtube.com/@yourname'
        }
    };
    
    const rules = platformRules[platform];
    if (!rules) {
        errorDiv.classList.add('hidden');
        return true;
    }
    
    const url = new URL(value);
    const hasDomain = rules.domains.some(domain => url.hostname.includes(domain));
    
    if (!hasDomain) {
        errorDiv.textContent = `❌ ${rules.message}`;
        errorDiv.classList.remove('hidden');
        return false;
    }
    
    // Check pattern if defined
    if (rules.patterns && rules.patterns.length > 0) {
        const hasValidPattern = rules.patterns.some(pattern => pattern.test(value));
        if (!hasValidPattern) {
            errorDiv.textContent = `❌ ${rules.message}`;
            errorDiv.classList.remove('hidden');
            return false;
        }
    }
    
    errorDiv.classList.add('hidden');
    return true;
}

// Validate Calendly link
function validateCalendlyLink(input) {
    const value = input.value.trim();
    const errorDiv = document.getElementById('calendly-error');
    const errorMsg = document.getElementById('calendly-error-msg');
    
    // If empty, no error
    if (!value) {
        errorDiv.classList.add('hidden');
        return true;
    }
    
    // Check if it's a valid URL
    try {
        new URL(value);
    } catch (e) {
        errorMsg.textContent = 'Please enter a valid URL';
        errorDiv.classList.remove('hidden');
        return false;
    }
    
    // List of known booking platforms
    const validDomains = [
        'calendly.com',
        'acuityscheduling.com',
        'bookingpage.app',
        'setmore.com',
        'appointy.com',
        'square.com',
        'mindbody.io',
        'genbook.com',
        'simplybook.me',
        'timely.md',
        'youcanbook.me',
        'doodle.com',
        'when2meet.com',
        'bookme.name',
        'calendarbooking.com'
    ];
    
    const url = new URL(value);
    const isValidDomain = validDomains.some(domain => url.hostname.includes(domain));
    
    if (!isValidDomain) {
        errorMsg.textContent = 'Link should be from a booking platform (Calendly, Acuity Scheduling, etc.)';
        errorDiv.classList.remove('hidden');
        return false;
    }
    
    errorDiv.classList.add('hidden');
    return true;
}

// Add new CTA button
function addCTAButton() {
    const container = document.getElementById('cta-buttons-container');
    const buttonCount = container.querySelectorAll('.cta-button-group').length;
    
    if (buttonCount >= 3) {
        showToast('Maximum 3 buttons allowed', 'error');
        return;
    }
    
    const buttonIndex = buttonCount;
    const colors = ['bg-blue-50 border-blue-500', 'bg-purple-50 border-purple-500', 'bg-green-50 border-green-500'];
    const colorClass = colors[buttonIndex] || colors[0];
    const labels = ['Primary Button', 'Secondary Button', 'Tertiary Button'];
    
    const newButton = document.createElement('div');
    newButton.className = `cta-button-group ${colorClass} border-l-4 p-4 rounded`;
    newButton.innerHTML = `
        <div class="flex items-center justify-between mb-3">
            <label class="form-label font-semibold">${labels[buttonIndex]}</label>
            <button type="button" onclick="removeCTAButton(this)" class="text-red-500 hover:text-red-700 text-sm">
                <i class="fas fa-trash"></i> Remove
            </button>
        </div>
        <div class="space-y-3">
            <div>
                <label class="form-label text-sm">Button Text</label>
                <input type="text" name="addons.cta.${buttonIndex}.label" class="form-input" placeholder="Call to Action">
            </div>
            <div>
                <label class="form-label text-sm">Button URL</label>
                <input type="url" name="addons.cta.${buttonIndex}.url" class="form-input" placeholder="https://example.com">
            </div>
            <div>
                <label class="form-label text-sm">Button Style</label>
                <select name="addons.cta.${buttonIndex}.cornerRadius" class="form-input">
                    <option value="none">Square Corners</option>
                    <option value="small">Slightly Rounded</option>
                    <option value="medium" selected>Rounded</option>
                    <option value="large">Very Rounded</option>
                </select>
            </div>
        </div>
    `;
    
    container.appendChild(newButton);
    
    // Add event listeners to new inputs
    newButton.querySelectorAll('input, select').forEach(el => {
        el.addEventListener('input', () => {
            updateConfigFromForm();
            updatePreview();
            triggerAutoSave();
        });
        el.addEventListener('change', () => {
            updateConfigFromForm();
            updatePreview();
            triggerAutoSave();
        });
    });
    
    showToast('Button added', 'success');
}

// Remove CTA button
function removeCTAButton(button) {
    const container = document.getElementById('cta-buttons-container');
    const buttonCount = container.querySelectorAll('.cta-button-group').length;
    
    if (buttonCount <= 1) {
        showToast('You must have at least one button', 'error');
        return;
    }
    
    button.closest('.cta-button-group').remove();
    updateConfigFromForm();
    updatePreview();
    triggerAutoSave();
    showToast('Button removed', 'success');
}

// Setup drag and drop for logo
function setupLogoDragDrop() {
    const dropZone = document.getElementById('logoDropZone');
    if (!dropZone) return;
    
    dropZone.addEventListener('click', () => {
        document.getElementById('logoFileInput').click();
    });
    
    // Prevent default drag behaviors
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    // Highlight drop zone when dragging over it
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight(e) {
        dropZone.classList.add('border-primary', 'bg-blue-50');
    }
    
    function unhighlight(e) {
        dropZone.classList.remove('border-primary', 'bg-blue-50');
    }
    
    // Handle dropped files
    dropZone.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length > 0) {
            document.getElementById('logoFileInput').files = files;
            handleLogoFileUpload({ target: { files: files } });
        }
    }
}

// Dark mode toggle
document.getElementById('dark-mode-toggle')?.addEventListener('click', () => {
    const preview = document.getElementById('signature-preview');
    preview.classList.toggle('dark-mode');
    state.config.options.darkModePreview = preview.classList.contains('dark-mode');
});

// Toast notification
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const messageEl = document.getElementById('toast-message');
    
    messageEl.textContent = message;
    toast.classList.remove('hidden');
    
    setTimeout(() => {
        toast.classList.add('hidden');
    }, 3000);
}

// Load user data (tier, grandfathered status)
async function loadUserData() {
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=validate`, {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success && data.user) {
            state.user = data.user;
            console.log('User loaded:', state.user);
            return true;
        }
    } catch (error) {
        console.error('Error loading user data:', error);
    }
    return false;
}

// Initialize app
document.addEventListener('DOMContentLoaded', async () => {
    console.log('🚀 Initializing app...');
    
    // Load user data first (for tier checking)
    await loadUserData();
    console.log('✅ User data loaded');
    
    // Load templates (now that user data is available)
    await loadTemplates();
    console.log('✅ Templates loaded');
    
    // Try to load user preferences from database (if logged in)
    const loadedFromDb = await loadUserPreferences();
    
    // If not logged in, try to load from localStorage
    if (!loadedFromDb) {
        loadFromLocalStorage();
    }
    
    // Setup event listeners
    setupFormListeners();
    
    // Setup logo drag and drop
    setupLogoDragDrop();
    
    // Check URL params
    checkUrlParams();
    
    // Track page view
    trackEvent('page_view', { page: 'signature_generator' });
    
    // Add save preferences button handler
    const savePrefsBtn = document.getElementById('save-preferences-btn');
    if (savePrefsBtn) {
        savePrefsBtn.addEventListener('click', async () => {
            await saveUserPreferences();
        });
    }
    
    console.log('✅ App initialized');
});

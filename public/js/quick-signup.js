/**
 * Quick Signup Modal
 * Appears when non-logged-in users try to save preferences
 */

// Show quick signup modal
function showQuickSignup() {
    const modal = document.getElementById('quick-signup-modal');
    if (modal) {
        modal.classList.remove('hidden');
        
        // Pre-fill email if they already entered it
        const emailInput = document.querySelector('input[name="contact.email"]');
        const signupEmail = document.getElementById('quick-signup-email');
        if (emailInput && signupEmail && emailInput.value) {
            signupEmail.value = emailInput.value;
        }
        
        // Focus on first empty field
        if (signupEmail && !signupEmail.value) {
            signupEmail.focus();
        } else {
            document.getElementById('quick-signup-password').focus();
        }
    }
}

// Close quick signup modal
function closeQuickSignup() {
    const modal = document.getElementById('quick-signup-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Handle quick signup form submission
async function handleQuickSignup(event) {
    event.preventDefault();
    
    const email = document.getElementById('quick-signup-email').value;
    const password = document.getElementById('quick-signup-password').value;
    const submitBtn = document.getElementById('quick-signup-submit');
    const errorDiv = document.getElementById('quick-signup-error');
    
    // Validate
    if (!email || !password) {
        errorDiv.textContent = 'Email and password are required';
        errorDiv.classList.remove('hidden');
        return;
    }
    
    if (password.length < 8) {
        errorDiv.textContent = 'Password must be at least 8 characters';
        errorDiv.classList.remove('hidden');
        return;
    }
    
    // Show loading
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
    errorDiv.classList.add('hidden');
    
    try {
        // Register user
        const response = await fetch(`${API_BASE}/auth.php?action=register`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Registration successful - store session token (auto-login)
            if (data.session_token) {
                // Store session token in cookie (30 days)
                const expiryDate = new Date();
                expiryDate.setDate(expiryDate.getDate() + 30);
                // Set cookie with Secure flag for HTTPS - use path=/ to ensure it's sent everywhere
                document.cookie = `session_token=${data.session_token}; expires=${expiryDate.toUTCString()}; path=/; Secure; SameSite=Lax`;
                
                // Verify cookie was set
                console.log('Signup successful! Cookie set:', document.cookie);
                console.log('Session token:', data.session_token);
            }
            
            submitBtn.innerHTML = '<i class="fas fa-check"></i> Account Created! Saving...';
            
            // Save preferences to database (now logged in!)
            await saveUserPreferences();
            
            // Close modal
            closeQuickSignup();
            
            // Show success message
            showToast('🎉 Welcome! Your account is created and info is saved!', 'success');
            
            // Redirect to dashboard
            setTimeout(() => {
                window.location.href = 'dashboard.html';
            }, 1000);
        } else {
            // Registration failed
            errorDiv.textContent = data.error || 'Registration failed. Please try again.';
            errorDiv.classList.remove('hidden');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-rocket"></i> Create Free Account & Save';
        }
    } catch (error) {
        console.error('Quick signup error:', error);
        errorDiv.textContent = 'Network error. Please try again.';
        errorDiv.classList.remove('hidden');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-rocket"></i> Create Free Account & Save';
    }
}

// Initialize quick signup
document.addEventListener('DOMContentLoaded', () => {
    // Close modal when clicking outside
    const modal = document.getElementById('quick-signup-modal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeQuickSignup();
            }
        });
    }
    
    // Handle form submission
    const form = document.getElementById('quick-signup-form');
    if (form) {
        form.addEventListener('submit', handleQuickSignup);
    }
    
    // Close button
    const closeBtn = document.getElementById('quick-signup-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeQuickSignup);
    }
});

/**
 * Shared Layout Components
 * Header and Footer for consistent design across all pages
 */

// Initialize layout on page load
document.addEventListener('DOMContentLoaded', () => {
    initializeLayout();
    checkAuthStatus();
    setupModalCloseHandlers();
});

// Initialize header and footer
function initializeLayout() {
    // Insert header
    const headerPlaceholder = document.getElementById('app-header');
    if (headerPlaceholder) {
        headerPlaceholder.innerHTML = getHeaderHTML();
        
        // Setup dropdown close handler AFTER header is inserted
        setupDropdownCloseHandler();
    }
    
    // Insert footer
    const footerPlaceholder = document.getElementById('app-footer');
    if (footerPlaceholder) {
        footerPlaceholder.innerHTML = getFooterHTML();
    }
}

// Setup dropdown close on outside click
function setupDropdownCloseHandler() {
    document.addEventListener('click', (e) => {
        const userMenu = document.getElementById('user-menu');
        const dropdown = document.getElementById('user-dropdown');
        
        if (userMenu && dropdown && !userMenu.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
}

// Header HTML
function getHeaderHTML() {
    return `
        <header class="bg-white border-b-2 border-gray-100 sticky top-0 z-50 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-20">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <a href="index.html" id="logo-link" class="flex items-center gap-3 pr-8 border-r border-gray-200">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-900 to-blue-600 rounded-xl flex items-center justify-center shadow-md">
                                <i class="fas fa-signature text-white text-xl"></i>
                            </div>
                            <div>
                                <h1 class="text-lg font-display font-bold text-gray-900 leading-tight">Email Signature</h1>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-600">Generator</span>
                                    <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-bold">BETA</span>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <!-- Navigation & Auth -->
                    <div class="flex items-center gap-8">
                        <!-- Guest Navigation -->
                        <nav id="guest-nav" class="lg:flex items-center gap-2">
                            <a href="index.html" class="px-5 py-2.5 text-sm font-semibold text-gray-700 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition">
                                Home
                            </a>
                            <a href="app.html" class="flex items-center gap-2 px-5 py-2.5 text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700 rounded-lg transition shadow-md">
                                <i class="fas fa-plus-circle"></i>
                                <span>Create Signature</span>
                            </a>
                        </nav>
                        
                        <!-- Logged-in Navigation -->
                        <nav id="user-nav" class="hidden lg:flex items-center gap-2">
                            <a href="dashboard.html" class="flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition">
                                <i class="fas fa-th-large text-base"></i>
                                <span>Dashboard</span>
                            </a>
                            <a href="signatures.html" class="flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition">
                                <i class="fas fa-folder text-base"></i>
                                <span>My Signatures</span>
                            </a>
                            <a href="analytics.html" class="flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition">
                                <i class="fas fa-chart-line text-base"></i>
                                <span>Analytics</span>
                            </a>
                        </nav>
                        
                        <!-- Auth Buttons (Guest) -->
                        <div id="auth-buttons" class="flex items-center gap-3 pl-6 border-l border-gray-200">
                            <button onclick="showLoginModal()" class="px-4 py-2 text-sm font-semibold text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-lg transition">
                                Login
                            </button>
                            <button onclick="showRegisterModal()" class="flex items-center gap-2 bg-gradient-to-r from-purple-600 to-blue-600 text-white px-5 py-2.5 rounded-lg hover:shadow-lg transition font-bold text-sm">
                                <i class="fas fa-crown"></i>
                                <span>Sign Up Free</span>
                            </button>
                        </div>
                        
                        <!-- User Menu (Logged-in) -->
                        <div id="user-menu" class="hidden relative pl-6 border-l border-gray-200">
                            <button onclick="toggleUserDropdown()" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 rounded-lg transition">
                                <div class="w-10 h-10 bg-gradient-to-br from-purple-600 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-sm ring-2 ring-purple-100 shadow-md">
                                    <span id="user-initials">U</span>
                                </div>
                                <div class="hidden md:block text-left">
                                    <div id="user-email" class="text-sm font-bold text-gray-900"></div>
                                    <div class="text-xs text-purple-600 font-semibold">
                                        <i class="fas fa-crown"></i> Grandfathered
                                    </div>
                                </div>
                                <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                            </button>
                            <div id="user-dropdown" class="hidden absolute right-0 mt-2 w-64 bg-white rounded-xl shadow-2xl border-2 border-gray-100 py-2 z-50">
                                <div class="px-4 py-3 border-b-2 border-gray-100 bg-gradient-to-r from-purple-50 to-blue-50">
                                    <p class="text-sm font-bold text-gray-900" id="dropdown-email"></p>
                                    <p class="text-xs text-purple-600 mt-1 font-bold">
                                        <i class="fas fa-crown"></i> Free Forever
                                    </p>
                                </div>
                                <a href="dashboard.html" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition">
                                    <i class="fas fa-th-large w-5 text-base"></i>
                                    <span>Dashboard</span>
                                </a>
                                <a href="app.html" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition">
                                    <i class="fas fa-plus-circle w-5 text-base"></i>
                                    <span>Create Signature</span>
                                </a>
                                <a href="signatures.html" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition">
                                    <i class="fas fa-folder w-5 text-base"></i>
                                    <span>My Signatures</span>
                                </a>
                                <a href="profile.html" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition">
                                    <i class="fas fa-user-circle w-5 text-base"></i>
                                    <span>My Profile</span>
                                </a>
                                <a href="help.html" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition">
                                    <i class="fas fa-question-circle w-5 text-base"></i>
                                    <span>Help & FAQ</span>
                                </a>
                                <div class="border-t-2 border-gray-100 my-2"></div>
                                <button onclick="logout()" class="w-full flex items-center gap-3 px-4 py-3 text-sm font-semibold text-red-600 hover:bg-red-50 transition">
                                    <i class="fas fa-sign-out-alt w-5 text-base"></i>
                                    <span>Logout</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
    `;
}

// Footer HTML
function getFooterHTML() {
    return `
        <footer class="bg-gradient-to-br from-gray-900 to-gray-800 text-gray-400 py-12 px-4 mt-20 border-t-4 border-blue-600">
            <div class="max-w-6xl mx-auto">
                <!-- Footer Grid -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
                    <!-- Brand -->
                    <div class="md:col-span-1">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-purple-600 rounded-lg flex items-center justify-center">
                                <i class="fas fa-signature text-white text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-white font-bold text-sm">Email Signature</h3>
                                <p class="text-xs text-gray-500">Generator</p>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 leading-relaxed">Create professional email signatures in minutes. Free forever for early users.</p>
                    </div>
                    
                    <!-- Product -->
                    <div>
                        <h4 class="text-white font-bold text-sm mb-4">Product</h4>
                        <ul class="space-y-2 text-sm">
                            <li><a href="index.html" class="hover:text-white transition flex items-center gap-2"><i class="fas fa-home text-xs w-4"></i> Home</a></li>
                            <li><a href="app.html" class="hover:text-white transition flex items-center gap-2"><i class="fas fa-plus-circle text-xs w-4"></i> Create Signature</a></li>
                            <li><a href="dashboard.html" class="hover:text-white transition flex items-center gap-2"><i class="fas fa-th-large text-xs w-4"></i> Dashboard</a></li>
                            <li><a href="analytics.html" class="hover:text-white transition flex items-center gap-2"><i class="fas fa-chart-line text-xs w-4"></i> Analytics</a></li>
                        </ul>
                    </div>
                    
                    <!-- Support -->
                    <div>
                        <h4 class="text-white font-bold text-sm mb-4">Support</h4>
                        <ul class="space-y-2 text-sm">
                            <li><a href="help.html" class="hover:text-white transition flex items-center gap-2"><i class="fas fa-question-circle text-xs w-4"></i> Help & FAQ</a></li>
                            <li><a href="mailto:support@ironcrestsoftware.com" class="hover:text-white transition flex items-center gap-2"><i class="fas fa-envelope text-xs w-4"></i> Contact Support</a></li>
                            <li><a href="https://ironcrestsoftware.com" class="hover:text-white transition flex items-center gap-2"><i class="fas fa-globe text-xs w-4"></i> Ironcrest Software</a></li>
                            <li><a href="https://apps.ironcrestsoftware.com" class="hover:text-white transition flex items-center gap-2"><i class="fas fa-th text-xs w-4"></i> More Apps</a></li>
                        </ul>
                    </div>
                    
                    <!-- Legal -->
                    <div>
                        <h4 class="text-white font-bold text-sm mb-4">Legal</h4>
                        <ul class="space-y-2 text-sm">
                            <li><a href="terms.html" class="hover:text-white transition flex items-center gap-2"><i class="fas fa-file-contract text-xs w-4"></i> Terms of Service</a></li>
                            <li><a href="privacy.html" class="hover:text-white transition flex items-center gap-2"><i class="fas fa-shield-alt text-xs w-4"></i> Privacy Policy</a></li>
                        </ul>
                        <div class="mt-6">
                            <p class="text-xs text-gray-500 mb-2">Made with ❤️ by</p>
                            <a href="https://ironcrestsoftware.com" class="text-blue-400 hover:text-blue-300 transition font-bold text-sm">Ironcrest Software</a>
                        </div>
                    </div>
                </div>
                
                <!-- Bottom Bar -->
                <div class="border-t border-gray-800 pt-6 flex flex-col md:flex-row justify-between items-center gap-4">
                    <p class="text-xs text-gray-500">© 2025 Ironcrest Software. All rights reserved.</p>
                    <div class="flex items-center gap-4">
                        <span class="text-xs bg-purple-900/50 text-purple-300 px-3 py-1 rounded-full font-bold border border-purple-700">
                            <i class="fas fa-crown mr-1"></i> BETA - Free Forever
                        </span>
                    </div>
                </div>
            </div>
        </footer>
    `;
}

// Check authentication status
async function checkAuthStatus() {
    // Get token from cookie
    const token = getCookie('session_token');
    if (!token) return;
    
    try {
        const response = await fetch('../api/auth.php?action=validate', {
            headers: {
                'Authorization': 'Bearer ' + token
            }
        });
        
        const data = await response.json();
        
        if (data.success && data.user) {
            showUserMenu(data.user);
        } else {
            // Clear invalid token
            document.cookie = 'session_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
        }
    } catch (error) {
        console.error('Auth check failed:', error);
    }
}

// Helper function to get cookie value
function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

// Show user menu when logged in
function showUserMenu(user) {
    const authButtons = document.getElementById('auth-buttons');
    const userMenu = document.getElementById('user-menu');
    const guestNav = document.getElementById('guest-nav');
    const userNav = document.getElementById('user-nav');
    const userEmail = document.getElementById('user-email');
    const dropdownEmail = document.getElementById('dropdown-email');
    const userInitials = document.getElementById('user-initials');
    const logoLink = document.getElementById('logo-link');
    
    // Hide guest elements, show user elements
    if (authButtons) authButtons.classList.add('hidden');
    if (guestNav) guestNav.classList.add('hidden');
    if (userMenu) userMenu.classList.remove('hidden');
    if (userNav) userNav.classList.remove('hidden');
    
    // Change logo link to dashboard for logged-in users
    if (logoLink) logoLink.href = 'dashboard.html';
    
    // Set user info
    if (userEmail) userEmail.textContent = user.email;
    if (dropdownEmail) dropdownEmail.textContent = user.email;
    
    // Set user initials
    if (userInitials && user.email) {
        const initials = user.email.substring(0, 2).toUpperCase();
        userInitials.textContent = initials;
    }
}

// Toggle user dropdown menu
function toggleUserDropdown() {
    const dropdown = document.getElementById('user-dropdown');
    if (dropdown) {
        dropdown.classList.toggle('hidden');
    }
}

// Show login modal
function showLoginModal() {
    const modal = document.getElementById('login-modal');
    if (modal) {
        modal.classList.remove('hidden');
        console.log('Login modal opened');
        
        // Focus on email input
        setTimeout(() => {
            const emailInput = document.getElementById('login-email');
            if (emailInput) emailInput.focus();
        }, 100);
    }
}

// Close login modal
function closeLoginModal() {
    const modal = document.getElementById('login-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
        
        // Clear form and errors
        const form = document.getElementById('login-form');
        if (form) form.reset();
        hideError('login-error');
    }
}

// Show register modal (use quick signup)
function showRegisterModal() {
    if (typeof showQuickSignup === 'function') {
        showQuickSignup();
    }
}

// Close register modal
function closeRegisterModal() {
    const modal = document.getElementById('register-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
        
        // Clear form and errors
        const form = document.getElementById('register-form');
        if (form) form.reset();
        hideError('register-error');
    }
}

// Switch between modals
function switchToRegister() {
    closeLoginModal();
    showRegisterModal();
}

function switchToLogin() {
    closeRegisterModal();
    showLoginModal();
}

// Handle login form submission
async function handleLogin(e) {
    e.preventDefault();
    
    const form = e.target;
    const email = document.getElementById('login-email').value;
    const password = document.getElementById('login-password').value;
    const submitBtn = document.getElementById('login-submit');
    const errorDiv = document.getElementById('login-error');
    
    console.log('=== LOGIN ATTEMPT ===');
    console.log('Email:', email);
    
    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Signing in...';
    if (errorDiv) errorDiv.classList.add('hidden');
    
    try {
        const response = await fetch('../api/auth.php?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        
        const data = await response.json();
        
        console.log('=== LOGIN RESPONSE ===');
        console.log('Success:', data.success);
        console.log('Has token:', !!data.session_token);
        
        if (data.success && data.session_token) {
            // Set cookie (exactly like the working test)
            const expiryDate = new Date();
            expiryDate.setDate(expiryDate.getDate() + 30);
            document.cookie = `session_token=${data.session_token}; expires=${expiryDate.toUTCString()}; path=/; Secure; SameSite=Lax`;
            
            console.log('✅ Cookie set! Redirecting to dashboard...');
            
            // Close modal and redirect to dashboard
            closeLoginModal();
            window.location.href = 'dashboard.html';
        } else {
            if (errorDiv) {
                errorDiv.textContent = data.error || 'Login failed';
                errorDiv.classList.remove('hidden');
            }
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-sign-in-alt mr-2"></i> Sign In';
        }
    } catch (error) {
        console.error('Login error:', error);
        if (errorDiv) {
            errorDiv.textContent = 'Network error. Please try again.';
            errorDiv.classList.remove('hidden');
        }
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-sign-in-alt mr-2"></i> Sign In';
    }
}

// Handle register form submission
async function handleRegister(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Creating account...';
    
    try {
        const response = await fetch('../api/auth.php?action=register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                email: formData.get('email'),
                password: formData.get('password')
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Store session token
            localStorage.setItem('session_token', data.session_token);
            
            // Close modal and reload
            closeRegisterModal();
            window.location.reload();
        } else {
            showError('register-error', data.message || 'Registration failed. Please try again.');
        }
    } catch (error) {
        console.error('Registration error:', error);
        showError('register-error', 'Network error. Please try again.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-rocket mr-2"></i> Create Free Account';
    }
}

// Show error message
function showError(elementId, message) {
    const errorDiv = document.getElementById(elementId);
    const errorMessage = document.getElementById(elementId + '-message');
    
    if (errorDiv && errorMessage) {
        errorMessage.textContent = message;
        errorDiv.classList.remove('hidden');
    }
}

// Hide error message
function hideError(elementId) {
    const errorDiv = document.getElementById(elementId);
    if (errorDiv) {
        errorDiv.classList.add('hidden');
    }
}

// Logout
async function logout() {
    const token = getCookie('session_token');
    
    if (token) {
        try {
            await fetch('../api/auth.php?action=logout', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token
                }
            });
        } catch (error) {
            console.error('Logout failed:', error);
        }
    }
    
    // Clear cookie
    document.cookie = 'session_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    window.location.href = 'index.html';
}

// Setup modal close handlers
function setupModalCloseHandlers() {
    // Login modal close button
    const loginCloseBtn = document.getElementById('login-close');
    if (loginCloseBtn) {
        loginCloseBtn.addEventListener('click', closeLoginModal);
    }
    
    // Login modal - click outside to close
    const loginModal = document.getElementById('login-modal');
    if (loginModal) {
        loginModal.addEventListener('click', (e) => {
            if (e.target === loginModal) {
                closeLoginModal();
            }
        });
    }
    
    // Attach login form handler
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
        console.log('✅ Login form handler attached on page load');
    } else {
        console.warn('⚠️ Login form not found on page load');
    }
    
    // Quick signup modal close button
    const signupCloseBtn = document.getElementById('quick-signup-close');
    if (signupCloseBtn) {
        signupCloseBtn.addEventListener('click', () => {
            if (typeof closeQuickSignup === 'function') {
                closeQuickSignup();
            }
        });
    }
    
    // Quick signup modal - click outside to close
    const signupModal = document.getElementById('quick-signup-modal');
    if (signupModal) {
        signupModal.addEventListener('click', (e) => {
            if (e.target === signupModal && typeof closeQuickSignup === 'function') {
                closeQuickSignup();
            }
        });
    }
    
    // ESC key to close modals
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (loginModal && !loginModal.classList.contains('hidden')) {
                closeLoginModal();
            }
            if (signupModal && !signupModal.classList.contains('hidden') && typeof closeQuickSignup === 'function') {
                closeQuickSignup();
            }
        }
    });
}

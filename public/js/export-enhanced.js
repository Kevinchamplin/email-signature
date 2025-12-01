/**
 * Enhanced Export Functions
 * Improved copy, download, and export functionality
 */

// Enhanced copy HTML with better feedback
async function copyHtmlEnhanced() {
    try {
        const html = state.signatureHtml;
        
        if (!html) {
            showToast('Please generate your signature first', 'error');
            return;
        }
        
        // Try modern clipboard API first
        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(html);
            showToast('✓ HTML copied to clipboard! Ready to paste in your email client.', 'success');
            trackEvent('signature_copied', { template: state.selectedTemplate });
            
            // Track activity
            if (typeof ActivityTracker !== 'undefined') {
                ActivityTracker.trackCopyHTML(state.currentSignatureId);
            }
        } else {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = html;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            
            try {
                document.execCommand('copy');
                showToast('✓ HTML copied to clipboard!', 'success');
                trackEvent('signature_copied', { template: state.selectedTemplate });
                
                // Track activity
                if (typeof ActivityTracker !== 'undefined') {
                    ActivityTracker.trackCopyHTML(state.currentSignatureId);
                }
            } catch (err) {
                showToast('Failed to copy. Please try again.', 'error');
            }
            
            document.body.removeChild(textarea);
        }
    } catch (error) {
        console.error('Copy error:', error);
        showToast('Failed to copy. Please try the download option instead.', 'error');
    }
}

// Enhanced download with better filename
function downloadHtmlEnhanced() {
    try {
        const html = state.signatureHtml;
        
        if (!html) {
            showToast('Please generate your signature first', 'error');
            return;
        }
        
        // Create full HTML document
        const fullHtml = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Signature - ${state.config.identity.name || 'Signature'}</title>
</head>
<body style="margin: 0; padding: 20px; font-family: Arial, sans-serif;">
    <h1 style="color: #333; font-size: 24px; margin-bottom: 20px;">Your Email Signature</h1>
    <p style="color: #666; margin-bottom: 20px;">Copy the signature below and paste it into your email client settings.</p>
    <div style="border: 2px dashed #ccc; padding: 20px; background: #f9f9f9; margin-bottom: 20px;">
        ${html}
    </div>
    <div style="background: #f0f9ff; border-left: 4px solid #3b82f6; padding: 15px; margin-top: 20px;">
        <h2 style="color: #1e40af; font-size: 18px; margin: 0 0 10px 0;">Installation Instructions</h2>
        <p style="color: #1e3a8a; margin: 5px 0;"><strong>Gmail:</strong> Settings → See all settings → Signature → Create new → Paste HTML</p>
        <p style="color: #1e3a8a; margin: 5px 0;"><strong>Outlook (Desktop App):</strong> Settings → Accounts → Signatures → New → Use "Copy Visual" option for best results</p>
        <p style="color: #1e3a8a; margin: 5px 0;"><strong>Outlook (Web):</strong> Settings ⚙️ → View all Outlook settings → Mail → Compose and reply → Signatures → Create new → Paste HTML</p>
        <p style="color: #1e3a8a; margin: 5px 0;"><strong>Apple Mail:</strong> Mail → Preferences → Signatures → + → Paste HTML</p>
    </div>
</body>
</html>`;
        
        // Create blob and download
        const blob = new Blob([fullHtml], { type: 'text/html' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        
        // Generate filename
        const name = state.config.identity.name || 'signature';
        const safeName = name.replace(/[^a-z0-9]/gi, '-').toLowerCase();
        const timestamp = new Date().toISOString().split('T')[0];
        a.download = `email-signature-${safeName}-${timestamp}.html`;
        
        a.href = url;
        a.click();
        
        URL.revokeObjectURL(url);
        
        showToast('✓ Signature downloaded! Open the file to view installation instructions.', 'success');
        trackEvent('signature_downloaded', { template: state.selectedTemplate });
        
        // Track activity
        if (typeof ActivityTracker !== 'undefined') {
            ActivityTracker.trackDownload(state.currentSignatureId);
        }
    } catch (error) {
        console.error('Download error:', error);
        showToast('Failed to download. Please try again.', 'error');
    }
}

// Quick copy button for preview
function addQuickCopyButton() {
    const preview = document.getElementById('signature-preview');
    if (!preview) return;
    
    // Check if button already exists
    if (document.getElementById('quick-copy-btn')) return;
    
    // Create quick copy button
    const btn = document.createElement('button');
    btn.id = 'quick-copy-btn';
    btn.className = 'absolute top-2 right-2 bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1 rounded-lg shadow-lg transition-colors opacity-0 hover:opacity-100 focus:opacity-100';
    btn.innerHTML = '<i class="fas fa-copy"></i> Quick Copy';
    btn.onclick = copyHtmlEnhanced;
    
    // Make preview container relative
    const container = preview.parentElement;
    if (container) {
        container.style.position = 'relative';
        container.appendChild(btn);
        
        // Show button on hover
        container.addEventListener('mouseenter', () => {
            btn.style.opacity = '1';
        });
        container.addEventListener('mouseleave', () => {
            if (document.activeElement !== btn) {
                btn.style.opacity = '0';
            }
        });
    }
}

// Override existing functions if they exist
if (typeof copyHtml !== 'undefined') {
    window.copyHtml = copyHtmlEnhanced;
}
if (typeof downloadHtml !== 'undefined') {
    window.downloadHtml = downloadHtmlEnhanced;
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    // Add quick copy button after a short delay
    setTimeout(addQuickCopyButton, 1000);
});

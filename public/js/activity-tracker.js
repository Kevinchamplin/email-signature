/**
 * User Activity Tracker
 * Track user actions for analytics (both logged-in users and guests)
 */

const ActivityTracker = {
    /**
     * Get or create guest ID
     */
    getGuestId() {
        let guestId = localStorage.getItem('guest_id');
        
        if (!guestId) {
            // Generate unique guest ID
            guestId = 'guest_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            localStorage.setItem('guest_id', guestId);
        }
        
        return guestId;
    },
    
    /**
     * Log an activity
     */
    async log(activityType, signatureId = null, data = null) {
        try {
            const payload = {
                activityType,
                signatureId,
                data,
                guestId: this.getGuestId() // Always include guest ID for fallback
            };
            
            const response = await fetch(`${API_BASE}/activity.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify(payload)
            });
            
            const result = await response.json();
            return result.success;
        } catch (error) {
            console.error('Activity tracking error:', error);
            return false;
        }
    },
    
    /**
     * Track copy HTML action
     */
    trackCopyHTML(signatureId) {
        return this.log('copy_html', signatureId);
    },
    
    /**
     * Track download action
     */
    trackDownload(signatureId) {
        return this.log('download_html', signatureId);
    },
    
    /**
     * Track email signature action
     */
    trackEmail(signatureId) {
        return this.log('email_signature', signatureId);
    },
    
    /**
     * Track signature creation
     */
    trackCreate(signatureId, templateKey) {
        return this.log('create_signature', signatureId, { templateKey });
    },
    
    /**
     * Track signature edit
     */
    trackEdit(signatureId) {
        return this.log('edit_signature', signatureId);
    },
    
    /**
     * Track template change
     */
    trackTemplateChange(signatureId, oldTemplate, newTemplate) {
        return this.log('template_change', signatureId, { oldTemplate, newTemplate });
    },
    
    /**
     * Track preview
     */
    trackPreview(signatureId) {
        return this.log('preview_signature', signatureId);
    },
    
    /**
     * Track analytics view
     */
    trackAnalyticsView(signatureId) {
        return this.log('view_analytics', signatureId);
    }
};

// Make it globally available
window.ActivityTracker = ActivityTracker;

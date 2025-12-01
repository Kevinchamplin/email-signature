<?php
/**
 * Beta Configuration
 * Control grandfathered access and beta status
 * 
 * IMPORTANT: Set BETA_ACTIVE to false when launching paid tiers
 */

return [
    // Is beta active? (Grandfathered access enabled)
    'BETA_ACTIVE' => true,
    
    // Beta end date (optional - for display purposes)
    'BETA_END_DATE' => null, // e.g., '2025-12-31'
    
    // Message to show when beta is active
    'BETA_MESSAGE' => 'Sign up during beta for lifetime free access!',
    
    // Message to show when beta ends
    'POST_BETA_MESSAGE' => 'Beta has ended. New users will be on paid tiers.',
    
    // Automatically set is_grandfathered=1 for new signups?
    'AUTO_GRANDFATHER' => true,
];

<?php
/**
 * Bridge API Configuration
 * Connection settings to main taller-automotriz-app system
 */

return [
    // Bridge API endpoint URL
    'api_url' => getenv('BRIDGE_API_URL') ?: 'https://errautomotriz.online/api/bridge/get_client_by_order.php',

    // API key for authentication
    'api_key' => getenv('BRIDGE_API_KEY') ?: 'SEE_BRIDGE_API_KEY_2026',

    // Timeout for bridge API calls (seconds)
    // Keep this low - if main system is down, we don't want to hang
    'timeout' => 5,

    // Connect timeout (seconds)
    'connect_timeout' => 3,

    // Cache settings
    'cache' => [
        'enabled' => true,
        'ttl_hours' => 24,  // Cache client data for 24 hours
        'use_database' => true  // Use cache_client_data table
    ],

    // Retry settings
    'retry' => [
        'enabled' => true,
        'max_attempts' => 2,
        'delay_seconds' => 1
    ],

    // Fallback behavior if bridge is unreachable
    'fallback' => [
        'continue_on_failure' => true,  // Don't block evidence upload if bridge fails
        'log_failures' => true,
        'alert_after_failures' => 5  // Alert admin after 5 consecutive failures
    ]
];

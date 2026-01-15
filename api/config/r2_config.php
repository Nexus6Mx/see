<?php
/**
 * Cloudflare R2 Configuration
 * S3-compatible storage credentials
 * 
 * IMPORTANT: Update these values after Cloudflare R2 setup
 * See: /docs/CLOUDFLARE_R2_REQUIREMENTS.md
 */

return [
    // S3 SDK configuration
    'version' => 'latest',
    'region' => 'auto',

    // Cloudflare R2 endpoint
    // Format: https://<ACCOUNT_ID>.r2.cloudflarestorage.com
    'endpoint' => getenv('R2_ENDPOINT') ?: 'https://18dbe6ea0c4e14c1bc159b576ba6256e.r2.cloudflarestorage.com',

    // Use path-style endpoint (required for R2)
    'use_path_style_endpoint' => true,

    // API credentials from Cloudflare R2 dashboard
    'credentials' => [
        'key' => getenv('R2_ACCESS_KEY') ?: '78c4906212499b6d86d5a54c715074fc',
        'secret' => getenv('R2_SECRET_KEY') ?: '6667429941eab534470d9904d4d4246a702c37fd541a64f6eebb607b81d54bd9'
    ],

    // Bucket name
    'bucket' => getenv('R2_BUCKET') ?: 'err-evidencias',

    // Public CDN URL for accessing files
    // Option 1: Custom domain (recommended)
    'cdn_url' => getenv('R2_CDN_URL') ?: 'https://cdn.errautomotriz.online',

    // Option 2: Cloudflare R2 dev URL (if not using custom domain)
    // 'cdn_url' => 'https://pub-xxxxxxxx.r2.dev',

    // File upload settings
    'upload' => [
        'acl' => 'public-read',  // Make files publicly readable
        'max_file_size' => 100 * 1024 * 1024,  // 100 MB
        'allowed_image_types' => ['image/jpeg', 'image/png', 'image/jpg'],
        'allowed_video_types' => ['video/mp4', 'video/quicktime', 'video/x-msvideo'],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'mp4', 'mov', 'avi']
    ],

    // Thumbnail settings
    'thumbnail' => [
        'width' => 300,
        'height' => 300,
        'quality' => 80,
        'suffix' => '_thumb'
    ],

    // Timeouts and retries
    'timeout' => 30,  // seconds
    'connect_timeout' => 10,  // seconds
    'retries' => 3
];

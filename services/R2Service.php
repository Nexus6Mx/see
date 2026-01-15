<?php
/**
 * R2Service - Cloudflare R2 Storage Service
 * Handles file uploads, downloads, and management using S3-compatible API
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class R2Service
{
    private $s3Client;
    private $config;
    private $bucket;
    private $cdnUrl;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../api/config/r2_config.php';
        $this->bucket = $this->config['bucket'];
        $this->cdnUrl = rtrim($this->config['cdn_url'], '/');

        $this->initializeClient();
    }

    /**
     * Initialize S3 client for R2
     */
    private function initializeClient()
    {
        try {
            $this->s3Client = new S3Client([
                'version' => $this->config['version'],
                'region' => $this->config['region'],
                'endpoint' => $this->config['endpoint'],
                'use_path_style_endpoint' => $this->config['use_path_style_endpoint'],
                'credentials' => [
                    'key' => $this->config['credentials']['key'],
                    'secret' => $this->config['credentials']['secret']
                ],
                'http' => [
                    'timeout' => $this->config['timeout'],
                    'connect_timeout' => $this->config['connect_timeout']
                ]
            ]);
        } catch (Exception $e) {
            error_log("[R2Service] Failed to initialize S3 client: " . $e->getMessage());
            throw new Exception("Failed to initialize R2 storage client");
        }
    }

    /**
     * Upload file to R2
     * 
     * @param string $localPath Local file path
     * @param string $orderNumber Order number for organization
     * @param string $originalFilename Original filename
     * @param string $fileType 'imagen' or 'video'
     * @return array ['path' => R2 path, 'url' => public URL, 'size' => file size]
     */
    public function upload($localPath, $orderNumber, $originalFilename, $fileType = 'imagen')
    {
        if (!file_exists($localPath)) {
            throw new Exception("Local file not found: {$localPath}");
        }

        // Validate file size
        $fileSize = filesize($localPath);
        if ($fileSize > $this->config['upload']['max_file_size']) {
            throw new Exception("File size exceeds maximum allowed");
        }

        // Generate R2 path: /YYYY/MM/orden_numero/filename.ext
        $r2Path = $this->generatePath($orderNumber, $originalFilename);

        try {
            // Get MIME type
            $mimeType = mime_content_type($localPath);

            // Upload to R2
            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $r2Path,
                'SourceFile' => $localPath,
                'ContentType' => $mimeType,
                'ACL' => $this->config['upload']['acl'],
                'Metadata' => [
                    'orden-numero' => $orderNumber,
                    'tipo' => $fileType,
                    'uploaded-at' => date('Y-m-d H:i:s')
                ]
            ]);

            $publicUrl = $this->getPublicUrl($r2Path);

            error_log("[R2Service] Uploaded file to R2: {$r2Path}");

            return [
                'path' => $r2Path,
                'url' => $publicUrl,
                'size' => $fileSize,
                'mime_type' => $mimeType,
                'etag' => $result['ETag'] ?? null
            ];

        } catch (AwsException $e) {
            error_log("[R2Service] AWS Error uploading file: " . $e->getMessage());
            throw new Exception("Failed to upload file to R2: " . $e->getAwsErrorMessage());
        } catch (Exception $e) {
            error_log("[R2Service] Error uploading file: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Upload thumbnail to R2
     * 
     * @param string $localPath Local thumbnail path
     * @param string $originalR2Path Original file R2 path
     * @return array ['path' => R2 path, 'url' => public URL]
     */
    public function uploadThumbnail($localPath, $originalR2Path)
    {
        if (!file_exists($localPath)) {
            throw new Exception("Thumbnail file not found: {$localPath}");
        }

        // Generate thumbnail path (same directory, add _thumb suffix)
        $pathInfo = pathinfo($originalR2Path);
        $thumbPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] .
            $this->config['thumbnail']['suffix'] . '.' . $pathInfo['extension'];

        try {
            $mimeType = mime_content_type($localPath);

            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $thumbPath,
                'SourceFile' => $localPath,
                'ContentType' => $mimeType,
                'ACL' => $this->config['upload']['acl']
            ]);

            error_log("[R2Service] Uploaded thumbnail to R2: {$thumbPath}");

            return [
                'path' => $thumbPath,
                'url' => $this->getPublicUrl($thumbPath)
            ];

        } catch (AwsException $e) {
            error_log("[R2Service] Error uploading thumbnail: " . $e->getMessage());
            // Don't throw - thumbnails are not critical
            return null;
        }
    }

    /**
     * Delete file from R2
     * 
     * @param string $r2Path Path in R2
     * @return bool Success
     */
    public function delete($r2Path)
    {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $r2Path
            ]);

            error_log("[R2Service] Deleted file from R2: {$r2Path}");
            return true;

        } catch (AwsException $e) {
            error_log("[R2Service] Error deleting file: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if file exists in R2
     * 
     * @param string $r2Path Path in R2
     * @return bool
     */
    public function exists($r2Path)
    {
        try {
            $this->s3Client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $r2Path
            ]);
            return true;
        } catch (AwsException $e) {
            return false;
        }
    }

    /**
     * Get file metadata from R2
     * 
     * @param string $r2Path Path in R2
     * @return array|null
     */
    public function getMetadata($r2Path)
    {
        try {
            $result = $this->s3Client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $r2Path
            ]);

            return [
                'size' => $result['ContentLength'],
                'content_type' => $result['ContentType'],
                'last_modified' => $result['LastModified'],
                'etag' => $result['ETag'],
                'metadata' => $result['Metadata'] ?? []
            ];

        } catch (AwsException $e) {
            error_log("[R2Service] Error getting metadata: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate R2 path for file
     * Format: YYYY/MM/orden_numero/filename.ext
     * 
     * @param string $orderNumber Order number
     * @param string $filename Original filename
     * @return string R2 path
     */
    private function generatePath($orderNumber, $filename)
    {
        $year = date('Y');
        $month = date('m');

        // Sanitize filename
        $safeFilename = $this->sanitizeFilename($filename);

        return "{$year}/{$month}/{$orderNumber}/{$safeFilename}";
    }

    /**
     * Sanitize filename for safe storage
     * 
     * @param string $filename Original filename
     * @return string Safe filename
     */
    private function sanitizeFilename($filename)
    {
        // Remove special characters, keep only alphanumeric, dots, dashes, underscores
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Remove multiple consecutive underscores
        $filename = preg_replace('/_+/', '_', $filename);

        // Add timestamp if filename is too generic
        if (strlen($filename) < 5) {
            $pathInfo = pathinfo($filename);
            $filename = 'file_' . time() . '.' . ($pathInfo['extension'] ?? 'bin');
        }

        return $filename;
    }

    /**
     * Get public URL for R2 file
     * 
     * @param string $r2Path Path in R2
     * @return string Public URL
     */
    public function getPublicUrl($r2Path)
    {
        return $this->cdnUrl . '/' . ltrim($r2Path, '/');
    }

    /**
     * Get presigned URL for temporary access (if needed for private files)
     * 
     * @param string $r2Path Path in R2
     * @param int $expirationMinutes Expiration in minutes
     * @return string Presigned URL
     */
    public function getPresignedUrl($r2Path, $expirationMinutes = 60)
    {
        try {
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => $r2Path
            ]);

            $request = $this->s3Client->createPresignedRequest(
                $cmd,
                '+' . $expirationMinutes . ' minutes'
            );

            return (string) $request->getUri();

        } catch (Exception $e) {
            error_log("[R2Service] Error generating presigned URL: " . $e->getMessage());
            return $this->getPublicUrl($r2Path); // Fallback to public URL
        }
    }

    /**
     * List files for an order
     * 
     * @param string $orderNumber Order number
     * @return array List of files
     */
    public function listOrderFiles($orderNumber)
    {
        try {
            $year = date('Y');
            $month = date('m');
            $prefix = "{$year}/{$month}/{$orderNumber}/";

            $result = $this->s3Client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => $prefix
            ]);

            $files = [];
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $files[] = [
                        'key' => $object['Key'],
                        'size' => $object['Size'],
                        'last_modified' => $object['LastModified'],
                        'url' => $this->getPublicUrl($object['Key'])
                    ];
                }
            }

            return $files;

        } catch (AwsException $e) {
            error_log("[R2Service] Error listing files: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Test R2 connection
     * 
     * @return bool
     */
    public function testConnection()
    {
        try {
            $this->s3Client->headBucket([
                'Bucket' => $this->bucket
            ]);
            return true;
        } catch (Exception $e) {
            error_log("[R2Service] Connection test failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get storage stats for an order
     * 
     * @param string $orderNumber Order number
     * @return array ['count' => int, 'total_size' => int]
     */
    public function getOrderStats($orderNumber)
    {
        $files = $this->listOrderFiles($orderNumber);

        $totalSize = 0;
        foreach ($files as $file) {
            $totalSize += $file['size'];
        }

        return [
            'count' => count($files),
            'total_size' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2)
        ];
    }
}
?>
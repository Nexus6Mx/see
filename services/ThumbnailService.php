<?php
/**
 * ThumbnailService - Generate Thumbnails for Images and Videos
 * Supports GD library for images and FFmpeg for videos
 */

class ThumbnailService
{
    private $config;
    private $tempDir;
    private $ffmpegAvailable;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../api/config/r2_config.php';
        $appConfig = require __DIR__ . '/../api/config/app_config.php';

        $this->tempDir = $appConfig['files']['temp_dir'];
        $this->ffmpegAvailable = $this->checkFfmpeg();

        // Create temp directory if it doesn't exist
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * Generate thumbnail for image or video
     * 
     * @param string $sourcePath Local path to source file
     * @param string $mimeType MIME type of source
     * @return string|null Path to generated thumbnail or null on failure
     */
    public function generate($sourcePath, $mimeType)
    {
        if (!file_exists($sourcePath)) {
            error_log("[ThumbnailService] Source file not found: {$sourcePath}");
            return null;
        }

        if (strpos($mimeType, 'image') === 0) {
            return $this->generateImageThumbnail($sourcePath, $mimeType);
        } elseif (strpos($mimeType, 'video') === 0) {
            return $this->generateVideoThumbnail($sourcePath);
        }

        error_log("[ThumbnailService] Unsupported MIME type: {$mimeType}");
        return null;
    }

    /**
     * Generate thumbnail for image file
     * 
     * @param string $sourcePath Local path to image
     * @param string $mimeType MIME type
     * @return string|null Path to thumbnail
     */
    private function generateImageThumbnail($sourcePath, $mimeType)
    {
        try {
            // Create image resource based on MIME type
            switch ($mimeType) {
                case 'image/jpeg':
                case 'image/jpg':
                    $source = imagecreatefromjpeg($sourcePath);
                    break;
                case 'image/png':
                    $source = imagecreatefrompng($sourcePath);
                    break;
                case 'image/gif':
                    $source = imagecreatefromgif($sourcePath);
                    break;
                default:
                    error_log("[ThumbnailService] Unsupported image type: {$mimeType}");
                    return null;
            }

            if ($source === false) {
                error_log("[ThumbnailService] Failed to create image resource from: {$sourcePath}");
                return null;
            }

            // Get source dimensions
            $srcWidth = imagesx($source);
            $srcHeight = imagesy($source);

            // Calculate thumbnail dimensions while maintaining aspect ratio
            $thumbDimensions = $this->calculateDimensions($srcWidth, $srcHeight);
            $thumbWidth = $thumbDimensions['width'];
            $thumbHeight = $thumbDimensions['height'];

            // Create thumbnail
            $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);

            // Preserve transparency for PNG/GIF
            if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
                $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
                imagefilledrectangle($thumbnail, 0, 0, $thumbWidth, $thumbHeight, $transparent);
            }

            // Resample image
            imagecopyresampled(
                $thumbnail,
                $source,
                0,
                0,
                0,
                0,
                $thumbWidth,
                $thumbHeight,
                $srcWidth,
                $srcHeight
            );

            // Generate output path
            $outputPath = $this->tempDir . '/' . uniqid('thumb_') . '.jpg';

            // Save thumbnail
            imagejpeg($thumbnail, $outputPath, $this->config['thumbnail']['quality']);

            // Free memory
            imagedestroy($source);
            imagedestroy($thumbnail);

            error_log("[ThumbnailService] Generated image thumbnail: {$outputPath}");
            return $outputPath;

        } catch (Exception $e) {
            error_log("[ThumbnailService] Error generating image thumbnail: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate thumbnail for video file
     * 
     * @param string $sourcePath Local path to video
     * @return string|null Path to thumbnail
     */
    private function generateVideoThumbnail($sourcePath)
    {
        if (!$this->ffmpegAvailable) {
            error_log("[ThumbnailService] FFmpeg not available, using placeholder for video");
            return $this->generatePlaceholder('video');
        }

        try {
            $outputPath = $this->tempDir . '/' . uniqid('thumb_video_') . '.jpg';
            $width = $this->config['thumbnail']['width'];
            $height = $this->config['thumbnail']['height'];

            // Extract frame at 1 second
            $command = sprintf(
                'ffmpeg -i %s -ss 00:00:01 -vframes 1 -vf "scale=%d:%d:force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2" %s 2>&1',
                escapeshellarg($sourcePath),
                $width,
                $height,
                $width,
                $height,
                escapeshellarg($outputPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($outputPath)) {
                error_log("[ThumbnailService] Generated video thumbnail: {$outputPath}");
                return $outputPath;
            } else {
                error_log("[ThumbnailService] FFmpeg failed with code {$returnCode}: " . implode("\n", $output));
                return $this->generatePlaceholder('video');
            }

        } catch (Exception $e) {
            error_log("[ThumbnailService] Error generating video thumbnail: " . $e->getMessage());
            return $this->generatePlaceholder('video');
        }
    }

    /**
     * Generate placeholder thumbnail
     * 
     * @param string $type 'image' or 'video'
     * @return string|null Path to placeholder
     */
    private function generatePlaceholder($type = 'video')
    {
        try {
            $width = $this->config['thumbnail']['width'];
            $height = $this->config['thumbnail']['height'];

            $placeholder = imagecreatetruecolor($width, $height);

            // Background color (dark gray)
            $bgColor = imagecolorallocate($placeholder, 51, 51, 51);
            imagefilledrectangle($placeholder, 0, 0, $width, $height, $bgColor);

            // Icon color (lighter gray)
            $iconColor = imagecolorallocate($placeholder, 170, 170, 170);

            if ($type === 'video') {
                // Draw play button
                $centerX = $width / 2;
                $centerY = $height / 2;
                $triangleSize = 40;

                $points = [
                    $centerX - $triangleSize / 2,
                    $centerY - $triangleSize / 2,
                    $centerX + $triangleSize / 2,
                    $centerY,
                    $centerX - $triangleSize / 2,
                    $centerY + $triangleSize / 2
                ];

                imagefilledpolygon($placeholder, $points, 3, $iconColor);
            } else {
                // Draw image icon (simple rectangle)
                $iconX = $width / 4;
                $iconY = $height / 4;
                $iconW = $width / 2;
                $iconH = $height / 2;

                imagerectangle($placeholder, $iconX, $iconY, $iconX + $iconW, $iconY + $iconH, $iconColor);
            }

            // Save placeholder
            $outputPath = $this->tempDir . '/' . uniqid('placeholder_') . '.jpg';
            imagejpeg($placeholder, $outputPath, 80);
            imagedestroy($placeholder);

            error_log("[ThumbnailService] Generated placeholder thumbnail: {$outputPath}");
            return $outputPath;

        } catch (Exception $e) {
            error_log("[ThumbnailService] Error generating placeholder: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Calculate thumbnail dimensions maintaining aspect ratio
     * 
     * @param int $srcWidth Source width
     * @param int $srcHeight Source height
     * @return array ['width' => int, 'height' => int]
     */
    private function calculateDimensions($srcWidth, $srcHeight)
    {
        $maxWidth = $this->config['thumbnail']['width'];
        $maxHeight = $this->config['thumbnail']['height'];

        $ratio = min($maxWidth / $srcWidth, $maxHeight / $srcHeight);

        return [
            'width' => (int) ($srcWidth * $ratio),
            'height' => (int) ($srcHeight * $ratio)
        ];
    }

    /**
     * Check if FFmpeg is available
     * 
     * @return bool
     */
    private function checkFfmpeg()
    {
        exec('ffmpeg -version 2>&1', $output, $returnCode);
        $available = ($returnCode === 0 || $returnCode === 1);

        if ($available) {
            error_log("[ThumbnailService] FFmpeg is available");
        } else {
            error_log("[ThumbnailService] FFmpeg is NOT available - will use placeholders for videos");
        }

        return $available;
    }

    /**
     * Clean up old temporary files
     * 
     * @param int $olderThanHours Delete files older than X hours
     * @return int Number of files deleted
     */
    public function cleanupTempFiles($olderThanHours = 24)
    {
        if (!is_dir($this->tempDir)) {
            return 0;
        }

        $deleted = 0;
        $cutoffTime = time() - ($olderThanHours * 3600);

        $files = glob($this->tempDir . '/*');
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }

        if ($deleted > 0) {
            error_log("[ThumbnailService] Cleaned up {$deleted} temporary files");
        }

        return $deleted;
    }

    /**
     * Get temp directory path
     * 
     * @return string
     */
    public function getTempDir()
    {
        return $this->tempDir;
    }
}
?>
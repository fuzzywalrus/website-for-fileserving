<?php
// Immediate session start for authentication
session_start();

// Include configuration file
require_once 'config.php';

// Include functions file
require_once 'functions.php';

/**
 * HLS Stream Handler
 * Handles transcoding and serving of HLS video streams
 */

// Check if streaming is enabled
if (!$streamingEnabled) {
    header('HTTP/1.1 503 Service Unavailable');
    header('Content-Type: text/plain');
    exit('Video streaming is not enabled');
}

// Authentication check
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain');
    exit('Authentication required');
}

// Honor secondary user scope
if (!empty($_SESSION['is_secondary_user']) && $_SESSION['is_secondary_user'] === true) {
    $baseDir = $secondaryBaseDir;
}

// Get request type (playlist or segment)
$requestType = $_GET['type'] ?? 'playlist';
$validTypes = ['playlist', 'segment', 'direct', 'status'];

if (!in_array($requestType, $validTypes)) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid request type');
}

// Get and decrypt file ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('File ID not specified');
}

$encryptedId = validateEncryptedId($_GET['id']);
if ($encryptedId === false) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid file ID format');
}

$filePath = decryptPath($encryptedId);
if ($filePath === false) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid or expired file ID');
}

// Validate and get full file path
$filePath = validateFilePath($filePath);
if ($filePath === false) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid file path');
}

$fullPath = $baseDir . '/' . ltrim($filePath, '/');
$realFilePath = realpath($fullPath);
$realBasePath = realpath($baseDir);

if (!$realFilePath || strpos($realFilePath, $realBasePath) !== 0 || !is_file($realFilePath)) {
    header('HTTP/1.1 404 Not Found');
    exit('File not found');
}

// Verify it's a video file
if (!isVideoFile($realFilePath)) {
    header('HTTP/1.1 400 Bad Request');
    exit('Not a video file');
}

// Get file extension
$extension = strtolower(pathinfo($realFilePath, PATHINFO_EXTENSION));

// For browser-native formats (MP4/WebM), serve directly
if ($requestType === 'direct' || isBrowserNativeFormat($extension)) {
    // Redirect to file-handler for direct playback
    header('Location: file-handler.php?id=' . urlencode($encryptedId));
    exit;
}

// Get cache path for this file (use real file path for consistent cache key)
$cachePath = getHLSCachePath($realFilePath);
$playlistFile = $cachePath . '/playlist.m3u8';
$metadataFile = $cachePath . '/metadata.json';

// Randomly trigger cache cleanup (1 in 20 requests)
if (rand(1, 20) === 1) {
    cleanupExpiredHLSCache();
}

// Handle segment requests
if ($requestType === 'segment') {
    $segmentName = $_GET['seg'] ?? '';
    
    // Validate segment name (only allow alphanumeric, dash, underscore, and .ts extension)
    if (!preg_match('/^segment_\d{3}\.ts$/', $segmentName)) {
        header('HTTP/1.1 400 Bad Request');
        exit('Invalid segment name');
    }
    
    $segmentFile = $cachePath . '/' . $segmentName;
    
    if (!file_exists($segmentFile)) {
        header('HTTP/1.1 404 Not Found');
        exit('Segment not found');
    }
    
    // Update last access time
    updateHLSCacheAccess($cachePath);
    
    // Serve segment file
    header('Content-Type: video/mp2t');
    header('Content-Length: ' . filesize($segmentFile));
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=31536000'); // Segments are immutable
    
    readfile($segmentFile);
    exit;
}

// Handle status check requests
if ($requestType === 'status') {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache');
    
    $status = [
        'ready' => file_exists($playlistFile),
        'transcoding' => file_exists($cachePath . '/transcode.pid'),
        'progress' => 0
    ];
    
    // Count available segments
    if (is_dir($cachePath)) {
        $segments = glob($cachePath . '/segment_*.ts');
        $status['segments'] = count($segments ?: []);
    } else {
        $status['segments'] = 0;
    }
    
    echo json_encode($status);
    exit;
}

// Handle playlist requests
if ($requestType === 'playlist') {
    // Check if cache exists and is valid
    $needsTranscode = true;
    $transcodeInProgress = false;
    
    if (file_exists($playlistFile) && file_exists($metadataFile)) {
        $metadata = getHLSCacheMetadata($cachePath);
        
        if ($metadata !== false) {
            // Cache exists and is valid
            $needsTranscode = false;
            
            // Update last access time
            updateHLSCacheAccess($cachePath);
        }
    }
    
    // Check if transcoding is already in progress (check for PID file and running process)
    $pidFile = $cachePath . '/transcode.pid';
    if (file_exists($pidFile)) {
        $pid = trim(file_get_contents($pidFile));
        if (posix_kill((int)$pid, 0)) {
            // Process is still running
            $transcodeInProgress = true;
            $needsTranscode = false;
        } else {
            // Process died, clean up
            @unlink($pidFile);
        }
    }
    
    // Transcode if needed
    if ($needsTranscode) {
        // Create cache directory with absolute path
        if (!is_dir($cachePath)) {
            if (!@mkdir($cachePath, 0755, true)) {
                error_log("Failed to create cache directory: {$cachePath}");
                header('HTTP/1.1 500 Internal Server Error');
                exit('Failed to create cache directory');
            }
        }
        
        // Ensure cache path is absolute
        $cachePath = realpath($cachePath);
        $playlistFile = $cachePath . '/playlist.m3u8';
        $metadataFile = $cachePath . '/metadata.json';
        $pidFile = $cachePath . '/transcode.pid';
        $logFile = $cachePath . '/transcode.log';
        
        // Detect hardware acceleration if set to auto
        // For now, use software encoding for reliability
        $hwAccelType = $hwAccel;
        if ($hwAccel === 'auto') {
            // Use software encoding for better compatibility
            $hwAccelType = 'none';
            error_log("Using software encoding for compatibility");
        }

        // Write cache metadata early so cleanup does not remove an active transcode
        $metadata = [
            'created' => time(),
            'last_access' => time(),
            'source_path' => $realFilePath,
            'source_mtime' => @filemtime($realFilePath),
            'source_size' => @filesize($realFilePath),
            'quality' => $streamingQuality,
            'hwaccel' => $hwAccelType
        ];
        if (@file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT)) === false) {
            error_log("Failed to write cache metadata: {$metadataFile}");
        }
        
        // Build FFmpeg command
        $ffmpegCmd = buildFFmpegCommand($realFilePath, $cachePath, $hwAccelType, $streamingQuality);
        
        error_log("Starting transcode for: {$realFilePath}");
        error_log("Cache path: {$cachePath}");
        error_log("FFmpeg command: {$ffmpegCmd}");
        
        // Start transcoding in background using shell
        $bgCmd = "nohup {$ffmpegCmd} > " . escapeshellarg($logFile) . " 2>&1 & echo \$!";
        $pid = trim(shell_exec($bgCmd));
        
        if (!empty($pid) && is_numeric($pid)) {
            // Save PID to file
            file_put_contents($pidFile, $pid);
            error_log("FFmpeg started with PID: {$pid}");
            
            // Wait a moment for FFmpeg to start
            sleep(2);
            
            $transcodeInProgress = true;
        } else {
            error_log("Failed to start FFmpeg. Output: {$pid}");
            header('HTTP/1.1 500 Internal Server Error');
            exit('Failed to start transcoding');
        }
    }
    
    // Check again if playlist exists after starting transcode
    if (file_exists($playlistFile)) {
        // Read and modify playlist to use our stream handler for segments
        $playlist = file_get_contents($playlistFile);
        
        // Replace segment filenames with our handler URLs
        $playlist = preg_replace_callback(
            '/(segment_\d{3}\.ts)/',
            function($matches) use ($encryptedId) {
                $segmentName = $matches[1];
                return 'stream-handler.php?type=segment&id=' . urlencode($encryptedId) . '&seg=' . urlencode($segmentName);
            },
            $playlist
        );
        
        // Serve the modified playlist
        header('Content-Type: application/vnd.apple.mpegurl');
        header('Content-Length: ' . strlen($playlist));
        header('Cache-Control: no-cache');
        header('Access-Control-Allow-Origin: *');
        
        echo $playlist;
        exit;
    }
    
    // If transcoding is in progress but no playlist yet, return a "please wait" status
    if ($transcodeInProgress) {
        header('HTTP/1.1 202 Accepted');
        header('Content-Type: application/json');
        header('Cache-Control: no-cache');
        header('Retry-After: 3');
        
        $segments = [];
        if (is_dir($cachePath)) {
            $segments = glob($cachePath . '/segment_*.ts') ?: [];
        }
        
        echo json_encode([
            'status' => 'transcoding',
            'message' => 'Video is being prepared for streaming. Please wait...',
            'segments_ready' => count($segments),
            'retry_after' => 3
        ]);
        exit;
    }
    
    header('HTTP/1.1 500 Internal Server Error');
    exit('Failed to start transcoding');
}

// Should not reach here
header('HTTP/1.1 400 Bad Request');
exit('Invalid request');

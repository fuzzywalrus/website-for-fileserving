<?php
// Immediate session start for authentication
session_start();

// Include configuration file
require_once 'config.php';

// Include functions file
require_once 'functions.php';

// Authentication check
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: index.php');
    exit;
}

// Check if streaming is enabled
if (!$streamingEnabled) {
    header('HTTP/1.1 503 Service Unavailable');
    exit('Video streaming is not enabled');
}

// Honor secondary user scope
if (!empty($_SESSION['is_secondary_user']) && $_SESSION['is_secondary_user'] === true) {
    $baseDir = $secondaryBaseDir;
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

// Get file info
$fileName = basename($realFilePath);
$extension = strtolower(pathinfo($realFilePath, PATHINFO_EXTENSION));

// Determine if we should use HLS or direct playback
$useHLS = isStreamableVideo($realFilePath);
$useDirect = isBrowserNativeFormat($extension);

// Build video source URL
if ($useHLS) {
    $videoUrl = 'stream-handler.php?type=playlist&id=' . urlencode($encryptedId);
    $videoType = 'application/x-mpegURL';
} elseif ($useDirect) {
    $videoUrl = 'file-handler.php?id=' . urlencode($encryptedId);
    $videoType = 'video/' . ($extension === 'webm' ? 'webm' : 'mp4');
} else {
    // Fallback to HLS
    $videoUrl = 'stream-handler.php?type=playlist&id=' . urlencode($encryptedId);
    $videoType = 'application/x-mpegURL';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($fileName) ?> - <?= htmlspecialchars($siteTitle) ?></title>
    
    <!-- Video.js CSS -->
    <link href="https://vjs.zencdn.net/8.6.1/video-js.css" rel="stylesheet" />
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background-color: #000;
            color: #fff;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        
        .video-container {
            position: relative;
            width: 100vw;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .video-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.8), rgba(0,0,0,0));
            padding: 15px 20px;
            z-index: 1000;
            transition: opacity 0.3s;
        }
        
        .video-header.hidden {
            opacity: 0;
        }
        
        .video-header h4 {
            margin: 0;
            font-size: 18px;
            color: #fff;
        }
        
        .video-header .btn {
            margin-left: 10px;
        }
        
        #video-player {
            width: 100%;
            max-width: 100%;
            height: auto;
        }
        
        .video-js {
            width: 100% !important;
            height: 100vh !important;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.9);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .loading-overlay.hidden {
            display: none;
        }
        
        .spinner {
            width: 60px;
            height: 60px;
            border: 5px solid rgba(255, 255, 255, 0.1);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loading-text {
            margin-top: 20px;
            font-size: 16px;
            color: #fff;
        }
        
        .error-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.95);
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            padding: 20px;
            text-align: center;
        }
        
        .error-overlay.show {
            display: flex;
        }
        
        .error-icon {
            font-size: 60px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        
        .error-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .error-message {
            font-size: 16px;
            color: #ccc;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="spinner"></div>
        <div class="loading-text" id="loading-text">
            <?= $useHLS ? 'Preparing video stream...' : 'Loading video...' ?>
        </div>
        <div class="loading-status" id="loading-status" style="margin-top: 15px; font-size: 14px; color: #888;"></div>
    </div>
    
    <!-- Error Overlay -->
    <div class="error-overlay" id="error-overlay">
        <div class="error-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="error-title">Video Playback Error</div>
        <div class="error-message" id="error-message">
            An error occurred while loading the video.
        </div>
        <button class="btn btn-primary" onclick="location.reload()">
            <i class="fas fa-redo"></i> Retry
        </button>
        <button class="btn btn-secondary" onclick="window.close()">
            <i class="fas fa-times"></i> Close
        </button>
    </div>
    
    <!-- Video Header -->
    <div class="video-header d-flex justify-content-between align-items-center" id="video-header">
        <h4>
            <i class="fas fa-film me-2"></i>
            <?= htmlspecialchars($fileName) ?>
        </h4>
        <div>
            <a href="file-handler.php?id=<?= urlencode($encryptedId) ?>&download=1" 
               class="btn btn-sm btn-outline-light" 
               title="Download">
                <i class="fas fa-download"></i> Download
            </a>
            <a href="index.php" class="btn btn-sm btn-outline-light" title="Back to Files">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
    
    <!-- Video Container -->
    <div class="video-container">
        <video
            id="video-player"
            class="video-js vjs-big-play-centered"
            controls
            preload="auto">
            <p class="vjs-no-js">
                To view this video please enable JavaScript, and consider upgrading to a web browser that
                <a href="https://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a>
            </p>
        </video>
    </div>
    
    <!-- Video.js -->
    <script src="https://vjs.zencdn.net/8.6.1/video.min.js"></script>
    
    <!-- hls.js for browsers without native HLS support -->
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.4.12/dist/hls.min.js"></script>
    
    <!-- Player initialization -->
    <script>
        (function() {
            const loadingOverlay = document.getElementById('loading-overlay');
            const loadingText = document.getElementById('loading-text');
            const loadingStatus = document.getElementById('loading-status');
            const errorOverlay = document.getElementById('error-overlay');
            const errorMessage = document.getElementById('error-message');
            const videoHeader = document.getElementById('video-header');
            
            const useHLS = <?= $useHLS ? 'true' : 'false' ?>;
            const videoUrl = <?= json_encode($videoUrl) ?>;
            const statusUrl = <?= json_encode(str_replace('type=playlist', 'type=status', $videoUrl)) ?>;
            
            let player;
            let hls;
            let hideHeaderTimeout;
            let pollInterval;
            
            // Hide header after inactivity
            function resetHeaderTimeout() {
                videoHeader.classList.remove('hidden');
                clearTimeout(hideHeaderTimeout);
                hideHeaderTimeout = setTimeout(() => {
                    if (player && !player.paused()) {
                        videoHeader.classList.add('hidden');
                    }
                }, 3000);
            }
            
            // Mouse movement shows header
            document.addEventListener('mousemove', resetHeaderTimeout);
            document.addEventListener('touchstart', resetHeaderTimeout);
            
            // Show error
            function showError(message) {
                console.error('Video error:', message);
                loadingOverlay.classList.add('hidden');
                errorMessage.textContent = message;
                errorOverlay.classList.add('show');
            }
            
            // Hide loading overlay
            function hideLoading() {
                loadingOverlay.classList.add('hidden');
                if (pollInterval) {
                    clearInterval(pollInterval);
                    pollInterval = null;
                }
            }
            
            // Update loading status
            function updateLoadingStatus(message, segments) {
                loadingText.textContent = 'Transcoding video...';
                if (segments > 0) {
                    loadingStatus.textContent = `${segments} segments ready. Video will start soon...`;
                } else {
                    loadingStatus.textContent = message || 'Please wait, this may take a few minutes...';
                }
            }
            
            // Initialize Video.js player (without data-setup to avoid double init)
            player = videojs('video-player', {
                fluid: true,
                responsive: true,
                controls: true,
                preload: 'auto',
                html5: {
                    vhs: {
                        overrideNative: true
                    },
                    nativeAudioTracks: false,
                    nativeVideoTracks: false
                }
            });
            
            // Get the actual HTML video element from Video.js
            const videoEl = player.tech({ IWillNotUseThisInPlugins: true }).el();
            
            // Function to start video playback once ready
            function startPlayback() {
                console.log('Starting video playback');
                
                // Check if browser has native HLS support (Safari)
                const hasNativeHLS = videoEl.canPlayType('application/vnd.apple.mpegurl') !== '';
                
                if (hasNativeHLS) {
                    console.log('Using native HLS support');
                    player.src({
                        src: videoUrl,
                        type: 'application/vnd.apple.mpegurl'
                    });
                } 
                // Use hls.js for browsers without native HLS support (Firefox, Chrome)
                else if (typeof Hls !== 'undefined' && Hls.isSupported()) {
                    console.log('Using hls.js for HLS playback');
                    
                    hls = new Hls({
                        debug: false,
                        enableWorker: true,
                        lowLatencyMode: false,
                        backBufferLength: 90,
                        maxBufferLength: 30,
                        maxMaxBufferLength: 60
                    });
                    
                    hls.loadSource(videoUrl);
                    hls.attachMedia(videoEl);
                    
                    hls.on(Hls.Events.MANIFEST_PARSED, function() {
                        console.log('HLS manifest parsed');
                        hideLoading();
                    });
                    
                    hls.on(Hls.Events.ERROR, function(event, data) {
                        console.error('HLS.js error:', data);
                        
                        if (data.fatal) {
                            switch(data.type) {
                                case Hls.ErrorTypes.NETWORK_ERROR:
                                    console.log('Network error, trying to recover...');
                                    hls.startLoad();
                                    break;
                                case Hls.ErrorTypes.MEDIA_ERROR:
                                    console.log('Media error, trying to recover...');
                                    hls.recoverMediaError();
                                    break;
                                default:
                                    showError('Fatal error: Unable to play video.');
                                    hls.destroy();
                                    break;
                            }
                        }
                    });
                } else {
                    showError('Your browser does not support HLS video streaming. Please use a modern browser like Chrome, Firefox, Safari, or Edge.');
                }
            }
            
            // Function to check transcoding status and start playback when ready
            function checkStatus() {
                fetch(statusUrl)
                    .then(response => response.json())
                    .then(data => {
                        console.log('Status:', data);
                        
                        if (data.ready) {
                            // Playlist is ready, start playback
                            if (pollInterval) {
                                clearInterval(pollInterval);
                                pollInterval = null;
                            }
                            startPlayback();
                        } else if (data.transcoding) {
                            // Still transcoding
                            updateLoadingStatus(data.message, data.segments || 0);
                            
                            // If we have some segments, try to start playback
                            if (data.segments >= 2) {
                                console.log('Some segments ready, trying to start playback...');
                                if (pollInterval) {
                                    clearInterval(pollInterval);
                                    pollInterval = null;
                                }
                                startPlayback();
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Status check error:', error);
                    });
            }
            
            // If using HLS
            if (useHLS) {
                // First, check the status
                fetch(videoUrl)
                    .then(response => {
                        if (response.status === 202) {
                            // Transcoding in progress
                            return response.json().then(data => {
                                console.log('Transcoding in progress:', data);
                                updateLoadingStatus(data.message, data.segments_ready || 0);
                                
                                // Start polling for status
                                pollInterval = setInterval(checkStatus, 3000);
                            });
                        } else if (response.ok) {
                            // Playlist is ready, start playback
                            startPlayback();
                        } else {
                            throw new Error('Failed to load video: ' + response.status);
                        }
                    })
                    .catch(error => {
                        console.error('Initial load error:', error);
                        // Try starting playback anyway
                        startPlayback();
                    });
            } else {
                // Direct playback for MP4/WebM
                console.log('Using direct playback');
                player.src({
                    src: videoUrl,
                    type: <?= json_encode($videoType) ?>
                });
            }
            
            // Player event handlers
            player.on('loadstart', function() {
                console.log('Video loading started');
            });
            
            player.on('loadeddata', function() {
                console.log('Video data loaded');
                hideLoading();
            });
            
            player.on('canplay', function() {
                console.log('Video can play');
                hideLoading();
            });
            
            player.on('playing', function() {
                console.log('Video playing');
                hideLoading();
                resetHeaderTimeout();
            });
            
            player.on('error', function() {
                const err = player.error();
                console.error('Player error:', err);
                
                // Don't show error if HLS.js is handling it
                if (useHLS && hls) {
                    return;
                }
                
                let message = 'An error occurred while playing the video.';
                if (err) {
                    switch(err.code) {
                        case 1:
                            message = 'Video loading was aborted.';
                            break;
                        case 2:
                            message = 'Network error: Unable to load video.';
                            break;
                        case 3:
                            message = 'Video decoding failed. The video may be corrupted.';
                            break;
                        case 4:
                            message = 'Video format not supported. The video may still be transcoding.';
                            break;
                    }
                }
                
                showError(message);
            });
            
            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (!player) return;
                
                switch(e.key) {
                    case ' ':
                    case 'k':
                        e.preventDefault();
                        if (player.paused()) {
                            player.play();
                        } else {
                            player.pause();
                        }
                        break;
                    case 'f':
                        e.preventDefault();
                        if (player.isFullscreen()) {
                            player.exitFullscreen();
                        } else {
                            player.requestFullscreen();
                        }
                        break;
                    case 'ArrowLeft':
                        e.preventDefault();
                        player.currentTime(Math.max(0, player.currentTime() - 10));
                        break;
                    case 'ArrowRight':
                        e.preventDefault();
                        player.currentTime(Math.min(player.duration(), player.currentTime() + 10));
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        player.volume(Math.min(1, player.volume() + 0.1));
                        break;
                    case 'ArrowDown':
                        e.preventDefault();
                        player.volume(Math.max(0, player.volume() - 0.1));
                        break;
                    case 'm':
                        e.preventDefault();
                        player.muted(!player.muted());
                        break;
                }
            });
            
            // Initial timeout
            resetHeaderTimeout();
        })();
    </script>
</body>
</html>

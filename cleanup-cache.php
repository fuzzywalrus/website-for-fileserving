#!/usr/bin/env php
<?php
/**
 * HLS Cache Cleanup Script
 * 
 * This script removes HLS cache entries that have not been accessed
 * within the configured TTL window.
 * 
 * Usage:
 *   php cleanup-cache.php [--verbose] [--dry-run]
 * 
 * Options:
 *   --verbose    Show detailed output
 *   --dry-run    Show what would be deleted without actually deleting
 * 
 * Cron example (run daily at 3 AM):
 *   0 3 * * * cd /path/to/website && php cleanup-cache.php >> /var/log/hls-cache-cleanup.log 2>&1
 */

// Parse command line arguments
$verbose = in_array('--verbose', $argv);
$dryRun = in_array('--dry-run', $argv);

// Include configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Check if streaming is enabled
if (!$streamingEnabled) {
    echo "Video streaming is not enabled. Exiting.\n";
    exit(0);
}

// Start timing
$startTime = microtime(true);
$startMemory = memory_get_usage();

echo "========================================\n";
echo "HLS Cache Cleanup\n";
echo "========================================\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n";
echo "Cache directory: {$streamingCacheDir}\n";
echo "Cache TTL: " . formatDuration($streamingCacheTTL) . "\n";

if ($dryRun) {
    echo "Mode: DRY RUN (no files will be deleted)\n";
}

echo "\n";

// Check if cache directory exists
if (!is_dir($streamingCacheDir)) {
    echo "Cache directory does not exist. Nothing to clean.\n";
    exit(0);
}

// Run cleanup
echo "Scanning cache directory...\n";
$stats = cleanupExpiredHLSCache();

// Display results
echo "\n";
echo "========================================\n";
echo "Cleanup Results\n";
echo "========================================\n";
echo "Directories cleaned: {$stats['directories_cleaned']}\n";
echo "Space freed: " . formatFileSize($stats['space_freed']) . "\n";

// Calculate remaining cache size
$remainingSize = getDirSize($streamingCacheDir);
echo "Remaining cache size: " . formatFileSize($remainingSize) . "\n";

// Performance stats
$duration = microtime(true) - $startTime;
$memoryUsed = memory_get_usage() - $startMemory;

echo "\n";
echo "Execution time: " . round($duration, 2) . " seconds\n";
echo "Memory used: " . formatFileSize($memoryUsed) . "\n";

echo "\n";
echo "Completed: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n";

// Verbose mode: Show remaining cache entries
if ($verbose && $stats['directories_cleaned'] > 0) {
    echo "\n";
    echo "Remaining cache entries:\n";
    echo "========================================\n";
    
    $subdirs = glob($streamingCacheDir . '/*', GLOB_ONLYDIR);
    $totalEntries = 0;
    
    foreach ($subdirs as $subdir) {
        $cacheDirs = glob($subdir . '/*', GLOB_ONLYDIR);
        
        foreach ($cacheDirs as $cacheDir) {
            $metadataFile = $cacheDir . '/metadata.json';
            
            if (file_exists($metadataFile)) {
                $metadata = json_decode(file_get_contents($metadataFile), true);
                
                if ($metadata !== null) {
                    $lastAccess = $metadata['last_access'] ?? $metadata['created'] ?? 0;
                    $age = time() - $lastAccess;
                    $filename = $metadata['original_filename'] ?? 'Unknown';
                    $quality = $metadata['quality'] ?? 'Unknown';
                    $size = formatFileSize($metadata['size_bytes'] ?? 0);
                    
                    echo sprintf(
                        "  - %s (%s, %s) - Last accessed: %s ago\n",
                        $filename,
                        $quality,
                        $size,
                        formatDuration($age)
                    );
                    
                    $totalEntries++;
                }
            }
        }
    }
    
    if ($totalEntries === 0) {
        echo "  (none)\n";
    }
    
    echo "========================================\n";
}

exit(0);

/**
 * Format duration in seconds to human-readable string
 * 
 * @param int $seconds Duration in seconds
 * @return string Formatted duration
 */
function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . ' seconds';
    } elseif ($seconds < 3600) {
        $minutes = round($seconds / 60);
        return $minutes . ' minute' . ($minutes != 1 ? 's' : '');
    } elseif ($seconds < 86400) {
        $hours = round($seconds / 3600);
        return $hours . ' hour' . ($hours != 1 ? 's' : '');
    } else {
        $days = round($seconds / 86400);
        return $days . ' day' . ($days != 1 ? 's' : '');
    }
}

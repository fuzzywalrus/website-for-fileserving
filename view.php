<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteTitle) ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><?= htmlspecialchars($siteTitle) ?></h2>
                
                <div class="d-flex align-items-center gap-3">
                    <!-- Search Form -->
                    <form method="get" class="d-flex">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search files..." 
                               value="<?= htmlspecialchars($searchTerm ?? '') ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                    </form>
                    
                    <!-- Logout Button -->
                    <a href="?logout=1" class="btn btn-outline-danger btn-sm" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($isSearchRequest): ?>
                    <!-- Search Results Header -->
                    <div class="alert alert-info">
                        <i class="fas fa-search me-2"></i> 
                        Search results for: <strong><?= htmlspecialchars($searchTerm) ?></strong> 
                        (<?= count($searchResults) ?> <?= count($searchResults) == 1 ? 'result' : 'results' ?> found)
                    </div>
                <?php else: ?>
                    <!-- Breadcrumb Navigation for Normal Browsing -->
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                                <li class="breadcrumb-item<?= ($index === count($breadcrumbs) - 1) ? ' active' : '' ?>">
                                    <?php if ($index === count($breadcrumbs) - 1): ?>
                                        <?= htmlspecialchars($crumb['name']) ?>
                                    <?php else: ?>
                                        <a href="?path=<?= urlencode(str_replace($baseDir . '/', '', $crumb['path'])) ?>">
                                            <?= htmlspecialchars($crumb['name']) ?>
                                        </a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </nav>
                    
                    <!-- Parent Directory link for Normal Browsing -->
                    <?php if ($currentDir !== $baseDir): ?>
                        <a href="?path=<?= urlencode(str_replace($baseDir . '/', '', $parentDir)) ?>" class="btn btn-sm btn-outline-secondary mb-3">
                            <i class="fas fa-arrow-left"></i> Back to parent directory
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Directory contents table -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table">
                            <tr>
                                <th>Name</th>
                                <?php if ($isSearchRequest): ?>
                                    <th>Location</th>
                                <?php endif; ?>
                                <th>Size</th>
                                <th class="modified-column">Last Modified</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($dirContents) === 0): ?>
                                <tr>
                                    <td colspan="<?= $isSearchRequest ? '5' : '4' ?>" class="text-center py-3">
                                        <div class="alert alert-info mb-0">
                                            <?= $isSearchRequest ? 'No search results found' : 'Directory is empty' ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            
                            <?php foreach ($dirContents as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($item['is_dir']): ?>
                                                <i class="fas fa-folder directory-icon file-icon fa-lg"></i>
                                            <?php elseif ($item['type'] === 'video'): ?>
                                                <i class="fas fa-film video-icon file-icon fa-lg"></i>
                                            <?php elseif ($item['type'] === 'audio'): ?>
                                                <i class="fas fa-music audio-icon file-icon fa-lg"></i>
                                            <?php elseif ($item['type'] === 'image'): ?>
                                                <i class="fas fa-image image-icon file-icon fa-lg"></i>
                                            <?php elseif ($item['type'] === 'document'): ?>
                                                <i class="fas fa-file-alt document-icon file-icon fa-lg"></i>
                                            <?php elseif ($item['type'] === 'archive'): ?>
                                                <i class="fas fa-archive archive-icon file-icon fa-lg"></i>
                                            <?php elseif ($item['type'] === 'diskimage'): ?>
                                                <i class="fas fa-compact-disc diskimage-icon file-icon fa-lg"></i>
                                            <?php elseif ($item['type'] === 'rom'): ?>
                                                <i class="fas fa-gamepad rom-icon file-icon fa-lg"></i>
                                            <?php else: ?>
                                                <i class="fas fa-file file-icon fa-lg"></i>
                                            <?php endif; ?>
                                            
                                            <?php if ($isSearchRequest): ?>
                                                <?php 
                                                // Highlight search term in filename for search results
                                                $displayName = htmlspecialchars($item['name']);
                                                if (!empty($searchTerm)) {
                                                    $pattern = '/' . preg_quote($searchTerm, '/') . '/i';
                                                    $displayName = preg_replace($pattern, '<span class="search-highlight">$0</span>', $displayName);
                                                }
                                                ?>
                                                
                                                <?php if ($item['is_dir']): ?>
                                                    <a href="?path=<?= urlencode(str_replace($baseDir . '/', '', $item['path'])) ?>" class="ms-2 text-decoration-none">
                                                        <?= $displayName ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="ms-2"><?= $displayName ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if ($item['is_dir']): ?>
                                                    <a href="?path=<?= urlencode(str_replace($baseDir . '/', '', $item['path'])) ?>" class="ms-2 text-decoration-none">
                                                        <?= htmlspecialchars($item['name']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="ms-2"><?= htmlspecialchars($item['name']) ?></span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <?php if ($isSearchRequest): ?>
                                        <td>
                                            <?php
                                            // Display the directory path for search results
                                            $dirPath = dirname($item['relative_path']);
                                            echo $dirPath === '.' ? '/' : '/' . htmlspecialchars($dirPath);
                                            ?>
                                        </td>
                                    <?php endif; ?>
                                    
                                    <td><?= $item['size'] ?></td>
<td class="modified-column"><?= $item['modified'] ?></td>
<td class="action-buttons">
    <?php if ($item['is_dir']): ?>
        <a href="?path=<?= urlencode(str_replace($baseDir . '/', '', $item['path'])) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-folder-open"></i> <span class="d-none d-sm-inline">Open</span>
        </a>
    <?php else: ?>
        <?php 
            // Get relative path for this file
            $relativePath = str_replace($baseDir . '/', '', $item['path']);
            // Create file ID
            $fileId = encryptPath($relativePath);
        ?>
        <a href="file-handler.php?id=<?= $fileId ?>&download=1" class="btn btn-sm btn-outline-primary" target="_blank">
           <i class="fas fa-download"></i> <span class="d-none d-sm-inline">Download</span>
        </a>
        
        <?php if (isPlayable($item['name'])): ?>
            <a href="file-handler.php?id=<?= $fileId ?>" target="_blank" class="btn btn-sm btn-outline-success">
                <i class="fas fa-play"></i> <span class="d-none d-sm-inline">View</span>
            </a>
        <?php endif; ?>
    <?php endif; ?>
</td>
                             
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-muted text-center">
                <?= htmlspecialchars($siteStats) ?>
                <?php if (isset($_SESSION['extended_session']) && $_SESSION['extended_session']): ?>
                    <div class="small mt-1">
                        <i class="fas fa-clock me-1"></i>Extended session active (30 days)
                    </div>
                <?php else: ?>
                    <div class="small mt-1">
                        <i class="fas fa-timer me-1"></i>Session expires in 24 hours
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
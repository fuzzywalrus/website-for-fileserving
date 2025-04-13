<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bourbonic Directory Browser</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
            padding-bottom: 20px;
        }
        .directory-icon { color: #ffc107; }
        .video-icon { color: #dc3545; }
        .audio-icon { color: #28a745; }
        .image-icon { color: #17a2b8; }
        .document-icon { color: #6610f2; }
        .archive-icon { color: #fd7e14; }
        .file-icon { margin-right: 10px; }
        .table-hover tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.075);
        }
        .action-buttons .btn {
            margin-right: 5px;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
        }
        .search-highlight {
            background-color: #fff3cd;
            padding: 0 2px;
            border-radius: 2px;
        }
        
        /* Hide modified date column on mobile */
        @media (max-width: 767.98px) {
            .modified-column {
                display: none;
            }
            
            .card-header {
                flex-direction: column;
                align-items: stretch !important;
            }
            
            .card-header h2 {
                margin-bottom: 1rem;
                text-align: center;
            }
            
            .action-buttons .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Bourbonic Directory Browser</h2>
                
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
                        <thead class="table-light">
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
                                            <a href="<?= htmlspecialchars($item['path']) ?>" download class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-download"></i> <span class="d-none d-sm-inline">Download</span>
                                            </a>
                                            
                                            <?php if (isPlayable($item['name'])): ?>
                                                <a href="<?= htmlspecialchars($item['path']) ?>" target="_blank" class="btn btn-sm btn-outline-success">
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
                I vibe coded this
            </div>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
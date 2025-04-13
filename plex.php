<?php
// Option 1: Using header() function (preferred method)
header("Location: index.php");
exit;

// Option 2: Using JavaScript (alternative if header doesn't work for some reason)
// echo '<script>window.location.href = "index.php";</script>';
// exit;

// Option 3: Using HTML meta refresh (fallback option)
// echo '<meta http-equiv="refresh" content="0;url=index.php">';
// exit;
?>
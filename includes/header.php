<?php
// includes/header.php
// Expected variables before including: $pageTitle
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . " - " : "" ?>Ticket Management System</title>
    
    <?php
        // Determine path based on directory depth
        $is_subfolder = strpos($_SERVER['PHP_SELF'], '/user/') !== false || 
                        strpos($_SERVER['PHP_SELF'], '/staff/') !== false || 
                        strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
        
        $css_path = $is_subfolder ? "../assets/css/style.css" : "assets/css/style.css";
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $css_path ?>">
    <script>
        (function() {
            var theme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
</head>
<body>
    <div class="app-container">

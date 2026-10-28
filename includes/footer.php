<?php
// includes/footer.php
?>
    </div> <!-- End of app-container -->
    
    <?php
        $is_subfolder = strpos($_SERVER['PHP_SELF'], '/user/') !== false || 
                        strpos($_SERVER['PHP_SELF'], '/staff/') !== false || 
                        strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
        
        $js_path = $is_subfolder ? "../assets/js/script.js" : "assets/js/script.js";
    ?>
    <script src="<?= $js_path ?>"></script>
</body>
</html>

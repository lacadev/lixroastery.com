<?php
/**
 * Base app layout.
 *
 * This layout controls the global structure of the theme.
 * It loads the header, main content, and footer sections.
 *
 * @link    https://docs.wpemerge.com/#/framework/views/layouts
 * @package WPEmergeTheme
 */

// If not a PJAX request, render the full header (includes <head> and site navigation)
if (empty($_GET['_pjax'])) :
    WPEmerge\render('header');
else :
    // For PJAX requests, only update the <title> tag for partial page updates
    echo '<title>';
    wp_title();
    echo '</title>';
endif;
?>

<!-- Main content area where page-specific content will be injected -->
<main id="main_content">
    <?php
    // Render the main layout content
    WPEmerge\layout_content();
    ?>
</main>

<?php
// If not a PJAX request, render the footer (site-wide footer and scripts)
if (empty($_GET['_pjax'])) :
    WPEmerge\render('footer');
endif;
?>

<?php
/*
Plugin Name: JOLT Cleanify
Plugin URI: https://github.com/johnoltmans/JOLT-Cleanify
Description: Simple plugin to clean WordPress cache and database from an admin page. Includes a quick link in the admin bar.
Version: 1.0.7
Author: John Oltmans
Author URI: https://www.johnoltmans.nl/
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

if (!defined('ABSPATH')) {
    exit;
}

// Ensure Dashicons are loaded in the admin
add_action('admin_enqueue_scripts', function() {
    wp_enqueue_style('dashicons');
});

// Add plugin under Settings menu
add_action('admin_menu', 'jolt_cleanify_add_admin_menu');
function jolt_cleanify_add_admin_menu() {
    add_options_page(
        'JOLT Cleanify',
        'JOLT Cleanify',
        'manage_options',
        'jolt-cleanify',
        'jolt_cleanify_admin_page'
    );
}

// Add quick link to admin bar
add_action('admin_bar_menu', 'jolt_cleanify_admin_bar', 100);
function jolt_cleanify_admin_bar($wp_admin_bar) {
    if (!current_user_can('manage_options')) return;

    $wp_admin_bar->add_node([
        'id'    => 'jolt_cleanify_cache_cleaner',
        'title' => 'JOLT Cache Cleaner',
        'href'  => admin_url('admin.php?page=jolt-cleanify'),
        'meta'  => [
            'title' => 'Go to JOLT Cleanify settings',
        ]
    ]);
}

// Admin page content
function jolt_cleanify_admin_page() {
    if (!current_user_can('manage_options')) return;

    // Handle form actions
    if (isset($_POST['jolt_cleanify_action'])) {
        check_admin_referer('jolt_cleanify_action');
        $message = '';
        switch ($_POST['jolt_cleanify_action']) {
            case 'clear_cache':
                $cleared = jolt_cleanify_clear_cache();
                $message = "Cache cleared! $cleared transients deleted.";
                break;
            case 'clean_database':
                $dbmsg = jolt_cleanify_clean_database();
                $message = "Database cleaned! $dbmsg";
                break;
        }
        echo '<div class="notice notice-success" style="background: #23282d; color: #fff; border-left: 4px solid #0073aa;"><p>' . esc_html($message) . '</p></div>';
    }
    ?>
    <style>
    /* Make background of the entire plugin page #1d2327 */
    body.settings_page_jolt-cleanify {
        background: #004287 !important;
    }
    /* Remove white border/shadow from the plugin content container */
    .wrap.jolt-cleanify-bg {
        background: #004287 !important;
        box-shadow: none !important;
        border: none !important;
        min-height: 100vh;
        padding: 40px 30px 30px 30px;
        font-size: 1.08em;
    }
    .jolt-cleanify-bg h1,
    .jolt-cleanify-bg h2,
    .jolt-cleanify-bg h3,
    .jolt-cleanify-bg strong {
        color: #fff !important;
    }
    .jolt-cleanify-bg a,
    .jolt-cleanify-bg .button,
    .jolt-cleanify-bg .button-primary {
        color: #fff !important;
        background: #0073aa !important;
        border-color: #006799 !important;
        text-shadow: none !important;
    }
    .jolt-cleanify-bg .button:hover,
    .jolt-cleanify-bg .button-primary:hover {
        background: #005177 !important;
        border-color: #003c56 !important;
    }
    .jolt-cleanify-bg hr {
        border: none;
        border-top: 1px solid #34393f;
        margin: 30px 0;
    }
    .jolt-cleanify-bg p,
    .jolt-cleanify-bg span,
    .jolt-cleanify-bg em {
        color: #f3f3f3 !important;
    }
    .jolt-cleanify-bg .notice-success {
        color: #fff !important;
    }
    </style>
    <div class="wrap jolt-cleanify-bg">
         <img src="<?php echo plugins_url('source/jolt cleanify small.jpg', __FILE__); ?>" height="200">
        
        <p>
            <strong>Welcome to JOLT Cleanify!</strong> This plugin helps you keep your WordPress site running smoothly by allowing you to quickly clear cached data and clean up your database.<br>
            <em>Not sure what these buttons do? Read the explanations below before clicking.</em>
        </p>
        <form method="post" style="margin-bottom:40px;">
            <?php wp_nonce_field('jolt_cleanify_action'); ?>
            <h2>Clear Cache</h2>
            <p>
                <strong>What does this do?</strong><br>
                This will delete all <b>transients</b> from your database. Transients are temporary cache items created by WordPress and plugins to speed up your site.<br>
                <span style="color:#61caf5;">It is safe to clear the cache. Your site will rebuild the cache as needed and you won't lose any content.</span>
            </p>
            <button type="submit" name="jolt_cleanify_action" value="clear_cache" class="button button-primary">
                Clear Cache
            </button>
        </form>
        <hr style="border-top: 1px solid white;">
        <h2 style="display:flex;align-items:center;">
            <span id="jolt_advanced_toggle_group" style="cursor:pointer;display:inline-flex;align-items:center;">
                Advanced Settings
                <span id="jolt_advanced_toggle" style="display:inline-block;margin-left:10px;font-size:0.75em;vertical-align:middle;" title="Show advanced settings">&#x25BC;</span>
            </span>
        </h2>
        <div id="jolt_advanced_clean" style="display:none;">
            <form method="post">
                <?php wp_nonce_field('jolt_cleanify_action'); ?>
                <h3>Clean Database</h3>
                <p>
                    <strong>What does this do?</strong><br>
                    This will delete <b>post revisions</b> (old autosaves of your posts and pages), <b>spam comments</b>, <b>trashed comments</b>, <b>trashed posts</b>, and <b>expired transients</b>.<br>
                    <span style="color:#e7913a;">Use with caution: This action will permanently remove these items from your database.</span><br>
                    <span style="color:#61caf5;">It will not delete published content or approved comments.</span>
                </p>
                <button type="submit" name="jolt_cleanify_action" value="clean_database" class="button">
                    Clean Database
                </button>
            </form>
        </div>
        <hr style="border-top: 1px solid white;">
        <p style="color:#bbb; font-size:0.96em;">
            <strong>Need help?</strong> Hover over the buttons or read the explanations above. If you're not sure what something does, ask your website administrator before proceeding.
        </p>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleGroup = document.getElementById('jolt_advanced_toggle_group');
            const toggle = document.getElementById('jolt_advanced_toggle');
            const advanced = document.getElementById('jolt_advanced_clean');
            let expanded = false;

            function updateArrow() {
                toggle.innerHTML = expanded ? '\u25B2' : '\u25BC'; // ▲ : ▼
                toggle.title = expanded ? "Hide advanced settings" : "Show advanced settings";
            }

            toggleGroup.addEventListener('click', function() {
                expanded = !expanded;
                advanced.style.display = expanded ? 'block' : 'none';
                updateArrow();
            });

            advanced.style.display = 'none';
            updateArrow();
        });
        </script>
    </div>
    <?php
}

// Function: Clear cache (transients)
function jolt_cleanify_clear_cache() {
    global $wpdb;
    // Remove old transients
    $transient_names = $wpdb->get_col("SELECT option_name FROM $wpdb->options WHERE option_name LIKE '\_transient\_%'");
    $cleared = 0;
    foreach ($transient_names as $transient) {
        delete_option($transient);
        $cleared++;
    }
    return $cleared;
}

// Function: Clean database
function jolt_cleanify_clean_database() {
    global $wpdb;

    // Delete revisions
    $revisions = $wpdb->query("DELETE FROM $wpdb->posts WHERE post_type = 'revision'");
    // Delete spam comments
    $spam = $wpdb->query("DELETE FROM $wpdb->comments WHERE comment_approved = 'spam'");
    // Delete trashed comments
    $trash = $wpdb->query("DELETE FROM $wpdb->comments WHERE comment_approved = 'trash'");
    // Delete trashed posts
    $trash_posts = $wpdb->query("DELETE FROM $wpdb->posts WHERE post_status = 'trash'");
    // Delete expired transients
    $expired = $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '\_transient\_timeout\_%' AND option_value < UNIX_TIMESTAMP()");

    return "$revisions revisions, $spam spam comments, $trash trashed comments, $trash_posts trashed posts, $expired expired transients deleted.";
}
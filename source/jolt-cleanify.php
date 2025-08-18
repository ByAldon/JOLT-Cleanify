<?php
/*
Plugin Name: JOLT Cleanify
Description: Simple plugin to clean WordPress cache and database from an admin page. Includes a Quick Clean button in the admin bar.
Version: 1.2
Author: JOLT (johnoltmans)
*/

if (!defined('ABSPATH')) {
    exit;
}

// Ensure Dashicons are loaded in the admin
add_action('admin_enqueue_scripts', function() {
    wp_enqueue_style('dashicons');
});

// Add admin menu
add_action('admin_menu', 'jolt_cleanify_add_admin_menu');
function jolt_cleanify_add_admin_menu() {
    add_menu_page(
        'JOLT Cleanify',
        'JOLT Cleanify',
        'manage_options',
        'jolt-cleanify',
        'jolt_cleanify_admin_page',
        'dashicons-trash',
        62
    );
}

// Add Quick Clean button to admin bar
add_action('admin_bar_menu', 'jolt_cleanify_admin_bar', 100);
function jolt_cleanify_admin_bar($wp_admin_bar) {
    if (!current_user_can('manage_options')) return;

    $wp_admin_bar->add_node([
        'id'    => 'jolt_cleanify_quick_clean',
        'title' => 'JOLT Quick Clean',
        'href'  => '#',
        'meta'  => [
            'title' => 'Quickly clear WordPress cache',
            'onclick' => 'joltCleanifyQuickClean(); return false;',
        ]
    ]);
}

// Add the JavaScript for quick clean functionality
add_action('admin_head', function() {
    ?>
    <script>
    function joltCleanifyQuickClean() {
        if (confirm("Are you sure you want to clear the cache? This will remove temporary cached data, but will not affect your posts or comments.")) {
            jQuery.post(ajaxurl, {action: 'jolt_cleanify_quick_clean'}, function(response) {
                if (response && response.success) {
                    alert(response.data ? response.data : "Cache cleared!");
                } else {
                    alert("Could not clear cache.");
                }
            });
        }
    }
    </script>
    <?php
});

// Handle AJAX request for quick clean
add_action('wp_ajax_jolt_cleanify_quick_clean', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
    $cleared = jolt_cleanify_clear_cache();
    wp_send_json_success("$cleared transients deleted.");
});

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
        echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>JOLT Cleanify</h1>
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
                <span style="color:#0073aa;">It is safe to clear the cache. Your site will rebuild the cache as needed and you won't lose any content.</span>
            </p>
            <button type="submit" name="jolt_cleanify_action" value="clear_cache" class="button button-primary">
                Clear Cache
            </button>
        </form>
        <hr>
        <h2>
            Advanced Settings
            <button type="button" onclick="document.getElementById('jolt_advanced_clean').style.display = (document.getElementById('jolt_advanced_clean').style.display==='block'?'none':'block');" class="button" style="margin-left:10px;">Show/Hide</button>
        </h2>
        <div id="jolt_advanced_clean" style="display:none;">
            <form method="post">
                <?php wp_nonce_field('jolt_cleanify_action'); ?>
                <h3>Clean Database</h3>
                <p>
                    <strong>What does this do?</strong><br>
                    This will delete <b>post revisions</b> (old autosaves of your posts and pages), <b>spam comments</b>, <b>trashed comments</b>, <b>trashed posts</b>, and <b>expired transients</b>.<br>
                    <span style="color:#ca4a1f;">Use with caution: This action will permanently remove these items from your database.</span><br>
                    <span style="color:#0073aa;">It will not delete published content or approved comments.</span>
                </p>
                <button type="submit" name="jolt_cleanify_action" value="clean_database" class="button">
                    Clean Database
                </button>
            </form>
        </div>
        <hr>
        <p style="color:#555; font-size:0.96em;">
            <strong>Need help?</strong> Hover over the buttons or read the explanations above. If you're not sure what something does, ask your website administrator before proceeding.
        </p>
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
<?php
/**
 * Plugin Name: Plugin Composer Exporter
 * Description: Scans all installed plugins and exports their information to generate new composer.json definitions for active and inactive plugins.
 * Version: 1.0
 * Author: Your Name
 */

add_action('admin_menu', 'pce_register_admin_page');

function pce_register_admin_page() {
    add_menu_page(
        'Plugin Composer Exporter',
        'Composer Exporter',
        'manage_options',
        'plugin-composer-exporter',
        'pce_admin_page_content',
        'dashicons-admin-tools',
        100
    );
}

function pce_admin_page_content() {
    $all_plugins = get_plugins();
    echo '<div class="wrap">';
    echo '<h1>Plugin Composer Exporter</h1>';
    if (isset($_POST['generate_composer'])) {
        check_admin_referer('pce_generate_composer');
        $selected_plugins = isset($_POST['plugins']) ? array_map('sanitize_text_field', $_POST['plugins']) : array();
        pce_generate_composer_files($selected_plugins);
    } else {
        echo '<form method="post">';
        wp_nonce_field('pce_generate_composer');
        echo '<p>Select the plugins to generate composer.json files for:</p>';
        echo '<ul>';
        foreach ($all_plugins as $path => $details) {
            $slug = dirname($path);
            echo '<li><input type="checkbox" name="plugins[]" value="' . esc_attr($slug) . '" checked> ' . esc_html($details['Name']) . '</li>';
        }
        echo '</ul>';
        echo '<input type="submit" name="generate_composer" class="button button-primary" value="Generate Composer Files">';
        echo '</form>';
    }
    echo '</div>';
}

function pce_generate_composer_files($selected_plugins) {
    global $wp_filesystem;

    // Ensure selected_plugins is an array
    if (!is_array($selected_plugins)) {
        $selected_plugins = array();
    }

    // Get all plugins
    $all_plugins = get_plugins();
    $active_plugins = get_option('active_plugins');
    
    $active = [];
    $inactive = [];
    
    // Filter plugins based on selection and active status
    foreach ($all_plugins as $path => $details) {
        $slug = dirname($path);
        if (!in_array($slug, $selected_plugins)) {
            continue; // Skip plugins not selected
        }
        if (in_array($path, $active_plugins)) {
            $active[$slug] = $details;
        } else {
            $inactive[$slug] = $details;
        }
    }
    
    // Generate composer.json content
    $active_content = pce_generate_composer_content($active);
    $inactive_content = pce_generate_composer_content($inactive);
    
    // Initialize WP Filesystem
    if (false === ($credentials = request_filesystem_credentials('', '', false, false, null))) {
        return; // stop processing here
    }

    if (!WP_Filesystem($credentials)) {
        echo '<p>Could not access filesystem.</p>';
        return;
    }
    
    // Save to files
    $wp_filesystem->put_contents(plugin_dir_path(__FILE__) . 'active-plugins-composer.json', json_encode($active_content, JSON_PRETTY_PRINT));
    $wp_filesystem->put_contents(plugin_dir_path(__FILE__) . 'inactive-plugins-composer.json', json_encode($inactive_content, JSON_PRETTY_PRINT));
    
    echo '<p>Composer files generated successfully.</p>';
}

function pce_generate_composer_content($plugins) {
    $composer_content = [
        'repositories' => [
            [
                'type' => 'composer',
                'url' => 'https://wpackagist.org'
            ]
        ],
        'require' => []
    ];

    foreach ($plugins as $slug => $details) {
        $version = isset($details['Version']) ? $details['Version'] : 'dev-master';
        
        // Construct the WPackagist plugin name
        $wpackagist_slug = "wpackagist-plugin/$slug";
        
        // Check if the plugin exists on WPackagist (this function should be updated or removed)
        if (plugin_exists_on_wpackagist($wpackagist_slug, $version)) {
            $composer_content['require'][$wpackagist_slug] = $version;
        } else {
            // Fallback logic if the plugin does not exist on WPackagist
            $repo_url = null;
            if (isset($details['PluginURI'])) {
                $repo_url = $details['PluginURI'];
            } elseif (isset($details['AuthorURI'])) {
                $repo_url = $details['AuthorURI'];
            }
            
            if ($repo_url) {
                $composer_content['repositories'][] = [
                    'type' => 'vcs',
                    'url' => $repo_url
                ];
                $composer_content['require'][$slug] = $version;
            }
        }

        // Include plugin description if available
        if (isset($details['Description']) && !empty($details['Description'])) {
            $composer_content['description'] = $details['Description'];
        }
    }

    return $composer_content;
}

function plugin_exists_on_wpackagist($plugin_name, $version) {
    $url = "https://wpackagist.org/search?q=$plugin_name";
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    
    // Create a new DOMDocument and load the HTML
    $dom = new DOMDocument();
    @$dom->loadHTML($body); // Suppress warnings from invalid HTML

    // Use XPath to find the relevant nodes
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query("//a[contains(@href, '/packages/$plugin_name')]");

    // Check if any nodes were found
    if ($nodes->length > 0) {
        // Further validation could be added here to check for the specific version
        return true;
    }

    return false;
}
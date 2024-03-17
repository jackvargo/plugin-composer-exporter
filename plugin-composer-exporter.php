<?php
/**
 * Plugin Name: Plugin Composer Exporter
 * Description: Scans all installed plugins and exports their information to generate new composer.json definitions for active and inactive plugins.
 * Version: 1.0
 * Author: Jack Vargo
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
        pce_generate_composer_files($_POST['plugins']);
    } else {
        echo '<form method="post">';
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


function pce_generate_composer_files() {
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
    
    // Save to files
    file_put_contents(plugin_dir_path(__FILE__) . 'active-plugins-composer.json', json_encode($active_content, JSON_PRETTY_PRINT));
    file_put_contents(plugin_dir_path(__FILE__) . 'inactive-plugins-composer.json', json_encode($inactive_content, JSON_PRETTY_PRINT));
    
    echo '<p>Composer files generated successfully.</p>';
}

function pce_generate_composer_content($plugins) {
    $composer_content = [
        'repositories' => [],
        'require' => []
    ];
    
    foreach ($plugins as $slug => $details) {
        // Here, adjust the logic based on how you determine each plugin's corresponding package name and version
        $composer_content['repositories'][] = [
            'type' => 'vcs',
            'url' => "https://path/to/{$slug}/repository" // Placeholder; adjust as needed
        ];
        $composer_content['require'][$slug] = 'dev-master'; // Placeholder version
    }
    
    return $composer_content;
}

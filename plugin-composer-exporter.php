<?php
/**
 * Plugin Name: Plugin Composer Exporter
 * Description: Exports selected plugin information to a single JSON file with active and inactive plugins.
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
    if (isset($_POST['save_settings'])) {
        // Save settings
        $plugin_sources = isset($_POST['plugin_sources']) ? $_POST['plugin_sources'] : array();
        foreach ($plugin_sources as $slug => $source) {
            if (!isset($source['is_wpackagist'])) {
                $plugin_sources[$slug]['is_wpackagist'] = false;
            }
        }
        update_option('pce_plugin_sources', $plugin_sources);
        echo '<p>Settings saved successfully.</p>';
    } elseif (isset($_POST['generate_composer'])) {
        $selected_plugins = isset($_POST['plugins']) ? $_POST['plugins'] : array();
        $composer_json_content = pce_generate_composer_file($selected_plugins);
    }

    $saved_sources = get_option('pce_plugin_sources', array());

    echo '<form method="post">';
    echo '<p>Select the plugins and specify their sources:</p>';
    echo '<ul>';
    foreach ($all_plugins as $path => $details) {
        $slug = dirname($path);
        $is_wpackagist = isset($saved_sources[$slug]['is_wpackagist']) ? $saved_sources[$slug]['is_wpackagist'] : true;
        $repo_url = isset($saved_sources[$slug]['repo_url']) ? $saved_sources[$slug]['repo_url'] : '';
        echo '<li style="margin-bottom: 10px;">';
        echo '<input type="checkbox" name="plugins[]" value="' . esc_attr($slug) . '" checked> ' . esc_html($details['Name']);
        echo '<div style="margin-left: 20px;">';
        echo '<label><input type="checkbox" name="plugin_sources[' . esc_attr($slug) . '][is_wpackagist]" value="1"' . checked($is_wpackagist, true, false) . ' onclick="toggleRepoUrl(this, \'' . esc_attr($slug) . '\')"> WPackagist</label>';
        echo '<input type="text" id="repo_url_' . esc_attr($slug) . '" name="plugin_sources[' . esc_attr($slug) . '][repo_url]" value="' . esc_attr($repo_url) . '" placeholder="Repository URL" ' . disabled($is_wpackagist, true, false) . ' style="margin-left: 10px; width: 50%;">';
        echo '</div>';
        echo '</li>';
    }
    echo '</ul>';
    echo '<input type="submit" name="save_settings" class="button button-primary" value="Save Settings">';
    echo '<input type="submit" name="generate_composer" class="button button-primary" value="Generate Composer File">';
    echo '</form>';
    echo '</div>';
    
    if (isset($composer_json_content)) {
        echo '<h2>Generated composer.json</h2>';
        echo '<textarea style="width: 100%; height: 300px;">' . esc_textarea($composer_json_content) . '</textarea>';
    }

    echo '<script>
    function toggleRepoUrl(checkbox, slug) {
        var repoUrlField = document.getElementById("repo_url_" + slug);
        repoUrlField.disabled = checkbox.checked;
    }
    </script>';
}

function pce_generate_composer_file($selected_plugins) {
    if (!is_array($selected_plugins)) {
        $selected_plugins = array();
    }

    $all_plugins = get_plugins();
    $active_plugins = get_option('active_plugins');
    $saved_sources = get_option('pce_plugin_sources', array());
    
    $plugins_data = [
        'active' => [],
        'inactive' => []
    ];
    
    $composer_content = [
        'repositories' => [],
        'require' => []
    ];

    $exported_slugs = [];

    foreach ($all_plugins as $path => $details) {
        $slug = dirname($path);
        if (!in_array($slug, $selected_plugins)) {
            continue;
        }
        $is_active = in_array($path, $active_plugins);
        $plugins_data[$is_active ? 'active' : 'inactive'][$slug] = $details;
        $exported_slugs[] = $slug;

        if (isset($saved_sources[$slug]['is_wpackagist']) && $saved_sources[$slug]['is_wpackagist']) {
            $composer_content['require']["wpackagist-plugin/$slug"] = $details['Version'];
        } else {
            $repo_url = $saved_sources[$slug]['repo_url'];
            $composer_content['repositories'][] = [
                'type' => 'vcs',
                'url' => $repo_url
            ];

            // Extract repository owner from the URL and use it in the require statement
            $owner = parse_url($repo_url, PHP_URL_PATH);
            $owner = explode('/', trim($owner, '/'))[0];
            $composer_content['require']["$owner/$slug"] = $details['Version'];
        }
    }

    $datecode = date('dHis');
    $filename = plugin_dir_path(__FILE__) . "exported_plugins_{$datecode}.json";
    file_put_contents($filename, json_encode($plugins_data, JSON_PRETTY_PRINT));
    
    $composer_filename = plugin_dir_path(__FILE__) . "composer_{$datecode}.json";
    $composer_json_content = json_encode($composer_content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents($composer_filename, $composer_json_content);

    // Log the exported plugin slugs
    $exported_slugs_list = implode(', ', $exported_slugs);
    echo '<p>Composer file generated successfully: ' . $composer_filename . '</p>';
    echo '<p>Exported plugins: ' . $exported_slugs_list . '</p>';
    pce_log_to_console("Composer file generated successfully: " . $composer_filename);
    pce_log_to_console("Exported plugins: " . $exported_slugs_list);
    
    return $composer_json_content;
}

function pce_log_to_console($message) {
    echo '<script>console.log(' . json_encode($message) . ');</script>';
}
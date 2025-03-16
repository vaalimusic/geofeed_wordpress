<?php
/**
 * Plugin Name: Geo Feed MaxMind
 * Plugin URI: https://everty.ru
 * Description: A plugin for sending Geo Feed data to MaxMind.
 * Version: 1.5
 * Author: Arthur Valeiv
 * Author URI: https://everty.ru
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

define( 'GEO_FEED_MAXMIND_VERSION', '1.5' );
define( 'GEO_FEED_MAXMIND_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GEO_FEED_MAXMIND_OPTION', 'geo_feed_maxmind_data' );
define( 'GEO_FEED_MAXMIND_FILENAME', ABSPATH . 'geo_feed_maxmind.csv' );
define( 'GEO_FEED_MAXMIND_FIELDS', ['ip_prefix', 'country', 'region', 'city', 'postal_code']);

default_data();

add_action('admin_menu', 'geo_feed_maxmind_add_admin_menu');
add_action('admin_init', 'geo_feed_maxmind_settings_init');
add_action('init', 'geo_feed_maxmind_create_file');
add_action('init', 'geo_feed_maxmind_create_endpoint');
add_action('template_redirect', 'geo_feed_maxmind_output_data');

function default_data() {
    $data = get_option(GEO_FEED_MAXMIND_OPTION, []);
    if (empty($data)) {
        $data = [
            ['ip_prefix' => '185.191.143.0/24', 'country' => 'RU', 'region' => 'STA', 'city' => 'Stavropol', 'postal_code' => ''],
            ['ip_prefix' => '45.146.40.0/24', 'country' => 'RU', 'region' => 'STA', 'city' => 'Stavropol', 'postal_code' => '']
        ];
        update_option(GEO_FEED_MAXMIND_OPTION, $data);
    }
}

function geo_feed_maxmind_add_admin_menu() {
    add_menu_page('Geo Feed MaxMind', 'Geo Feed MaxMind', 'manage_options', 'geo-feed-maxmind', 'geo_feed_maxmind_options_page');
}

function geo_feed_maxmind_settings_init() {
    register_setting('geoFeedMaxMind', 'geo_feed_maxmind_settings');
}

function geo_feed_maxmind_options_page() {
    // Получаем данные с опции
    $data = get_option(GEO_FEED_MAXMIND_OPTION, []);

    // Проверяем, была ли отправлена форма с новыми данными
    if (isset($_POST['save_geo_feed_data'])) {
        // Удаляем пулы, которые отмечены для удаления
        if (isset($_POST['delete'])) {
            $data = array_values(array_filter($data, function($entry, $index) {
                return !in_array($index, $_POST['delete']);
            }, ARRAY_FILTER_USE_BOTH));
        }

        // Обновляем данные для каждого пула
        foreach ($data as $index => &$entry) {
            foreach (GEO_FEED_MAXMIND_FIELDS as $field) {
                $entry[$field] = sanitize_text_field($_POST[$field][$index] ?? '');
            }
        }

        // Сохраняем обновленные данные в опцию
        update_option(GEO_FEED_MAXMIND_OPTION, $data);
        geo_feed_maxmind_create_file(); // Перегенерируем CSV файл
        echo '<div class="notice notice-success"><p>Data saved successfully!</p></div>';
    }

    // Проверяем, была ли нажата кнопка для добавления нового пула
    if (isset($_POST['add_and_save_new_pool'])) {
        $data[] = array_fill_keys(GEO_FEED_MAXMIND_FIELDS, ''); // Добавляем новый пустой пул в массив
        update_option(GEO_FEED_MAXMIND_OPTION, $data);
        geo_feed_maxmind_create_file(); // Перегенерируем CSV файл
        echo '<div class="notice notice-success"><p>New IP Pool added and saved successfully!</p></div>';
    }

    // URL для скачивания CSV
    $url = site_url('/geo-feed-maxmind-data.csv');
    ?>
    <div class="wrap">
        <h2>Geo Feed MaxMind Settings</h2>
        <form action="" method="post">
            <div id="pools-container" style="display: flex;">
                <?php foreach ($data as $index => $entry): ?>
                    <fieldset style="margin-bottom: 20px; border: 1px solid #ddd; padding: 10px;">
                        <legend><strong>IP Pool <?php echo $index + 1; ?></strong></legend>
                        <?php foreach (GEO_FEED_MAXMIND_FIELDS as $field): ?>
                            <label><?php echo ucfirst(str_replace('_', ' ', $field)); ?>:
                            <input type="text" name="<?php echo $field; ?>[<?php echo $index; ?>]" value="<?php echo esc_attr($entry[$field] ?? ''); ?>" /></label><br><br>
                        <?php endforeach; ?>
                        <label>Delete Pool: <input type="checkbox" name="delete[]" value="<?php echo $index; ?>" /></label>
                    </fieldset>
                <?php endforeach; ?>
            </div>
            <input type="submit" name="save_geo_feed_data" value="Save Data" class="button button-primary" />
        </form>
        
        <form action="" method="post">
            <input type="submit" name="add_and_save_new_pool" value="Add and Save New IP Pool" class="button" />
        </form>

        <h3>Generated CSV Link:</h3>
        <p><a href="<?php echo esc_url($url); ?>" target="_blank">Download CSV</a></p>
    </div>
    <?php
}

function geo_feed_maxmind_create_file() {
    // Получаем данные для записи в CSV
    $data = get_option(GEO_FEED_MAXMIND_OPTION, []);

    // Открываем файл для записи
    $output = fopen(GEO_FEED_MAXMIND_FILENAME, 'w');
    //fputcsv($output, GEO_FEED_MAXMIND_FIELDS); // Записываем заголовки

    // Записываем данные каждого пула
    foreach ($data as $entry) {
        $row = [];
        foreach (GEO_FEED_MAXMIND_FIELDS as $field) {
            $row[] = $entry[$field] ?? ''; // Записываем данные каждого пула
        }
        fputcsv($output, $row);
    }

    fclose($output); // Закрываем файл
}

function geo_feed_maxmind_create_endpoint() {
    add_rewrite_rule('geo-feed-maxmind-data.csv/?$', 'index.php?geo_feed_maxmind=1', 'top');
}

function geo_feed_maxmind_output_data() {
    if (get_query_var('geo_feed_maxmind')) {
        if (file_exists(GEO_FEED_MAXMIND_FILENAME)) {
            header('Content-Type: text/csv');
            header('Content-Disposition: inline; filename="geo_feed_maxmind.csv"');
            readfile(GEO_FEED_MAXMIND_FILENAME);
            exit;
        }
    }
}

add_filter('query_vars', function($vars) {
    $vars[] = 'geo_feed_maxmind';
    return $vars;
});

register_activation_hook(__FILE__, 'geo_feed_maxmind_activate');
function geo_feed_maxmind_activate() {
    geo_feed_maxmind_create_endpoint();
    flush_rewrite_rules();
    geo_feed_maxmind_create_file();
}

register_deactivation_hook(__FILE__, 'geo_feed_maxmind_deactivate');
function geo_feed_maxmind_deactivate() {
    flush_rewrite_rules();
}

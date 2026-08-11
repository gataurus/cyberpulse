<?php
/**
 * Plugin Name: CyberPulse — Advanced Security & Bot Protection
 * Description: CyberPulse — сердцебиение безопасности вашего WordPress. Мониторинг каждого запроса в реальном времени, мгновенная блокировка ботов, защита от брутфорса и AI-скрапинга. Почувствуйте пульс защиты. Бесплатная версия с возможностью апгрейда до PRO.
 * Version: 5.8.9.3
 * Author: CyberPulse Security
 * Author URI: https://cyberpulse-security.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cyberpulse
 * Domain Path: /languages
 * Requires at least: 5.3
 * Requires PHP: 7.4
 * Tested up to: 7.0
 */
if (!defined('ABSPATH')) exit;

// ==================== FREE VERSION FLAG ====================
if (!defined('CYBERSEC_IS_FREE_VERSION')) {
    define('CYBERSEC_IS_FREE_VERSION', true);
}

// ==================== WP FILESYSTEM HELPERS ====================
function cybersec_fs_init() {
    global $wp_filesystem;
    if ( ! $wp_filesystem ) {
        require_once ABSPATH . '/wp-admin/includes/file.php';
        WP_Filesystem();
    }
    return $wp_filesystem;
}

function cybersec_fs_put_contents($file, $data, $flags = 0) {
    $dir = dirname($file);
    if (!cybersec_fs_exists($dir)) {
        wp_mkdir_p($dir);
    }
    $fs = cybersec_fs_init();
    
    if ($flags & FILE_APPEND) {
        $existing = $fs->exists($file) ? $fs->get_contents($file) : '';
        $data = $existing . $data;
    }
    
    $result = $fs->put_contents($file, $data);
    if ($result !== false && $fs->exists($file)) {
        $fs->chmod($file, 0644);
    }
    return $result;
}

function cybersec_fs_get_contents($file) {
    $fs = cybersec_fs_init();
    if (!$fs->exists($file)) return '';
    return $fs->get_contents($file);
}

function cybersec_fs_delete($file) {
    $fs = cybersec_fs_init();
    return $fs->delete($file);
}

function cybersec_fs_exists($file) {
    $fs = cybersec_fs_init();
    return $fs->exists($file);
}

function cybersec_fs_move($source, $destination, $overwrite = false) {
    $fs = cybersec_fs_init();
    return $fs->move($source, $destination, $overwrite);
}

function cybersec_fs_is_writable($path) {
    $fs = cybersec_fs_init();
    return $fs->is_writable($path);
}

function cybersec_fs_mkdir($path) {
    $fs = cybersec_fs_init();
    return $fs->mkdir($path);
}

// ==================== TRANSLATIONS ====================
function cybersec_get_available_languages() {
    return array(
        'ru_RU' => 'Русский',
        'en_US' => 'English',
        'de_DE' => 'Deutsch',
        'fr_FR' => 'Français',
        'it_IT' => 'Italiano',
        'zh_CN' => '中文',
        'ja'    => '日本語',
        'ko_KR' => '한국어',
        'es_ES' => 'Español',
        'pt_BR' => 'Português',
    );
}

function cybersec_get_locale() {
    $settings = get_option('secwall_settings', array());
    $forced_locale = isset($settings['cybersec_language']) ? $settings['cybersec_language'] : 'auto';
    if ($forced_locale !== 'auto' && array_key_exists($forced_locale, cybersec_get_available_languages())) {
        return $forced_locale;
    }
    if ($forced_locale === 'auto') {
        $wp_locale = determine_locale();
        if (array_key_exists($wp_locale, cybersec_get_available_languages())) {
            return $wp_locale;
        }
    }
    return 'en_US';
}

function cybersec_translate($text) {
    static $translations = null;
    static $loaded_locale = null;
    $current_locale = cybersec_get_locale();
    if ($translations === null || $loaded_locale !== $current_locale) {
        $file = __DIR__ . '/languages/cyberpulse-' . $current_locale . '.php';
        if (file_exists($file)) {
            $translations = include $file;
        } else {
            $translations = include __DIR__ . '/languages/cyberpulse-en_US.php';
        }
        $loaded_locale = $current_locale;
    }
    return isset($translations[$text]) ? $translations[$text] : $text;
}

add_filter('gettext', function($translation, $text, $domain) {
    if ($domain === 'cyberpulse') {
        $custom = cybersec_translate($text);
        if ($custom !== $text) return $custom;
    }
    return $translation;
}, 10, 3);

// ==================== LOAD MODULES ====================
require_once __DIR__ . '/cyberpulse-core.php';
require_once __DIR__ . '/cyberpulse-tabs.php';

// ==================== ACTIVATION / DEACTIVATION HOOKS ====================
register_activation_hook(__FILE__, 'cybersec_activate');
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('cybersec_weekly_report_cron');
    wp_clear_scheduled_hook('cybersec_daily_cleanup_cron');
});

// ==================== SETTINGS LINK ====================
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'cybersec_add_settings_link');
function cybersec_add_settings_link($links) {
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=cyber-security#tab-settings')) . '">' . esc_html(cybersec_translate('Settings')) . '</a>';
    $links[] = $settings_link;
    return $links;
}

<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
if (!defined('ABSPATH')) exit;

// ==================== BLOCK 1: CONSTANTS AND CORE ====================
$cybersec_upload_dir = wp_upload_dir();
define('CYBERSEC_LOG_DIR', $cybersec_upload_dir['basedir'] . '/cyber-pulse-logs/');
define('CYBERSEC_PAGES_OPTION', 'secwall_tracked_pages');
define('CYBERSEC_BLOCKED_OPTION', 'secwall_blocked_subnets');
define('CYBERSEC_BOT_OPTIONS', 'secwall_bot_detection_options');
define('CYBERSEC_SETTINGS_OPTION', 'secwall_settings');
define('CYBERSEC_WHITELIST_OPTION', 'secwall_custom_whitelist');
define('CYBERSEC_TEMP_BLOCKS_OPTION', 'secwall_temp_blocks');
define('CYBERSEC_STATS_OPTION', 'secwall_daily_stats');
define('CYBERSEC_BRUTE_OPTION', 'secwall_brute_data');
define('CYBERSEC_REPEAT_OPTION', 'secwall_repeat_offenders');
define('CYBERSEC_PERMANENT_BLOCKED_SUBNETS', 'secwall_permanent_blocked_subnets');
define('CYBERSEC_UA_BLOCKS_OPTION', 'secwall_blocked_user_agents');
define('CYBERSEC_VERSION', '5.8.9.3');

if (!file_exists(CYBERSEC_LOG_DIR)) {
    wp_mkdir_p(CYBERSEC_LOG_DIR);
}
if (!cybersec_fs_exists(CYBERSEC_LOG_DIR . '.htaccess')) {
    cybersec_fs_put_contents(CYBERSEC_LOG_DIR . '.htaccess', "Require all denied\n");
}
if (!cybersec_fs_exists(CYBERSEC_LOG_DIR . 'index.php')) {
    cybersec_fs_put_contents(CYBERSEC_LOG_DIR . 'index.php', '<?php // Silence is golden');
}

define('CYBERSEC_MAX_LOG_SIZE', 5 * 1024 * 1024);
define('CYBERSEC_MAX_TOTAL_LOG_SIZE', 50 * 1024 * 1024);
define('CYBERSEC_GEO_CACHE_DAYS', 7);

/**
 * Get plugin settings with defaults.
 */
function cybersec_get_settings() {
    static $cache = null;
    if ($cache !== null) return $cache;
    
    $defaults = array(
        'blocked_email' => '',
        'auto_unblock_minutes' => 30,
        'auto_whitelist_server' => 1,
        'alert_threshold' => 20,
        'alert_minutes' => 5,
        'alert_enabled' => 1,
        'brute_max_attempts' => 5,
        'brute_window_minutes' => 15,
        'brute_enabled' => 1,
        'hide_wp' => 1,
        'referer_safe_check' => 1,
        'referer_history_threshold' => 1,
        'referer_rate_1min' => 5,
        'referer_rate_5min' => 10,
        'referer_same_page' => 3,
        'referer_no_proxy_rate' => 3,
        'datacenter_enhanced' => 1,
        'hide_wp_version' => 1,
        'remove_script_versions' => 1,
        'hide_login_errors' => 1,
        'disable_xmlrpc_pingbacks' => 1,
        'force_secure_cookies' => 1,
        'rate_limiting_enabled' => 1,
        'rate_limit_requests' => 60,
        'rate_limit_window' => 60,
        'rest_api_protection' => 1,
        'hide_plugins_themes' => 1,
        'auto_clean_logs_days' => 30,
        'max_revisions' => 5,
        'track_user_agents' => 1,
        'login_logging' => 1,
        '404_detection_enabled' => 1,
        '404_threshold' => 10,
        '404_window' => 5,
        'url_firewall_enabled' => 0,
        'url_firewall_patterns' => '',
        'cybersec_language' => 'auto',
    );
    $cache = wp_parse_args(get_option(CYBERSEC_SETTINGS_OPTION, array()), $defaults);
    return $cache;
}

function cybersec_get_blocked_email() {
    $s = cybersec_get_settings();
    if (!empty($s['blocked_email']) && is_email($s['blocked_email'])) return $s['blocked_email'];
    $admin_email = get_option('admin_email');
    if (!empty($admin_email) && is_email($admin_email)) return $admin_email;
    $admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => array('user_email')));
    if (!empty($admins) && !empty($admins[0]->user_email) && is_email($admins[0]->user_email)) return $admins[0]->user_email;
    $sitename = wp_parse_url(network_home_url(), PHP_URL_HOST);
    if ($sitename) return 'wordpress@' . $sitename;
    return 'admin@localhost';
}

function cybersec_get_auto_unblock_minutes() { $s = cybersec_get_settings(); return isset($s['auto_unblock_minutes']) ? (int)$s['auto_unblock_minutes'] : 30; }
function cybersec_is_auto_whitelist_server_enabled() { $s = cybersec_get_settings(); return isset($s['auto_whitelist_server']) ? (int)$s['auto_whitelist_server'] : 1; }
function cybersec_is_referer_safe_check_enabled() { $s = cybersec_get_settings(); return isset($s['referer_safe_check']) ? (int)$s['referer_safe_check'] : 1; }

function cybersec_get_referer_settings() {
    $s = cybersec_get_settings();
    return array(
        'history_threshold' => isset($s['referer_history_threshold']) ? (int)$s['referer_history_threshold'] : 1,
        'rate_1min' => isset($s['referer_rate_1min']) ? (int)$s['referer_rate_1min'] : 5,
        'rate_5min' => isset($s['referer_rate_5min']) ? (int)$s['referer_rate_5min'] : 10,
        'same_page' => isset($s['referer_same_page']) ? (int)$s['referer_same_page'] : 3,
        'no_proxy_rate' => isset($s['referer_no_proxy_rate']) ? (int)$s['referer_no_proxy_rate'] : 3,
    );
}

function cybersec_get_default_bot_options() {
    return array('empty_user_agent' => 1, 'user_agent_anomalies' => 1, 'empty_accept_language' => 1, 'fake_referer' => 1, 'http_1_0' => 1, 'suspicious_headers' => 1, 'direct_ip_access' => 1, 'bad_referer_spam' => 1, 'cloudflare_origin' => 0, 'ipv6_connection' => 0);
}

function cybersec_normalize_slug($raw) {
    $raw = trim($raw); $raw = strtok($raw, '#');
    if (preg_match('#^https?://[^/]+/?(.*)$#', $raw, $m)) { $path = trim($m[1], '/'); if (empty($path)) return $raw; return $path; }
    return trim($raw, '/');
}

function cybersec_is_real_browser() {
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
    if (empty($ua)) return false;
    if (stripos($ua, 'Mozilla/') !== false && (stripos($ua, 'AppleWebKit/') !== false || stripos($ua, 'Gecko/') !== false)) return true;
    $real_browsers = array('Chrome','Firefox','Safari','Edge','Opera','OPR','SamsungBrowser','UCBrowser','YaBrowser','Vivaldi','Brave','CriOS','FxiOS','EdgiOS','OPiOS','Arc','DuckDuckGo');
    foreach ($real_browsers as $b) { if (stripos($ua,$b)!==false) return true; }
    if (stripos($ua,'Android')!==false && stripos($ua,'Mobile')!==false) return true;
    if (stripos($ua,'iPhone')!==false || stripos($ua,'iPad')!==false) return true;
    return false;
}

function cybersec_get_visitor_ip(){ 
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    if(!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
        $ips=explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']))); 
        $ip = sanitize_text_field(trim($ips[0]));
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    } 
    return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '127.0.0.1'; 
}

function cybersec_get_subnet_24($ip){ $p=explode('.',$ip); if(count($p)===4) return $p[0].'.'.$p[1].'.'.$p[2].'.0/24'; return $ip.'/24'; }

function cybersec_ip_in_subnet($ip,$subnet){ 
    if(strpos($subnet,'/')===false) return $ip===$subnet; 
    list($b,$m)=explode('/',$subnet); $m=(int)$m; if($m===32) return $ip===$b; 
    $il=ip2long($ip); $sl=ip2long($b); if($il===false || $sl===false) return false; 
    return ($il & (-1<<(32-$m))) === $sl; 
}

function cybersec_get_country_by_ip($ip){ 
    $ck='cybersec_geo_'.md5($ip); 
    $ca=get_transient($ck); 
    if($ca!==false) return $ca; 
    $r='—';
    if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) {
        set_transient($ck, '—', DAY_IN_SECONDS);
        return '—';
    }
    $service_down = get_transient('cybersec_geo_service_down');
    if ($service_down) return $r;
    $lang = substr(cybersec_get_locale(), 0, 2);
    $response = wp_remote_get('http://ip-api.com/json/'.$ip.'?fields=country,countryCode&lang=' . $lang, array(
    'timeout' => 0.5, 
    'blocking' => true
));

if (is_wp_error($response)) {
    set_transient('cybersec_geo_service_down', true, 15 * MINUTE_IN_SECONDS);
    return '—';
}
    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        $body = wp_remote_retrieve_body($response);
        $d = json_decode($body, true);
        if (!empty($d['country'])) $r = $d['country'].' ('.$d['countryCode'].')';
    } else {
        set_transient('cybersec_geo_service_down', true, 5 * MINUTE_IN_SECONDS);
    }
    set_transient($ck, $r, CYBERSEC_GEO_CACHE_DAYS * DAY_IN_SECONDS); 
    return $r; 
}

function cybersec_is_server_ip($v){ $s=cybersec_get_server_ip(); return $v===$s || $v===cybersec_get_subnet_24($s); }

function cybersec_is_admin_ip($ip) {
    $wl = cybersec_get_custom_whitelist();
    foreach ($wl as $item) { if (isset($item['note']) && stripos($item['note'], 'administrator') !== false) { if ($item['type'] === 'ip' && $item['value'] === $ip) return true; if ($item['type'] === 'subnet' && cybersec_ip_in_subnet($ip, $item['value'])) return true; } }
    return false;
}

function cybersec_get_server_ip(){ 
    $cached = get_transient('cybersec_server_ip');
    if ($cached && filter_var($cached, FILTER_VALIDATE_IP)) return $cached;
    if(!empty($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR']!=='127.0.0.1' && $_SERVER['SERVER_ADDR']!=='::1') {
        $server_addr = sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR']));
        set_transient('cybersec_server_ip', $server_addr, DAY_IN_SECONDS);
        return $server_addr;
    }
    $d=wp_parse_url(get_site_url(),PHP_URL_HOST); 
    if($d){ $ip=gethostbyname($d); if($ip && $ip!==$d && filter_var($ip,FILTER_VALIDATE_IP) && $ip!=='127.0.0.1') { set_transient('cybersec_server_ip', $ip, DAY_IN_SECONDS); return $ip; } } 
    $response = wp_remote_get('https://api.ipify.org', array('timeout' => 3));
    if (!is_wp_error($response)) { $ei = wp_remote_retrieve_body($response); if($ei && filter_var(trim($ei),FILTER_VALIDATE_IP)) { set_transient('cybersec_server_ip', trim($ei), DAY_IN_SECONDS); return trim($ei); } }
    if(!empty($_SERVER['REMOTE_ADDR'])) return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])); 
    return '127.0.0.1'; 
}

// ==================== BLOCK 1.5: HOSTING PROVIDER SUBNETS ====================
function cybersec_get_datacenter_subnets() {
    return array(
        '64.23.0.0/16','134.122.0.0/16','134.209.0.0/16','137.184.0.0/16','138.68.0.0/16','138.197.0.0/16','139.59.0.0/16','142.93.0.0/16','143.110.0.0/16','143.198.0.0/16','144.126.0.0/16','146.190.0.0/16','147.182.0.0/16','157.230.0.0/16','157.245.0.0/16','159.65.0.0/16','159.89.0.0/16','159.203.0.0/16','161.35.0.0/16','164.90.0.0/16','164.92.0.0/16','165.22.0.0/16','165.227.0.0/16','167.71.0.0/16','167.99.0.0/16','167.172.0.0/16','170.64.0.0/16','174.138.0.0/16','178.62.0.0/16','178.128.0.0/16','188.166.0.0/16','192.34.56.0/16','192.81.208.0/16','192.241.128.0/16','198.199.64.0/16','198.211.96.0/16','199.247.0.0/16','206.81.0.0/16','206.189.0.0/16','207.154.192.0/16','208.68.36.0/16','209.38.0.0/16','209.97.128.0/16',
        '3.0.0.0/9','18.0.0.0/8','35.80.0.0/12','43.250.192.0/18','52.0.0.0/8','54.0.0.0/8','99.0.0.0/8','100.20.0.0/14','150.222.0.0/16','157.175.0.0/16','176.34.0.0/16','34.0.0.0/8','35.184.0.0/13','104.154.0.0/15','104.196.0.0/14','107.167.0.0/16','130.211.0.0/16','146.148.0.0/16','4.0.0.0/8','13.64.0.0/11','20.0.0.0/8','40.64.0.0/10','51.0.0.0/8','65.52.0.0/14','70.37.0.0/17','104.40.0.0/13','191.232.0.0/16',
        '45.33.0.0/16','45.56.64.0/16','45.79.0.0/16','50.116.0.0/16','66.175.208.0/16','66.228.32.0/16','69.164.192.0/16','72.14.176.0/16','74.207.224.0/16','96.126.96.0/16','97.107.128.0/16','104.200.16.0/16','104.237.128.0/16','139.144.0.0/16','172.104.0.0/16','173.230.128.0/16','173.255.192.0/16','192.53.160.0/16','192.155.80.0/16','194.195.0.0/16','198.58.96.0/16','198.74.48.0/16',
        '45.32.0.0/16','45.63.0.0/16','45.76.0.0/16','45.77.0.0/16','64.176.0.0/16','66.42.0.0/16','70.34.192.0/16','78.141.192.0/16','80.240.16.0/16','95.179.128.0/16','104.156.224.0/16','104.207.128.0/16','104.238.128.0/16','107.191.32.0/16','108.61.0.0/16','128.199.0.0/16','139.180.128.0/16','140.82.0.0/16','144.202.0.0/16','149.28.0.0/16','155.138.128.0/16','158.247.192.0/16','192.248.128.0/16','199.247.0.0/16','200.25.32.0/16','207.148.0.0/16','207.246.64.0/16','208.64.120.0/16','209.222.0.0/16','216.155.128.0/16','217.69.0.0/16',
        '5.9.0.0/16','46.4.0.0/16','78.46.0.0/16','78.47.0.0/16','85.10.192.0/16','88.99.0.0/16','91.107.128.0/16','95.216.0.0/16','116.202.0.0/16','116.203.0.0/16','128.140.0.0/16','135.181.0.0/16','136.243.0.0/16','138.201.0.0/16','142.132.128.0/16','144.76.0.0/16','148.251.0.0/16','159.69.0.0/16','162.55.0.0/16','167.233.0.0/16','168.119.0.0/16','176.9.0.0/16','178.63.0.0/16','188.40.0.0/16','195.201.0.0/16','213.133.96.0/16','213.239.192.0/16',
        '5.39.0.0/16','5.135.0.0/16','37.59.0.0/16','37.187.0.0/16','46.105.0.0/16','51.38.0.0/16','51.68.0.0/16','51.75.0.0/16','51.77.0.0/16','51.89.0.0/16','51.91.0.0/16','51.178.0.0/16','51.195.0.0/16','51.210.0.0/16','51.222.0.0/16','51.254.0.0/16','54.36.0.0/16','54.37.0.0/16','54.38.0.0/16','79.137.0.0/16','87.98.128.0/16','91.121.0.0/16','91.134.0.0/16','92.222.0.0/16','94.23.0.0/16','135.125.0.0/16','137.74.0.0/16','139.99.0.0/16','141.94.0.0/16','141.95.0.0/16','142.4.192.0/16','142.44.128.0/16','144.217.0.0/16','145.239.0.0/16','146.59.0.0/16','147.135.0.0/16','149.56.0.0/16','149.202.0.0/16','151.80.0.0/16','152.228.0.0/16','158.69.0.0/16','164.132.0.0/16','167.114.0.0/16','176.31.0.0/16','178.32.0.0/16','178.33.0.0/16','188.165.0.0/16','192.95.0.0/16','192.99.0.0/16','193.70.0.0/16','198.27.64.0/16','198.50.128.0/16','198.100.144.0/16','198.244.128.0/16','198.245.48.0/16','213.32.0.0/16','213.186.32.0/16','217.182.0.0/16',
    );
}

function cybersec_is_datacenter_ip($ip) { 
    static $cache = array();

    $cache_key = substr($ip, 0, strrpos($ip, '.'));
    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }
    
    $subnets = cybersec_get_datacenter_subnets(); 
    foreach ($subnets as $subnet) { 
        if (cybersec_ip_in_subnet($ip, $subnet)) {
            $cache[$cache_key] = true;
            return true; 
        }
    } 
    $cache[$cache_key] = false;
    return false; 
}

function cybersec_is_blocked_user_agent($ua) { $blocked_uas = get_option(CYBERSEC_UA_BLOCKS_OPTION, array()); foreach ($blocked_uas as $blocked) { if (stripos($ua, $blocked) !== false) return true; } return false; }
function cybersec_get_blocked_user_agents() { return get_option(CYBERSEC_UA_BLOCKS_OPTION, array()); }
function cybersec_add_blocked_user_agent($ua) { $blocked = cybersec_get_blocked_user_agents(); if (!in_array($ua, $blocked)) { $blocked[] = $ua; update_option(CYBERSEC_UA_BLOCKS_OPTION, $blocked); return true; } return false; }
function cybersec_remove_blocked_user_agent($ua) { $blocked = cybersec_get_blocked_user_agents(); $key = array_search($ua, $blocked); if ($key !== false) { unset($blocked[$key]); update_option(CYBERSEC_UA_BLOCKS_OPTION, array_values($blocked)); return true; } return false; }
function cybersec_get_ua_stats() { return get_option('secwall_ua_stats', array()); }

// ==================== BLOCK 2: STATISTICS AND ALERTS ====================
function cybersec_track_repeat_offender($ip,$reason) { 
    $off=get_option(CYBERSEC_REPEAT_OPTION,array()); 
    if(!isset($off[$ip])) $off[$ip]=array('count'=>0,'first_seen'=>time(),'last_seen'=>time(),'reasons'=>array(),'country'=>cybersec_get_country_by_ip($ip),'ua'=>isset($_SERVER['HTTP_USER_AGENT'])?sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])):cybersec_translate('Unknown')); 
    $off[$ip]['count']++; $off[$ip]['last_seen']=time(); $off[$ip]['country']=cybersec_get_country_by_ip($ip); 
    foreach(explode(', ',$reason) as $r){$r=trim($r);if(!isset($off[$ip]['reasons'][$r]))$off[$ip]['reasons'][$r]=0;$off[$ip]['reasons'][$r]++;} 
    if(count($off)>500){uasort($off,function($a,$b){return $b['count']-$a['count'];});$off=array_slice($off,0,500,true);} 
    update_option(CYBERSEC_REPEAT_OPTION,$off); 
}

function cybersec_get_top_offenders($limit=20){ 
    $off = get_option(CYBERSEC_REPEAT_OPTION, array()); $permanent = cybersec_get_permanent_blocked_subnets(); $changed = false;
    foreach ($off as $ip => $data) { $subnet = cybersec_get_subnet_24($ip); if (isset($permanent[$subnet])) { unset($off[$ip]); $changed = true; } }
    if ($changed) update_option(CYBERSEC_REPEAT_OPTION, $off);
    uasort($off, function($a,$b){ return $b['count'] - $a['count']; });
    return array_slice($off, 0, $limit, true);
}

function cybersec_get_daily_stats(){ $t=gmdate('Y-m-d'); $s=get_option(CYBERSEC_STATS_OPTION,array()); return isset($s[$t])?$s[$t]:array('blocked'=>0,'allowed'=>0,'brute'=>0,'hidden_wp'=>0); }

function cybersec_increment_stat($key){ 
    $t = gmdate('Y-m-d');
    $cache_key = 'cybersec_stats_cache_' . $t;
    $stats = get_transient($cache_key);
    
    if ($stats === false) {
        $stats = get_option(CYBERSEC_STATS_OPTION, array());
        if (!isset($stats[$t])) {
            $stats[$t] = array('blocked' => 0, 'allowed' => 0, 'brute' => 0, 'hidden_wp' => 0);
        }
        // Clean old entries
        foreach (array_keys($stats) as $d) {
            if ($d < gmdate('Y-m-d', strtotime('-30 days'))) unset($stats[$d]);
        }
    }
    
    if (!isset($stats[$t][$key])) $stats[$t][$key] = 0;
    $stats[$t][$key]++;
    
    set_transient($cache_key, $stats, 60);
    
    // Schedule DB write on shutdown if not already scheduled
    if (!defined('CYBERSEC_STATS_FLUSH_SCHEDULED')) {
        define('CYBERSEC_STATS_FLUSH_SCHEDULED', true);
        add_action('shutdown', 'cybersec_flush_stats_cache');
    }
    
    if ($key === 'blocked') {
        cybersec_check_alert_threshold();
        delete_transient('cybersec_blocked_hour_' . gmdate('Y-m-d_H'));
    }
}

function cybersec_flush_stats_cache() {
    $t = gmdate('Y-m-d');
    $cache_key = 'cybersec_stats_cache_' . $t;
    $stats = get_transient($cache_key);
    
    if ($stats !== false) {
        update_option(CYBERSEC_STATS_OPTION, $stats, false); // false = no autoload
        delete_transient($cache_key);
    }
}

function cybersec_decrement_stat($key) {
    $t = gmdate('Y-m-d');
    $cache_key = 'cybersec_stats_cache_' . $t;
    $stats = get_transient($cache_key);
    
    if ($stats === false) {
        $stats = get_option(CYBERSEC_STATS_OPTION, array());
        if (!isset($stats[$t])) {
            $stats[$t] = array('blocked' => 0, 'allowed' => 0, 'brute' => 0, 'hidden_wp' => 0);
        }
    }
    
    if (!isset($stats[$t][$key])) $stats[$t][$key] = 0;
    if ($stats[$t][$key] > 0) $stats[$t][$key]--;
    
    set_transient($cache_key, $stats, 60);
    
    if (!defined('CYBERSEC_STATS_FLUSH_SCHEDULED')) {
        define('CYBERSEC_STATS_FLUSH_SCHEDULED', true);
        add_action('shutdown', 'cybersec_flush_stats_cache');
    }
}

function cybersec_check_alert_threshold(){ 
    $s=cybersec_get_settings(); if(!$s['alert_enabled']) return; $th=(int)$s['alert_threshold']; $mn=(int)$s['alert_minutes']; if($th<=0) return; 
    $rk='cybersec_alert_'.gmdate('Y-m-d_H').'_'.floor(gmdate('i')/$mn); $c=get_transient($rk); if($c===false) $c=0; $c++; set_transient($rk,$c,$mn*60); 
    if($c>=$th){ $sk='cybersec_alert_sent_'.$rk; if(!get_transient($sk)){ set_transient($sk,1,$mn*60); $em=cybersec_get_blocked_email(); $sn=get_bloginfo('name'); wp_mail($em,"[CyberPulse] $sn — $c " . cybersec_translate('blocks in') . " $mn " . cybersec_translate('min'), cybersec_translate('On site') . " $sn " . cybersec_translate('recorded') . " $c " . cybersec_translate('blocks in last') . " $mn " . cybersec_translate('minutes') . ".\n\n" . cybersec_translate('Possible bot attack') . ".\n\n" . cybersec_translate('Check logs') . ": ".admin_url('admin.php?page=cyber-security')); } } 
}

function cybersec_get_suspicious_ips() {
    global $wpdb;
    if (!$wpdb || !$wpdb->options) return array();
    $suspicious = array();
    $transients = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s AND option_value > %d LIMIT 100", '_transient_cybersec_rate_%', 3));
    if (is_array($transients)) { foreach ($transients as $transient) { $ip = str_replace('_transient_cybersec_rate_', '', $transient); $requests = get_transient('cybersec_rate_' . $ip); if ($requests && $requests > 3) $suspicious[$ip]['rate_1min'] = $requests; } }
    $transients_5min = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s AND option_value > %d LIMIT 100", '_transient_cybersec_rate_5min_%', 5));
    if (is_array($transients_5min)) { foreach ($transients_5min as $transient) { $ip = str_replace('_transient_cybersec_rate_5min_', '', $transient); $requests = get_transient('cybersec_rate_5min_' . $ip); if ($requests && $requests > 5) $suspicious[$ip]['rate_5min'] = $requests; } }
    return $suspicious;
}

function cybersec_get_threat_level() {
    $hour_key = 'cybersec_blocked_hour_' . gmdate('Y-m-d_H');
    $blocked = get_transient($hour_key);
    if ($blocked === false) {
        $bf = CYBERSEC_LOG_DIR . 'blocked-bots-' . gmdate('Y-m-d') . '.log';
        $blocked = 0;
        if (cybersec_fs_exists($bf)) {
            $content = cybersec_fs_get_contents($bf);
            $lines = !empty($content) ? explode("\n", $content) : array();
            $hour_start = gmdate('Y-m-d H:');
            foreach ($lines as $line) { if (strpos($line, '[' . $hour_start) === 0) $blocked++; }
        }
        set_transient($hour_key, $blocked, HOUR_IN_SECONDS / 2);
    }
    if ($blocked >= 50) return array('level' => 'critical', 'color' => '#ff2020', 'label' => 'Critical', 'text' => 'Attack!');
    if ($blocked >= 20) return array('level' => 'high', 'color' => '#ff8020', 'label' => 'High', 'text' => 'Many attacks');
    if ($blocked >= 5) return array('level' => 'medium', 'color' => '#ffb800', 'label' => 'Medium', 'text' => 'Elevated');
    return array('level' => 'low', 'color' => '#00e048', 'label' => 'Low', 'text' => 'Quiet');
}

// ==================== BLOCK 3: WHITELIST AND AUTO-DETECTION ====================
function cybersec_get_custom_whitelist(){ return get_option(CYBERSEC_WHITELIST_OPTION,array()); }

function cybersec_is_whitelisted($ip){ 
    if (get_transient('cybersec_test_mode_global')) return false;
    if(cybersec_is_legitimate_bot($ip)) return true; 
    if(cybersec_is_auto_whitelist_server_enabled() && $ip===cybersec_get_server_ip()) return true; 
    $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    $c=cybersec_get_custom_whitelist(); 
    if(!empty($c)) { foreach($c as $i){ if(empty($i['value'])) continue; 
        switch($i['type']){
            case'ip':if($ip===$i['value']) return true;break;
            case'subnet':if(cybersec_ip_in_subnet($ip,$i['value'])) return true;break;
            case'hostname':$h=gethostbyaddr($ip);if($h && $h!==$ip && stripos($h,$i['value'])!==false) return true;break;
            case'ua':$ua=isset($_SERVER['HTTP_USER_AGENT'])?sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])):'';if(stripos($ua,$i['value'])!==false) return true;break;
            case'url':if(stripos($request_uri, $i['value']) !== false) return true;break;
        } } }
    return false; 
}

function cybersec_get_hostname_cached($ip) {
    $cache_key = 'cybersec_host_' . md5($ip);
    $hostname = get_transient($cache_key);
    
    if ($hostname !== false) {
        return $hostname === '__null__' ? false : $hostname;
    }
    
    // Use WordPress HTTP API to avoid raw gethostbyaddr timeout issues
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_gethostbyaddr
    $hostname = @gethostbyaddr($ip);
    
    if ($hostname === $ip || $hostname === false) {
        set_transient($cache_key, '__null__', 6 * HOUR_IN_SECONDS);
        return false;
    }
    
    set_transient($cache_key, $hostname, 6 * HOUR_IN_SECONDS);
    return $hostname;
}

function cybersec_is_legitimate_bot($ip){ 
    $cache_key = 'cybersec_legit_bot_' . md5($ip); $cached = get_transient($cache_key);
    if ($cached === 'yes') return true; if ($cached === 'no') return false;
    $sn=cybersec_get_known_bot_subnets(); foreach($sn as $s){if(cybersec_ip_in_subnet($ip,$s)) { set_transient($cache_key, 'yes', HOUR_IN_SECONDS); return true; }} 
    $ua=isset($_SERVER['HTTP_USER_AGENT'])?sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])):''; $skip_auto_allow = false;
    $h=cybersec_get_hostname_cached($ip); if($h && $h!==$ip){
        foreach(array('googleusercontent.com','google.com') as $d){ if(stripos($h,$d)!==false){ if(stripos($ua,'Googlebot')!==false) { set_transient($cache_key, 'yes', HOUR_IN_SECONDS); return true; } $skip_auto_allow = true; break; } }
        if(!$skip_auto_allow){ foreach(array('search.msn.com','bing.com','msn.com','yandex.ru','yandex.net','yandex.com','spider.yandex','yandex-team.ru','baidu.com','baidu.jp','duckduckgo.com','ahrefs.com','semrush.com','moz.com','facebook.com','twitter.com') as $d){ if(stripos($h,$d)!==false) { set_transient($cache_key, 'yes', HOUR_IN_SECONDS); return true; } } }
    } 
    foreach(array('Googlebot','Bingbot','YandexBot','YandexMobileBot','Baiduspider','DuckDuckBot','Slurp','Facebot','Twitterbot','AhrefsBot','SemrushBot','MozBot') as $a){ if(stripos($ua,$a)!==false){ foreach($sn as $s){if(cybersec_ip_in_subnet($ip,$s)) { set_transient($cache_key, 'yes', HOUR_IN_SECONDS); return true; }} set_transient($cache_key, 'no', HOUR_IN_SECONDS); return false; } } 
    set_transient($cache_key, 'no', HOUR_IN_SECONDS); return false; 
}

function cybersec_get_known_bot_subnets(){ 
    return array(
        '66.249.64.0/19','64.233.160.0/19','72.14.192.0/18','74.125.0.0/16','216.239.32.0/19','40.77.167.0/24','65.55.0.0/16','131.253.0.0/16','157.55.0.0/16','207.46.0.0/16',
        '5.45.192.0/18','37.9.64.0/18','77.88.0.0/18','87.250.224.0/19','93.158.128.0/18','95.108.128.0/17','141.8.128.0/17','213.180.192.0/19','17.246.15.0/24',
        '20.191.0.0/16','40.76.0.0/16','40.80.0.0/16','52.142.0.0/16','52.143.0.0/16','52.148.0.0/16','52.150.0.0/16','5.255.128.0/17','185.191.128.0/17','195.211.0.0/16',
        '46.17.96.0/21','85.208.96.0/24','31.13.0.0/16','66.220.0.0/16','69.63.0.0/16','69.171.0.0/16','74.119.0.0/16','103.4.0.0/16','129.134.0.0/16','157.240.0.0/16',
        '173.252.0.0/16','179.60.0.0/16','185.60.0.0/16','204.15.0.0/16','199.16.0.0/16','199.59.0.0/16','104.244.0.0/16',
        '13.64.0.0/16','13.65.0.0/16','13.66.0.0/16','13.67.0.0/16','13.68.0.0/16','13.69.0.0/16','13.70.0.0/16','13.71.0.0/16','13.72.0.0/16','13.73.0.0/16','13.74.0.0/16','13.75.0.0/16','13.76.0.0/16','13.77.0.0/16','13.78.0.0/16','13.79.0.0/16','13.80.0.0/16','13.81.0.0/16','13.82.0.0/16','13.83.0.0/16','13.84.0.0/16','13.85.0.0/16','13.86.0.0/16','13.87.0.0/16','13.88.0.0/16','13.89.0.0/16','13.90.0.0/16','13.91.0.0/16','13.92.0.0/16','13.93.0.0/16','13.94.0.0/16','13.95.0.0/16','13.96.0.0/16','13.104.0.0/16','13.105.0.0/16',
        '54.236.0.0/16','91.108.0.0/16','149.154.0.0/16',
    ); 
}

function cybersec_auto_whitelist_server(){ 
    if(!cybersec_is_auto_whitelist_server_enabled()) return; 
    $sip=cybersec_get_server_ip(); $wl=cybersec_get_custom_whitelist(); $f=false; 
    foreach($wl as $i){if(cybersec_is_server_ip($i['value'])){$f=true;break;}} 
    if(!$f){$wl[]=array('type'=>'ip','value'=>$sip,'note'=>'auto_server'); update_option(CYBERSEC_WHITELIST_OPTION,$wl);} 
    $bl=get_option(CYBERSEC_BLOCKED_OPTION,array()); $ch=false; 
    foreach($bl as $k=>$sub){if(cybersec_ip_in_subnet($sip,$sub)){unset($bl[$k]);$ch=true;}} 
    if($ch) update_option(CYBERSEC_BLOCKED_OPTION,array_values($bl)); 
}
add_action('init','cybersec_auto_whitelist_server',1);

function cybersec_auto_whitelist_admin_ip() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) return;
    $ip = cybersec_get_visitor_ip();
    if (cybersec_is_server_ip($ip)) return;
    if (cybersec_is_whitelisted($ip)) return;
    $wl = cybersec_get_custom_whitelist();
    foreach ($wl as $item) { if ($item['type'] === 'ip' && $item['value'] === $ip) return; if ($item['type'] === 'subnet' && cybersec_ip_in_subnet($ip, $item['value'])) return; }
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        $user = wp_get_current_user(); $username = $user ? $user->user_login : 'unknown';
        $wl[] = array('type' => 'ip', 'value' => $ip, 'note' => cybersec_translate('Auto: IP administrator') . ' (' . $username . ')');
        update_option(CYBERSEC_WHITELIST_OPTION, $wl);
        cybersec_audit_log('WHITELIST_ADDED', cybersec_translate('Automatically added administrator IP') . ": {$ip} ({$username})");
    }
}
add_action('admin_init', 'cybersec_auto_whitelist_admin_ip', 10);

// ==================== BLOCK 3.5: PERIODIC CLEANUP ====================
function cybersec_cleanup_expired_temp_blocks(){ 
    $tb=get_option(CYBERSEC_TEMP_BLOCKS_OPTION,array()); if(empty($tb)) return; $n=time(); $ch=false; 
    foreach($tb as $ip=>$exp){if($n>=$exp){unset($tb[$ip]);$ch=true;}} if($ch) update_option(CYBERSEC_TEMP_BLOCKS_OPTION,$tb); 
    if (!get_transient('cybersec_daily_transient_cleanup')) {
        global $wpdb;
        if ($wpdb && $wpdb->options) {
            $expired_transients = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s AND option_value < %d LIMIT 500", '_transient_timeout_cybersec_%', time()));
            if (!empty($expired_transients)) {
                foreach ($expired_transients as $expired) {
                    delete_option($expired);
                    delete_option(str_replace('_transient_timeout_', '_transient_', $expired));
                }
            }
            $orphan_transients = $wpdb->get_col($wpdb->prepare("SELECT t.option_name FROM $wpdb->options t LEFT JOIN $wpdb->options tt ON tt.option_name = CONCAT('_transient_timeout_', SUBSTRING(t.option_name, 12)) WHERE t.option_name LIKE %s AND tt.option_id IS NULL LIMIT 500", '_transient_cybersec_%'));
            if (!empty($orphan_transients)) {
                foreach ($orphan_transients as $orphan) {
                    delete_option($orphan);
                }
            }
        }
        set_transient('cybersec_daily_transient_cleanup', true, DAY_IN_SECONDS);
    }
}
add_action('init','cybersec_cleanup_expired_temp_blocks',1);

function cybersec_cleanup_stale_transients() {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || defined('REST_REQUEST')) return;
    $last_cleanup = get_transient('cybersec_last_transient_cleanup'); if ($last_cleanup) return; 
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        if ($load[0] > 5) return;
    }
    set_transient('cybersec_last_transient_cleanup', 1, HOUR_IN_SECONDS);
    global $wpdb; if (!$wpdb || !$wpdb->options) return;
    $now = time(); $cleaned = 0;
    $timeout_transients = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s LIMIT 100", '_transient_timeout_cybersec_rate_%'));
    if (is_array($timeout_transients)) { foreach ($timeout_transients as $timeout_name) { $expiry = get_option($timeout_name); if ($expiry && $expiry < $now) { delete_option($timeout_name); delete_option(str_replace('_transient_timeout_', '_transient_', $timeout_name)); $cleaned++; } } }
    $timeout_transients_5min = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s LIMIT 100", '_transient_timeout_cybersec_rate_5min_%'));
    if (is_array($timeout_transients_5min)) { foreach ($timeout_transients_5min as $timeout_name) { $expiry = get_option($timeout_name); if ($expiry && $expiry < $now) { delete_option($timeout_name); delete_option(str_replace('_transient_timeout_', '_transient_', $timeout_name)); $cleaned++; } } }
    $orphan_transients = $wpdb->get_results($wpdb->prepare("SELECT t.option_name FROM $wpdb->options t LEFT JOIN $wpdb->options tt ON tt.option_name = CONCAT('_transient_timeout_', SUBSTRING(t.option_name, 12)) WHERE t.option_name LIKE %s AND t.option_name NOT LIKE %s AND tt.option_id IS NULL LIMIT 100", '_transient_cybersec_rate_%', '_transient_timeout_%'));
    if (is_array($orphan_transients)) { foreach ($orphan_transients as $row) { delete_option($row->option_name); $cleaned++; } }
    $orphan_transients_5min = $wpdb->get_results($wpdb->prepare("SELECT t.option_name FROM $wpdb->options t LEFT JOIN $wpdb->options tt ON tt.option_name = CONCAT('_transient_timeout_', SUBSTRING(t.option_name, 12)) WHERE t.option_name LIKE %s AND t.option_name NOT LIKE %s AND tt.option_id IS NULL LIMIT 100", '_transient_cybersec_rate_5min_%', '_transient_timeout_%'));
    if (is_array($orphan_transients_5min)) { foreach ($orphan_transients_5min as $row) { delete_option($row->option_name); $cleaned++; } }
    $old_qcheck = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s AND option_value < %d LIMIT 100", '_transient_timeout_cybersec_qcheck_%', $now - 600));
    if (is_array($old_qcheck)) { foreach ($old_qcheck as $timeout_name) { delete_option($timeout_name); delete_option(str_replace('_transient_timeout_', '_transient_', $timeout_name)); $cleaned++; } }
    $orphan_qcheck = $wpdb->get_results($wpdb->prepare("SELECT t.option_name FROM $wpdb->options t LEFT JOIN $wpdb->options tt ON tt.option_name = CONCAT('_transient_timeout_', SUBSTRING(t.option_name, 12)) WHERE t.option_name LIKE %s AND t.option_name NOT LIKE %s AND tt.option_id IS NULL LIMIT 100", '_transient_cybersec_qcheck_%', '_transient_timeout_%'));
    if (is_array($orphan_qcheck)) { foreach ($orphan_qcheck as $row) { delete_option($row->option_name); $cleaned++; } }
    cybersec_check_total_log_size();
    if ($cleaned > 0) { cybersec_fs_put_contents(CYBERSEC_LOG_DIR . 'cleanup.log', '[' . current_time('Y-m-d H:i:s') . "] CLEANUP: " . cybersec_translate('removed') . " {$cleaned} " . cybersec_translate('expired/orphaned transients') . "\n", FILE_APPEND | LOCK_EX); }
}
add_action('init', 'cybersec_cleanup_stale_transients', 20);

function cybersec_check_total_log_size() {
    $total = 0; $files = array(); $log_files = glob(CYBERSEC_LOG_DIR . '*.log');
    if (!is_array($log_files)) return;
    foreach ($log_files as $f) { $size = filesize($f); $total += $size; $files[$f] = $size; }
    if ($total > CYBERSEC_MAX_TOTAL_LOG_SIZE) {
        uksort($files, function($a, $b) { return filemtime($a) - filemtime($b); });
        $target = CYBERSEC_MAX_TOTAL_LOG_SIZE * 0.6;
        foreach ($files as $file => $size) { if ($total <= $target) break; wp_delete_file($file); $total -= $size; }
    }
}

// ==================== BLOCK 4: PERMANENT BLOCKING ====================
function cybersec_get_permanent_blocked_subnets() { $permanent = get_option(CYBERSEC_PERMANENT_BLOCKED_SUBNETS, array()); if (!is_array($permanent)) $permanent = array(); return $permanent; }

function cybersec_add_permanent_blocked_subnet($subnet, $reason = '', $ip = '') {
    $permanent = cybersec_get_permanent_blocked_subnets();
    if (!isset($permanent[$subnet])) { 
        $permanent[$subnet] = array('subnet' => $subnet, 'reason' => $reason, 'blocked_at' => current_time('Y-m-d H:i:s'), 'source_ip' => $ip); 
        update_option(CYBERSEC_PERMANENT_BLOCKED_SUBNETS, $permanent); 
        if (!empty($ip)) { $offenders = get_option(CYBERSEC_REPEAT_OPTION, array()); if (isset($offenders[$ip])) { unset($offenders[$ip]); update_option(CYBERSEC_REPEAT_OPTION, $offenders); } } 
        return true; 
    }
    return false;
}

function cybersec_remove_permanent_blocked_subnet($subnet) { $permanent = cybersec_get_permanent_blocked_subnets(); if (isset($permanent[$subnet])) { unset($permanent[$subnet]); update_option(CYBERSEC_PERMANENT_BLOCKED_SUBNETS, $permanent); return true; } return false; }
function cybersec_is_permanently_blocked($ip) { $permanent = cybersec_get_permanent_blocked_subnets(); foreach ($permanent as $subnet => $data) { if (cybersec_ip_in_subnet($ip, $subnet)) return true; } return false; }

// ==================== BLOCK 5: BOT CHECKS ====================
function cybersec_check_empty_user_agent(){ $ua=isset($_SERVER['HTTP_USER_AGENT'])?trim(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))):''; return empty($ua)||strlen($ua)<5; }

function cybersec_check_user_agent_anomalies(){ 
    $ua=isset($_SERVER['HTTP_USER_AGENT'])?sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])):'';
    $ip=cybersec_get_visitor_ip();
    if(cybersec_is_server_ip($ip)) return false;
    if(stripos($ua,'Google-Apps-Script')!==false) return false;
    if(stripos($ua,'ChatGPT-User')!==false) return false;
    if(stripos($ua,'OAI-SearchBot')!==false) return false;
    if(stripos($ua,'GoogleOther')!==false) return false;
    if(stripos($ua,'Google-InspectionTool')!==false) return false;
    if(stripos($ua,'GoogleProducer')!==false) return false;
    if(stripos($ua,'Google-Read-Aloud')!==false) return false;
    if(stripos($ua,'Google-Safety')!==false) return false;
    if(stripos($ua,'Google-Podcast')!==false) return false;
    if(stripos($ua,'Google-Cloud')!==false) return false;
    if(stripos($ua,'Google-Assessment')!==false) return false;
    if(stripos($ua,'Google-Notifier')!==false) return false;
    foreach(array('libwww-perl','curl/','wget/','Go-http-client','Java/','Apache-HttpClient','zgrab','masscan','nmap','sqlmap','nikto','nessus','HeadlessChrome','PhantomJS','Selenium','puppeteer','keys-so-bot','SERankingBacklinksBot','scrapy','BacklinksExtendedBot','AffsignalCrawler','SeopultContentAnalyzer','Photon/') as $a){if(stripos($ua,$a)!==false) return true;}
    if(stripos($ua,'python-requests')!==false) return true;
    if(stripos($ua,'python-urllib')!==false) return true;
    if(stripos($ua,'node-fetch')!==false) return true;
    if(stripos($ua,'axios/')!==false) return true;
    if(stripos($ua,'okhttp/')!==false) return true;
    $l=strlen($ua); if($l>0 && ($l<15 || $l>500)) return true; if($l>50 && strpos($ua,' ')===false) return true; 
    if(preg_match('/Chrome\/(\d+)\.0\.0\.\d+/',$ua,$m)){
        $mv=(int)$m[1]; 
        if(stripos($ua,'Mobile')!==false||stripos($ua,'Android')!==false) return false; 
        $rb=array('YaBrowser','YaApp_Android','YaSearchBrowser','OPR/','Edg/','EdgiOS','EdgA','SamsungBrowser','Brave','Vivaldi','CriOS','FxiOS','Silk/','; wv)','Instagram','FBAN','FBAV','Snapchat','TikTok','Pinterest'); 
        foreach($rb as $b){if(stripos($ua,$b)!==false) return false;} 
        if(preg_match('/(Windows NT|Macintosh|X11; Linux|x86_64|Intel Mac OS X)/',$ua)) return false;
        if($mv > 200) return true;
        if($mv >= 100 && $mv <= 200) {
            if(preg_match('/Build\/[A-Z0-9]+/', $ua)) return false; 
            if(preg_match('/; [A-Z][A-Z0-9]+\)/', $ua)) return false;
            if(stripos($ua, 'Mobile') !== false) return false;
            if(stripos($ua, 'Android') !== false) return false;
            if(preg_match('/(Windows NT|Macintosh|X11; Linux|x86_64|Intel Mac OS X)/', $ua)) return false;
            return true; 
        }
    }
    if(preg_match('/Windows NT/',$ua) && stripos($ua,'Safari')!==false && stripos($ua,'Chrome')===false) return true; 
    if(preg_match('/Chrome\/([1-3]?\d)\./',$ua,$m)){$v=(int)$m[1]; if($v<70){$real=array('YaBrowser','OPR/','Edg/','SamsungBrowser','Brave','Vivaldi','CriOS','FxiOS');$ir=false;foreach($real as $r){if(stripos($ua,$r)!==false){$ir=true;break;}}if(!$ir) return true;}}
    $al=isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])?trim(sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT_LANGUAGE']))):''; if(empty($al) && preg_match('/Chrome\/\d+\.0\.0\.\d+/',$ua)) return true; 
    if(preg_match('/Safari\/\d+\.\d+.*Chrome\//',$ua) && stripos($ua,'Mobile')===false) return true;
    if(preg_match('/iPhone OS (\d+)_/', $ua, $m)) { $ios_ver = (int)$m[1]; if($ios_ver < 14) { if(stripos($ua, 'Safari') !== false && stripos($ua, 'Chrome') === false) return true; } }
    return false; 
}

function cybersec_check_empty_accept_language(){ if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) || !empty($_SERVER['HTTP_VIA'])) return false; return empty(trim(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])?sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT_LANGUAGE'])):'')); }

function cybersec_check_fake_referer(){ 
    $ref=isset($_SERVER['HTTP_REFERER'])?sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])):''; 
    if(empty($ref)) return false; 
    if(stripos($ref,wp_parse_url(get_site_url(),PHP_URL_HOST))!==false) return false; 
    if(preg_match('#^https?[^:]#i', $ref)) return true;
    foreach(array('buttons-for-website.com','semalt.com','darodar.com','ilovevitaly.com','priceg.com','blackhatworth.com','hulfingtonpost.com','o-o-6-o-o.com','social-buttons.com') as $d){ if(stripos($ref,$d)!==false) return true; } 
    return false; 
}
function cybersec_check_cloudflare_origin(){ if(!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return false; $hs=false; foreach(array('HTTP_CF_IPCOUNTRY','HTTP_CF_RAY','HTTP_CF_VISITOR') as $h){if(!empty($_SERVER[$h])){$hs=true;break;}} return $hs && empty($_SERVER['HTTP_CF_CONNECTING_IP']); }
function cybersec_check_ipv6_connection($ip){ return filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV6)!==false; }
function cybersec_check_http_1_0(){ $p=isset($_SERVER['SERVER_PROTOCOL'])?sanitize_text_field(wp_unslash($_SERVER['SERVER_PROTOCOL'])):''; return $p==='HTTP/1.0' || $p==='HTTP/1'; }

function cybersec_check_suspicious_headers(){ 
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) || !empty($_SERVER['HTTP_VIA']) || !empty($_SERVER['HTTP_X_FORWARDED_HOST'])) return false;
    foreach(array('HTTP_X_HTTP_METHOD_OVERRIDE','HTTP_X_METHOD_OVERRIDE','HTTP_X_ORIGINAL_URL','HTTP_X_REWRITE_URL','HTTP_X_HTTP_METHOD') as $h){if(!empty($_SERVER[$h])) return true;} 
    $a=isset($_SERVER['HTTP_ACCEPT'])?sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT'])):''; 
    if(empty($a) || $a==='*/*'){ $ua=isset($_SERVER['HTTP_USER_AGENT'])?sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])):''; if(preg_match('/Chrome\/(\d+)\.(\d+)\.(\d+)\.(\d+)/',$ua,$m)){if((int)$m[2]>0 || (int)$m[3]>0) return false;} if(preg_match('/Firefox\/(\d+\.\d+)/',$ua)) return false; if(preg_match('/(Chrome|Firefox|Safari|Edge|Opera)/i',$ua)) return true; } 
    return false; 
}

function cybersec_check_direct_ip_access(){ $h=isset($_SERVER['HTTP_HOST'])?sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])):''; if(filter_var($h,FILTER_VALIDATE_IP)){$sh=wp_parse_url(get_site_url(),PHP_URL_HOST); if($h!==$sh) return true;} return false; }
function cybersec_check_bad_referer_spam(){ return cybersec_check_fake_referer(); }

// ==================== BLOCK 6: BRUTE FORCE AND WP HIDING ====================
function cybersec_is_login_page(){ 
    $ip = cybersec_get_visitor_ip(); if (cybersec_is_server_ip($ip)) return false;
    $logged_in = false;
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    if (isset($_COOKIE)) { $prefixes = array('wordpress_logged_in_','wordpress_sec_','wordpressuser_','wp-settings-','wp-postpass_'); foreach ($_COOKIE as $name => $value) { foreach ($prefixes as $prefix) { if (strpos($name, $prefix) === 0) { $logged_in = true; break 2; } } } }
    if (!$logged_in && function_exists('is_user_logged_in') && is_user_logged_in()) $logged_in = true;
    if (!$logged_in && !empty($_REQUEST['_wpnonce'])) $logged_in = true;
    // phpcs:enable
    if ($logged_in) return false;
    $u=isset($_SERVER['REQUEST_URI'])?sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])):''; 
    if(strpos($u,'wp-login.php')!==false) return true; if(strpos($u,'xmlrpc.php')!==false) return true;
    if(strpos($u,'wp-admin')!==false && strpos($u,'admin-ajax.php')===false && strpos($u,'async-upload.php')===false) return true;
    return false; 
}

function cybersec_check_brute_force(){ 
    $s=cybersec_get_settings(); if(!$s['brute_enabled']) return; if(!cybersec_is_login_page()) return; 
    $ip=cybersec_get_visitor_ip(); if (cybersec_is_server_ip($ip)) return; if (cybersec_is_whitelisted($ip)) return;
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    if (cybersec_is_permanently_blocked($ip)) { cybersec_show_block_page($ip, esc_html(cybersec_translate("IP in permanent block list (brute force)"))); exit; }
    $mx=(int)$s['brute_max_attempts']; $wn=(int)$s['brute_window_minutes']; $br=get_option(CYBERSEC_BRUTE_OPTION,array()); $n=time(); 
    if(!isset($br[$ip])) $br[$ip]=array('attempts'=>0,'first'=>0); 
    if($br[$ip]['first']<$n-($wn*60)){$br[$ip]['attempts']=0;$br[$ip]['first']=$n;} if($br[$ip]['first']==0) $br[$ip]['first']=$n; $br[$ip]['attempts']++; 
    if($br[$ip]['attempts']>$mx){ $attempts = $br[$ip]['attempts']; $subnet = cybersec_get_subnet_24($ip); cybersec_add_permanent_blocked_subnet($subnet, esc_html(cybersec_translate('Brute force wp-login') . ' (' . $attempts . ' ' . cybersec_translate('attempts') . ')'), $ip); unset($br[$ip]); update_option(CYBERSEC_BRUTE_OPTION, $br); cybersec_increment_stat('brute'); cybersec_log_blocked_bot($ip, esc_html(cybersec_translate('Brute force wp-login') . " ({$attempts} " . cybersec_translate('attempts') . ") — " . cybersec_translate('PERMANENTLY BLOCKED'))); cybersec_show_block_page($ip, esc_html(cybersec_translate("Brute force protection. Subnet permanently blocked."))); exit; } 
    // phpcs:enable
    update_option(CYBERSEC_BRUTE_OPTION,$br); 
}
add_action('init','cybersec_check_brute_force',0);

function cybersec_hide_wordpress(){ 
    $s=cybersec_get_settings(); if(!$s['hide_wp']) return; if(is_admin()||wp_doing_ajax()||wp_doing_cron()) return; 
    $ip=cybersec_get_visitor_ip(); if(cybersec_is_whitelisted($ip)) return; 
    $u=isset($_SERVER['REQUEST_URI'])?strtok(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])),'?'):''; 
    $pt=array('#/wp-admin$#','#/wp-admin/$#','#/wp-login\.php$#','#/xmlrpc\.php$#','#[?&]author=\d+#','#/wp-config\.php#','#/wp-content/plugins/#','#/wp-includes/#','#/\.env$#','#/\.git/#','#/readme\.html$#','#/wp-content/themes/#','#/wp-content/backup#','#/wp-content/cache#','#/wp-content/upgrade#','#/wp-content/uploads/woocommerce_uploads#','#/wp-json/wp/v2/users#'); 
    foreach($pt as $p){if(preg_match($p,$u)){cybersec_increment_stat('hidden_wp'); cybersec_show_block_page($ip, cybersec_translate('Access to WordPress system files is denied')); exit;}} 
}
add_action('template_redirect','cybersec_hide_wordpress',0);

// ==================== BLOCK 7: BROWSER HEADER DETECTION ====================
function cybersec_has_browser_headers() {
    if (!empty($_SERVER['HTTP_SEC_FETCH_SITE'])) return true;
    if (!empty($_SERVER['HTTP_SEC_FETCH_MODE'])) return true;
    if (!empty($_SERVER['HTTP_SEC_FETCH_DEST'])) return true;
    if (!empty($_SERVER['HTTP_SEC_CH_UA'])) return true;
    return false;
}

// ==================== BLOCK 8: REFERER CHECK ====================
function cybersec_safe_referer_check($ip, $mp) {
    if (!cybersec_is_referer_safe_check_enabled()) return false; 
    if (cybersec_is_whitelisted($ip)) return false;
    $referer = isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
    if (!empty($referer)) { if (preg_match('#^(https?)://https?://(.+)$#i', $referer, $rm)) { $referer = $rm[1] . '://' . $rm[2]; } elseif (preg_match('#^https?[^:]#i', $referer)) { $referer = 'https://' . ltrim($referer, 'https'); } }
    $is_homepage = ($mp === '' || $mp === '/' || strpos($mp, 'https://') === 0); 
    if ($is_homepage) return false;
    if (!empty($referer)) { $site_host = wp_parse_url(get_site_url(), PHP_URL_HOST); if (stripos($referer, $site_host) !== false) return false; }
    $rs = cybersec_get_referer_settings(); 
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
    if (cybersec_has_browser_headers()) { if (!isset($_COOKIE['cybersec_visited'])) { setcookie('cybersec_visited', '1', time() + 86400, COOKIEPATH, COOKIE_DOMAIN, false, true); } return false; }
    if (!isset($_COOKIE['cybersec_visited'])) { setcookie('cybersec_visited', '1', time() + 86400, COOKIEPATH, COOKIE_DOMAIN, false, true); $offenders = get_option(CYBERSEC_REPEAT_OPTION, array()); $ip_history = isset($offenders[$ip]) ? $offenders[$ip]['count'] : 0; if ($ip_history == 0) return false; }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) || !empty($_SERVER['HTTP_VIA'])) { if (!isset($_COOKIE['cybersec_visited'])) { setcookie('cybersec_visited', '1', time() + 86400, COOKIEPATH, COOKIE_DOMAIN, false, true); } return false; }
    $safe_ua_patterns = array('Googlebot','YandexBot','Bingbot','Baiduspider','facebookexternalhit','Twitterbot','TelegramBot','Applebot','PetalBot','LinkedInBot','Discordbot','Slackbot');
    foreach ($safe_ua_patterns as $pattern) { if (stripos($ua, $pattern) !== false) return false; }
    $request_key = 'cybersec_rate_' . $ip; 
$requests = wp_cache_get($request_key, 'cybersec_rate');
if ($requests === false) {
    $requests = get_transient($request_key);
    if ($requests !== false) {
        wp_cache_set($request_key, $requests, 'cybersec_rate', 30);
    }
} if ($requests === false) $requests = 0; $requests++; set_transient($request_key, $requests, 60);
    $request_key_5min = 'cybersec_rate_5min_' . $ip; $requests_5min = get_transient($request_key_5min); if ($requests_5min === false) $requests_5min = 0; $requests_5min++; set_transient($request_key_5min, $requests_5min, 300);
    $page_key = 'cybersec_page_' . $ip . '_' . md5($mp); $page_hits = get_transient($page_key); if ($page_hits === false) $page_hits = 0; $page_hits++; set_transient($page_key, $page_hits, 300);
    $should_block = false; $block_reason = '';
    if ($requests_5min >= $rs['rate_5min']) { $should_block = true; $block_reason = cybersec_translate('High frequency without Referer') . ' (' . $requests_5min . ' ' . cybersec_translate('requests in 5 min') . ')'; }
    elseif ($page_hits >= $rs['same_page'] && empty($referer)) { $should_block = true; $block_reason = cybersec_translate('Page parsing') . ' (' . $page_hits . ' ' . cybersec_translate('hits in 5 min without Referer') . ')'; }
    elseif ($requests >= $rs['rate_1min'] && empty($referer)) { $should_block = true; $block_reason = cybersec_translate('Request rate') . ' (' . $requests . '/' . cybersec_translate('min') . ') ' . cybersec_translate('without Referer'); }
    if ($should_block) return $block_reason;
    return false;
}

// ==================== BLOCK 9: MAIN CHECK AND LOGGING ====================
function cybersec_get_tracked_page_match() {
    $tracked = get_option(CYBERSEC_PAGES_OPTION, array()); if (empty($tracked)) return false;
    global $wp; $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    $current_path = trim(strtok(strtok($request_uri, '?'), '#'), '/'); $wp_slug = trim($wp->request, '/');
    usort($tracked, function($a, $b) { return strlen($b) - strlen($a); });
    foreach ($tracked as $slug) { $clean = trim($slug, '/'); if (strpos($slug, 'https://') === 0 || strpos($slug, 'http://') === 0) { if ($current_path === '' || $current_path === '/' || is_front_page() || is_home()) return $slug; continue; } if ($wp_slug === $clean) return $slug; if ($current_path === $clean) return $slug; if (strpos($current_path . '/', $clean . '/') === 0) return $slug; }
    return false;
}

function cybersec_is_bot_user_agent($ua, $ip) {
    $referer = isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
    $accept_lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT_LANGUAGE'])) : '';
    $has_bot_signs = false;
    if (empty($referer)) $has_bot_signs = true;
    if (empty($accept_lang)) $has_bot_signs = true;
    $offenders = get_option(CYBERSEC_REPEAT_OPTION, array());
    if (isset($offenders[$ip]) && $offenders[$ip]['count'] > 0) $has_bot_signs = true;
    $subnet = cybersec_get_subnet_24($ip); $permanent_blocked = cybersec_get_permanent_blocked_subnets();
    if (isset($permanent_blocked[$subnet])) $has_bot_signs = true;
    $rate_key = 'cybersec_rate_' . $ip; $rate = get_transient($rate_key); if ($rate && $rate > 2) $has_bot_signs = true;
    if (!$has_bot_signs) return false;
    $bot_patterns = array('/Android 10; K.*Chrome\/1[3-9][0-9]\.0\.0\.0/','/iPhone.*CPU iPhone OS 13_.*like Mac OS X/','/Chrome\/(5[7-9]|6[0-6])\.0\./','/Android [4-6]\.0.*Chrome\/[4-7][0-9]\.0\./','/keys-so-bot/i','/BacklinksExtendedBot/i','/AffsignalCrawler/i','/SeopultContentAnalyzer/i','/Photon\/\d/i');
    foreach ($bot_patterns as $pattern) { if (preg_match($pattern, $ua)) return true; }
    return false;
}

function cybersec_get_blockable_file_patterns() {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    
    $excluded_urls = array(
        '/cyber-security-api/',
    );
    
    foreach ($excluded_urls as $excluded_url) {
        if (strpos($request_uri, $excluded_url) !== false) {
            return array();
        }
    }
    
    return array(
    '/\.git(/|$)', '/\.env$', '/DS_Store$', '/wp-config\.php$', '/wp-config\.bak$',
    '/\.htaccess$', '/\.htpasswd$', '/\.user\.ini$', '/php\.ini$',
    '/Dockerfile$', '/docker-compose\.yml$', '/package\.json$',
    '/composer\.json$', '/composer\.lock$', '/vendor(/|$)', '/\.sql$', '/\.zip$',
    '/\.bak$', '/error_log$', '/debug\.log$', '/access\.log$',
    '/wp-content/debug\.log$', '/credentials$', '/passwd$', '/shadow$',
    '/secret$', '/id_rsa$', '/id_dsa$', '/\.pem$', '/\.key$', '/\.crt$',
    '/database\.sql$', '/dump\.sql$', '/license\.txt$', '/readme\.html$',
    '/phpinfo\.php$', '/info\.php$', '/test\.php$'
);
}

function cybersec_auto_ban_subnet($ip) {
    $subnet = cybersec_get_subnet_24($ip); $recent_blocks = get_transient('cybersec_subnet_attack_' . $subnet);
    if (!is_array($recent_blocks)) $recent_blocks = array('ips' => array(), 'count' => 0, 'first_seen' => time());
    if (!in_array($ip, $recent_blocks['ips'])) { $recent_blocks['ips'][] = $ip; $recent_blocks['count']++; }
    if ($recent_blocks['count'] >= 5) {
        cybersec_add_permanent_blocked_subnet($subnet, cybersec_translate('Mass attack') . ': ' . $recent_blocks['count'] . ' ' . cybersec_translate('IPs from subnet in an hour'), $ip);
        cybersec_log_blocked_bot($ip, cybersec_translate('AUTO-BAN SUBNET') . ": {$recent_blocks['count']} " . cybersec_translate('IPs from') . " {$subnet} — " . cybersec_translate('PERMANENTLY BLOCKED'));
        delete_transient('cybersec_subnet_attack_' . $subnet);
        return true;
    }
    set_transient('cybersec_subnet_attack_' . $subnet, $recent_blocks, HOUR_IN_SECONDS);
    return false;
}

function cybersec_check_all(){ 
    if(is_admin()||wp_doing_ajax()||wp_doing_cron()) return;
    if (defined('REST_REQUEST') && REST_REQUEST) return;
    
    $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    $static_extensions = array('.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.webp', '.woff', '.woff2', '.ttf', '.eot', '.ico', '.xml', '.mp4', '.mp3', '.webm', '.pdf');
    foreach ($static_extensions as $ext) {
        if (stripos($request_uri, $ext) !== false) {
            return;
        }
    }

        $ip = cybersec_get_visitor_ip();
    
    $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    if (get_transient('cybersec_test_mode_global')) return;
    
    $cache_key = 'cybersec_qcheck_' . $ip; 
$cached = wp_cache_get($cache_key, 'cybersec_quick');
if ($cached === false) {
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        wp_cache_set($cache_key, $cached, 'cybersec_quick', 60);
    }
}
    if ($cached === 'allowed') { cybersec_increment_stat('allowed'); $mp = cybersec_get_tracked_page_match(); if ($mp) cybersec_log_page_visit($mp, $ip); return; }
    if ($cached === 'blocked') { cybersec_show_block_page($ip, cybersec_translate('Repeat block (cached)')); exit; }
    
        if (!cybersec_is_whitelisted($ip)) {
    $xss_patterns = array('/<script[^>]*>.*?<\/script>/i','/javascript\s*:/i','/on\w+\s*=\s*["\']?[^"\'>]*["\']?/i','/<iframe[^>]*>/i','/<embed[^>]*>/i','/<object[^>]*>/i','/data\s*:\s*text\/html/i','/<link[^>]*rel\s*=\s*["\']?stylesheet["\']?[^>]*>/i','/<meta[^>]*http-equiv\s*=\s*["\']?refresh["\']?[^>]*>/i');
    $check_data = array(isset($_SERVER['QUERY_STRING']) ? sanitize_text_field(wp_unslash($_SERVER['QUERY_STRING'])) : '', isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '');
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    foreach ($_GET as $key => $val) { if (is_string($val)) $check_data[] = $val; if (is_string($key)) $check_data[] = $key; }
    // phpcs:enable
    // phpcs:disable WordPress.Security.NonceVerification.Missing
    // Check POST body for non-admin users only
    if (!current_user_can('manage_options')) {
        foreach ($_POST as $key => $val) { if (is_string($val)) $check_data[] = $val; if (is_string($key)) $check_data[] = $key; }
    }
    // phpcs:enable
    foreach ($check_data as $data) { foreach ($xss_patterns as $pattern) { if (preg_match($pattern, $data)) {
        $subnet = cybersec_get_subnet_24($ip); $safe_data = htmlspecialchars(substr($data, 0, 80), ENT_QUOTES, 'UTF-8'); $safe_data_full = htmlspecialchars(substr($data, 0, 100), ENT_QUOTES, 'UTF-8');
        cybersec_add_permanent_blocked_subnet($subnet, esc_html(cybersec_translate('XSS attack') . ': ' . $safe_data), $ip); cybersec_increment_stat('blocked');
        cybersec_log_blocked_bot($ip, esc_html(cybersec_translate('XSS attack in request') . ": " . $safe_data_full)); cybersec_show_block_page($ip, esc_html(cybersec_translate("XSS attack detected. Subnet permanently blocked."))); exit;
    } } }
}
    
    if (get_transient('cybersec_rehab_' . $ip)) { if (!isset($_COOKIE['cybersec_visited'])) { setcookie('cybersec_visited', '1', time() + 86400, COOKIEPATH, COOKIE_DOMAIN, false, true); } cybersec_increment_stat('allowed'); $mp = cybersec_get_tracked_page_match(); if ($mp) cybersec_log_page_visit($mp, $ip); return; }
    
    if (cybersec_has_browser_headers()) { if (!isset($_COOKIE['cybersec_visited'])) { setcookie('cybersec_visited', '1', time() + 86400, COOKIEPATH, COOKIE_DOMAIN, false, true); } cybersec_increment_stat('allowed'); $mp = cybersec_get_tracked_page_match(); if ($mp) cybersec_log_page_visit($mp, $ip); return; }
    
    $s = cybersec_get_settings();
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
    $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    $mp = cybersec_get_tracked_page_match();
    $op = get_option(CYBERSEC_BOT_OPTIONS, cybersec_get_default_bot_options());
    $rb = cybersec_is_real_browser();
    $datacenter_flag = (cybersec_is_datacenter_ip($ip) && !cybersec_is_whitelisted($ip));
    
    if (cybersec_is_blocked_user_agent($ua)) { $subnet = cybersec_get_subnet_24($ip); cybersec_add_permanent_blocked_subnet($subnet, cybersec_translate('Blocked User-Agent') . ': ' . substr($ua, 0, 80), $ip); cybersec_increment_stat('blocked'); cybersec_log_blocked_bot($ip, cybersec_translate('Blocked User-Agent') . ": " . substr($ua, 0, 100)); cybersec_show_block_page($ip, cybersec_translate("Your User-Agent is blacklisted. Access denied.")); exit; }
    
    $patterns = cybersec_get_blockable_file_patterns();
    if(!empty($patterns)) { $pattern_string = '#/(' . implode('|', $patterns) . ')#i'; if(preg_match($pattern_string, $request_uri) && !cybersec_is_whitelisted($ip)){ $subnet = cybersec_get_subnet_24($ip); cybersec_add_permanent_blocked_subnet($subnet, cybersec_translate('Hidden file scanner') . ': ' . $request_uri, $ip); cybersec_increment_stat('blocked'); cybersec_log_blocked_bot($ip, cybersec_translate('Hidden file scanner') . " (" . $request_uri . ") — " . cybersec_translate('PERMANENTLY BLOCKED')); cybersec_show_block_page($ip, cybersec_translate("Access to system files is denied.")); exit; } }
    
    if(cybersec_is_whitelisted($ip)){ cybersec_increment_stat('allowed'); cybersec_log_allowed_bot($ip); if($mp) cybersec_log_page_visit($mp, $ip); return; }
    if ($mp) { $referer_block_reason = cybersec_safe_referer_check($ip, $mp); if ($referer_block_reason) { cybersec_block_and_exit($ip, $referer_block_reason); } }
    if (cybersec_is_permanently_blocked($ip)) { cybersec_show_block_page($ip, cybersec_translate("IP in permanent block list")); exit; }
    $tb = get_option(CYBERSEC_TEMP_BLOCKS_OPTION, array()); if(isset($tb[$ip])){ if(time() < $tb[$ip]){ cybersec_show_block_page($ip, cybersec_translate("Temporary block.")); exit; } else { unset($tb[$ip]); update_option(CYBERSEC_TEMP_BLOCKS_OPTION, $tb); } }
    if (cybersec_is_bot_user_agent($ua, $ip)) { cybersec_block_and_exit($ip, cybersec_translate("Bot by User-Agent pattern + additional signs"), true); }
    
    $checks = array(
        array(cybersec_translate('Empty User-Agent'), function() use ($op) { return !empty($op['empty_user_agent']) && cybersec_check_empty_user_agent(); }),
        array(cybersec_translate('User-Agent anomaly'), function() use ($op) { return !empty($op['user_agent_anomalies']) && cybersec_check_user_agent_anomalies(); }),
        array(cybersec_translate('Fake referer'), function() use ($op) { return !empty($op['fake_referer']) && cybersec_check_fake_referer(); }),
        array(cybersec_translate('HTTP/1.0'), function() use ($op) { return !empty($op['http_1_0']) && cybersec_check_http_1_0(); }),
        array(cybersec_translate('Suspicious headers'), function() use ($op) { return !empty($op['suspicious_headers']) && cybersec_check_suspicious_headers(); }),
        array(cybersec_translate('Direct IP access'), function() use ($op) { return !empty($op['direct_ip_access']) && cybersec_check_direct_ip_access(); }),
        array(cybersec_translate('Spam referer'), function() use ($op) { return !empty($op['bad_referer_spam']) && cybersec_check_bad_referer_spam(); }),
        array(cybersec_translate('Empty Accept-Language'), function() use ($op, $rb) { return !empty($op['empty_accept_language']) && !$rb && cybersec_check_empty_accept_language(); }),
        array(cybersec_translate('Cloudflare bypass'), function() use ($op) { return !empty($op['cloudflare_origin']) && cybersec_check_cloudflare_origin(); }),
        array(cybersec_translate('IPv6'), function() use ($op, $rb, $ip) { return !empty($op['ipv6_connection']) && !$rb && cybersec_check_ipv6_connection($ip); }),
    );
    
    $reasons = array();
    foreach ($checks as $check) { if ($check[1]()) { $reasons[] = $check[0]; } }
    if ($datacenter_flag && count($reasons) >= 1) { $reasons[] = cybersec_translate('Hosting provider'); }
    if (!empty($reasons)) { cybersec_block_and_exit($ip, implode(', ', $reasons)); }
    
    set_transient('cybersec_qcheck_' . $ip, 'allowed', 60);
    if($mp) cybersec_log_page_visit($mp, $ip);
}
add_action('template_redirect','cybersec_check_all',2);

function cybersec_block_and_exit($ip, $reason, $permanent = false) {
    $mn = cybersec_get_auto_unblock_minutes();
    if ($mn > 0) { set_transient('cybersec_qcheck_' . $ip, 'blocked', $mn * 60 + 300); } else { set_transient('cybersec_qcheck_' . $ip, 'blocked', HOUR_IN_SECONDS); }
    cybersec_auto_ban_subnet($ip);
    if ($permanent) { $subnet = cybersec_get_subnet_24($ip); cybersec_add_permanent_blocked_subnet($subnet, $reason, $ip); cybersec_increment_stat('blocked'); cybersec_log_blocked_bot($ip, $reason . " — " . cybersec_translate('PERMANENTLY BLOCKED')); cybersec_show_block_page($ip, $reason . ". " . cybersec_translate("Subnet permanently blocked.")); exit; }
    cybersec_track_repeat_offender($ip, $reason); cybersec_increment_stat('blocked'); cybersec_log_blocked_bot($ip, $reason); 
    $offenders = get_option(CYBERSEC_REPEAT_OPTION, array()); $block_count = isset($offenders[$ip]) ? $offenders[$ip]['count'] : 0; 
    if ($block_count >= 5) { $subnet = cybersec_get_subnet_24($ip); cybersec_add_permanent_blocked_subnet($subnet, cybersec_translate('Auto-permaban after') . ' ' . $block_count . ' ' . cybersec_translate('blocks'), $ip); cybersec_log_blocked_bot($ip, cybersec_translate('AUTO-PERMABAN after') . " {$block_count} " . cybersec_translate('violations') . " — " . cybersec_translate('PERMANENTLY BLOCKED')); cybersec_show_block_page($ip, cybersec_translate("Repeat offender. Subnet permanently blocked.")); exit; } 
    $mn = cybersec_get_auto_unblock_minutes(); if ($mn > 0) { $tb = get_option(CYBERSEC_TEMP_BLOCKS_OPTION, array()); $tb[$ip] = time() + ($mn * 60); update_option(CYBERSEC_TEMP_BLOCKS_OPTION, $tb); } 
    cybersec_show_block_page($ip, $reason); exit;
}

function cybersec_log_page_visit($slug,$ip){ 
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
    if (stripos($ua, 'WP Fastest Cache') !== false || stripos($ua, 'Preload Bot') !== false) return;
    if (stripos($ua, 'WordPress/') !== false) return;
    $d=current_time('Y-m-d H:i:s'); $rf=isset($_SERVER['HTTP_REFERER'])?sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])):'Direct';
    $f=CYBERSEC_LOG_DIR.sanitize_file_name(str_replace('/','-',$slug)).'.log';
    if (cybersec_fs_exists($f) && filesize($f) > CYBERSEC_MAX_LOG_SIZE) { $content = cybersec_fs_get_contents($f); $content = substr($content, strlen($content) / 2); cybersec_fs_put_contents($f, $content); }
    cybersec_fs_put_contents($f,"[$d] IP: $ip | UA: $ua | Ref: $rf\n",FILE_APPEND|LOCK_EX); 
}

function cybersec_log_blocked_bot($ip,$reason){ 
    if (cybersec_is_admin_ip($ip)) return;
    $c=cybersec_get_country_by_ip($ip); 
    $log_file = CYBERSEC_LOG_DIR . 'blocked-bots-' . gmdate('Y-m-d') . '.log';
    if (cybersec_fs_exists($log_file) && filesize($log_file) > CYBERSEC_MAX_LOG_SIZE) { $content = cybersec_fs_get_contents($log_file); $content = substr($content, strlen($content) / 2); cybersec_fs_put_contents($log_file, $content); }
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : 'Unknown';
    $url = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
    cybersec_fs_put_contents($log_file,'['.current_time('Y-m-d H:i:s')."] BLOCKED | IP: $ip | Country: {$c} | Reason: $reason | UA: ".$ua.' | URL: '.$url."\n",FILE_APPEND|LOCK_EX);
    do_action('cybersec_bot_blocked', $ip, $reason);
}

function cybersec_log_allowed_bot($ip){ 
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
    if (stripos($ua, 'WP Fastest Cache') !== false || stripos($ua, 'Preload Bot') !== false) return;
    if (stripos($ua, 'WordPress/') !== false) return;
    $c=cybersec_get_country_by_ip($ip); 
    $log_file = CYBERSEC_LOG_DIR . 'allowed-bots-' . gmdate('Y-m-d') . '.log';
    if (cybersec_fs_exists($log_file) && filesize($log_file) > CYBERSEC_MAX_LOG_SIZE) { $content = cybersec_fs_get_contents($log_file); $content = substr($content, strlen($content) / 2); cybersec_fs_put_contents($log_file, $content); }
    $url = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
    cybersec_fs_put_contents($log_file,'['.current_time('Y-m-d H:i:s')."] ALLOWED | IP: $ip | Country: {$c} | UA: $ua | URL: ".$url."\n",FILE_APPEND|LOCK_EX);
    do_action('cybersec_bot_allowed', $ip);
}

function cybersec_log_unblock($ip, $method = 'human-test') {
    cybersec_fs_put_contents(CYBERSEC_LOG_DIR.'unblocks.log', '['.current_time('Y-m-d H:i:s')."] UNBLOCKED | IP: {$ip} | Method: {$method}\n", FILE_APPEND|LOCK_EX);
}

// ==================== BLOCK 10: 403 PAGE ====================
function cybersec_generate_unblock_token($ip) { $secret = wp_salt('nonce'); $expires = time() + (cybersec_get_auto_unblock_minutes() * 60) + 300; $data = $ip . '|' . $expires; $hash = hash_hmac('sha256', $data, $secret); return base64_encode($data . '|' . $hash); }
function cybersec_verify_unblock_token($token) { $secret = wp_salt('nonce'); $decoded = base64_decode($token); if (!$decoded) return false; $parts = explode('|', $decoded); if (count($parts) !== 3) return false; list($ip, $expires, $hash) = $parts; if (time() > (int)$expires) return false; $expected_hash = hash_hmac('sha256', $ip . '|' . $expires, $secret); if (!hash_equals($expected_hash, $hash)) return false; return $ip; }
function cybersec_generate_human_token($ip) { $secret = wp_salt('nonce'); $expires = time() + 300; $data = $ip . '|' . $expires . '|human_test'; $hash = hash_hmac('sha256', $data, $secret); return base64_encode($data . '|' . $hash); }
function cybersec_verify_human_token($token) { $secret = wp_salt('nonce'); $decoded = base64_decode($token); if (!$decoded) return false; $parts = explode('|', $decoded); if (count($parts) !== 4) return false; list($ip, $expires, $action, $hash) = $parts; if ($action !== 'human_test') return false; if (time() > (int)$expires) return false; $expected_hash = hash_hmac('sha256', $ip . '|' . $expires . '|' . $action, $secret); if (!hash_equals($expected_hash, $hash)) return false; return $ip; }

function cybersec_remove_all_blocks($ip) { 
    $tb = get_option(CYBERSEC_TEMP_BLOCKS_OPTION, array()); if (isset($tb[$ip])) { unset($tb[$ip]); update_option(CYBERSEC_TEMP_BLOCKS_OPTION, $tb); } 
    $subnet = cybersec_get_subnet_24($ip); $permanent = cybersec_get_permanent_blocked_subnets(); if (isset($permanent[$subnet])) { unset($permanent[$subnet]); update_option(CYBERSEC_PERMANENT_BLOCKED_SUBNETS, $permanent); } 
    delete_transient('cybersec_rate_' . $ip); delete_transient('cybersec_rate_5min_' . $ip); 
    $offenders = get_option(CYBERSEC_REPEAT_OPTION, array()); if (isset($offenders[$ip])) { unset($offenders[$ip]); update_option(CYBERSEC_REPEAT_OPTION, $offenders); } 
    $brute = get_option(CYBERSEC_BRUTE_OPTION, array()); if (isset($brute[$ip])) { unset($brute[$ip]); update_option(CYBERSEC_BRUTE_OPTION, $brute); }
    delete_transient('cybersec_qcheck_' . $ip);
    $used_tokens = get_transient('cybersec_used_unblock_tokens'); if (is_array($used_tokens) && count($used_tokens) > 500) { $used_tokens = array_slice($used_tokens, -500); set_transient('cybersec_used_unblock_tokens', $used_tokens, 7 * DAY_IN_SECONDS); }
    set_transient('cybersec_rehab_' . $ip, true, 5 * MINUTE_IN_SECONDS);
    cybersec_log_unblock($ip, 'human-test');
}

function cybersec_handle_self_unblock() { 
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['cybersec_unblock_token'])) { 
        $token = sanitize_text_field(wp_unslash($_GET['cybersec_unblock_token'])); $ip = cybersec_verify_unblock_token($token); 
        // phpcs:enable
        if ($ip) {
            $used_tokens = get_transient('cybersec_used_unblock_tokens'); if (!is_array($used_tokens)) $used_tokens = array();
            $token_hash = md5($token);
            if (in_array($token_hash, $used_tokens)) { 
    header('HTTP/1.1 403 Forbidden'); 
    wp_register_style('cybersec-token-error', false, array(), CYBERSEC_VERSION);
    wp_enqueue_style('cybersec-token-error');
    wp_add_inline_style('cybersec-token-error', 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;text-align:center;padding:60px 20px;background:#1a1d23;color:#e0e0e0}h1{color:#ff4040}');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>' . esc_html(cybersec_translate('Token Used')) . '</title>'; wp_print_styles('cybersec-token-error'); echo '</head><body><h1> ' . esc_html(cybersec_translate('Token Already Used')) . '</h1><p>' . esc_html(cybersec_translate('This unblock link has already been used. Request a new one.')) . '</p></body></html>'; exit; 
}
            $used_tokens[] = $token_hash; set_transient('cybersec_used_unblock_tokens', $used_tokens, 7 * DAY_IN_SECONDS);
            cybersec_remove_all_blocks($ip); wp_safe_redirect(home_url()); exit; 
        }
        header('HTTP/1.1 403 Forbidden'); 
wp_register_style('cybersec-token-error', false, array(), CYBERSEC_VERSION);
wp_enqueue_style('cybersec-token-error');
wp_add_inline_style('cybersec-token-error', 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;text-align:center;padding:60px 20px;background:#1a1d23;color:#e0e0e0}h1{color:#ff4040}');
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . esc_html(cybersec_translate('Error')) . '</title>'; wp_print_styles('cybersec-token-error'); echo '</head><body><h1> ' . esc_html(cybersec_translate('Invalid or expired link')) . '</h1><p>' . esc_html(cybersec_translate('Try refreshing the page in a few minutes.')) . '</p></body></html>'; exit; 
    } 
}
add_action('init', 'cybersec_handle_self_unblock', 5);

/**
 * Enqueue styles for the 403 block page via WordPress API
 */
function cybersec_enqueue_block_page_styles() {
    $inline_css = ':root{--bg:#0f1117;--card-bg:#1a1d27;--border:#2a2d3a;--text:#e2e4e9;--text-secondary:#9ca3af;--accent:#3b82f6;--accent-hover:#2563eb;--danger:#ef4444;--warning:#f59e0b;--success:#10b981;--input-bg:#111318;--radius:12px}*{margin:0;padding:0;box-sizing:border-box}body{font-family:\'Inter\',-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:var(--text);display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px;line-height:1.5;-webkit-font-smoothing:antialiased}.sw-card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius);padding:40px;max-width:480px;width:100%;text-align:center;box-shadow:0 25px 50px -12px rgba(0,0,0,.5)}.sw-icon{width:64px;height:64px;background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px}.sw-icon svg{width:28px;height:28px;stroke:var(--accent)}.sw-title{font-size:1.25rem;font-weight:600;margin-bottom:8px;color:#fff}.sw-description{font-size:.9rem;color:var(--text-secondary);margin-bottom:24px}.sw-ip-badge{display:inline-flex;align-items:center;gap:6px;background:var(--input-bg);border:1px solid var(--border);padding:6px 12px;border-radius:20px;font-family:\'JetBrains Mono\',\'Fira Code\',monospace;font-size:.8rem;color:var(--text-secondary);margin-bottom:24px}.sw-ip-badge::before{content:\'\';display:inline-block;width:6px;height:6px;background:var(--danger);border-radius:50%;margin-right:4px}.sw-auto-unblock{font-size:.8rem;color:var(--success);background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.2);padding:10px 16px;border-radius:8px;margin-bottom:20px}.sw-permanent-warning{font-size:.8rem;color:var(--warning);background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.2);padding:10px 16px;border-radius:8px;margin-bottom:20px}.sw-footer{margin-top:20px;padding-top:20px;border-top:1px solid var(--border)}.sw-footer-links{display:flex;justify-content:center;gap:16px;font-size:.8rem}.sw-footer-links a{color:var(--text-secondary);text-decoration:none;transition:color .2s}.sw-footer-links a:hover{color:var(--accent)}.sw-brand{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:16px;font-size:.75rem;color:#4b5563}.sw-brand svg{width:14px;height:14px}.sw-test-box{background:var(--input-bg);border:1px solid var(--border);border-radius:var(--radius);padding:24px;margin-bottom:24px}.sw-test-box h3{font-size:.9rem;font-weight:500;color:var(--text);margin-bottom:12px}.sw-test-box p{font-size:.8rem;color:var(--text-secondary);margin-bottom:16px}.sw-math{font-size:2rem;font-weight:700;letter-spacing:4px;margin:16px 0;color:#fff}.sw-answer-input{width:100px;padding:12px 16px;font-size:1.2rem;text-align:center;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:#fff;outline:none;transition:border-color .2s}.sw-answer-input:focus{border-color:var(--accent)}.sw-btn{display:inline-block;padding:12px 24px;border:none;border-radius:8px;font-weight:600;font-size:.9rem;cursor:pointer;transition:all .2s;text-decoration:none;color:#fff;background:var(--accent);margin-top:16px}.sw-btn:hover{background:var(--accent-hover)}.sw-btn:disabled{background:#374151;cursor:not-allowed;color:#9ca3af}.sw-error{color:var(--danger);font-size:.8rem;margin-top:12px;display:none}.sw-error a{color:var(--accent);text-decoration:underline}noscript div{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);border-radius:8px;padding:16px;margin-bottom:20px}noscript p{color:var(--danger);margin:0;font-size:.9rem}';
    
    wp_register_style('cybersec-block-page', false, array(), CYBERSEC_VERSION);
    wp_enqueue_style('cybersec-block-page');
    wp_add_inline_style('cybersec-block-page', $inline_css);
}

function cybersec_show_block_page($ip, $reason = '') {
    cybersec_show_unified_block_page($ip, $reason);
}

function cybersec_show_unified_block_page($ip, $reason = '') {
    $mn = cybersec_get_auto_unblock_minutes();
    $current_url = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
    $num1 = wp_rand(2, 9); $num2 = wp_rand(2, 9); $expected_answer = $num1 + $num2;
    $human_token = cybersec_generate_human_token($ip);
    $unblock_token = ($mn > 0) ? cybersec_generate_unblock_token($ip) : '';
    $unblock_url = $unblock_token ? home_url('?cybersec_unblock_token=' . urlencode($unblock_token)) : '';
    
    header('HTTP/1.1 403 Forbidden');
    header('X-Blocked-By: CyberPulse');
    if($reason) header('X-Block-Reason: '.$reason);
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
    
    ?><!DOCTYPE html>
<html lang="<?php echo esc_attr(substr(cybersec_get_locale(), 0, 2)); ?>">
<head>
    <meta charset="UTF-8"><meta name="robots" content="noindex,nofollow"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?php echo esc_html(cybersec_translate('Security Check')); ?> | CyberPulse</title>
    <?php cybersec_enqueue_block_page_styles(); ?>
    <?php wp_head(); ?>
</head>
<body>
    <div class="sw-card">
        <div class="sw-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
        <h1 class="sw-title"><?php echo esc_html(cybersec_translate('Security Check')); ?></h1>
        <p class="sw-description"><?php echo esc_html(cybersec_translate('We noticed suspicious traffic from your IP address. Confirm you are human to continue.')); ?></p>
        <div class="sw-ip-badge"><?php echo esc_html($ip);?></div>
        <div id="sw-simple-block"><div class="sw-test-box"><h3> <?php echo esc_html(cybersec_translate('Solve the example')); ?></h3><p><?php echo esc_html(cybersec_translate('Even if your IP is permanently blocked — the test will remove all restrictions.')); ?></p><div class="sw-math"><?php echo (int)$num1; ?> + <?php echo (int)$num2; ?> = ?</div><input type="number" id="sw-human-answer" class="sw-answer-input" placeholder="?" autofocus><br><button class="sw-btn" id="sw-verify-btn" onclick="verifyHuman()"> <?php echo esc_html(cybersec_translate('Unblock Access')); ?></button><div id="sw-test-error" class="sw-error"></div></div>
        <?php if($unblock_url):?><a href="<?php echo esc_url($unblock_url);?>" class="sw-btn"> <?php echo esc_html(cybersec_translate('Unblock via link')); ?></a><?php endif;?></div>
        <?php if($mn > 0): ?><p class="sw-auto-unblock"> <?php echo esc_html(cybersec_translate('Auto-unblock in')); ?> <strong><?php echo (int)$mn;?> <?php echo esc_html(cybersec_translate('min')); ?></strong></p><?php endif; ?>
        <div class="sw-footer"><div class="sw-footer-links"><a href="https://github.com/gataurus/cyberpulse" target="_blank" rel="noopener"> CyberPulse</a></div><div class="sw-brand"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg><?php echo esc_html(cybersec_translate('Protected by CyberPulse')); ?></div></div>
    </div>
        <?php
    // Register and enqueue a dummy script for inline JS
    wp_register_script('cybersec-block-page-js', false, array(), CYBERSEC_VERSION, true);
    wp_enqueue_script('cybersec-block-page-js');
    $inline_script = '
    function verifyHuman(){var a=document.getElementById("sw-human-answer").value,e="' . (int)$expected_answer . '",r=document.getElementById("sw-test-error"),t=document.getElementById("sw-verify-btn");if(!a){r.textContent="' . esc_js(cybersec_translate('Enter answer')) . '";r.style.display="block";return}if(a!==e){r.innerHTML="' . esc_js(cybersec_translate('Incorrect.')) . ' <a href=\"' . esc_url($current_url) . '\">' . esc_js(cybersec_translate('Refresh the page')) . '</a> ' . esc_js(cybersec_translate('and try again.')) . '";r.style.display="block";document.getElementById("sw-human-answer").value="";return}r.style.display="none";t.disabled=true;t.innerHTML="' . esc_js(cybersec_translate('Verifying...')) . '";var d=new FormData();d.append("action","cybersec_human_verify");d.append("cybersec_human_token","' . esc_js($human_token) . '");d.append("cybersec_human_answer",a);d.append("cybersec_human_expected",e);d.append("cybersec_redirect_url","' . esc_js($current_url) . '");fetch("' . esc_js(admin_url('admin-ajax.php')) . '",{method:"POST",body:d}).then(function(r){return r.json()}).then(function(d){if(d.success){t.innerHTML=" ' . esc_js(cybersec_translate('Access granted!')) . '";setTimeout(function(){window.location.href=d.data.redirect||"/"},1500)}else{r.innerHTML=(d.data.message||"' . esc_js(cybersec_translate('Error')) . '")+" <a href=\"' . esc_url($current_url) . '\">' . esc_js(cybersec_translate('Refresh the page')) . '</a>.";r.style.display="block";t.disabled=false;t.innerHTML=" ' . esc_js(cybersec_translate('Unblock Access')) . '";document.getElementById("sw-human-answer").value=""}}).catch(function(){r.innerHTML="' . esc_js(cybersec_translate('Connection error.')) . ' <a href=\"' . esc_url($current_url) . '\">' . esc_js(cybersec_translate('Refresh the page')) . '</a>";r.style.display="block";t.disabled=false;t.innerHTML=" ' . esc_js(cybersec_translate('Unblock Access')) . '"});}
    document.getElementById("sw-human-answer")&&document.getElementById("sw-human-answer").addEventListener("keypress",function(e){if(e.key==="Enter")verifyHuman()});
    ';
    wp_add_inline_script('cybersec-block-page-js', $inline_script);
    ?>
</body>
</html>
<?php
    exit;
}

// ==================== BLOCK 11: ADMIN MENU AND AJAX ====================
function cybersec_add_admin_menu(){ 
    add_menu_page(
        'CyberPulse',
        'CyberPulse',
        'manage_options',
        'cyber-security',
        'cybersec_admin_page',
        'dashicons-shield',
        30
    ); 
}
add_action('admin_menu','cybersec_add_admin_menu');

// Make the icon bigger
add_action('admin_enqueue_scripts', function() {
    wp_register_style('cyberpulse-admin-menu-icon', false, array(), CYBERSEC_VERSION);
    wp_enqueue_style('cyberpulse-admin-menu-icon');
    wp_add_inline_style('cyberpulse-admin-menu-icon', '
        #adminmenu .toplevel_page_cyber-security .wp-menu-image img {
            width: 28px !important;
            height: 28px !important;
            padding: 4px 0 !important;
        }
    ');
});

function cybersec_admin_bar($wp_admin_bar) {
    if (!current_user_can('manage_options')) return;
    $threat = cybersec_get_threat_level(); $stats = cybersec_get_daily_stats(); $count = (int)$stats['blocked'];
    $shield_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 36" width="21" height="21" style="vertical-align:middle;margin-right:3px;margin-bottom:3px;"><path fill="none" stroke="#000000" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M 18.0,30.8 C 17.2,30.4 9.2,25.9 7.6,10.5 C 7.5,7.5 12.3,5.2 18.0,5.2 C 23.7,5.2 28.5,7.5 28.4,10.5 C 26.8,26.8 18.0,30.8 18.0,30.8 Z"/><path fill="#D0CFCE" d="M 18.0,5.5 C 12.5,5.5 8.0,7.8 8.0,10.5 L 8.0,10.6 C 9.5,25.8 17.3,30.3 18.0,30.7 L 18.0,30.7 C 18.0,30.7 26.6,26.7 28.0,10.7 C 28.2,7.9 23.5,5.5 18.0,5.5 Z"/><path fill="#D0CFCE" d="M 18.0,27.5 L 18.0,27.5 C 17.6,27.2 11.3,23.9 10.0,11.5 L 10.0,11.5 C 10.0,9.3 13.6,7.5 18.0,7.5 C 22.4,7.5 26.2,9.3 26.0,11.5 C 24.8,24.2 18.0,27.5 18.0,27.5 Z"/><path fill="#E60012" d="M 17.6,9.0 L 19.0,11.0 L 17.6,13.1 L 19.0,15.0 L 17.6,17.0 L 19.0,19.0 L 17.6,21.0 L 19.0,23.1 L 17.6,25.1 L 18.0,27.7 C 18.0,27.7 10.5,22.2 10.0,11.7 C 10.0,11.7 10.0,7.7 18.0,7.7 L 17.6,9.0 Z"/><path fill="#FFFFFF" d="M 18.0,7.7 L 17.6,9.0 L 19.0,11.0 L 17.6,13.1 L 19.0,15.0 L 17.6,17.0 L 19.0,19.0 L 17.6,21.0 L 19.0,23.1 L 17.6,25.1 L 18.0,27.7 C 18.0,27.7 25.5,23.1 26.0,11.7 C 26.0,11.5 25.5,7.7 18.0,7.7 Z"/></svg>';
    $wp_admin_bar->add_node(array('id'=>'cybersec-stats','title'=>'<span style="color:' . esc_attr($threat['color']) . ';font-weight:700;">' . $shield_svg . (int)$count . '</span>','href'=>esc_url(admin_url('admin.php?page=cyber-security')),'meta'=>array('title'=>'CyberPulse — ' . esc_attr($threat['label']) . ' (' . (int)$count . ' ' . esc_attr(cybersec_translate('blocks today')) . ')')));
}
add_action('admin_bar_menu', 'cybersec_admin_bar', 999);

// ==================== AJAX HANDLERS ====================
add_action('wp_ajax_cybersec_ajax_temp_block', function(){ 
    check_ajax_referer('cybersec_ajax_action','cybersec_ajax_nonce'); 
    if (!current_user_can('manage_options')) { wp_send_json_error(array('message' => 'Access denied')); }
    $input = sanitize_text_field(wp_unslash($_POST['subnet'] ?? '')); 
$minutes = isset($_POST['minutes']) ? absint(wp_unslash($_POST['minutes'])) : 30; 
    $blocked = 0; $skipped = 0; 
    if(!empty($input)){ 
        if(strpos($input,'/')!==false){ $subnet = $input; $ip = strtok($input,'/'); if(filter_var($ip,FILTER_VALIDATE_IP) && !cybersec_is_whitelisted($ip)){ $subs = get_option(CYBERSEC_BLOCKED_OPTION,array()); if(!in_array($subnet,$subs)){ $subs[]=$subnet; update_option(CYBERSEC_BLOCKED_OPTION,array_values($subs)); $blocked++; } } else { $skipped++; } } 
        else { $ip = $input; if(filter_var($ip,FILTER_VALIDATE_IP) && !cybersec_is_whitelisted($ip)){ $subnet = cybersec_get_subnet_24($ip); $subs = get_option(CYBERSEC_BLOCKED_OPTION,array()); if(!in_array($subnet,$subs)){ $subs[]=$subnet; update_option(CYBERSEC_BLOCKED_OPTION,array_values($subs)); $blocked++; } } else { $skipped++; } } 
    } 
    wp_send_json_success(array('blocked'=>$blocked,'skipped'=>$skipped)); 
});

add_action('wp_ajax_cybersec_block_ua', function() {
    check_ajax_referer('cybersec_ajax_action', 'cybersec_ajax_nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error(array('message' => 'Access denied')); }
    $ua = sanitize_text_field(wp_unslash($_POST['user_agent'] ?? ''));
    if (empty($ua)) wp_send_json_error(array('message' => cybersec_translate('Empty User-Agent')));
    $result = cybersec_add_blocked_user_agent($ua);
    if ($result) { wp_send_json_success(array('message' => cybersec_translate('User-Agent added to blacklist.'))); }
    else { wp_send_json_error(array('message' => cybersec_translate('User-Agent already in blacklist.'))); }
});

add_action('wp_ajax_cybersec_unblock_ua', function() {
    check_ajax_referer('cybersec_ajax_action', 'cybersec_ajax_nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error(array('message' => 'Access denied')); }
    $ua = sanitize_text_field(wp_unslash($_POST['user_agent'] ?? ''));
    if (empty($ua)) wp_send_json_error(array('message' => cybersec_translate('Empty User-Agent')));
    $result = cybersec_remove_blocked_user_agent($ua);
    if ($result) { wp_send_json_success(array('message' => cybersec_translate('User-Agent removed from blacklist.'))); }
    else { wp_send_json_error(array('message' => cybersec_translate('User-Agent not found in blacklist.'))); }
});

add_action('wp_ajax_cybersec_ajax_clear', function(){ 
    check_ajax_referer('cybersec_ajax_action','cybersec_ajax_nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error(array('message' => 'Access denied')); }
    $action = sanitize_text_field(wp_unslash($_POST['sub_action'] ?? ''));
    switch ($action) {
        case 'clear_log': $slug = sanitize_text_field(wp_unslash($_POST['slug'] ?? '')); $f = CYBERSEC_LOG_DIR . sanitize_file_name(str_replace('/','-',$slug)) . '.log'; if(cybersec_fs_exists($f)) cybersec_fs_put_contents($f,''); break;
        case 'clear_all_page_logs': $log_files = glob(CYBERSEC_LOG_DIR.'*.log'); if (is_array($log_files)) { foreach($log_files as $f){ $bn=basename($f); if(strpos($bn, 'blocked-bots-') === false && strpos($bn, 'allowed-bots-') === false) wp_delete_file($f); } } break;
        case 'clear_blocked_log': cybersec_fs_put_contents(CYBERSEC_LOG_DIR . 'blocked-bots-' . gmdate('Y-m-d') . '.log', ''); break;
        case 'clear_allowed_log': cybersec_fs_put_contents(CYBERSEC_LOG_DIR . 'allowed-bots-' . gmdate('Y-m-d') . '.log', ''); break;
        case 'clear_audit_log': cybersec_fs_put_contents(CYBERSEC_LOG_DIR.'audit.log',''); break;
        case 'reset_stats': update_option(CYBERSEC_STATS_OPTION,array()); update_option(CYBERSEC_REPEAT_OPTION,array()); cybersec_fs_put_contents(CYBERSEC_LOG_DIR . 'blocked-bots-' . gmdate('Y-m-d') . '.log', ''); cybersec_fs_put_contents(CYBERSEC_LOG_DIR . 'allowed-bots-' . gmdate('Y-m-d') . '.log', ''); $log_files = glob(CYBERSEC_LOG_DIR.'*.log'); if (is_array($log_files)) { foreach($log_files as $f){ $bn=basename($f); if($bn!=='blocked-bots.log' && $bn!=='allowed-bots.log') wp_delete_file($f); } } break;
        case 'clear_offenders': update_option(CYBERSEC_REPEAT_OPTION, array()); break;
        case 'clear_ua_stats': update_option('cybersec_ua_stats', array()); break;
        case 'clear_brute': update_option(CYBERSEC_BRUTE_OPTION, array()); break;
        case 'clear_integrity_log': cybersec_fs_put_contents(CYBERSEC_LOG_DIR . 'integrity.log', ''); break;
        case 'reset_hashes': update_option('secwall_file_hashes', array()); break;
        case 'clear_all_bot_logs': cybersec_fs_put_contents(CYBERSEC_LOG_DIR . 'blocked-bots-' . gmdate('Y-m-d') . '.log', ''); cybersec_fs_put_contents(CYBERSEC_LOG_DIR . 'allowed-bots-' . gmdate('Y-m-d') . '.log', ''); break;
    }
    wp_send_json_success();
});

function cybersec_ajax_human_verify() {
    // phpcs:disable WordPress.Security.NonceVerification.Missing
    if (!isset($_POST['cybersec_human_token']) || !isset($_POST['cybersec_human_answer']) || !isset($_POST['cybersec_human_expected'])) { wp_send_json_error(array('message' => esc_html(cybersec_translate('Invalid parameters.')))); }
    $ip = cybersec_get_visitor_ip(); $attempts = get_transient('cybersec_human_attempts_' . $ip);
    if ($attempts && $attempts >= 10) { wp_send_json_error(array('message' => esc_html(cybersec_translate('Too many attempts. Wait 5 minutes.')))); }
    $attempts = ($attempts ? $attempts : 0) + 1; set_transient('cybersec_human_attempts_' . $ip, $attempts, 5 * MINUTE_IN_SECONDS);
    $token = sanitize_text_field(wp_unslash($_POST['cybersec_human_token'])); $answer = sanitize_text_field(wp_unslash($_POST['cybersec_human_answer'])); $expected = sanitize_text_field(wp_unslash($_POST['cybersec_human_expected']));
    $verified_ip = cybersec_verify_human_token($token);
    if ($verified_ip && $answer === $expected) { delete_transient('cybersec_human_attempts_' . $ip); cybersec_remove_all_blocks($verified_ip); $redirect = home_url(); if (!empty($_POST['cybersec_redirect_url'])) { $redirect = home_url(sanitize_text_field(wp_unslash($_POST['cybersec_redirect_url']))); } wp_send_json_success(array('redirect' => $redirect, 'message' => ' ' . esc_html(cybersec_translate('Block removed! Even permanent. Redirecting...')))); }
    else { wp_send_json_error(array('message' => esc_html(cybersec_translate('Incorrect answer. Refresh the page and try again.')))); }
    // phpcs:enable
}
add_action('wp_ajax_nopriv_cybersec_human_verify','cybersec_ajax_human_verify');
add_action('wp_ajax_cybersec_human_verify','cybersec_ajax_human_verify');

function cybersec_handle_simple_check() {
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['cybersec_use_simple'])) {
        if ($_GET['cybersec_use_simple'] === '1') { setcookie('cybersec_use_simple', '1', time() + 86400, COOKIEPATH, COOKIE_DOMAIN, false, true); }
        else { setcookie('cybersec_use_simple', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, false, true); }
        // phpcs:enable
        wp_safe_redirect(remove_query_arg('cybersec_use_simple')); exit;
    }
}
add_action('init', 'cybersec_handle_simple_check', 5);

// ==================== BLOCK 12: SECURITY FUNCTIONS ====================
function cybersec_hide_wp_version_init(){ $s=cybersec_get_settings(); if(empty($s['hide_wp_version'])) return; remove_action('wp_head','wp_generator'); add_filter('the_generator','__return_empty_string'); }
add_action('init','cybersec_hide_wp_version_init');

function cybersec_remove_script_versions($src){ $s=cybersec_get_settings(); if(empty($s['remove_script_versions'])) return $src; if(strpos($src,'ver=')!==false) $src=remove_query_arg('ver',$src); return $src; }
add_filter('style_loader_src','cybersec_remove_script_versions',9999); add_filter('script_loader_src','cybersec_remove_script_versions',9999);

function cybersec_hide_login_errors_filter(){ $s=cybersec_get_settings(); if(empty($s['hide_login_errors'])) return; add_filter('login_errors',function(){ return '<strong>' . esc_html(cybersec_translate('Error')) . '</strong>: ' . esc_html(cybersec_translate('Invalid login credentials.')); }); }
add_action('init','cybersec_hide_login_errors_filter');

function cybersec_disable_xmlrpc_pingbacks(){ $s=cybersec_get_settings(); if(empty($s['disable_xmlrpc_pingbacks'])) return; add_filter('xmlrpc_methods',function($m){ unset($m['pingback.ping'],$m['pingback.extensions.getPingbacks']); return $m; }); add_filter('wp_headers',function($h){ unset($h['X-Pingback']); return $h; }); }
add_action('init','cybersec_disable_xmlrpc_pingbacks');

function cybersec_force_secure_cookies() {
    $s = cybersec_get_settings();
    if (empty($s['force_secure_cookies'])) return;
    
    add_filter('secure_auth_cookie', '__return_true');
    add_filter('secure_logged_in_cookie', '__return_true');
}
add_action('init', 'cybersec_force_secure_cookies', 1);

function cybersec_rate_limiting(){ 
    $s=cybersec_get_settings(); if(empty($s['rate_limiting_enabled'])) return; if(is_admin()||wp_doing_ajax()||wp_doing_cron()) return; 
    $ip=cybersec_get_visitor_ip(); if(cybersec_is_whitelisted($ip)) return; if (cybersec_is_permanently_blocked($ip)) { header('HTTP/1.1 403 Forbidden'); exit; }
    $mr=(int)$s['rate_limit_requests']; $w=(int)$s['rate_limit_window']; $rk='cybersec_global_rate_'.$ip; $r=get_transient($rk); if($r===false)$r=0; $r++; set_transient($rk,$r,$w); 
    if($r>$mr){ cybersec_track_repeat_offender($ip, esc_html(cybersec_translate("Request flood"))); $off=get_option(CYBERSEC_REPEAT_OPTION,array()); $bc=isset($off[$ip])?$off[$ip]['count']:0;
        if($bc < 3) { cybersec_increment_stat('blocked'); cybersec_log_blocked_bot($ip, esc_html(cybersec_translate("Flood") . ": {$r} " . cybersec_translate('requests per') . " {$w} " . cybersec_translate('sec'))); }
        if($bc>=3){ $sub=cybersec_get_subnet_24($ip); cybersec_add_permanent_blocked_subnet($sub, esc_html(cybersec_translate("Flood attack") . " ({$r} " . cybersec_translate('requests') . "/{$w} " . cybersec_translate('sec') . ")"),$ip); cybersec_log_blocked_bot($ip, esc_html(cybersec_translate("Flood attack") . " — " . cybersec_translate('PERMANENTLY BLOCKED'))); cybersec_show_block_page($ip, esc_html(cybersec_translate("Too many requests. Subnet permanently blocked."))); exit; }
        $mn=cybersec_get_auto_unblock_minutes(); if($mn>0){ $tb=get_option(CYBERSEC_TEMP_BLOCKS_OPTION,array()); $tb[$ip]=time()+($mn*60); update_option(CYBERSEC_TEMP_BLOCKS_OPTION,$tb); } 
        cybersec_show_block_page($ip, esc_html(cybersec_translate("Too many requests. Limit") . ": {$mr} " . cybersec_translate('per') . " {$w} " . cybersec_translate('sec') . ".")); exit; } 
}
add_action('template_redirect','cybersec_rate_limiting',1);

function cybersec_rest_api_protection(){ $s=cybersec_get_settings(); if(empty($s['rest_api_protection'])) return; add_filter('rest_endpoints',function($ep){ if(isset($ep['/wp/v2/users'])) unset($ep['/wp/v2/users']); if(isset($ep['/wp/v2/users/(?P<id>[\d]+)'])&&!is_user_logged_in()) unset($ep['/wp/v2/users/(?P<id>[\d]+)']); return $ep; }); }
add_action('init','cybersec_rest_api_protection');

function cybersec_hide_plugins_themes(){ $s=cybersec_get_settings(); if(empty($s['hide_plugins_themes'])) return; if(is_admin()||wp_doing_ajax()) return; $uri=isset($_SERVER['REQUEST_URI'])?sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])):''; $bp=array('/wp-content/plugins/','/wp-content/themes/'); foreach($bp as $p){ if(stripos($uri,$p)!==false){ $exts=array('.css','.js','.png','.jpg','.jpeg','.gif','.svg','.webp','.woff','.woff2','.ttf','.eot','.ico'); $al=false; foreach($exts as $e){ if(stripos($uri,$e)!==false){$al=true;break;} } if(!$al){ $ip=cybersec_get_visitor_ip(); if(!cybersec_is_whitelisted($ip)){ cybersec_increment_stat('hidden_wp'); cybersec_show_block_page($ip, cybersec_translate("Access to plugins/themes is denied.")); exit; } } } } }
add_action('template_redirect','cybersec_hide_plugins_themes',3);

function cybersec_auto_clean_logs(){ $s=cybersec_get_settings(); $d=(int)$s['auto_clean_logs_days']; if($d<=0) return; $lc=get_transient('cybersec_logs_cleanup'); if($lc) return; set_transient('cybersec_logs_cleanup',1,DAY_IN_SECONDS); $cut=time()-($d*DAY_IN_SECONDS); $log_files = glob(CYBERSEC_LOG_DIR.'*.log'); if (is_array($log_files)) { foreach($log_files as $f){ if(filemtime($f)<$cut) wp_delete_file($f); } } $st=get_option(CYBERSEC_STATS_OPTION,array()); $ch=false; foreach(array_keys($st) as $dt){ if(strtotime($dt)<strtotime("-{$d} days")){ unset($st[$dt]); $ch=true; } } if($ch) update_option(CYBERSEC_STATS_OPTION,$st); }
add_action('init','cybersec_auto_clean_logs',25);

/**
 * Limit post revisions via filter instead of constant
 */
function cybersec_limit_revisions($num) { 
    $s = cybersec_get_settings(); 
    $mx = (int)($s['max_revisions'] ?? 0); 
    if ($mx > 0) return $mx; 
    return $num; 
}
add_filter('wp_revisions_to_keep', 'cybersec_limit_revisions', 10, 1);

function cybersec_track_user_agent($ip,$ua,$action){ $s=cybersec_get_settings(); if(empty($s['track_user_agents'])) return; $uh=md5($ua); $us=substr($ua,0,180); $st=get_option('cybersec_ua_stats',array()); if(!isset($st[$uh])) $st[$uh]=array('ua'=>$us,'first_seen'=>time(),'last_seen'=>time(),'blocked'=>0,'allowed'=>0,'count'=>0); $st[$uh]['last_seen']=time(); $st[$uh]['count']++; if($action==='blocked') $st[$uh]['blocked']++; if($action==='allowed') $st[$uh]['allowed']++; if(count($st)>2000){ uasort($st,function($a,$b){ return $b['count']-$a['count']; }); $st=array_slice($st,0,1000,true); } update_option('cybersec_ua_stats',$st); }
add_action('cybersec_bot_blocked',function($ip,$r){ $ua=isset($_SERVER['HTTP_USER_AGENT'])?sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])):'Unknown'; cybersec_track_user_agent($ip,$ua,'blocked'); },10,2);
add_action('cybersec_bot_allowed',function($ip){ $ua=isset($_SERVER['HTTP_USER_AGENT'])?sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])):'Unknown'; cybersec_track_user_agent($ip,$ua,'allowed'); },10,1);

function cybersec_login_logging(){ $s=cybersec_get_settings(); if(empty($s['login_logging'])) return; add_action('wp_login',function($ul){ $ip=cybersec_get_visitor_ip(); $ua=isset($_SERVER['HTTP_USER_AGENT'])?sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])):'Unknown'; cybersec_fs_put_contents(CYBERSEC_LOG_DIR.'login-attempts.log','['.current_time('Y-m-d H:i:s')."] LOGIN | User: {$ul} | IP: {$ip} | UA: {$ua}\n",FILE_APPEND|LOCK_EX); },10,1); add_action('wp_login_failed',function($un){ $ip=cybersec_get_visitor_ip(); $ua=isset($_SERVER['HTTP_USER_AGENT'])?sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])):'Unknown'; cybersec_fs_put_contents(CYBERSEC_LOG_DIR.'login-attempts.log','['.current_time('Y-m-d H:i:s')."] FAILED | User: {$un} | IP: {$ip} | UA: {$ua}\n",FILE_APPEND|LOCK_EX); },10,1); }
add_action('init','cybersec_login_logging');

// ==================== BLOCK 13: SECURITY SCORE ====================
function cybersec_get_security_score() {
    $score = 100;
    $issues = array();
    $s = cybersec_get_settings();
    
    if (!cybersec_is_wordpress_updated()) { $score -= 10; $issues[] = array('text' => cybersec_translate('Outdated WordPress version'), 'penalty' => 10); }
    
    $outdated = cybersec_get_outdated_plugins();
    if (count($outdated) > 0) { $penalty = min(count($outdated) * 3, 15); $score -= $penalty; $issues[] = array('text' => count($outdated) . ' ' . cybersec_translate('outdated plugins'), 'penalty' => $penalty); }
    
    if (empty($s['brute_enabled'])) { $score -= 10; $issues[] = array('text' => cybersec_translate('Brute force protection is disabled'), 'penalty' => 10); }
    if (empty($s['hide_wp'])) { $score -= 5; $issues[] = array('text' => cybersec_translate('WordPress system files are not hidden'), 'penalty' => 5); }
    if (empty($s['rate_limiting_enabled'])) { $score -= 5; $issues[] = array('text' => cybersec_translate('Rate Limiting is disabled'), 'penalty' => 5); }
    if (empty($s['hide_wp_version'])) { $score -= 3; $issues[] = array('text' => cybersec_translate('WordPress version is not hidden'), 'penalty' => 3); }
    if (empty($s['remove_script_versions'])) { $score -= 3; $issues[] = array('text' => cybersec_translate('Script/style versions are visible in URLs'), 'penalty' => 3); }
    if (empty($s['hide_login_errors'])) { $score -= 3; $issues[] = array('text' => cybersec_translate('Detailed login errors are not hidden'), 'penalty' => 3); }
    if (empty($s['disable_xmlrpc_pingbacks'])) { $score -= 3; $issues[] = array('text' => cybersec_translate('XML-RPC pingbacks are not disabled'), 'penalty' => 3); }
    if (empty($s['force_secure_cookies'])) { $score -= 3; $issues[] = array('text' => cybersec_translate('Secure Cookies are not enabled'), 'penalty' => 3); }
    if (empty($s['rest_api_protection'])) { $score -= 3; $issues[] = array('text' => cybersec_translate('REST API (user list) is not protected'), 'penalty' => 3); }
    if (empty($s['hide_plugins_themes'])) { $score -= 3; $issues[] = array('text' => cybersec_translate('Direct access to plugins/themes is not blocked'), 'penalty' => 3); }
    if (empty($s['referer_safe_check'])) { $score -= 5; $issues[] = array('text' => cybersec_translate('Referer check is disabled'), 'penalty' => 5); }
    
    $score = max(0, min(100, $score));
    
    return array(
        'score' => $score,
        'grade' => cybersec_get_score_grade($score),
        'issues' => $issues,
    );
}

function cybersec_get_score_grade($score) { if ($score >= 90) return array('grade' => 'A', 'color' => '#00e048'); if ($score >= 80) return array('grade' => 'B', 'color' => '#ffb800'); if ($score >= 70) return array('grade' => 'C', 'color' => '#ffb800'); if ($score >= 60) return array('grade' => 'D', 'color' => '#ff4040'); return array('grade' => 'F', 'color' => '#ff4040'); }

function cybersec_is_wordpress_updated() { $current = get_bloginfo('version'); $response = wp_remote_get('https://api.wordpress.org/core/version-check/1.7/'); if (!is_wp_error($response)) { $body = json_decode(wp_remote_retrieve_body($response)); if (isset($body->offers[0]->version)) return version_compare($current, $body->offers[0]->version, '>='); } return true; }

function cybersec_get_outdated_plugins() { $outdated = array(); $plugins = get_plugins(); $updates = get_site_transient('update_plugins'); foreach ($plugins as $file => $plugin) { if (isset($updates->response[$file])) $outdated[] = $plugin['Name']; } return $outdated; }

// ==================== BLOCK 14: AUDIT LOGGING ====================
function cybersec_audit_log($action, $description = '', $user_id = null) {
    if (!$user_id) $user_id = get_current_user_id();
    $user = get_userdata($user_id); $username = $user ? $user->user_login : 'System'; $ip = cybersec_get_visitor_ip();
    $log_entry = sprintf("[%s] %s | User: %s (ID:%d) | IP: %s | %s\n", current_time('Y-m-d H:i:s'), strtoupper($action), $username, $user_id, $ip, $description);
    cybersec_fs_put_contents(CYBERSEC_LOG_DIR . 'audit.log', $log_entry, FILE_APPEND | LOCK_EX);
    $log_file = CYBERSEC_LOG_DIR . 'audit.log'; if (file_exists($log_file) && filesize($log_file) > CYBERSEC_MAX_LOG_SIZE) { $content = cybersec_fs_get_contents($log_file); $content = substr($content, strlen($content) / 2); cybersec_fs_put_contents($log_file, $content); }
}

function cybersec_audit_hooks() {
    add_action('wp_login', function($ul, $u) { cybersec_audit_log('LOGIN', cybersec_translate('User') . " {$ul} " . cybersec_translate('logged in'), $u->ID); }, 10, 2);
    add_action('wp_login_failed', function($un) { $u = get_user_by('login', $un); $uid = $u ? $u->ID : 0; cybersec_audit_log('LOGIN_FAILED', cybersec_translate('Failed login attempt') . ": {$un}", $uid); });
    add_action('wp_logout', function() { $u = wp_get_current_user(); cybersec_audit_log('LOGOUT', cybersec_translate('User') . " {$u->user_login} " . cybersec_translate('logged out'), $u->ID); });
    add_action('password_reset', function($u, $np) { cybersec_audit_log('PASSWORD_RESET', cybersec_translate('User') . " {$u->user_login} " . cybersec_translate('changed password'), $u->ID); }, 10, 2);
    add_action('profile_update', function($uid, $oud) { $u = get_userdata($uid); cybersec_audit_log('PROFILE_UPDATE', cybersec_translate('User') . " {$u->user_login} " . cybersec_translate('updated profile'), $uid); }, 10, 2);
    add_action('activated_plugin', function($p, $nw) { $u = wp_get_current_user(); cybersec_audit_log('PLUGIN_ACTIVATED', cybersec_translate('Plugin') . " '{$p}' " . cybersec_translate('activated by user') . " {$u->user_login}", $u->ID); }, 10, 2);
    add_action('deactivated_plugin', function($p, $nw) { $u = wp_get_current_user(); cybersec_audit_log('PLUGIN_DEACTIVATED', cybersec_translate('Plugin') . " '{$p}' " . cybersec_translate('deactivated by user') . " {$u->user_login}", $u->ID); }, 10, 2);
    add_action('update_option_' . CYBERSEC_SETTINGS_OPTION, function($ov, $nv) { $u = wp_get_current_user(); cybersec_audit_log('SETTINGS_CHANGED', cybersec_translate('CyberPulse settings changed by user') . " {$u->user_login}", $u->ID); }, 10, 2);
    add_action('update_option_' . CYBERSEC_WHITELIST_OPTION, function() { $u = wp_get_current_user(); cybersec_audit_log('WHITELIST_MODIFIED', cybersec_translate('Whitelist modified by user') . " {$u->user_login}", $u->ID); });
    add_action('cybersec_bot_blocked', function($ip, $r) { cybersec_audit_log('IP_BLOCKED', cybersec_translate('Blocked IP') . ": {$ip} | " . cybersec_translate('Reason') . ": {$r}"); }, 10, 2);
    add_action('cybersec_ip_unblocked', function($ip) { $u = wp_get_current_user(); cybersec_audit_log('IP_UNBLOCKED', cybersec_translate('Unblocked IP') . ": {$ip} " . cybersec_translate('by user') . " {$u->user_login}", $u->ID); });
}
add_action('init', 'cybersec_audit_hooks');

add_action('upgrader_process_complete', function($upgrader, $options) {
    $user = wp_get_current_user(); $username = $user ? $user->user_login : 'System'; $user_id = $user ? $user->ID : 0;
    if ($options['action'] === 'update') {
        if ($options['type'] === 'core') { cybersec_audit_log('WP_UPDATED', cybersec_translate('WordPress updated to version') . ' ' . get_bloginfo('version'), $user_id); }
        elseif ($options['type'] === 'plugin') { $plugins = isset($options['plugins']) ? (array)$options['plugins'] : array(); foreach ($plugins as $plugin) { $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin); $plugin_name = !empty($plugin_data['Name']) ? $plugin_data['Name'] : $plugin; cybersec_audit_log('PLUGIN_UPDATED', cybersec_translate('Plugin updated') . ": {$plugin_name} ({$plugin})", $user_id); } }
        elseif ($options['type'] === 'theme') { $themes = isset($options['themes']) ? (array)$options['themes'] : array(); foreach ($themes as $theme) { $theme_data = wp_get_theme($theme); $theme_name = $theme_data->exists() ? $theme_data->get('Name') : $theme; cybersec_audit_log('THEME_UPDATED', cybersec_translate('Theme updated') . ": {$theme_name}", $user_id); } }
    }
}, 10, 2);

// ==================== BLOCK 15: ACTIVATION ====================
function cybersec_activate(){
    if(!file_exists(CYBERSEC_LOG_DIR)) wp_mkdir_p(CYBERSEC_LOG_DIR);
    if(!cybersec_fs_exists(CYBERSEC_LOG_DIR.'.htaccess')) { cybersec_fs_put_contents(CYBERSEC_LOG_DIR.'.htaccess', "Require all denied\n"); }
    if(!cybersec_fs_exists(CYBERSEC_LOG_DIR.'index.php')) { cybersec_fs_put_contents(CYBERSEC_LOG_DIR.'index.php','<?php // Silence is golden'); }
    if(get_option(CYBERSEC_PAGES_OPTION)===false) update_option(CYBERSEC_PAGES_OPTION,array(home_url()));
    if(get_option(CYBERSEC_BOT_OPTIONS)===false) update_option(CYBERSEC_BOT_OPTIONS,cybersec_get_default_bot_options());
    if(get_option(CYBERSEC_SETTINGS_OPTION)===false) { $default_settings = cybersec_get_settings(); $recommended = array('auto_unblock_minutes'=>30,'alert_threshold'=>20,'alert_minutes'=>5,'brute_max_attempts'=>5,'brute_window_minutes'=>15,'rate_limit_requests'=>60,'rate_limit_window'=>60,'max_revisions'=>5,'auto_clean_logs_days'=>30,'referer_history_threshold'=>1,'referer_rate_1min'=>5,'referer_rate_5min'=>10,'referer_same_page'=>3,'referer_no_proxy_rate'=>3); $default_settings = array_merge($default_settings, $recommended); update_option(CYBERSEC_SETTINGS_OPTION, $default_settings); }
    if(get_option(CYBERSEC_WHITELIST_OPTION)===false) update_option(CYBERSEC_WHITELIST_OPTION,array());
    if(get_option(CYBERSEC_TEMP_BLOCKS_OPTION)===false) update_option(CYBERSEC_TEMP_BLOCKS_OPTION,array());
    if(get_option(CYBERSEC_BRUTE_OPTION)===false) update_option(CYBERSEC_BRUTE_OPTION,array());
    if(get_option(CYBERSEC_STATS_OPTION)===false) update_option(CYBERSEC_STATS_OPTION,array());
    if(get_option(CYBERSEC_REPEAT_OPTION)===false) update_option(CYBERSEC_REPEAT_OPTION,array());
    if(get_option(CYBERSEC_PERMANENT_BLOCKED_SUBNETS)===false) update_option(CYBERSEC_PERMANENT_BLOCKED_SUBNETS,array());
    if(get_option('secwall_ua_stats')===false) update_option('secwall_ua_stats',array());
    if(get_option(CYBERSEC_UA_BLOCKS_OPTION)===false) update_option(CYBERSEC_UA_BLOCKS_OPTION,array());
    cybersec_auto_whitelist_server();
    if (!wp_next_scheduled('cybersec_daily_cleanup_cron')) {
    wp_schedule_event(time(), 'daily', 'cybersec_daily_cleanup_cron');
}
    $old_blocked = CYBERSEC_LOG_DIR . 'blocked-bots.log'; $today_blocked = CYBERSEC_LOG_DIR . 'blocked-bots-' . gmdate('Y-m-d') . '.log';
    if (cybersec_fs_exists($old_blocked) && !cybersec_fs_exists($today_blocked)) { cybersec_fs_move($old_blocked, $today_blocked); }
    $old_allowed = CYBERSEC_LOG_DIR . 'allowed-bots.log'; $today_allowed = CYBERSEC_LOG_DIR . 'allowed-bots-' . gmdate('Y-m-d') . '.log';
    if (cybersec_fs_exists($old_allowed) && !cybersec_fs_exists($today_allowed)) { cybersec_fs_move($old_allowed, $today_allowed); }
}

// ==================== DAILY CRON CLEANUP ====================
function cybersec_daily_cleanup_cron() {
    $s = cybersec_get_settings();
    $days = (int)($s['auto_clean_logs_days'] ?? 30);
    if ($days <= 0) return;
    
    $cutoff = time() - ($days * DAY_IN_SECONDS);
    
    // Clean log files
    $log_files = glob(CYBERSEC_LOG_DIR . '*.log');
    if (is_array($log_files)) {
        foreach ($log_files as $f) {
            if (filemtime($f) < $cutoff) {
                wp_delete_file($f);
            }
        }
    }
    
    // Clean old stats
    $stats = get_option(CYBERSEC_STATS_OPTION, array());
    $changed = false;
    foreach (array_keys($stats) as $dt) {
        if (strtotime($dt) < strtotime("-{$days} days")) {
            unset($stats[$dt]);
            $changed = true;
        }
    }
    if ($changed) {
        update_option(CYBERSEC_STATS_OPTION, $stats);
    }
    
    // Clean old transients
    global $wpdb;
    if ($wpdb && $wpdb->options) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $wpdb->options WHERE option_name LIKE %s AND option_value < %d LIMIT 500",
            '_transient_timeout_cybersec_%',
            time()
        ));
        // phpcs:enable
    }
}
add_action('cybersec_daily_cleanup_cron', 'cybersec_daily_cleanup_cron');

// ==================== BLOCK 16: DASHBOARD WIDGET ====================
function cybersec_add_dashboard_widget() { 
    $shield_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 36" width="21" height="21" style="vertical-align:middle;flex-shrink:0;"><path fill="none" stroke="#000000" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M 18.0,30.8 C 17.2,30.4 9.2,25.9 7.6,10.5 C 7.5,7.5 12.3,5.2 18.0,5.2 C 23.7,5.2 28.5,7.5 28.4,10.5 C 26.8,26.8 18.0,30.8 18.0,30.8 Z"/><path fill="#D0CFCE" d="M 18.0,5.5 C 12.5,5.5 8.0,7.8 8.0,10.5 L 8.0,10.6 C 9.5,25.8 17.3,30.3 18.0,30.7 L 18.0,30.7 C 18.0,30.7 26.6,26.7 28.0,10.7 C 28.2,7.9 23.5,5.5 18.0,5.5 Z"/><path fill="#D0CFCE" d="M 18.0,27.5 L 18.0,27.5 C 17.6,27.2 11.3,23.9 10.0,11.5 L 10.0,11.5 C 10.0,9.3 13.6,7.5 18.0,7.5 C 22.4,7.5 26.2,9.3 26.0,11.5 C 24.8,24.2 18.0,27.5 18.0,27.5 Z"/><path fill="#E60012" d="M 17.6,9.0 L 19.0,11.0 L 17.6,13.1 L 19.0,15.0 L 17.6,17.0 L 19.0,19.0 L 17.6,21.0 L 19.0,23.1 L 17.6,25.1 L 18.0,27.7 C 18.0,27.7 10.5,22.2 10.0,11.7 C 10.0,11.7 10.0,7.7 18.0,7.7 L 17.6,9.0 Z"/><path fill="#FFFFFF" d="M 18.0,7.7 L 17.6,9.0 L 19.0,11.0 L 17.6,13.1 L 19.0,15.0 L 17.6,17.0 L 19.0,19.0 L 17.6,21.0 L 19.0,23.1 L 17.6,25.1 L 18.0,27.7 C 18.0,27.7 25.5,23.1 26.0,11.7 C 26.0,11.5 25.5,7.7 18.0,7.7 Z"/></svg>';
    wp_add_dashboard_widget('cybersec_dashboard_widget', '<span style="display:inline-flex;align-items:center;gap:4px;">' . $shield_svg . ' CyberPulse Security</span>', 'cybersec_dashboard_widget_content'); 
    
    add_action('admin_enqueue_scripts', function($hook) {
        if ($hook === 'index.php') {
            wp_register_style('cyberpulse-admin-widget', false, array(), CYBERSEC_VERSION);
            wp_enqueue_style('cyberpulse-admin-widget');
            $dash_css = '#cybersec_dashboard_widget .inside{margin:0;padding:0}#cybersec_dashboard_widget.postbox{overflow:hidden;border-radius:8px}.cs-dash-wrap{font-family:\'Inter\',-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;margin:0}@keyframes pulse{0%,100%{opacity:1}50%{opacity:0.4}}.cs-dash-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:0;background:#fff}.cs-dash-stat{padding:14px 10px;text-align:center;border-right:1px solid #e8ece8}.cs-dash-stat:last-child{border-right:none}.cs-dash-stat-num{font-size:1.4rem;font-weight:800;line-height:1}.cs-dash-stat-label{font-size:0.55rem;color:#6a7a68;margin-top:4px;text-transform:uppercase;letter-spacing:0.04em;font-weight:600}.cs-dash-section{padding:14px 18px;border-bottom:1px solid #e8ece8;background:#fff}.cs-dash-section:last-of-type{border-bottom:none}.cs-dash-section h4{margin:0 0 10px 0;font-size:0.62rem;color:#008a35;text-transform:uppercase;letter-spacing:0.06em;font-weight:700}.cs-dash-list{margin:0;padding:0;list-style:none}.cs-dash-list li{font-size:0.65rem;padding:5px 0;color:#2a3028;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #f0f2f0}.cs-dash-list li:last-child{border-bottom:none}.cs-dash-list code{background:rgba(0,138,53,0.06);padding:2px 6px;border-radius:3px;font-size:0.6rem;color:#008a35;font-weight:500}.cs-dash-badge{background:rgba(208,32,32,0.08);color:#a01818;padding:2px 7px;border-radius:3px;font-size:0.58rem;font-weight:700}.cs-dash-score-row{display:flex;align-items:center;gap:10px;margin-bottom:10px}.cs-dash-score-letter{font-size:2rem;font-weight:900;color:#00e048;line-height:1}.cs-dash-score-info{font-size:0.58rem;color:#6a7a68}.cs-dash-score-info strong{color:#1a2418;font-size:0.7rem}.cs-dash-issue{font-size:0.58rem;color:#a01818;padding:2px 0;padding-left:12px;text-indent:-12px}.cs-dash-ok{font-size:0.6rem;color:#008a35;font-weight:600}.cs-dash-footer{padding:12px 18px;text-align:center;background:#f8faf8;border-top:2px solid #e0e4e0;border-radius:0 0 8px 8px}.cs-dash-footer a{font-size:0.6rem;color:#008a35;text-decoration:none;font-weight:700;letter-spacing:0.04em;text-transform:uppercase}.cs-dash-footer a:hover{color:#00a040}';
            wp_add_inline_style('cyberpulse-admin-widget', $dash_css);
        }
    });
}
add_action('wp_dashboard_setup', 'cybersec_add_dashboard_widget');

function cybersec_dashboard_widget_content() {
    $dy = cybersec_get_daily_stats(); $to = cybersec_get_top_offenders(3); $tb = get_option(CYBERSEC_TEMP_BLOCKS_OPTION, array()); $permanent = cybersec_get_permanent_blocked_subnets(); $brute = get_option(CYBERSEC_BRUTE_OPTION, array()); $settings = cybersec_get_settings(); $score = cybersec_get_security_score();
    $active_brute = 0; $n = time(); foreach ($brute as $d) { if (isset($d['first']) && $d['first'] > $n - ((int)$settings['brute_window_minutes'] * 60)) $active_brute++; }
    $total_blocked = (int)$dy['blocked']; $total_brute = (int)$dy['brute']; $total_allowed = (int)$dy['allowed']; $active_threats = $active_brute + count($tb);
    ?>
    <div class="cs-dash-wrap"><div class="cs-dash-stats"><div class="cs-dash-stat"><div class="cs-dash-stat-num" style="color:#c02020;"><?php echo (int)$total_blocked; ?></div><div class="cs-dash-stat-label"><?php echo esc_html(cybersec_translate('Blocked')); ?></div></div><div class="cs-dash-stat"><div class="cs-dash-stat-num" style="color:#00a040;"><?php echo (int)$total_allowed; ?></div><div class="cs-dash-stat-label"><?php echo esc_html(cybersec_translate('Allowed')); ?></div></div><div class="cs-dash-stat"><div class="cs-dash-stat-num" style="color:<?php echo $active_threats>0?'#b08000':'#6a7a68'; ?>;"><?php echo (int)$active_threats; ?></div><div class="cs-dash-stat-label"><?php echo esc_html(cybersec_translate('Threats Now')); ?></div></div><div class="cs-dash-stat"><div class="cs-dash-stat-num" style="color:#0080b0;"><?php echo count($permanent); ?></div><div class="cs-dash-stat-label"><?php echo esc_html(cybersec_translate('Permaban')); ?></div></div></div>
    <div class="cs-dash-section"><?php $threat = cybersec_get_threat_level(); ?><div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;"><div class="cs-dash-score-row" style="margin-bottom:0;"><span class="cs-dash-score-letter"><?php echo esc_html($score['grade']['grade']); ?></span><span class="cs-dash-score-info"><strong><?php echo (int)$score['score']; ?>/100</strong><br><?php echo $score['score']>=90?esc_html(cybersec_translate('Excellent protection')):($score['score']>=70?esc_html(cybersec_translate('Good protection')):($score['score']>=50?esc_html(cybersec_translate('Needs attention')):esc_html(cybersec_translate('Critical level')))); ?></span></div><div style="text-align:right;"><div style="display:flex;align-items:center;gap:6px;"><div style="width:10px;height:10px;border-radius:50%;background:<?php echo esc_attr($threat['color']); ?>;box-shadow:0 0 8px <?php echo esc_attr($threat['color']); ?>;animation:pulse 2s infinite;"></div><span style="font-size:0.62rem;font-weight:700;"><?php echo esc_html($threat['label']); ?></span></div><span style="font-size:0.5rem;color:#6a7a68;"><?php echo esc_html($threat['text']); ?></span></div></div><?php if(!empty($score['issues'])): foreach(array_slice($score['issues'],0,3) as $issue): ?><div class="cs-dash-issue"> <?php echo esc_html($issue['text']); ?></div><?php endforeach; if(count($score['issues'])>3): ?><div class="cs-dash-issue" style="color:#6a7a68;"><?php echo esc_html(cybersec_translate('...and more')); ?> <?php echo count($score['issues'])-3; ?></div><?php endif; else: ?><div class="cs-dash-ok"> <?php echo esc_html(cybersec_translate('All checks passed')); ?></div><?php endif; ?></div>
    <?php if(!empty($to)): ?><div class="cs-dash-section"><h4> <?php echo esc_html(cybersec_translate('Top Attackers Now')); ?></h4><ul class="cs-dash-list"><?php $i=0; foreach($to as $ip=>$data): if($i++>=3) break; ?><li><code><?php echo esc_html($ip); ?></code><span style="font-size:0.58rem;color:#6a7a68;"><?php echo esc_html($data['country']); ?></span><span class="cs-dash-badge"><?php echo (int)$data['count']; ?></span></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="cs-dash-footer"><a href="<?php echo esc_url(admin_url('admin.php?page=cyber-security')); ?>"> <?php echo esc_html(cybersec_translate('Open Control Panel')); ?> </a></div></div>
    <?php
    wp_register_script('cyberpulse-admin-widget', false, array(), CYBERSEC_VERSION, true);
    wp_enqueue_script('cyberpulse-admin-widget');
    wp_add_inline_script('cyberpulse-admin-widget', "(function(){var w=document.getElementById('cybersec_dashboard_widget');if(!w)return;var n=document.getElementById('normal-sortables');if(!n)return;if(n.firstChild!==w)n.insertBefore(w,n.firstChild)})();");
}

// ==================== BLOCK 17: AUTOMATIC SECURITY TEST ====================
add_action('wp_ajax_cybersec_run_security_test', function() { 
    check_ajax_referer('cybersec_ajax_action','cybersec_ajax_nonce'); 
    set_transient('cybersec_test_mode_global',true,60); 
    $results=array(); 
    $site_url=get_site_url(); 
    $args=array('timeout'=>10,'sslverify'=>false,'blocking'=>true);
    
    $args['user-agent']=''; 
    $response=wp_remote_get($site_url.'/?t='.wp_rand(1000,9999),$args); 
    if(is_wp_error($response)){
        $results[]=array('test'=>esc_html(cybersec_translate('Bot protection (no User-Agent)')),'passed'=>false,'code'=>'ERR','detail'=>$response->get_error_message());
    }else{
        $code=wp_remote_retrieve_response_code($response);
        $results[]=array('test'=>esc_html(cybersec_translate('Bot protection (no User-Agent)')),'passed'=>in_array($code,array(403,429,503)),'code'=>$code,'detail'=>in_array($code,array(403,429,503))?esc_html(cybersec_translate('Working - bot blocked')):esc_html(cybersec_translate('Not working - bot allowed'))." ({$code})");
    }
    usleep(200000);
    
    $args['user-agent']='Mozilla/5.0'; 
    $response=wp_remote_get($site_url.'/readme.html?t='.wp_rand(1000,9999),$args); 
    if(is_wp_error($response)){
        $results[]=array('test'=>esc_html(cybersec_translate('System file hiding')),'passed'=>false,'code'=>'ERR','detail'=>$response->get_error_message());
    }else{
        $code=wp_remote_retrieve_response_code($response);
        $results[]=array('test'=>esc_html(cybersec_translate('System file hiding')),'passed'=>in_array($code,array(403,404)),'code'=>$code,'detail'=>in_array($code,array(403,404))?esc_html(cybersec_translate('Working - access denied')):esc_html(cybersec_translate('Not working - file accessible'))." ({$code})");
    }
    usleep(200000);
    
    $args['user-agent']='curl/7.68.0'; 
    $response=wp_remote_get($site_url.'/?t='.wp_rand(1000,9999),$args); 
    if(is_wp_error($response)){
        $results[]=array('test'=>esc_html(cybersec_translate('Script and tool blocking')),'passed'=>false,'code'=>'ERR','detail'=>$response->get_error_message());
    }else{
        $code=wp_remote_retrieve_response_code($response);
        $results[]=array('test'=>esc_html(cybersec_translate('Script and tool blocking')),'passed'=>in_array($code,array(403,429,503)),'code'=>$code,'detail'=>in_array($code,array(403,429,503))?esc_html(cybersec_translate('Working - script blocked')):esc_html(cybersec_translate('Not working - script allowed'))." ({$code})");
    }
    usleep(200000);
    
    $args['user-agent']='Mozilla/5.0'; 
    $response=wp_remote_get($site_url.'/wp-json/wp/v2/users?t='.wp_rand(1000,9999),$args); 
    if(is_wp_error($response)){
        $results[]=array('test'=>esc_html(cybersec_translate('User list protection')),'passed'=>false,'code'=>'ERR','detail'=>$response->get_error_message());
    }else{
        $code=wp_remote_retrieve_response_code($response);
        $body=wp_remote_retrieve_body($response);
        $has_users=(strpos($body,'"id":')!==false&&strpos($body,'"name":')!==false);
        $results[]=array('test'=>esc_html(cybersec_translate('User list protection')),'passed'=>!$has_users||$code===403,'code'=>$code,'detail'=>(!$has_users||$code===403)?esc_html(cybersec_translate('Working - list hidden')):esc_html(cybersec_translate('Not working - list accessible')));
    }
    usleep(200000);
    
    $response=wp_remote_get($site_url.'/wp-config.php?t='.wp_rand(1000,9999),$args); 
    if(is_wp_error($response)){
        $results[]=array('test'=>esc_html(cybersec_translate('wp-config.php protection')),'passed'=>false,'code'=>'ERR','detail'=>$response->get_error_message());
    }else{
        $code=wp_remote_retrieve_response_code($response);
        $results[]=array('test'=>esc_html(cybersec_translate('wp-config.php protection')),'passed'=>in_array($code,array(403,404)),'code'=>$code,'detail'=>in_array($code,array(403,404))?esc_html(cybersec_translate('Working - access denied')):esc_html(cybersec_translate('Not working - file accessible'))." ({$code})");
    }
    usleep(200000);
    
    $response=wp_remote_get($site_url.'/.env?t='.wp_rand(1000,9999),$args); 
    if(is_wp_error($response)){
        $results[]=array('test'=>esc_html(cybersec_translate('Environment file protection (.env)')),'passed'=>false,'code'=>'ERR','detail'=>$response->get_error_message());
    }else{
        $code=wp_remote_retrieve_response_code($response);
        $results[]=array('test'=>esc_html(cybersec_translate('Environment file protection (.env)')),'passed'=>in_array($code,array(403,404)),'code'=>$code,'detail'=>in_array($code,array(403,404))?esc_html(cybersec_translate('Working - access denied')):esc_html(cybersec_translate('Not working - file accessible'))." ({$code})");
    }
    usleep(200000);
    
    $response=wp_remote_get($site_url.'/wp-content/plugins/?t='.wp_rand(1000,9999),$args); 
    if(is_wp_error($response)){
        $results[]=array('test'=>esc_html(cybersec_translate('Plugin directory hiding')),'passed'=>false,'code'=>'ERR','detail'=>$response->get_error_message());
    }else{
        $code=wp_remote_retrieve_response_code($response);
        $results[]=array('test'=>esc_html(cybersec_translate('Plugin directory hiding')),'passed'=>in_array($code,array(403,404)),'code'=>$code,'detail'=>in_array($code,array(403,404))?esc_html(cybersec_translate('Working - access denied')):esc_html(cybersec_translate('Not working - directory accessible'))." ({$code})");
    }
    usleep(200000);
    
    $args['user-agent']='GPTBot/1.0'; 
    $response=wp_remote_get($site_url.'/?t='.wp_rand(1000,9999),$args); 
    if(is_wp_error($response)){
        $results[]=array('test'=>esc_html(cybersec_translate('AI scraping protection')),'passed'=>false,'code'=>'ERR','detail'=>$response->get_error_message());
    }else{
        $code=wp_remote_retrieve_response_code($response);
        $results[]=array('test'=>esc_html(cybersec_translate('AI scraping protection')),'passed'=>in_array($code,array(403,429,503)),'code'=>$code,'detail'=>in_array($code,array(403,429,503))?esc_html(cybersec_translate('Working - AI bot blocked')):esc_html(cybersec_translate('Not working - AI bot allowed'))." ({$code})");
    }
    usleep(200000);
    
    $args['user-agent']='Mozilla/5.0'; 
    $response=wp_remote_get($site_url.'/wp-includes/?t='.wp_rand(1000,9999),$args); 
    if(is_wp_error($response)){
        $results[]=array('test'=>esc_html(cybersec_translate('wp-includes directory hiding')),'passed'=>false,'code'=>'ERR','detail'=>$response->get_error_message());
    }else{
        $code=wp_remote_retrieve_response_code($response);
        $results[]=array('test'=>esc_html(cybersec_translate('wp-includes directory hiding')),'passed'=>in_array($code,array(403,404)),'code'=>$code,'detail'=>in_array($code,array(403,404))?esc_html(cybersec_translate('Working - access denied')):esc_html(cybersec_translate('Not working - directory accessible'))." ({$code})");
    }
    usleep(200000);
    
    $ip=str_replace(array('http://','https://'),'',$site_url); 
    $response=wp_remote_get('http://'.cybersec_get_server_ip().'/?t='.wp_rand(1000,9999),array_merge($args,array('headers'=>array('Host'=>$ip)))); 
    if(is_wp_error($response)){
        $results[]=array('test'=>esc_html(cybersec_translate('Direct IP access protection')),'passed'=>false,'code'=>'ERR','detail'=>$response->get_error_message());
    }else{
        $code=wp_remote_retrieve_response_code($response);
        $results[]=array('test'=>esc_html(cybersec_translate('Direct IP access protection')),'passed'=>$code===403,'code'=>$code,'detail'=>$code===403?esc_html(cybersec_translate('Working - access denied')):esc_html(cybersec_translate('Not working - access open'))." ({$code})");
    }
    usleep(200000);
    
    $response=wp_remote_get($site_url.'/?t='.wp_rand(1000,9999),$args); 
    if(is_wp_error($response)){
        $results[]=array('test'=>esc_html(cybersec_translate('WordPress version hiding')),'passed'=>false,'code'=>'ERR','detail'=>$response->get_error_message());
    }else{
        $body=wp_remote_retrieve_body($response);
        $has_generator=(stripos($body,'meta name="generator"')!==false);
        $results[]=array('test'=>esc_html(cybersec_translate('WordPress version hiding')),'passed'=>!$has_generator,'code'=>wp_remote_retrieve_response_code($response),'detail'=>!$has_generator?esc_html(cybersec_translate('Working - version hidden')):esc_html(cybersec_translate('Not working - version visible')));
    }
    usleep(200000);
    
    $response=wp_remote_get($site_url.'/?t='.wp_rand(1000,9999),array_merge($args,array('httpversion'=>'1.0'))); 
    if(is_wp_error($response)){
        $results[]=array('test'=>esc_html(cybersec_translate('HTTP/1.0 scanner blocking')),'passed'=>false,'code'=>'ERR','detail'=>$response->get_error_message());
    }else{
        $code=wp_remote_retrieve_response_code($response);
        $results[]=array('test'=>esc_html(cybersec_translate('HTTP/1.0 scanner blocking')),'passed'=>in_array($code,array(403,429,503)),'code'=>$code,'detail'=>in_array($code,array(403,429,503))?esc_html(cybersec_translate('Working - scanner blocked')):esc_html(cybersec_translate('Not working - request allowed'))." ({$code})");
    }
    usleep(200000);
    
    $args['httpversion']='1.1'; 
    $response=wp_remote_post($site_url.'/xmlrpc.php?t='.wp_rand(1000,9999),array_merge($args,array('body'=>'<?xml version="1.0"?><methodCall><methodName>pingback.ping</methodName></methodCall>'))); 
    if(is_wp_error($response)){
        $results[]=array('test'=>esc_html(cybersec_translate('XML-RPC pingback protection')),'passed'=>false,'code'=>'ERR','detail'=>$response->get_error_message());
    }else{
        $code=wp_remote_retrieve_response_code($response);
        $results[]=array('test'=>esc_html(cybersec_translate('XML-RPC pingback protection')),'passed'=>in_array($code,array(403,404,405)),'code'=>$code,'detail'=>in_array($code,array(403,404,405))?esc_html(cybersec_translate('Working - access denied')):esc_html(cybersec_translate('Not working - access open'))." ({$code})");
    }
    usleep(200000);
    
    $config_file=ABSPATH.'wp-config.php'; 
    if(file_exists($config_file)){
        $perms=substr(sprintf('%o',fileperms($config_file)),-4);
        $passed=($perms==='0400'||$perms==='0440'||$perms==='0600');
        $results[]=array('test'=>esc_html(cybersec_translate('wp-config.php permissions')),'passed'=>$passed,'code'=>$perms,'detail'=>$passed?esc_html(cybersec_translate('Secure'))." ({$perms})":esc_html(cybersec_translate('Dangerous!'))." ({$perms}) ".esc_html(cybersec_translate('440 recommended')));
    }else{
        $results[]=array('test'=>esc_html(cybersec_translate('wp-config.php permissions')),'passed'=>false,'code'=>'-','detail'=>esc_html(cybersec_translate('File not found')));
    }
    usleep(200000);
    
    $uploads_dir=wp_upload_dir()['basedir']; 
    if(file_exists($uploads_dir)){
        $perms=substr(sprintf('%o',fileperms($uploads_dir)),-4);
        $passed=($perms==='0755'||$perms==='0750');
        $results[]=array('test'=>esc_html(cybersec_translate('/wp-content/uploads/ permissions')),'passed'=>$passed,'code'=>$perms,'detail'=>$passed?esc_html(cybersec_translate('Secure'))." ({$perms})":esc_html(cybersec_translate('Warning'))." ({$perms}) ".esc_html(cybersec_translate('755 recommended')));
    }else{
        $results[]=array('test'=>esc_html(cybersec_translate('/wp-content/uploads/ permissions')),'passed'=>false,'code'=>'-','detail'=>esc_html(cybersec_translate('Directory not found')));
    }
    usleep(200000);
    
    if(file_exists(ABSPATH.'wp-admin')){
        $perms=substr(sprintf('%o',fileperms(ABSPATH.'wp-admin')),-4);
        $passed=($perms==='0755'||$perms==='0750');
        $results[]=array('test'=>esc_html(cybersec_translate('/wp-admin/ permissions')),'passed'=>$passed,'code'=>$perms,'detail'=>$passed?esc_html(cybersec_translate('Secure'))." ({$perms})":esc_html(cybersec_translate('Warning'))." ({$perms}) ".esc_html(cybersec_translate('755 recommended')));
    }else{
        $results[]=array('test'=>esc_html(cybersec_translate('/wp-admin/ permissions')),'passed'=>false,'code'=>'-','detail'=>esc_html(cybersec_translate('Directory not found')));
    }
    usleep(200000);
    
    if(file_exists(ABSPATH.'wp-includes')){
        $perms=substr(sprintf('%o',fileperms(ABSPATH.'wp-includes')),-4);
        $passed=($perms==='0755'||$perms==='0750');
        $results[]=array('test'=>esc_html(cybersec_translate('/wp-includes/ permissions')),'passed'=>$passed,'code'=>$perms,'detail'=>$passed?esc_html(cybersec_translate('Secure'))." ({$perms})":esc_html(cybersec_translate('Warning'))." ({$perms}) ".esc_html(cybersec_translate('755 recommended')));
    }else{
        $results[]=array('test'=>esc_html(cybersec_translate('/wp-includes/ permissions')),'passed'=>false,'code'=>'-','detail'=>esc_html(cybersec_translate('Directory not found')));
    }
    delete_transient('cybersec_test_mode_global'); 
    $passed=count(array_filter($results,function($r){return $r['passed'];})); 
    $total=count($results);
    wp_send_json_success(array('results'=>$results,'passed'=>$passed,'total'=>$total,'grade'=>$passed===$total?'A':($passed>=$total-1?'B':($passed>=$total-3?'C':($passed>=$total-6?'D':'F')))));
});

// ==================== BLOCK 18: 404 DETECTION + URL FIREWALL ====================
function cybersec_404_detection() { if(is_admin()||wp_doing_ajax())return;if(!is_404())return;$s=cybersec_get_settings();if(empty($s['404_detection_enabled']))return;$ip=cybersec_get_visitor_ip();if(cybersec_is_whitelisted($ip))return;$threshold=(int)($s['404_threshold']??10);$window=(int)($s['404_window']??5);$key='cybersec_404_'.$ip;$hits=get_transient($key);if($hits===false)$hits=0;$hits++;set_transient($key,$hits,$window*MINUTE_IN_SECONDS);if($hits>=$threshold){$subnet=cybersec_get_subnet_24($ip);cybersec_add_permanent_blocked_subnet($subnet,cybersec_translate("Site scanner").": {$hits} ".cybersec_translate('404 errors in')." {$window} ".cybersec_translate('min'),$ip);cybersec_increment_stat('blocked');cybersec_log_blocked_bot($ip,cybersec_translate("404 Detection").": {$hits} ".cybersec_translate('errors in')." {$window} ".cybersec_translate('min')." — ".cybersec_translate('PERMANENTLY BLOCKED'));cybersec_show_block_page($ip,cybersec_translate("Site scanning detected. Access denied."));exit;} }
add_action('template_redirect','cybersec_404_detection',5);

function cybersec_url_firewall() { if(is_admin()||wp_doing_ajax())return;$s=cybersec_get_settings();if(empty($s['url_firewall_enabled']))return;$patterns=trim($s['url_firewall_patterns']??'');if(empty($patterns))return;$ip=cybersec_get_visitor_ip();if(cybersec_is_whitelisted($ip))return;$request_uri=isset($_SERVER['REQUEST_URI'])?sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])):'';$pattern_list=explode("\n",$patterns);foreach($pattern_list as $pattern){$pattern=trim($pattern);if(empty($pattern))continue;if(stripos($request_uri,$pattern)!==false){cybersec_increment_stat('blocked');cybersec_log_blocked_bot($ip,cybersec_translate("URL Firewall").": {$pattern}");cybersec_show_block_page($ip,cybersec_translate("Access denied (URL Firewall)"));exit;}} }
add_action('init','cybersec_url_firewall',1);

add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'index.php') {
        wp_register_style('cyberpulse-admin-widget', false, array(), CYBERSEC_VERSION);
        wp_enqueue_style('cyberpulse-admin-widget');
        $dash_css = '#cybersec_dashboard_widget h2{justify-content:flex-start!important}#cybersec_dashboard_widget h2 span{display:inline-flex!important;align-items:center!important}';
        wp_add_inline_style('cyberpulse-admin-widget', $dash_css);
    }
});

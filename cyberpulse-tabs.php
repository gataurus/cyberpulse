<?php
// phpcs:disable WordPress.Security.ValidatedSanitizedInput
if (!defined('ABSPATH')) exit;

// ==================== ENQUEUE ADMIN ASSETS ====================
function cybersec_enqueue_admin_assets($hook) {
   
    // Menu icon pulse — on all admin pages
    wp_add_inline_style('dashicons', '
        #adminmenu .toplevel_page_cyber-security .wp-menu-image.dashicons-before::before {
            animation: cp-shield-pulse 2.2s ease-in-out infinite !important;
        }
        @keyframes cp-shield-pulse {
            0%, 100% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.13); opacity: 1; }
        }
    ');

    // CSS and JS — ONLY on plugin page
    if (strpos($hook, 'cyber-security') === false) {
        return;
    }
    
    wp_enqueue_style(
        'cyberpulse-admin',
        plugins_url('assets/cyberpulse-admin.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'assets/cyberpulse-admin.css')
    );
    
    wp_enqueue_script(
        'cyberpulse-admin',
        plugins_url('assets/cyberpulse-admin.js', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'assets/cyberpulse-admin.js'),
        true
    );
    
    wp_localize_script('cyberpulse-admin', 'cybersecAdmin', array(
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('cybersec_ajax_action'),
    'version' => CYBERSEC_VERSION,
    'i18n' => array(
        'confirmBlockUA' => cybersec_translate('Block all IPs with User-Agent:'),
        'confirmBlockUA2' => cybersec_translate('All new requests with this UA will be automatically blocked?'),
        'confirmUnblockUA' => cybersec_translate('Remove User-Agent from blacklist?'),
        'confirmClearLog' => cybersec_translate('Clear log?'),
        'confirmClearBlockedLog' => cybersec_translate('Clear blocked log?'),
        'confirmClearAllowedLog' => cybersec_translate('Clear allowed log?'),
        'confirmClearOffenders' => cybersec_translate('Clear TOP offenders?'),
        'confirmClearUA' => cybersec_translate('Clear UA stats?'),
        'confirmClearBrute' => cybersec_translate('Clear brute force stats?'),
        'confirmClearAllBots' => cybersec_translate('Clear ALL bot logs?'),
        'confirmClearAllPages' => cybersec_translate('Clear ALL page logs?'),
        'confirmClearAudit' => cybersec_translate('Clear event log?'),
        'enterIP' => cybersec_translate('Enter IP or subnet'),
        'unblock' => cybersec_translate('Unblock'),
        'block' => cybersec_translate('Block'),
        'blocked' => cybersec_translate('blocked'),
        'logEmpty' => cybersec_translate('Log is empty'),
        'listEmpty' => cybersec_translate('List is empty.'),
        'noActiveBrute' => cybersec_translate('No active brute force attempts.'),
        'clearing' => cybersec_translate('Clearing...'),
        'done' => cybersec_translate('Done'),
        'noEvents' => cybersec_translate('No events yet'),
        'copied' => cybersec_translate('Copied!'),
        'logCopied' => cybersec_translate('Entire log copied'),
        'chars' => cybersec_translate('chars'),
        'nothingFound' => cybersec_translate('Nothing found'),
        'of' => cybersec_translate('of'),
        'back' => cybersec_translate('Back'),
        'forward' => cybersec_translate('Forward'),
        'testing' => cybersec_translate('Testing...'),
        'repeatTest' => cybersec_translate('Repeat Test'),
        'repeat' => cybersec_translate('Repeat'),
        'noDataExport' => cybersec_translate('No data to export'),
        'error' => cybersec_translate('Error'),
        'serverError' => cybersec_translate('Server error'),
        'resetStatsConfirm' => cybersec_translate('Reset statistics and clear ALL logs?'),
        'removePageConfirm' => cybersec_translate('Remove page from tracking?'),
        'unblockSubnetConfirm' => cybersec_translate('Unblock subnet'),
        'unblockAllConfirm' => cybersec_translate('Unblock ALL?'),
        'resetCounterConfirm' => cybersec_translate('Reset counter?'),
        'blockSubnetConfirm' => cybersec_translate('Block /24 subnet permanently?'),
        'deleteEntryConfirm' => cybersec_translate('Delete entry?'),
        'today' => gmdate('Y-m-d'),
    ),
    'urls' => array(
        'adminPage' => admin_url('admin.php?page=cyber-security'),
        'adminAjax' => admin_url('admin-ajax.php'),
    ),
));
}
add_action('admin_enqueue_scripts', 'cybersec_enqueue_admin_assets');

// ==================== SETTINGS PAGE ====================
function cybersec_admin_page(){
    // Test mode handler
if(isset($_POST['cybersec_test_mode']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cybersec_nonce'])),'cybersec_action')){
    $ip = cybersec_get_visitor_ip();
    if (sanitize_text_field(wp_unslash($_POST['cybersec_test_mode'])) === '1') {
        set_transient('cybersec_test_mode_' . $ip, true, 5 * MINUTE_IN_SECONDS);
    } else {
        delete_transient('cybersec_test_mode_' . $ip);
    }
}
    
    $tr=get_option(CYBERSEC_PAGES_OPTION,array());
    $bl=get_option(CYBERSEC_BLOCKED_OPTION,array());
    $bo=get_option(CYBERSEC_BOT_OPTIONS,cybersec_get_default_bot_options());
    $st=cybersec_get_settings();
    // Fix ones to recommended values
$recommended_fix = array(
    'auto_unblock_minutes' => 30,
    'brute_max_attempts' => 5,
    'brute_window_minutes' => 15,
    'rate_limit_requests' => 60,
    'rate_limit_window' => 60,
    'max_revisions' => 5,
    'auto_clean_logs_days' => 30,
    'referer_history_threshold' => 1,
    'referer_rate_1min' => 5,
    'referer_rate_5min' => 10,
    'referer_same_page' => 3,
    'referer_no_proxy_rate' => 3,
    '404_threshold' => 10,
    '404_window' => 5,
);

// Check real values in database
$raw_settings = get_option(CYBERSEC_SETTINGS_OPTION, array());
$needs_update = false;

foreach ($recommended_fix as $key => $default) {
    if (!isset($raw_settings[$key]) || $raw_settings[$key] === 1 || $raw_settings[$key] === '1') {
        $st[$key] = $default;
        $raw_settings[$key] = $default;
        $needs_update = true;
    }
}

if ($needs_update) {
    update_option(CYBERSEC_SETTINGS_OPTION, $raw_settings);
}
    $wl=cybersec_get_custom_whitelist();
    $tb=get_option(CYBERSEC_TEMP_BLOCKS_OPTION,array());
    $sip=cybersec_get_server_ip();
    $dy=cybersec_get_daily_stats();
    $to=cybersec_get_top_offenders(20);
    $suspicious = cybersec_get_suspicious_ips();
    $rs_defaults = cybersec_get_referer_settings();
    $permanent_blocked = cybersec_get_permanent_blocked_subnets();
    $brute_data = get_option(CYBERSEC_BRUTE_OPTION, array());
    $server_software = isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : cybersec_translate('Unknown');
    $php_version = phpversion();
    $wp_version = get_bloginfo('version');
    $memory_limit = ini_get('memory_limit');
    $max_execution_time = ini_get('max_execution_time');
    $db_version = $GLOBALS['wpdb']->db_version();
    $log_dir = CYBERSEC_LOG_DIR;
    $log_dir_writable = cybersec_fs_is_writable($log_dir);
    $total_log_size = 0;
    foreach(glob($log_dir.'*.log') as $f) $total_log_size += filesize($f);
    $known_bots = count(cybersec_get_known_bot_subnets());
    $blocked_uas = cybersec_get_blocked_user_agents();
    $datacenter_subnets = cybersec_get_datacenter_subnets();
    $datacenter_count = count($datacenter_subnets);
    $datacenter_enhanced = !empty($st['datacenter_enhanced']);
    
    $file_hashes = array();
    $integrity_alerts = array();
    
   // ==================== POST HANDLER — UNIFIED SAVE FORM ====================
        if(isset($_POST['cybersec_save_settings']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cybersec_nonce'])),'cybersec_action')){
        $ns=array(
            'blocked_email'=>sanitize_email(trim(isset($_POST['cybersec_blocked_email']) ? sanitize_text_field(wp_unslash($_POST['cybersec_blocked_email'])) : '')),
            'auto_unblock_minutes'=>max(0,(int)(isset($_POST['cybersec_auto_unblock']) ? sanitize_text_field(wp_unslash($_POST['cybersec_auto_unblock'])) : 30)),
            'alert_threshold'=>max(0,(int)(isset($_POST['cybersec_alert_threshold']) ? sanitize_text_field(wp_unslash($_POST['cybersec_alert_threshold'])) : 20)),
            'alert_minutes'=>max(1,(int)(isset($_POST['cybersec_alert_minutes']) ? sanitize_text_field(wp_unslash($_POST['cybersec_alert_minutes'])) : 5)),
            'alert_enabled'=>isset($_POST['cybersec_alert_enabled'])?1:0,
            'brute_max_attempts'=>max(1,(int)(isset($_POST['cybersec_brute_max']) ? sanitize_text_field(wp_unslash($_POST['cybersec_brute_max'])) : 5)),
            'brute_window_minutes'=>max(1,(int)(isset($_POST['cybersec_brute_window']) ? sanitize_text_field(wp_unslash($_POST['cybersec_brute_window'])) : 15)),
            'brute_enabled'=>isset($_POST['cybersec_brute_enabled'])?1:0,
            'hide_wp'=>isset($_POST['cybersec_hide_wp'])?1:0,
            'referer_safe_check'=>isset($_POST['cybersec_referer_safe_check'])?1:0,
            'referer_history_threshold'=>max(0,(int)(isset($_POST['cybersec_referer_history_threshold']) ? sanitize_text_field(wp_unslash($_POST['cybersec_referer_history_threshold'])) : 1)),
            'referer_rate_1min'=>max(1,(int)(isset($_POST['cybersec_referer_rate_1min']) ? sanitize_text_field(wp_unslash($_POST['cybersec_referer_rate_1min'])) : 5)),
            'referer_rate_5min'=>max(1,(int)(isset($_POST['cybersec_referer_rate_5min']) ? sanitize_text_field(wp_unslash($_POST['cybersec_referer_rate_5min'])) : 10)),
            'referer_same_page'=>max(1,(int)(isset($_POST['cybersec_referer_same_page']) ? sanitize_text_field(wp_unslash($_POST['cybersec_referer_same_page'])) : 3)),
            'referer_no_proxy_rate'=>max(1,(int)(isset($_POST['cybersec_referer_no_proxy_rate']) ? sanitize_text_field(wp_unslash($_POST['cybersec_referer_no_proxy_rate'])) : 3)),
            'hide_wp_version'=>isset($_POST['cybersec_hide_wp_version'])?1:0,
            'remove_script_versions'=>isset($_POST['cybersec_remove_script_versions'])?1:0,
            'hide_login_errors'=>isset($_POST['cybersec_hide_login_errors'])?1:0,
            'disable_xmlrpc_pingbacks'=>isset($_POST['cybersec_disable_xmlrpc_pingbacks'])?1:0,
            'force_secure_cookies'=>isset($_POST['cybersec_force_secure_cookies'])?1:0,
            'rate_limiting_enabled'=>isset($_POST['cybersec_rate_limiting_enabled'])?1:0,
            'rate_limit_requests'=>max(1,(int)(isset($_POST['cybersec_rate_limit_requests']) ? sanitize_text_field(wp_unslash($_POST['cybersec_rate_limit_requests'])) : 60)),
            'rate_limit_window'=>max(1,(int)(isset($_POST['cybersec_rate_limit_window']) ? sanitize_text_field(wp_unslash($_POST['cybersec_rate_limit_window'])) : 60)),
            'rest_api_protection'=>isset($_POST['cybersec_rest_api_protection'])?1:0,
            'hide_plugins_themes'=>isset($_POST['cybersec_hide_plugins_themes'])?1:0,
            'auto_clean_logs_days'=>max(0,(int)(isset($_POST['cybersec_auto_clean_logs_days']) ? sanitize_text_field(wp_unslash($_POST['cybersec_auto_clean_logs_days'])) : 30)),
            '404_detection_enabled'=>isset($_POST['cybersec_404_enabled'])?1:0,
            '404_threshold'=>max(1,(int)(sanitize_text_field(wp_unslash($_POST['cybersec_404_threshold'] ?? '10')))),
            '404_window'=>max(1,(int)(sanitize_text_field(wp_unslash($_POST['cybersec_404_window'] ?? '5')))),
            'url_firewall_enabled'=>isset($_POST['cybersec_url_firewall_enabled'])?1:0,
            'url_firewall_patterns'=>sanitize_textarea_field(wp_unslash($_POST['cybersec_url_firewall_patterns'] ?? '')),
            'cybersec_language' => sanitize_text_field(wp_unslash($_POST['cybersec_language'] ?? 'auto')),
        );
        
        $existing = get_option(CYBERSEC_SETTINGS_OPTION, array());
        $lang_changed = (isset($ns['cybersec_language']) && ($existing['cybersec_language'] ?? 'auto') !== $ns['cybersec_language']);
        $merged = array_merge($existing, $ns);
        update_option(CYBERSEC_SETTINGS_OPTION, $merged);
        $st = $merged;
        
        if ($lang_changed) {
    wp_cache_flush();
    $active_tab = 'settings';
    // Force page reload to update script translations
    echo '<meta http-equiv="refresh" content="0;url=' . esc_url(admin_url('admin.php?page=cyber-security&tab=settings&nocache=' . time())) . '">';
    return;
}
        
        $rs_defaults = cybersec_get_referer_settings();
        $active_tab = 'settings';
    }

        if(isset($_POST['cybersec_save_bot_options']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cybersec_nonce'])),'cybersec_action')){
        $no=cybersec_get_default_bot_options(); 
        foreach($no as $k=>$d) $no[$k]=isset($_POST['cybersec_bot_'.$k])?1:0;
        if(isset($_POST['cybersec_bot_cloudflare_origin'])) $no['cloudflare_origin']=1; else $no['cloudflare_origin']=0;
        if(isset($_POST['cybersec_bot_ipv6_connection'])) $no['ipv6_connection']=1; else $no['ipv6_connection']=0;
        update_option(CYBERSEC_BOT_OPTIONS,$no); $bo=$no;
        $active_tab = 'settings';
    }
    
    // Whitelist
    if(isset($_POST['cybersec_add_whitelist']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cybersec_nonce'])),'cybersec_action')){
        $tp=sanitize_text_field(wp_unslash($_POST['cybersec_wl_type'])); $vl=sanitize_text_field(trim(wp_unslash($_POST['cybersec_wl_value']))); $nt=sanitize_text_field(trim(wp_unslash($_POST['cybersec_wl_note'])));
        if(!empty($vl)){ $wl[]=array('type'=>$tp,'value'=>$vl,'note'=>$nt); update_option(CYBERSEC_WHITELIST_OPTION,$wl); }
        else{ echo'<div class="notice notice-error"><p>' . esc_html(cybersec_translate('Empty value.')) . '</p></div>'; }
        $active_tab = 'whitelist';
    }
    
    if(isset($_GET['cybersec_wl_remove']) && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])),'cybersec_wl_remove')){
        $idx=(int)sanitize_text_field(wp_unslash($_GET['cybersec_wl_remove'])); if(isset($wl[$idx]) && !cybersec_is_server_ip($wl[$idx]['value'])){ unset($wl[$idx]); update_option(CYBERSEC_WHITELIST_OPTION,array_values($wl)); }
        else{ echo'<div class="notice notice-error"><p>' . esc_html(cybersec_translate('Cannot remove server IP.')) . '</p></div>'; }
        $active_tab = 'whitelist';
    }

    // Monitoring
    if(isset($_POST['cybersec_add_page']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cybersec_nonce'])),'cybersec_action')){ $ns=cybersec_normalize_slug(sanitize_text_field(wp_unslash($_POST['cybersec_page_slug']))); if(!empty($ns) && !in_array($ns,$tr)){ $tr[]=$ns; update_option(CYBERSEC_PAGES_OPTION,$tr); } else{ echo'<div class="notice notice-error" id="page-added-error"><p><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="#ff4040" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><line x1="4" y1="4" x2="12" y2="12"/><line x1="12" y1="4" x2="4" y2="12"/></svg>' . esc_html(cybersec_translate('Already tracked or empty.')) . '</p></div>'; } 
        $active_tab = 'dashboard'; }
    if(isset($_GET['cybersec_remove']) && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])),'cybersec_remove')){ $s=sanitize_text_field(wp_unslash($_GET['cybersec_remove'])); if(($k=array_search($s,$tr))!==false){ unset($tr[$k]); $tr=array_values($tr); update_option(CYBERSEC_PAGES_OPTION,$tr); } 
        $active_tab = 'dashboard'; }
    
    if(isset($_POST['cybersec_clear_page_log']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cybersec_nonce'])),'cybersec_action')){ $f=CYBERSEC_LOG_DIR.sanitize_file_name(str_replace('/','-',sanitize_text_field(wp_unslash($_POST['cybersec_clear_slug'])))).'.log'; if(cybersec_fs_exists($f)){ cybersec_fs_put_contents($f,''); } }
    if(isset($_POST['cybersec_clear_all_page_logs']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cybersec_nonce'])),'cybersec_action')){ foreach(glob(CYBERSEC_LOG_DIR.'*.log') as $f){ $bn=basename($f); if($bn!=='blocked-bots.log' && $bn!=='allowed-bots.log') wp_delete_file($f); } }
    
    if(isset($_POST['cybersec_reset_stats']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cybersec_nonce'])),'cybersec_action')){ 
    update_option(CYBERSEC_STATS_OPTION,array()); $dy=array('blocked'=>0,'allowed'=>0,'brute'=>0,'hidden_wp'=>0);
    cybersec_fs_put_contents(CYBERSEC_LOG_DIR.'blocked-bots.log', '');
    cybersec_fs_put_contents(CYBERSEC_LOG_DIR.'allowed-bots.log', '');
    foreach(glob(CYBERSEC_LOG_DIR.'*.log') as $f){ $bn=basename($f); if($bn!=='blocked-bots.log' && $bn!=='allowed-bots.log') wp_delete_file($f); }
    update_option(CYBERSEC_REPEAT_OPTION, array()); $to = array();
    $active_tab = 'dashboard';
}
    
    // Blocks
    if(isset($_GET['cybersec_unblock_temp']) && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])),'cybersec_unblock_temp')){ $ip=sanitize_text_field(wp_unslash($_GET['cybersec_unblock_temp'])); $tb=get_option(CYBERSEC_TEMP_BLOCKS_OPTION,array()); if(isset($tb[$ip])){ unset($tb[$ip]); update_option(CYBERSEC_TEMP_BLOCKS_OPTION,$tb); cybersec_decrement_stat('blocked'); }
        $active_tab = 'protection';
    }
    
    if(isset($_POST['cybersec_unblock_all_temp']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cybersec_nonce'])),'cybersec_action')){
        $tb=get_option(CYBERSEC_TEMP_BLOCKS_OPTION,array()); $count = count($tb);
        update_option(CYBERSEC_TEMP_BLOCKS_OPTION, array());
        $t=gmdate('Y-m-d'); $s=get_option(CYBERSEC_STATS_OPTION,array());
        if(isset($s[$t]['blocked'])) { $s[$t]['blocked'] = max(0, (int)$s[$t]['blocked'] - $count); update_option(CYBERSEC_STATS_OPTION, $s); }
        $active_tab = 'protection';
    }
    
    if(isset($_GET['cybersec_unblock_brute']) && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])),'cybersec_unblock_brute')){ $ip=sanitize_text_field(wp_unslash($_GET['cybersec_unblock_brute'])); $brute = get_option(CYBERSEC_BRUTE_OPTION, array()); if(isset($brute[$ip])){ unset($brute[$ip]); update_option(CYBERSEC_BRUTE_OPTION, $brute); }
        $active_tab = 'protection';
    }
    if(isset($_GET['cybersec_unblock_permanent']) && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])),'cybersec_unblock_permanent')){ 
        $subnet=sanitize_text_field(wp_unslash($_GET['cybersec_unblock_permanent'])); cybersec_remove_permanent_blocked_subnet($subnet);
        $active_tab = 'protection';
    }
    if(isset($_POST['cybersec_block_permanent']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cybersec_nonce'])),'cybersec_action')){ 
        $input=sanitize_text_field(trim(wp_unslash($_POST['cybersec_block_permanent_ip']))); $reason=sanitize_text_field(wp_unslash($_POST['cybersec_block_permanent_reason'])); 
        if(!empty($input)){ 
            if(strpos($input,'/')!==false){ $subnet = $input; $ip = strtok($input,'/');
                if(filter_var($ip,FILTER_VALIDATE_IP) && !cybersec_is_whitelisted($ip)){ cybersec_add_permanent_blocked_subnet($subnet, $reason, $ip); } }
            else{ $ip = $input;
                if(filter_var($ip,FILTER_VALIDATE_IP) && !cybersec_is_whitelisted($ip)){ $subnet = cybersec_get_subnet_24($ip); cybersec_add_permanent_blocked_subnet($subnet, $reason, $ip); } }
        }
        $active_tab = 'protection';
    }
    if(isset($_GET['cybersec_block_offender']) && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])),'cybersec_block_offender')){ 
        $ip=sanitize_text_field(wp_unslash($_GET['cybersec_block_offender'])); 
        if(!empty($ip) && filter_var($ip,FILTER_VALIDATE_IP) && !cybersec_is_whitelisted($ip)){ 
            $sub=cybersec_get_subnet_24($ip); cybersec_add_permanent_blocked_subnet($sub, cybersec_translate('TOP offender'), $ip); 
        }
        $active_tab = 'protection';
    }
    if(isset($_GET['cybersec_remove_offender']) && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])),'cybersec_remove_offender')){ $ip=sanitize_text_field(wp_unslash($_GET['cybersec_remove_offender'])); $off=get_option(CYBERSEC_REPEAT_OPTION,array()); if(isset($off[$ip])){ unset($off[$ip]); update_option(CYBERSEC_REPEAT_OPTION,$off); }
        $active_tab = 'protection';
    }
    
    // Reload after processing
    $tr=get_option(CYBERSEC_PAGES_OPTION,array()); $bl=get_option(CYBERSEC_BLOCKED_OPTION,array()); $wl=cybersec_get_custom_whitelist();
    $tb=get_option(CYBERSEC_TEMP_BLOCKS_OPTION,array()); $dy=cybersec_get_daily_stats(); $to=cybersec_get_top_offenders(20);
    $permanent_blocked = cybersec_get_permanent_blocked_subnets(); $brute_data = get_option(CYBERSEC_BRUTE_OPTION, array());
    $blocked_uas = cybersec_get_blocked_user_agents();
    
    $em=cybersec_get_blocked_email(); $am=cybersec_get_auto_unblock_minutes();
    $as=cybersec_is_auto_whitelist_server_enabled(); $rs=cybersec_is_referer_safe_check_enabled();
    $datacenter_enhanced = !empty($st['datacenter_enhanced']);
    $file_hashes = array();
    $integrity_alerts = array();
    
    $current_locale = cybersec_get_locale();
    $available_languages = cybersec_get_available_languages();
    
    // Determine active tab
    if (!isset($active_tab)) {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $active_tab = isset($_GET['cybersec_tab']) ? sanitize_text_field(wp_unslash($_GET['cybersec_tab'])) : 'dashboard';
        // phpcs:enable
    }
    ?>
    
    <div class="wrap">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:6px;">
    <h1 style="margin-bottom:0;">
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 36" width="36" height="36" class="cybersec-logo">
  <path fill="none" stroke="#000000" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M 18.0,30.8 C 17.2,30.4 9.2,25.9 7.6,10.5 C 7.5,7.5 12.3,5.2 18.0,5.2 C 23.7,5.2 28.5,7.5 28.4,10.5 C 26.8,26.8 18.0,30.8 18.0,30.8 Z"/>
  <path fill="#D0CFCE" d="M 18.0,5.5 C 12.5,5.5 8.0,7.8 8.0,10.5 L 8.0,10.6 C 9.5,25.8 17.3,30.3 18.0,30.7 L 18.0,30.7 C 18.0,30.7 26.6,26.7 28.0,10.7 C 28.2,7.9 23.5,5.5 18.0,5.5 Z"/>
  <path fill="#D0CFCE" d="M 18.0,27.5 L 18.0,27.5 C 17.6,27.2 11.3,23.9 10.0,11.5 L 10.0,11.5 C 10.0,9.3 13.6,7.5 18.0,7.5 C 22.4,7.5 26.2,9.3 26.0,11.5 C 24.8,24.2 18.0,27.5 18.0,27.5 Z"/>
  <path fill="#E60012" d="M 17.6,9.0 L 19.0,11.0 L 17.6,13.1 L 19.0,15.0 L 17.6,17.0 L 19.0,19.0 L 17.6,21.0 L 19.0,23.1 L 17.6,25.1 L 18.0,27.7 C 18.0,27.7 10.5,22.2 10.0,11.7 C 10.0,11.7 10.0,7.7 18.0,7.7 L 17.6,9.0 Z"/>
  <path fill="#FFFFFF" d="M 18.0,7.7 L 17.6,9.0 L 19.0,11.0 L 17.6,13.1 L 19.0,15.0 L 17.6,17.0 L 19.0,19.0 L 17.6,21.0 L 19.0,23.1 L 17.6,25.1 L 18.0,27.7 C 18.0,27.7 25.5,23.1 26.0,11.7 C 26.0,11.5 25.5,7.7 18.0,7.7 Z"/>
</svg> CyberPulse <span class="sw-version-badge">v<?php echo esc_html(CYBERSEC_VERSION); ?> • Heartbeat of Security</span></h1>
    <label class="sw-power-toggle" title="<?php echo esc_attr(cybersec_translate('Toggle theme')); ?>" style="margin-top:7px;">
        <input type="checkbox" id="sw-theme-checkbox" onchange="swToggleTheme()">
        <span class="sw-power-slider"></span>
    </label>
</div>
    
    <div id="sw-copy-toast" class="sw-copy-toast"><?php echo esc_html(cybersec_translate('Copied!')); ?></div>
    
    <?php // ==================== STATISTICS ==================== ?>
    <div class="card" style="max-width:100%;padding:24px 28px;margin-bottom:20px;">
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:8px;margin-bottom:16px;text-align:center;">
            <div style="background:var(--sw-card-bg-alt);padding:16px 8px;border-radius:var(--sw-radius);border:2px solid var(--sw-danger-border);box-shadow:2px 2px 0 rgba(0,0,0,0.15);"><div style="font-size:28px;font-weight:800;color:var(--sw-danger);text-shadow:0 0 10px var(--sw-danger-border);"><?php echo (int)$dy['blocked'];?></div><div style="font-size:11px;color:var(--sw-text-secondary);margin-top:4px;"><?php echo esc_html(cybersec_translate('Blocked')); ?></div></div>
            <div style="background:var(--sw-card-bg-alt);padding:16px 8px;border-radius:var(--sw-radius);border:2px solid var(--sw-success-border);box-shadow:2px 2px 0 rgba(0,0,0,0.15);"><div style="font-size:28px;font-weight:800;color:var(--sw-success);text-shadow:0 0 10px var(--sw-success-border);"><?php echo (int)$dy['allowed'];?></div><div style="font-size:11px;color:var(--sw-text-secondary);margin-top:4px;"><?php echo esc_html(cybersec_translate('Allowed')); ?></div></div>
            <div style="background:var(--sw-card-bg-alt);padding:16px 8px;border-radius:var(--sw-radius);border:2px solid var(--sw-warning-border);box-shadow:2px 2px 0 rgba(0,0,0,0.15);"><div style="font-size:28px;font-weight:800;color:var(--sw-warning);text-shadow:0 0 10px var(--sw-warning-border);"><?php echo (int)$dy['brute'];?></div><div style="font-size:11px;color:var(--sw-text-secondary);margin-top:4px;"><?php echo esc_html(cybersec_translate('Brute Force')); ?></div></div>
            <div style="background:var(--sw-card-bg-alt);padding:16px 8px;border-radius:var(--sw-radius);border:2px solid var(--sw-border-light);box-shadow:2px 2px 0 rgba(0,0,0,0.15);"><div style="font-size:28px;font-weight:800;color:var(--sw-text-secondary);"><?php echo (int)$dy['hidden_wp'];?></div><div style="font-size:11px;color:var(--sw-text-secondary);margin-top:4px;"><?php echo esc_html(cybersec_translate('Hidden WP')); ?></div></div>
            <div style="background:var(--sw-card-bg-alt);padding:16px 8px;border-radius:var(--sw-radius);border:2px solid var(--sw-info-border);box-shadow:2px 2px 0 rgba(0,0,0,0.15);"><div style="font-size:28px;font-weight:800;color:var(--sw-info);text-shadow:0 0 10px var(--sw-info-border);"><?php echo count($tb);?></div><div style="font-size:11px;color:var(--sw-text-secondary);margin-top:4px;"><?php echo esc_html(cybersec_translate('Temporary')); ?></div></div>
            <div style="background:var(--sw-card-bg-alt);padding:16px 8px;border-radius:var(--sw-radius);border:2px solid var(--sw-danger-border);box-shadow:2px 2px 0 rgba(0,0,0,0.15);"><div style="font-size:28px;font-weight:800;color:var(--sw-danger);text-shadow:0 0 10px var(--sw-danger-border);"><?php echo count($bl) + count($permanent_blocked);?></div><div style="font-size:11px;color:var(--sw-text-secondary);margin-top:4px;"><?php echo esc_html(cybersec_translate('Blocked Subnets')); ?></div></div>
            <div style="background:var(--sw-card-bg-alt);padding:16px 8px;border-radius:var(--sw-radius);border:2px solid var(--sw-success-border);box-shadow:2px 2px 0 rgba(0,0,0,0.15);"><div style="font-size:28px;font-weight:800;color:var(--sw-success);text-shadow:0 0 10px var(--sw-success-border);"><?php echo $rs ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="#00e048" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>' : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="#ff4040" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'; ?></div><div style="font-size:11px;color:var(--sw-text-secondary);margin-top:10px;"><?php echo esc_html(cybersec_translate('Referer')); ?></div></div>
        </div>
        <div style="display:flex;justify-content:center;align-items:center;gap:16px;flex-wrap:wrap;">
    <form method="post" style="display:inline;flex-shrink:0;" onsubmit="return confirm('<?php echo esc_js(cybersec_translate('Reset statistics and clear ALL logs?')); ?>');">
        <?php wp_nonce_field('cybersec_action','cybersec_nonce'); ?>
        <button type="submit" name="cybersec_reset_stats" class="button button-small" style="display:inline-flex;align-items:center;gap:3px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="11" height="11" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="M2,4 L5,4 L6.5,2.5 L9.5,2.5 L11,4 L14,4"/><path d="M3.5,4.5 L4,13 C4,13.5 4.5,14 5,14 L11,14 C11.5,14 12,13.5 12,13 L12.5,4.5"/><line x1="7" y1="7" x2="7" y2="11.5"/><line x1="9" y1="7" x2="9" y2="11.5"/></svg><?php echo esc_html(cybersec_translate('Reset All')); ?></button>
    </form>
    <p style="margin:0;font-size:11px;color:var(--sw-text-secondary);text-align:center;"><?php echo esc_html(cybersec_translate('Statistics for today')); ?> (<?php echo esc_html(gmdate('d.m.Y'));?>) • <?php echo esc_html(cybersec_translate('Auto-unblock')); ?>: <?php echo (int)$am;?> <?php echo esc_html(cybersec_translate('min')); ?>. • <?php echo esc_html(cybersec_translate('Server IP')); ?>: <?php echo esc_html($sip);?></p>
</div>
        <?php
        $hourly_data = array_fill(0,24,0); $bf = CYBERSEC_LOG_DIR . 'blocked-bots-' . gmdate('Y-m-d') . '.log';
        if (cybersec_fs_exists($bf)) { $log_content = cybersec_fs_get_contents($bf); $log_lines = !empty($log_content) ? array_filter(explode("\n", $log_content)) : array(); $today = gmdate('Y-m-d');
            foreach ($log_lines as $line) { if (preg_match('/^\[' . $today . ' (\d{2}):/', $line, $m)) $hourly_data[(int)$m[1]]++; } }
        $max_val = max($hourly_data) ?: 1; $total_hourly = array_sum($hourly_data);
        ?>
        <?php if ($total_hourly > 0): ?>
        <div style="margin-top:14px;padding-top:14px;border-top:2px solid var(--sw-border-light);">
            <div style="display:flex;align-items:flex-end;gap:2px;height:44px;margin-bottom:6px;">
                <?php foreach ($hourly_data as $h => $val): $height = round(($val / $max_val) * 42); $color = $val > 5 ? 'var(--sw-danger)' : ($val > 0 ? 'var(--sw-warning)' : 'var(--sw-input-bg)'); ?>
                <div title="<?php echo (int)$h; ?>:00 — <?php echo (int)$val; ?> <?php echo esc_attr(cybersec_translate('blocks')); ?>" style="flex:1;height:<?php echo (int)max((int)$height, 2);?>px;background:<?php echo esc_attr($color);?>;border-radius:2px 2px 0 0;min-width:4px;cursor:pointer;" onclick="alert('<?php echo (int)$h; ?>:00 — <?php echo (int)$val; ?> <?php echo esc_js(cybersec_translate('blocks')); ?>')"></div>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:9px;color:var(--sw-text-secondary);"><span>0h</span><span>3h</span><span>6h</span><span>9h</span><span>12h</span><span>15h</span><span>18h</span><span>21h</span><span>24h</span></div>
            <p style="text-align:center;font-size:10px;color:var(--sw-text-secondary);margin:6px 0 0 0;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="11" height="11" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><rect x="2" y="8" width="3" height="6" rx="0.5"/><rect x="6.5" y="4" width="3" height="10" rx="0.5"/><rect x="11" y="6" width="3" height="8" rx="0.5"/></svg><?php echo esc_html(cybersec_translate('Blocks by hour — total today')); ?>: <strong style="color:var(--sw-danger);"><?php echo (int)$total_hourly; ?></strong></p>
        </div>
        <?php else: ?>
        <div style="margin-top:14px;padding-top:14px;border-top:2px solid var(--sw-border-light);text-align:center;"><p style="font-size:10px;color:var(--sw-text-secondary);margin:0;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="11" height="11" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><rect x="2" y="8" width="3" height="6" rx="0.5"/><rect x="6.5" y="4" width="3" height="10" rx="0.5"/><rect x="11" y="6" width="3" height="8" rx="0.5"/></svg><?php echo esc_html(cybersec_translate('No blocks yet today')); ?></p></div>
        <?php endif; ?>
    </div>
    
    <nav class="nav-tab-wrapper" style="margin-bottom:20px;display:flex;flex-wrap:wrap;gap:2px;">
    <?php $tabs = array('dashboard'=>cybersec_translate('Dashboard'),'protection'=>cybersec_translate('Protection'),'whitelist'=>cybersec_translate('Whitelist'),'settings'=>cybersec_translate('Settings'),'info'=>cybersec_translate('Info')); foreach($tabs as $k=>$v): ?>
    <a href="#" data-tab="<?php echo esc_attr($k);?>" class="nav-tab <?php if($k===$active_tab) echo 'nav-tab-active';?>" style="flex:1;text-align:center;padding:10px 12px;font-size:13px;"><?php echo esc_html($v);?></a>
    <?php endforeach; ?>
</nav>

<?php // ==================== TAB 1: DASHBOARD ==================== ?>
<div id="tab-dashboard" class="sw-tab" style="display:<?php echo $active_tab==='dashboard'?'block':'none';?>;">

    <!-- Security Score + Security Test + Threat Level -->
    <?php $security_score = cybersec_get_security_score(); ?>
    <div class="card" style="max-width:100%;padding:20px 24px;margin-bottom:16px;">
        <div style="display:flex;align-items:center;gap:20px;margin-bottom:16px;">
            <div style="font-size:56px;font-weight:800;color:<?php echo esc_attr($security_score['grade']['color']); ?>;text-shadow:0 0 20px <?php echo esc_attr($security_score['grade']['color']); ?>;line-height:1;">
                <?php echo esc_html($security_score['grade']['grade']); ?>
            </div>
            <div>
                <h2 style="margin:0;font-size:16px;color:var(--sw-text-bright);"><?php echo esc_html(cybersec_translate('Security Score')); ?>: <strong style="color:<?php echo esc_attr($security_score['grade']['color']); ?>;"><?php echo (int)$security_score['score']; ?>/100</strong></h2>
                <p style="margin:4px 0 0 0;font-size:12px;color:var(--sw-text-secondary);">
                    <?php if ($security_score['score'] >= 90): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="#00e048" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><polyline points="3,8 6,11 13,4"/></svg><?php echo esc_html(cybersec_translate('Excellent protection! Your site is well protected.')); ?>
                    <?php elseif ($security_score['score'] >= 80): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="#ffb800" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><circle cx="8" cy="13" r="1.2" fill="#ffb800"/><path d="M8,3 L8,10"/></svg><?php echo esc_html(cybersec_translate('Good protection, but there is room for improvement.')); ?>
                    <?php elseif ($security_score['score'] >= 70): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="#ffb800" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><path d="M8,2 L1,14 L15,14 Z"/><line x1="8" y1="6" x2="8" y2="10"/><circle cx="8" cy="12" r="0.8" fill="#ffb800"/></svg><?php echo esc_html(cybersec_translate('Average protection level. Improvements recommended.')); ?>
                    <?php elseif ($security_score['score'] >= 60): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="#ff4040" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><circle cx="8" cy="8" r="6"/><line x1="8" y1="5" x2="8" y2="9"/><circle cx="8" cy="11.5" r="0.8" fill="#ff4040"/></svg><?php echo esc_html(cybersec_translate('Protection is below recommended level. Improvements needed.')); ?>
                    <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="#ff4040" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><polygon points="8,1 1,15 15,15" fill="#ff4040" fill-opacity="0.15"/><line x1="8" y1="5" x2="8" y2="10"/><circle cx="8" cy="12.5" r="0.8" fill="#ff4040"/></svg><?php echo esc_html(cybersec_translate('Critical protection level! Take action immediately!')); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <?php if (!empty($security_score['issues'])): ?>
        <div style="background:var(--sw-card-bg-alt);border:2px solid var(--sw-border-light);border-radius:var(--sw-radius);padding:16px;">
            <h3 style="margin:0 0 12px 0;font-size:13px;color:var(--sw-warning);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="#f9ab00" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><path d="M8,1.5 L1.5,14 L14.5,14 Z"/><line x1="8" y1="5.5" x2="8" y2="9"/><circle cx="8" cy="11.5" r="1" fill="#f9ab00" stroke="none"/></svg><?php echo esc_html(cybersec_translate('Issues found and recommendations:')); ?></h3>
            <table style="width:100%;font-size:12px;color:var(--sw-text-secondary);">
                <thead>
                    <tr style="color:var(--sw-accent);text-align:left;">
                        <th style="padding:8px;width:30px;">#</th>
                        <th style="padding:8px;"><?php echo esc_html(cybersec_translate('Issue')); ?></th>
                        <th style="padding:8px;width:80px;text-align:center;"><?php echo esc_html(cybersec_translate('Penalty')); ?></th>
                        <th style="padding:8px;width:200px;"><?php echo esc_html(cybersec_translate('How to Fix')); ?></th>
                    </tr>
                </thead>
                                <tbody>
                    <?php $i = 1; foreach ($security_score['issues'] as $issue): 
    $fix = '';
    if (stripos($issue['text'], cybersec_translate('outdated plugins')) !== false) {
        $fix = '<a href="' . esc_url(admin_url('plugins.php?plugin_status=upgrade')) . '" style="color:var(--sw-info);">' . esc_html(cybersec_translate('Update plugins')) . ' →</a>';
    } elseif (stripos($issue['text'], cybersec_translate('2FA not configured')) !== false) {
        $fix = '<a href="' . esc_url(admin_url('profile.php')) . '#cybersec-2fa-section" style="color:var(--sw-info);">' . esc_html(cybersec_translate('Configure in profile')) . ' →</a>';
    } elseif (stripos($issue['text'], cybersec_translate('Brute force protection')) !== false) {
        $fix = '<a href="#" onclick="sw_tab(\'settings\')" style="color:var(--sw-info);">' . esc_html(cybersec_translate('Enable in settings')) . ' →</a>';
    } elseif (stripos($issue['text'], cybersec_translate('not hidden')) !== false) {
        $fix = '<a href="#" onclick="sw_tab(\'settings\')" style="color:var(--sw-info);">' . esc_html(cybersec_translate('Enable in settings')) . ' →</a>';
    } elseif (stripos($issue['text'], cybersec_translate('CSP headers')) !== false) {
        $fix = '<a href="#" onclick="sw_tab(\'settings\')" style="color:var(--sw-info);">' . esc_html(cybersec_translate('Enable in settings')) . ' →</a>';
    } elseif (stripos($issue['text'], cybersec_translate('Rate Limiting')) !== false) {
        $fix = '<a href="#" onclick="sw_tab(\'settings\')" style="color:var(--sw-info);">' . esc_html(cybersec_translate('Enable in settings')) . ' →</a>';
    } elseif (stripos($issue['text'], cybersec_translate('Content protection')) !== false) {
        $fix = '<a href="#" onclick="sw_tab(\'settings\')" style="color:var(--sw-info);">' . esc_html(cybersec_translate('Enable in settings')) . ' →</a>';
    } else {
        $fix = '<a href="#" onclick="sw_tab(\'settings\')" style="color:var(--sw-info);">' . esc_html(cybersec_translate('Check settings')) . ' →</a>';
    }
?>
                    <tr style="border-bottom:1px solid var(--sw-border-light);">
                        <td style="padding:8px;text-align:center;"><?php echo (int)$i++; ?></td>
                        <td style="padding:8px;"><?php echo esc_html($issue['text']); ?></td>
                        <td style="padding:8px;text-align:center;color:var(--sw-danger);"><?php echo (int)$issue['penalty']; ?></td>
                        <td style="padding:8px;"><?php echo wp_kses_post($fix); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="background:var(--sw-success-light);border:2px solid var(--sw-success-border);border-radius:var(--sw-radius);padding:16px;text-align:center;">
            <p style="margin:0;font-size:13px;color:var(--sw-success);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="#34a853" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><polyline points="3,8 6,11 13,4"/></svg><?php echo esc_html(cybersec_translate('All checks passed! Your site is well protected.')); ?></p>
        </div>
        <?php endif; ?>
        
        <hr style="border:0;border-top:2px solid var(--sw-border-light);margin:20px 0;">
        
        <h2 style="margin:0 0 6px 0;font-size:16px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><path d="M8,1 L2,3.5 L2,8.5 C2,12 8,15 8,15 C8,15 14,12 14,8.5 L14,3.5 Z"/><polyline points="5,8 7,10 11,6"/></svg><?php echo esc_html(cybersec_translate('Security Test')); ?></h2>
        <p style="color:var(--sw-text-secondary);font-size:13px;margin-bottom:14px;"><?php echo esc_html(cybersec_translate('Tests the protection features by simulating typical attacks. The test runs from your server.')); ?></p>
        
        <div id="sw-test-results" style="display:none;">
            <div style="margin-bottom:14px;">
                <span style="font-size:0.8rem;font-weight:700;" id="sw-test-summary"></span>
            </div>
            <div id="sw-test-list"></div>
        </div>
        
        <div id="sw-test-empty" style="text-align:center;padding:20px;color:var(--sw-text-secondary);">
    <div style="margin-bottom:16px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" opacity="0.35"><path d="M24,3 L7,9 L7,22 C7,31 24,45 24,45 C24,45 41,31 41,22 L41,9 Z"/><circle cx="25" cy="25" r="9"/><line x1="31.5" y1="31.5" x2="37" y2="37"/><line x1="25" y1="20" x2="25" y2="30"/><line x1="20" y1="25" x2="30" y2="25"/></svg></div>
    <div style="font-weight:600;margin-bottom:8px;"><?php echo esc_html(cybersec_translate('Ready to check')); ?></div>
            <button type="button" class="button button-primary" onclick="runSecurityTest()" id="sw-test-btn"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="13" height="13" fill="currentColor" stroke="none" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><path d="M4,2 L4,14 L13,8 Z"/></svg><?php echo esc_html(cybersec_translate('Run Test')); ?></button>
        </div>
        
        <div style="background:var(--sw-info-light);border:2px solid var(--sw-info-border);border-radius:var(--sw-radius);padding:10px 14px;margin-top:14px;">
            <strong style="color:var(--sw-info);font-size:11px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><circle cx="8" cy="8" r="7"/><line x1="8" y1="4.5" x2="8" y2="4.5" stroke-width="2.5"/><line x1="8" y1="7" x2="8" y2="12"/></svg><?php echo esc_html(cybersec_translate('Note')); ?></strong>
            <p style="margin:6px 0 0 0;font-size:11px;color:var(--sw-text-secondary);line-height:1.5;">
                <?php echo esc_html(cybersec_translate('The test sends requests from your server IP. Tests like "Hide wp-login.php" and "Author enumeration" are not tested as the server cannot attack itself.')); ?><br>
                <strong><?php echo esc_html(cybersec_translate('For a complete check, use external services.')); ?></strong>
            </p>
        </div>
    </div>

    <!-- Page Tracking -->
    <div class="card" style="max-width:100%;padding:20px 24px;margin-bottom:16px;">
        <h2 style="margin:0 0 6px 0;font-size:16px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg><?php echo esc_html(cybersec_translate('Page Tracking')); ?></h2>
        <p style="color:var(--sw-text-secondary);font-size:13px;margin-bottom:12px;"><?php echo esc_html(cybersec_translate('Enter page slug or full URL. For homepage enter')); ?> <code><?php echo esc_html(home_url()); ?></code></p>
        <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;"><?php wp_nonce_field('cybersec_action','cybersec_nonce');?><input type="text" name="cybersec_page_slug" placeholder="my-page or blog/article" style="flex:1;min-width:200px;padding:8px;border-radius:4px;border:2px solid var(--sw-border);"><button type="submit" name="cybersec_add_page" class="button button-primary"><?php echo esc_html(cybersec_translate('Add')); ?></button></form>
        
        <?php if(!empty($tr)):?>
        <div style="overflow-x:auto;">
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th><?php echo esc_html(cybersec_translate('Page')); ?></th><th style="width:80px;"><?php echo esc_html(cybersec_translate('Entries')); ?></th><th style="width:280px;"><?php echo esc_html(cybersec_translate('Actions')); ?></th></tr></thead>
                <tbody>
                    <?php foreach($tr as $s): $f=CYBERSEC_LOG_DIR.sanitize_file_name(str_replace('/','-',$s)).'.log'; $log_content = cybersec_fs_exists($f) ? cybersec_fs_get_contents($f) : ''; $c = (!empty($log_content) && filesize($f) < CYBERSEC_MAX_LOG_SIZE) ? count(array_filter(explode("\n", $log_content))) : 0; $counter_id = 'counter-'.sanitize_key($s); ?>
                    <tr><td><code><?php echo esc_html($s);?></code></td><td style="text-align:center;"><strong class="sw-page-counter" id="<?php echo esc_attr($counter_id); ?>"><?php echo (int)$c;?></strong></td>
                        <td><a href="<?php echo esc_url(admin_url('admin.php?page=cyber-security&cybersec_view='.urlencode($s)));?>" class="button button-small"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg><?php echo esc_html(cybersec_translate('Log')); ?></a>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cyber-security&cybersec_remove='.urlencode($s)),'cybersec_remove'));?>" class="button button-small" style="color:var(--sw-danger);" onclick="return confirm('<?php echo esc_js(cybersec_translate('Remove page from tracking?')); ?>')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg><?php echo esc_html(cybersec_translate('Delete')); ?></a>
                            <button type="button" class="button button-small" onclick="cybersecAjax.clearLogAndUpdate('<?php echo esc_js($s); ?>', 'sw-page-log-ta', '<?php echo esc_js($counter_id); ?>', this)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg><?php echo esc_html(cybersec_translate('Clear')); ?></button></td></tr>
                    <?php endforeach;?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p style="color:var(--sw-text-secondary);text-align:center;padding:20px;"><?php echo esc_html(cybersec_translate('No tracked pages. Add your first page above.')); ?></p>
        <?php endif;?>
    </div>
    
    <?php
    $all_logs_content = '';
    if(!empty($tr)): foreach($tr as $s) { $f = CYBERSEC_LOG_DIR.sanitize_file_name(str_replace('/','-',$s)).'.log'; if(cybersec_fs_exists($f) && filesize($f) < CYBERSEC_MAX_LOG_SIZE) $all_logs_content .= cybersec_fs_get_contents($f); } endif;
    ?>
    
    <?php if(!empty($all_logs_content)): 
        $all_lines = array_filter(explode("\n", $all_logs_content)); $total_visits = count($all_lines); $all_ips = array(); $all_pages = array();
        foreach($all_lines as $line) { if(preg_match('/IP:\s*([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/', $line, $m)) { $ip = $m[1]; $all_ips[$ip] = ($all_ips[$ip]??0)+1; } }
        foreach($tr as $s) { $f = CYBERSEC_LOG_DIR.sanitize_file_name(str_replace('/','-',$s)).'.log'; $log_content = cybersec_fs_exists($f) ? cybersec_fs_get_contents($f) : ''; $c = (!empty($log_content) && filesize($f) < CYBERSEC_MAX_LOG_SIZE) ? count(array_filter(explode("\n", $log_content))) : 0; $all_pages[$s] = $c; }
        arsort($all_ips); arsort($all_pages);
    ?>
    <div class="card" style="max-width:100%;padding:20px 24px;margin-bottom:16px;">
        <h2 style="margin:0 0 6px 0;font-size:16px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><?php echo esc_html(cybersec_translate('Tracked Pages Traffic')); ?></h2>
        <div class="sw-log-analysis" style="margin:12px 0;">
            <div class="sw-stat-row">
                <div class="sw-stat-card"><h4><?php echo esc_html(cybersec_translate('Total Visits')); ?></h4><div class="sw-stat-num" style="color:var(--sw-success);"><?php echo (int)$total_visits; ?></div></div>
                <div class="sw-stat-card"><h4><?php echo esc_html(cybersec_translate('Active IPs')); ?></h4><ul><?php $i=0; foreach($all_ips as $ip=>$n): if($i++>=3) break; ?><li onclick="swCopyToClipboard('<?php echo esc_js($ip); ?>', this)" title="<?php echo esc_attr(cybersec_translate('Click to copy IP')); ?>"><code><?php echo esc_html($ip);?></code> <strong><?php echo (int)$n;?></strong></li><?php endforeach; ?></ul></div>
                <div class="sw-stat-card"><h4><?php echo esc_html(cybersec_translate('Top Pages')); ?></h4><ul><?php $i=0; foreach($all_pages as $p=>$n): if($i++>=3) break; ?><li onclick="swCopyToClipboard('<?php echo esc_js($p); ?>', this)"><code><?php echo esc_html(strlen($p)>35?substr($p,0,32).'...':$p);?></code> <strong><?php echo (int)$n;?></strong></li><?php endforeach; ?></ul></div>
                <div class="sw-stat-card"><h4><?php echo esc_html(cybersec_translate('Tracked')); ?></h4><div class="sw-stat-num" style="color:var(--sw-success);"><?php echo count($tr); ?></div><span style="font-size:12px;color:var(--sw-text-secondary);"><?php echo esc_html(cybersec_translate('pages')); ?></span></div>
            </div>
        </div>
        <input type="text" placeholder="<?php echo esc_attr(cybersec_translate('Search by IP or UA...')); ?>" onkeyup="swPageFilterLog(this.value)" style="width:100%;max-width:400px;padding:8px;margin-bottom:10px;border-radius:4px;border:2px solid var(--sw-border);">
        <div class="sw-page-log-viewer" id="sw-page-log-ta"><?php
            $lines = array_filter(explode("\n", $all_logs_content));
            foreach($lines as $line):
                $line = esc_html($line); $line = preg_replace('/^(\[[^\]]+\])/', '<span class="sw-page-log-date">$1</span>', $line);
                $line = preg_replace('/IP:\s*(\S+)/', 'IP: <span class="sw-page-log-ip">$1</span>', $line);
                $line = preg_replace('/UA:\s*(.+?)\s*\|/', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;opacity:0.6;"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg><span class="sw-page-log-ua">$1</span> |', $line);
                if(stripos($line,'Direct')!==false) { $line = preg_replace('/Ref:\s*Direct/', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;opacity:0.6;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><span class="sw-page-log-ref-direct">Direct</span>', $line); }
                else { $line = preg_replace('/Ref:\s*(\S+)/', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;opacity:0.6;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><span class="sw-page-log-ref">$1</span>', $line); }
                echo '<div class="sw-page-log-entry">' . wp_kses_post($line) . '</div>';
            endforeach;
        ?></div>
        <div class="sw-log-actions">
            <button type="button" class="button button-small" onclick="swDownloadLog('sw-page-log-ta', 'cybersec-pages-<?php echo esc_js(gmdate('Y-m-d')); ?>.txt')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg><?php echo esc_html(cybersec_translate('Download')); ?></button>
            <button type="button" class="button button-small" onclick="swCopyAllLog('sw-page-log-ta')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><?php echo esc_html(cybersec_translate('Copy All')); ?></button>
            <button type="button" class="button button-small" onclick="cybersecAjax.clearAllPageLogsAndUpdate('sw-page-log-ta', this)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg><?php echo esc_html(cybersec_translate('Clear All Logs')); ?></button>
        </div>
    </div>
    <?php elseif(!empty($tr)): ?>
    <div class="card" style="max-width:100%;padding:20px 24px;margin-bottom:16px;">
        <h2 style="margin:0 0 6px 0;font-size:16px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><?php echo esc_html(cybersec_translate('Tracked Pages Traffic')); ?></h2>
        <div class="sw-page-log-viewer" id="sw-page-log-ta" style="display:flex;align-items:center;justify-content:center;">
            <div style="text-align:center;"><div style="font-size:36px;margin-bottom:8px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.3"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div><div style="font-weight:600;"><?php echo esc_html(cybersec_translate('Log is empty')); ?></div></div>
        </div>
        <div class="sw-log-actions"><button type="button" class="button button-small" onclick="cybersecAjax.clearAllPageLogsAndUpdate('sw-page-log-ta', this)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg><?php echo esc_html(cybersec_translate('Clear All Logs')); ?></button></div>
    </div>
    <?php endif; ?>
    
    <?php $vs=isset($_GET['cybersec_view'])?sanitize_text_field(wp_unslash($_GET['cybersec_view'])):'';
    if(!empty($vs) && in_array($vs,$tr)):
        $vf=CYBERSEC_LOG_DIR.sanitize_file_name(str_replace('/','-',$vs)).'.log'; $page_log_content = (cybersec_fs_exists($vf) && filesize($vf) < CYBERSEC_MAX_LOG_SIZE) ? cybersec_fs_get_contents($vf) : '';
        $page_lines = !empty($page_log_content) ? array_filter(explode("\n", $page_log_content)) : array(); $page_total = count($page_lines); $page_ips = array();
        foreach($page_lines as $line) { if(preg_match('/IP:\s*([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/', $line, $m)) { $ip = $m[1]; $page_ips[$ip] = ($page_ips[$ip]??0)+1; } } arsort($page_ips);
    ?>
    <div class="card" style="max-width:100%;padding:20px 24px;margin-bottom:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;"><h2 style="margin:0;font-size:16px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg><?php echo esc_html(cybersec_translate('Log')); ?>: <code><?php echo esc_html($vs);?></code></h2><a href="<?php echo esc_url(admin_url('admin.php?page=cyber-security'));?>" class="button button-small"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg><?php echo esc_html(cybersec_translate('Back')); ?></a></div>
        <?php if($page_total > 0): ?>
        <div class="sw-log-analysis"><div class="sw-stat-row">
            <div class="sw-stat-card"><h4><?php echo esc_html(cybersec_translate('Visits')); ?></h4><div class="sw-stat-num" style="color:var(--sw-success);"><?php echo (int)$page_total; ?></div></div>
            <div class="sw-stat-card"><h4><?php echo esc_html(cybersec_translate('Unique IPs')); ?></h4><div class="sw-stat-num" style="color:var(--sw-success);"><?php echo count($page_ips); ?></div></div>
            <div class="sw-stat-card"><h4><?php echo esc_html(cybersec_translate('Top IPs')); ?></h4><ul><?php $i=0; foreach($page_ips as $ip=>$n): if($i++>=3) break; ?><li onclick="swCopyToClipboard('<?php echo esc_js($ip); ?>', this)"><code><?php echo esc_html($ip);?></code> <strong><?php echo (int)$n;?></strong></li><?php endforeach; ?></ul></div>
        </div></div><?php endif; ?>
        <input type="text" placeholder="<?php echo esc_attr(cybersec_translate('Search by IP or UA...')); ?>" onkeyup="swPageFilterLog(this.value)" autocomplete="off" style="width:100%;max-width:400px;padding:8px;margin-bottom:10px;border-radius:4px;border:2px solid var(--sw-border);">
        <?php if(!empty($page_log_content)): ?>
        <div class="sw-page-log-viewer" id="sw-page-log-ta-view"><?php
            $lines = array_filter(explode("\n", $page_log_content));
            foreach($lines as $line):
                $line = esc_html($line); $line = preg_replace('/^(\[[^\]]+\])/', '<span class="sw-page-log-date">$1</span>', $line);
                $line = preg_replace('/IP:\s*(\S+)/', 'IP: <span class="sw-page-log-ip">$1</span>', $line);
                $line = preg_replace('/UA:\s*(.+?)\s*\|/', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;opacity:0.6;"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg><span class="sw-page-log-ua">$1</span> |', $line);
                if(stripos($line,'Direct')!==false) { $line = preg_replace('/Ref:\s*Direct/', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;opacity:0.6;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><span class="sw-page-log-ref-direct">Direct</span>', $line); }
                else { $line = preg_replace('/Ref:\s*(\S+)/', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;opacity:0.6;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><span class="sw-page-log-ref">$1</span>', $line); }
                echo '<div class="sw-page-log-entry">' . wp_kses_post($line) . '</div>';
            endforeach;
        ?></div>
        <?php else: ?>
        <div class="sw-page-log-viewer" id="sw-page-log-ta-view" style="display:flex;align-items:center;justify-content:center;"><div style="text-align:center;"><div style="font-size:36px;margin-bottom:8px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.3"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div><div style="font-weight:600;"><?php echo esc_html(cybersec_translate('Log is empty')); ?></div></div></div>
        <?php endif; ?>
        <div class="sw-log-actions">
            <button type="button" class="button button-small" onclick="swDownloadLog('sw-page-log-ta-view', 'cybersec-page-<?php echo esc_js(gmdate('Y-m-d')); ?>.txt')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg><?php echo esc_html(cybersec_translate('Download')); ?></button>
            <button type="button" class="button button-small" onclick="swCopyAllLog('sw-page-log-ta-view')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><?php echo esc_html(cybersec_translate('Copy All')); ?></button>
            <button type="button" class="button button-small" onclick="cybersecAjax.clearLogAndUpdate('<?php echo esc_js($vs); ?>', 'sw-page-log-ta-view', null, this)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg><?php echo esc_html(cybersec_translate('Clear Log')); ?></button>
        </div>
    </div>
    <?php endif;?>
    
    <?php if(!empty($suspicious)):?>
    <div class="card" style="max-width:100%;padding:20px 24px;margin-bottom:16px;"><h2 style="margin:0 0 12px 0;font-size:16px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><?php echo esc_html(cybersec_translate('Suspicious IPs')); ?></h2><table class="wp-list-table widefat fixed striped"><thead><tr><th>IP</th><th><?php echo esc_html(cybersec_translate('Requests/min')); ?></th><th><?php echo esc_html(cybersec_translate('Requests/5 min')); ?></th><th style="width:140px;"><?php echo esc_html(cybersec_translate('Actions')); ?></th></tr></thead><tbody>
        <?php foreach($suspicious as $ip => $data): $block_url = wp_nonce_url(admin_url('admin.php?page=cyber-security&cybersec_block_offender=' . urlencode($ip)), 'cybersec_block_offender'); ?>
        <tr><td><code><?php echo esc_html($ip);?></code></td><td><strong style="color:<?php echo ($data['rate_1min']??0)>5?'var(--sw-danger)':'var(--sw-warning)';?>;"><?php echo isset($data['rate_1min']) ? (int)$data['rate_1min'] : '—';?></strong></td><td><strong style="color:<?php echo ($data['rate_5min']??0)>10?'var(--sw-danger)':'var(--sw-warning)';?>;"><?php echo isset($data['rate_5min']) ? (int)$data['rate_5min'] : '—';?></strong></td><td><a href="<?php echo esc_url($block_url);?>" class="button button-small" style="background:var(--sw-danger);border-color:var(--sw-danger);color:#fff;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg><?php echo esc_html(cybersec_translate('To Permanent')); ?></a></td></tr>
        <?php endforeach;?></tbody></table></div>
    <?php endif;?>

    <!-- ========== BLOCKED BOTS (LOG) ========== -->
    <div class="card" style="max-width:100%;padding:20px 24px;margin-bottom:16px;">
        <h2 style="margin:0 0 6px 0;font-size:16px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg><?php echo esc_html(cybersec_translate('Blocked Bots')); ?></h2>
        <?php $bf = CYBERSEC_LOG_DIR . 'blocked-bots-' . gmdate('Y-m-d') . '.log'; $blocked_log_content = cybersec_fs_exists($bf) ? cybersec_fs_get_contents($bf) : '';
        if(!empty($blocked_log_content)):
            $lines_arr = array_filter(explode("\n", $blocked_log_content)); $total_blocked = count($lines_arr); $reasons = array(); $countries = array(); $ips = array(); $urls = array();
            foreach($lines_arr as $line) { if(preg_match('/Reason:\s*(.+?)\s*\|/', $line, $m)) { $r = trim($m[1]); $reasons[$r] = ($reasons[$r]??0)+1; } if(preg_match('/Country:\s*(.+?)\s*\|/', $line, $m)) { $c = trim($m[1]); $countries[$c] = ($countries[$c]??0)+1; } if(preg_match('/IP:\s*([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/', $line, $m)) { $ip = $m[1]; $ips[$ip] = ($ips[$ip]??0)+1; } if(preg_match('/URL:\s*(\S+)/', $line, $m)) { $u = $m[1]; $urls[$u] = ($urls[$u]??0)+1; } }
            arsort($reasons); arsort($countries); arsort($ips); arsort($urls); $max_reason = max($reasons?:array(1));
        ?>
        <div class="sw-log-analysis"><h3><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><?php echo esc_html(cybersec_translate('Block Summary')); ?></h3><div class="sw-stat-row">
            <div class="sw-stat-card"><h4><?php echo esc_html(cybersec_translate('Blocked')); ?></h4><div class="sw-stat-num" style="color:var(--sw-danger);"><?php echo (int)$total_blocked; ?></div></div>
            <div class="sw-stat-card"><h4><?php echo esc_html(cybersec_translate('Top Reasons')); ?></h4><ul><?php $i=0; foreach($reasons as $r=>$c): if($i++>=4) break; $w=round(($c/$max_reason)*100); ?><li onclick="swCopyToClipboard('<?php echo esc_js($r); ?>', this)" style="display:flex;align-items:center;gap:6px;padding:4px 6px;margin:2px 0;border-radius:2px;cursor:pointer;overflow:hidden;"><span class="sw-stat-bar" style="min-width:<?php echo (int)max((int)$w,8);?>px;width:<?php echo (int)max((int)$w,8);?>px;height:8px;background:var(--sw-danger);border-radius:2px;flex-shrink:0;"></span><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;font-size:0.7rem;"><?php echo esc_html($r);?></span> <strong style="flex-shrink:0;"><?php echo (int)$c;?></strong></li><?php endforeach; ?></ul></div>
            <div class="sw-stat-card"><h4><?php echo esc_html(cybersec_translate('Countries')); ?></h4><ul><?php $i=0; foreach($countries as $c=>$n): if($i++>=4) break; ?><li onclick="swCopyToClipboard('<?php echo esc_js($c); ?>', this)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;opacity:0.6;"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg><?php echo esc_html($c);?> <strong><?php echo (int)$n;?></strong></li><?php endforeach; ?></ul></div>
            <div class="sw-stat-card"><h4><?php echo esc_html(cybersec_translate('Attacked URLs')); ?></h4><ul><?php $i=0; foreach($urls as $u=>$n): if($i++>=4) break; ?><li onclick="swCopyToClipboard('<?php echo esc_js($u); ?>', this)"><code><?php echo esc_html(strlen($u)>40?substr($u,0,37).'...':$u);?></code> <strong><?php echo (int)$n;?></strong></li><?php endforeach; ?></ul></div>
            <div class="sw-stat-card"><h4><?php echo esc_html(cybersec_translate('Aggressive IPs')); ?></h4><ul><?php $i=0; foreach($ips as $ip=>$n): if($i++>=4) break; ?><li onclick="swCopyToClipboard('<?php echo esc_js($ip); ?>', this)"><code><?php echo esc_html($ip);?></code> <strong><?php echo (int)$n;?></strong></li><?php endforeach; ?></ul></div>
        </div></div>
        <input type="text" placeholder="<?php echo esc_attr(cybersec_translate('Search by IP, reason or UA...')); ?>" onkeyup="swFilterBlockedLog(this.value)" autocomplete="off" style="width:100%;max-width:400px;padding:8px;margin-bottom:10px;border-radius:4px;border:2px solid var(--sw-border);">
        <div class="sw-log-viewer" id="sw-blocked-log-ta"><?php
            $lines = array_filter(explode("\n", $blocked_log_content));
            foreach($lines as $line):
                $reason_class = 'sw-reason-default'; if(stripos($line,'User-Agent')!==false) $reason_class='sw-reason-ua'; elseif(stripos($line,'Brute')!==false) $reason_class='sw-reason-brute'; elseif(stripos($line,'Referer')!==false||stripos($line,'Referrer')!==false) $reason_class='sw-reason-referer';
                $line = esc_html($line); $line = preg_replace('/^(\[[^\]]+\])/', '<span class="sw-log-date">$1</span>', $line); $line = str_replace('BLOCKED', '<span class="sw-log-status-blocked"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>BLOCKED</span>', $line);
                $line = preg_replace('/IP:\s*(\S+)/', 'IP: <span class="sw-log-ip">$1</span>', $line); $line = preg_replace('/Страна:\s*(.+?)\s*\|/', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;opacity:0.6;"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg><span class="sw-log-country">$1</span> |', $line);
                $line = preg_replace('/Причина:\s*(.+?)\s*\|/', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><span class="sw-log-reason '.$reason_class.'">$1</span> |', $line); $line = preg_replace('/UA:\s*(.+?)\s*\|/', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;opacity:0.6;"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg><span class="sw-log-ua">$1</span> |', $line);
                $line = preg_replace('/URL:\s*(\S+)/', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;opacity:0.6;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><span class="sw-log-url">$1</span>', $line); echo '<div class="sw-log-entry">' . wp_kses_post($line) . '</div>';
            endforeach;
        ?></div>
        <?php else: ?>
        <div class="sw-log-viewer" id="sw-blocked-log-ta" style="display:flex;align-items:center;justify-content:center;"><div style="text-align:center;"><div style="font-size:40px;margin-bottom:8px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.3"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div><div style="font-weight:600;"><?php echo esc_html(cybersec_translate('Log is empty')); ?></div></div></div>
        <?php endif; ?>
        <div class="sw-log-actions">
            <button type="button" class="button button-small" onclick="swDownloadLog('sw-blocked-log-ta', 'cybersec-blocked-<?php echo esc_js(gmdate('Y-m-d')); ?>.txt')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg><?php echo esc_html(cybersec_translate('Download')); ?></button>
            <button type="button" class="button button-small" onclick="swCopyAllLog('sw-blocked-log-ta')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><?php echo esc_html(cybersec_translate('Copy All')); ?></button>
            <button type="button" class="button button-small" onclick="cybersecAjax.clearBlockedLogAndUpdate('sw-blocked-log-ta', this)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg><?php echo esc_html(cybersec_translate('Clear')); ?></button>
        </div>
        
        <!-- ========== ALLOWED (WHITELIST) ========== -->
        <h2 style="margin:24px 0 6px 0;font-size:16px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><polyline points="20 6 9 17 4 12"/></svg><?php echo esc_html(cybersec_translate('Allowed (Whitelist)')); ?></h2>
        <?php $af = CYBERSEC_LOG_DIR . 'allowed-bots-' . gmdate('Y-m-d') . '.log'; $allowed_log_content = cybersec_fs_exists($af) ? cybersec_fs_get_contents($af) : '';
        if(!empty($allowed_log_content)):
            $alines = array_filter(explode("\n", $allowed_log_content)); $total_allowed = count($alines); $aips = array(); $aurls = array();
            foreach($alines as $line) { if(preg_match('/IP:\s*([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/', $line, $m)) { $aips[$m[1]] = ($aips[$m[1]]??0)+1; } if(preg_match('/URL:\s*(\S+)/', $line, $m)) { $aurls[$m[1]] = ($aurls[$m[1]]??0)+1; } } arsort($aips); arsort($aurls);
        ?>
        <div class="sw-log-analysis"><h3><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><?php echo esc_html(cybersec_translate('Allowed Summary')); ?></h3><div class="sw-stat-row">
            <div class="sw-stat-card"><h4><?php echo esc_html(cybersec_translate('Allowed')); ?></h4><div class="sw-stat-num" style="color:var(--sw-success);"><?php echo (int)$total_allowed; ?></div></div>
            <div class="sw-stat-card"><h4><?php echo esc_html(cybersec_translate('IPs (Whitelist)')); ?></h4><ul><?php $i=0; foreach($aips as $ip=>$n): if($i++>=4) break; ?><li onclick="swCopyToClipboard('<?php echo esc_js($ip); ?>', this)"><code><?php echo esc_html($ip);?></code> <strong><?php echo (int)$n;?></strong></li><?php endforeach; ?></ul></div>
            <div class="sw-stat-card"><h4><?php echo esc_html(cybersec_translate('Visited URLs')); ?></h4><ul><?php $i=0; foreach($aurls as $u=>$n): if($i++>=4) break; ?><li onclick="swCopyToClipboard('<?php echo esc_js($u); ?>', this)"><code><?php echo esc_html(strlen($u)>40?substr($u,0,37).'...':$u);?></code> <strong><?php echo (int)$n;?></strong></li><?php endforeach; ?></ul></div>
        </div></div>
        <div class="sw-log-viewer" id="sw-allowed-log-ta"><?php
            $lines = array_filter(explode("\n", $allowed_log_content));
            foreach($lines as $line):
                $line = esc_html($line); $line = preg_replace('/^(\[[^\]]+\])/', '<span class="sw-log-date">$1</span>', $line); $line = str_replace('ALLOWED', '<span class="sw-log-status-allowed"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><polyline points="20 6 9 17 4 12"/></svg>ALLOWED</span>', $line);
                $line = preg_replace('/IP:\s*(\S+)/', 'IP: <span class="sw-log-ip">$1</span>', $line); $line = preg_replace('/Страна:\s*(.+?)\s*\|/', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;opacity:0.6;"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg><span class="sw-log-country">$1</span> |', $line);
                $line = preg_replace('/UA:\s*(.+?)\s*\|/', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;opacity:0.6;"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg><span class="sw-log-ua">$1</span> |', $line); $line = preg_replace('/URL:\s*(\S+)/', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;opacity:0.6;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><span style="color:var(--sw-success);font-weight:500;">$1</span>', $line);
                echo '<div class="sw-log-entry">' . wp_kses_post($line) . '</div>';
            endforeach;
        ?></div>
        <?php else: ?>
        <div class="sw-log-viewer" id="sw-allowed-log-ta" style="display:flex;align-items:center;justify-content:center;"><div style="text-align:center;"><div style="font-size:40px;margin-bottom:8px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.3"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div><div style="font-weight:600;"><?php echo esc_html(cybersec_translate('Log is empty')); ?></div></div></div>
        <?php endif; ?>
        <div class="sw-log-actions">
            <button type="button" class="button button-small" onclick="swDownloadLog('sw-allowed-log-ta', 'cybersec-allowed-<?php echo esc_js(gmdate('Y-m-d')); ?>.txt')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg><?php echo esc_html(cybersec_translate('Download')); ?></button>
            <button type="button" class="button button-small" onclick="swCopyAllLog('sw-allowed-log-ta')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><?php echo esc_html(cybersec_translate('Copy All')); ?></button>
            <button type="button" class="button button-small" onclick="cybersecAjax.clearAllowedLogAndUpdate('sw-allowed-log-ta', this)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg><?php echo esc_html(cybersec_translate('Clear')); ?></button>
        </div>
        <div style="margin-top:16px;padding-top:14px;border-top:2px solid var(--sw-border-light);">
            <button type="button" class="button button-primary" style="background:var(--sw-danger);border-color:var(--sw-danger);color:#fff;" onclick="cybersecAjax.clearAllBotLogsAndUpdate(this)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg><?php echo esc_html(cybersec_translate('Clear All Logs')); ?></button>
        </div>
    </div>
    
        
    <!-- Audit Log -->
<div class="card" style="max-width:100%;padding:20px 24px;margin-bottom:16px;">
    <h2 style="margin:0 0 6px 0;font-size:16px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg><?php echo esc_html(cybersec_translate('Event Log')); ?></h2>
    <p style="color:var(--sw-text-secondary);font-size:13px;margin-bottom:12px;"><?php echo esc_html(cybersec_translate('Important site actions: logins, logouts, settings changes, plugin activation, and other security events.')); ?></p>
    
    <?php 
    $audit_log = CYBERSEC_LOG_DIR . 'audit.log';
    $audit_content = cybersec_fs_exists($audit_log) ? cybersec_fs_get_contents($audit_log) : '';
    
    if (!empty($audit_content)):
        $audit_lines = array_filter(explode("\n", $audit_content));
        $audit_lines = array_reverse($audit_lines);
        $audit_lines = array_filter($audit_lines, function($line) { return stripos($line, 'IP_BLOCKED') === false && stripos($line, 'IP_UNBLOCKED') === false; });
        $audit_lines = array_values(array_filter($audit_lines, function($line) { return trim($line) !== ''; }));
    ?>
    
    <div style="margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
        <span style="font-size:12px;color:var(--sw-text-secondary);"><?php echo esc_html(cybersec_translate('Events')); ?>: <strong style="color:var(--sw-accent);"><?php echo count($audit_lines); ?></strong></span>
        <div style="display:flex;gap:8px;align-items:stretch;flex-wrap:wrap;">
            <div style="position:relative;display:inline-flex;align-items:stretch;">
                <select id="audit-filter" onchange="filterAuditEvents()" style="padding:7px 32px 7px 12px;font-size:0.72rem;height:100%;background:var(--sw-input-bg);color:var(--sw-accent);border:2px solid var(--sw-input-border);border-radius:var(--sw-radius);font-family:var(--sw-font-mono);appearance:none;-webkit-appearance:none;-moz-appearance:none;cursor:pointer;line-height:1.4;">
                    <option value="all"><?php echo esc_html(cybersec_translate('All Events')); ?></option>
                    <option value="LOGIN"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><polyline points="20 6 9 17 4 12"/></svg><?php echo esc_html(cybersec_translate('Logins')); ?></option>
                    <option value="LOGIN_FAILED"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg><?php echo esc_html(cybersec_translate('Failed Logins')); ?></option>
                    <option value="LOGOUT"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><?php echo esc_html(cybersec_translate('Logouts')); ?></option>
                    <option value="SETTINGS"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg><?php echo esc_html(cybersec_translate('Settings')); ?></option>
                    <option value="PLUGIN"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg><?php echo esc_html(cybersec_translate('Plugins')); ?></option>
                    <option value="UPLOAD"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg><?php echo esc_html(cybersec_translate('Uploads')); ?></option>
                </select>
                <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--sw-accent);font-size:10px;">▼</span>
            </div>
            <button type="button" class="button button-small" onclick="exportAuditCSV()" title="<?php echo esc_attr(cybersec_translate('Export to Excel (CSV)')); ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>CSV</button>
            <button type="button" class="button button-small" onclick="swDownloadLog('sw-audit-log-ta', 'cybersec-audit-<?php echo esc_js(gmdate('Y-m-d')); ?>.txt')" title="<?php echo esc_attr(cybersec_translate('Download as text (TXT)')); ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>TXT</button>
            <button type="button" class="button button-small" onclick="swCopyAllLog('sw-audit-log-ta')" title="<?php echo esc_attr(cybersec_translate('Copy all to clipboard')); ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><?php echo esc_html(cybersec_translate('Copy')); ?></button>
            <button type="button" class="button button-small" onclick="cybersecAjax.clearAuditLogAndUpdate('sw-audit-log-ta', this)" title="<?php echo esc_attr(cybersec_translate('Clear event log')); ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg><?php echo esc_html(cybersec_translate('Clear')); ?></button>
        </div>
    </div>
    
    <div class="sw-log-viewer" id="sw-audit-log-ta" style="height:350px;"><?php 
        foreach ($audit_lines as $line): 
            $line = esc_html($line);
            $line = preg_replace('/^(\[[^\]]+\])/', '<span style="opacity:0.5;color:var(--sw-text-secondary);">$1</span>', $line);
            $line = str_replace('LOGIN', '<span style="color:var(--sw-success);font-weight:700;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><polyline points="20 6 9 17 4 12"/></svg>' . esc_html(cybersec_translate('LOGIN')) . '</span>', $line);
            $line = str_replace('LOGIN_FAILED', '<span style="color:var(--sw-danger);font-weight:700;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' . esc_html(cybersec_translate('FAILED LOGIN')) . '</span>', $line);
            $line = str_replace('LOGOUT', '<span style="color:var(--sw-warning);font-weight:700;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>' . esc_html(cybersec_translate('LOGOUT')) . '</span>', $line);
            $line = str_replace('PASSWORD_RESET', '<span style="color:var(--sw-warning);font-weight:700;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>' . esc_html(cybersec_translate('PASSWORD CHANGE')) . '</span>', $line);
            $line = str_replace('PROFILE_UPDATE', '<span style="color:var(--sw-info);font-weight:700;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>' . esc_html(cybersec_translate('PROFILE UPDATE')) . '</span>', $line);
            $line = str_replace('SETTINGS_CHANGED', '<span style="color:var(--sw-info);font-weight:700;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>' . esc_html(cybersec_translate('SETTINGS')) . '</span>', $line);
            $line = str_replace('PLUGIN_ACTIVATED', '<span style="color:var(--sw-success);font-weight:700;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>' . esc_html(cybersec_translate('PLUGIN ON')) . '</span>', $line);
            $line = str_replace('PLUGIN_DEACTIVATED', '<span style="color:var(--sw-danger);font-weight:700;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>' . esc_html(cybersec_translate('PLUGIN OFF')) . '</span>', $line);
            $line = str_replace('WHITELIST_MODIFIED', '<span style="color:var(--sw-info);font-weight:700;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>' . esc_html(cybersec_translate('WHITELIST')) . '</span>', $line);
            $line = preg_replace('/User:\s*(\S+)/', esc_html(cybersec_translate('User')) . ': <span style="color:var(--sw-warning);font-weight:600;">$1</span>', $line);
            echo '<div class="sw-log-entry">' . wp_kses_post($line) . '</div>';
        endforeach;
    ?></div>
    
    <?php else: ?>
    <div class="sw-log-viewer" id="sw-audit-log-ta" style="height:350px;display:flex;align-items:center;justify-content:center;">
        <div style="text-align:center;"><div style="font-size:40px;margin-bottom:8px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.3"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div><div style="font-weight:600;"><?php echo esc_html(cybersec_translate('No events yet')); ?></div></div>
    </div>
    <?php endif; ?>
</div>
    
</div>

<?php // ==================== TAB 2: PROTECTION ==================== ?>
<div id="tab-protection" class="sw-tab" style="display:<?php echo $active_tab==='protection'?'block':'none';?>;">
    <div class="card sw-block-section" style="max-width:100%;padding:20px 24px;margin-bottom:16px;">
        <div class="sw-permanent-header">
            <h2 style="margin:0;font-size:16px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg><?php echo esc_html(cybersec_translate('Permanently Blocked Subnets')); ?></h2>
            <?php if(!empty($permanent_blocked)): ?><span class="sw-permanent-badge"><?php echo count($permanent_blocked); ?> <?php echo esc_html(cybersec_translate('subnets')); ?></span><?php endif; ?>
        </div>
        <p class="sw-permanent-subtitle"><?php echo esc_html(cybersec_translate('These subnets are blocked')); ?> <strong><?php echo esc_html(cybersec_translate('forever')); ?></strong> <?php echo esc_html(cybersec_translate('and will not be auto-unblocked.')); ?></p>
        <div class="sw-block-tabs" style="margin:14px 0;"><div class="sw-block-tab active" onclick="swSwitchBlockTab('sw-blocked-temp', this)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><?php echo esc_html(cybersec_translate('Temporary')); ?></div><div class="sw-block-tab" onclick="swSwitchBlockTab('sw-blocked-perm', this)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg><?php echo esc_html(cybersec_translate('Permanent')); ?></div></div>
        <div class="sw-block-panel active" id="sw-blocked-temp">
            <div class="sw-block-form-row">
                <div><label><?php echo esc_html(cybersec_translate('IP or subnet')); ?></label><input type="text" id="sw-blocked-temp-subnet" placeholder="192.168.1.0/24"></div>
                <div style="max-width:100px;"><label><?php echo esc_html(cybersec_translate('Minutes')); ?></label><input type="number" id="sw-blocked-temp-minutes" value="<?php echo (int)$am; ?>" min="1" max="43200"></div>
                <button type="button" class="button button-primary" style="background:var(--sw-warning);border-color:var(--sw-warning);color:var(--sw-bg);" onclick="cybersecAjax.tempBlock(this, 'sw-blocked-temp')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><?php echo esc_html(cybersec_translate('Block')); ?></button>
            </div>
        </div>
        <div class="sw-block-panel" id="sw-blocked-perm">
            <form method="post" class="sw-block-form-row" autocomplete="off"><?php wp_nonce_field('cybersec_action','cybersec_nonce');?>
                <div><label><?php echo esc_html(cybersec_translate('IP or subnet')); ?></label><input type="text" name="cybersec_block_permanent_ip" placeholder="185.146.158.0/24"></div>
                <div><label><?php echo esc_html(cybersec_translate('Reason')); ?></label><input type="text" name="cybersec_block_permanent_reason" placeholder="<?php echo esc_attr(cybersec_translate('Bot Android 10 K')); ?>"></div>
                <button type="submit" name="cybersec_block_permanent" class="button button-primary" style="background:var(--sw-danger);border-color:var(--sw-danger);color:#fff;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg><?php echo esc_html(cybersec_translate('Block /24')); ?></button>
            </form>
        </div>
        <?php if(empty($permanent_blocked)):?><div class="sw-permanent-empty"><?php echo esc_html(cybersec_translate('No permanently blocked subnets.')); ?></div><?php else:?>
        <div id="sw-permanent-wrap" class="sw-permanent-table-wrap" style="max-height:300px;margin-top:16px;"><table class="wp-list-table widefat fixed striped"><thead><tr>
            <th style="width:120px;text-align:left;"><?php echo esc_html(cybersec_translate('Subnet')); ?></th>
<th style="width:auto;text-align:center;"><?php echo esc_html(cybersec_translate('Reason')); ?></th>
<th style="width:160px;text-align:center;"><?php echo esc_html(cybersec_translate('Date')); ?></th>
<th style="width:130px;text-align:center;"><?php echo esc_html(cybersec_translate('Actions')); ?></th></tr></thead><tbody>
            <?php uasort($permanent_blocked, function($a,$b){ return strcmp($b['blocked_at'], $a['blocked_at']); });
            foreach($permanent_blocked as $subnet => $data): ?>
            <tr><td style="text-align:left;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><code><?php echo esc_html($subnet);?></code></td><td style="font-size:12px;text-align:center;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html($data['reason']);?></td><td style="font-size:11px;color:var(--sw-text-secondary);text-align:center;"><?php echo esc_html($data['blocked_at']);?></td><td style="text-align:center;"><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cyber-security&cybersec_unblock_permanent='.urlencode($subnet)),'cybersec_unblock_permanent'));?>" class="button button-small" onclick="return confirm('<?php echo esc_js(cybersec_translate('Unblock subnet')); ?> <?php echo esc_js($subnet);?>?')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg><?php echo esc_html(cybersec_translate('Unblock')); ?></a></td></tr>
            <?php endforeach;?></tbody></table></div>
        <?php endif;?>
    </div>
    
    <div class="card" style="max-width:100%;padding:16px 20px;">
        <h2><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg><?php echo esc_html(cybersec_translate('TOP Offenders')); ?></h2>
        <?php if(empty($to)):?><p><?php echo esc_html(cybersec_translate('List is empty.')); ?></p><?php else:?>
        <div id="sw-offenders-wrap" class="sw-permanent-table-wrap" style="max-height:400px;">
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th style="width:30px;text-align:center;">#</th>
<th style="width:160px;text-align:center;">IP</th>
<th style="width:160px;text-align:center;"><?php echo esc_html(cybersec_translate('Country')); ?></th>
<th style="width:70px;text-align:center;"><?php echo esc_html(cybersec_translate('Blocks')); ?></th>
<th style="width:auto;text-align:center;"><?php echo esc_html(cybersec_translate('Reasons')); ?></th>
<th style="width:210px;text-align:center;"><?php echo esc_html(cybersec_translate('Actions')); ?></th></tr></thead>
                <tbody id="sw-offenders-table">
                <?php $pos=0;foreach($to as $ip=>$d):$pos++;$bu=wp_nonce_url(admin_url('admin.php?page=cyber-security&cybersec_block_offender='.urlencode($ip)),'cybersec_block_offender');$ru=wp_nonce_url(admin_url('admin.php?page=cyber-security&cybersec_remove_offender='.urlencode($ip)),'cybersec_remove_offender');?>
                <tr>
                    <td style="text-align:center;"><?php echo (int)$pos;?></td>
<td style="text-align:center;"><code><?php echo esc_html($ip);?></code></td>
<td style="text-align:center;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html($d['country']);?></td>
<td style="text-align:center;"><strong style="color:var(--sw-danger);"><?php echo (int)$d['count'];?></strong></td>
<td style="text-align:center;"><?php foreach(array_slice($d['reasons'],0,3,true) as $r=>$cnt):?><span style="background:var(--sw-card-bg-alt);padding:2px 5px;border-radius:3px;"><?php echo esc_html($r);?> (<?php echo (int)$cnt;?>)</span><?php endforeach;?></td>
<td style="text-align:center;white-space:nowrap;">
    <a href="<?php echo esc_url($bu);?>" class="button button-small" style="background:var(--sw-danger);color:#fff;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg><?php echo esc_html(cybersec_translate('To Perm')); ?></a>
    <a href="<?php echo esc_url($ru);?>" class="button button-small" style="color:var(--sw-danger);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg><?php echo esc_html(cybersec_translate('Delete')); ?></a>
</td>
                </tr>
                <?php endforeach;?>
                </tbody>
            </table>
        </div>
            <button type="button" class="button button-secondary" style="margin-top:12px;" onclick="cybersecAjax.clearOffendersAndUpdate('sw-offenders-table', this)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg><?php echo esc_html(cybersec_translate('Clear List')); ?></button>
        <?php endif;?>
    </div>
    
    <!-- User-Agent Blocking and Statistics -->
    <div class="card sw-block-section" style="max-width:100%;padding:20px 24px;margin-bottom:16px;">
        <h2 style="margin:0 0 6px 0;font-size:16px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><?php echo esc_html(cybersec_translate('User-Agent: Blocking and Statistics')); ?></h2>
        
        <div style="background:var(--sw-info-light);border:2px solid var(--sw-info-border);border-radius:var(--sw-radius);padding:14px 16px;margin:12px 0 16px 0;">
            <strong style="color:var(--sw-info);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg><?php echo esc_html(cybersec_translate('How it works')); ?></strong>
            <p style="margin:6px 0 0 0;font-size:12px;color:var(--sw-text-secondary);line-height:1.5;">
                <?php echo esc_html(cybersec_translate('Here are all User-Agents seen on the site.')); ?> <strong style="color:var(--sw-success);"><?php echo esc_html(cybersec_translate('Green button')); ?></strong> — <?php echo esc_html(cybersec_translate('can be safely blocked (bots and scanners).')); ?> <strong><?php echo esc_html(cybersec_translate('No button')); ?></strong> — <?php echo esc_html(cybersec_translate('blocking is not available (search engines or legitimate browsers).')); ?>
            </p>
        </div>
        
        <?php
        $ua_stats = cybersec_get_ua_stats();
        $blocked_uas = cybersec_get_blocked_user_agents();
        
        if(!empty($ua_stats)):
            uasort($ua_stats, function($a, $b) { return $b['count'] - $a['count']; });
            $top_ua = array_slice($ua_stats, 0, 100);
        ?>
        
        <div id="sw-ua-block-table" class="sw-ua-block-table" style="border:2px solid var(--sw-border);border-radius:var(--sw-radius);">
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th style="width:55%;word-wrap:break-word;overflow-wrap:break-word;">User-Agent</th>
<th style="width:70px;text-align:center;"><?php echo esc_html(cybersec_translate('Total')); ?></th>
<th style="width:90px;color:var(--sw-danger);text-align:center;"><?php echo esc_html(cybersec_translate('Blocked')); ?></th>
<th style="width:90px;color:var(--sw-success);text-align:center;"><?php echo esc_html(cybersec_translate('Allowed')); ?></th>
<th style="width:160px;text-align:center;"><?php echo esc_html(cybersec_translate('Action')); ?></th></tr></thead>
                <tbody>
                    <?php foreach($top_ua as $hash => $data): 
                        $ua_text = $data['ua'];
                        $already_blocked = in_array($ua_text, $blocked_uas);
                        $is_search_bot = (stripos($ua_text, 'Googlebot') !== false || stripos($ua_text, 'YandexBot') !== false || stripos($ua_text, 'Bingbot') !== false || stripos($ua_text, 'Baiduspider') !== false || stripos($ua_text, 'DuckDuckBot') !== false);
                        $is_bad_bot = (stripos($ua_text, 'python-requests') !== false || stripos($ua_text, 'Go-http-client') !== false || stripos($ua_text, 'scrapy') !== false || stripos($ua_text, 'curl') !== false || stripos($ua_text, 'wget') !== false || stripos($ua_text, 'libwww') !== false || stripos($ua_text, 'Java/') !== false || stripos($ua_text, 'DataForSeoBot') !== false || stripos($ua_text, 'serpstatbot') !== false || stripos($ua_text, 'SERankingBacklinksBot') !== false || stripos($ua_text, 'br-crawler') !== false || stripos($ua_text, 'ChatGPT-User') !== false || stripos($ua_text, 'Empty User Agent') !== false);
                        
                        if ($already_blocked) {
                            $badge = ' <span style="white-space:nowrap;background:var(--sw-danger-light);color:var(--sw-danger);padding:1px 5px;border-radius:3px;font-size:10px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:1px;position:relative;top:1px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>' . esc_html(cybersec_translate('blocked')) . '</span>';
                            $action = '<button type="button" class="button button-small" style="min-width:150px;" onclick="cybersecAjax.unblockUA(\''.esc_js($ua_text).'\', this)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>' . esc_html(cybersec_translate('Unblock')) . '</button>';
                        } elseif ($is_search_bot) {
                            $badge = ' <span style="white-space:nowrap;background:var(--sw-info-light);color:var(--sw-info);padding:1px 5px;border-radius:3px;font-size:10px;">' . esc_html(cybersec_translate('search engine')) . '</span>';
                            $action = '<span style="font-size:11px;color:var(--sw-info);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><polyline points="20 6 9 17 4 12"/></svg>' . esc_html(cybersec_translate('Search engine')) . '</span>';
                        } elseif ($is_bad_bot) {
                            $badge = ' <span style="white-space:nowrap;background:var(--sw-success-light);color:var(--sw-success);padding:1px 5px;border-radius:3px;font-size:10px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:1px;position:relative;top:1px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' . esc_html(cybersec_translate('malicious bot')) . '</span>';
                            $action = '<button type="button" class="button button-small" style="min-width:150px;background:var(--sw-success);color:var(--sw-bg);border-color:var(--sw-success);" onclick="cybersecAjax.blockUA(\''.esc_js($ua_text).'\', this)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>' . esc_html(cybersec_translate('Block')) . '</button>';
                        } elseif (empty(trim($ua_text))) {
                            $badge = ' <span style="white-space:nowrap;background:var(--sw-warning-light);color:var(--sw-warning);padding:1px 5px;border-radius:3px;font-size:10px;">' . esc_html(cybersec_translate('empty UA (hidden activity)')) . '</span>';
                            $action = '<button type="button" class="button button-small" style="min-width:150px;background:var(--sw-warning);color:var(--sw-bg);border-color:var(--sw-warning);" onclick="if(confirm(\'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> ' . esc_js(cybersec_translate('Empty User-Agent — a bot sign, but there may be rare legitimate requests. Block?')) . '\')) cybersecAjax.blockUA(\''.esc_js($ua_text).'\', this)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' . esc_html(cybersec_translate('Block')) . '</button>';
                        } else {
                            $badge = ' <span style="white-space:nowrap;background:rgba(255,255,255,0.05);color:var(--sw-text-secondary);padding:1px 5px;border-radius:3px;font-size:10px;">' . esc_html(cybersec_translate('browser')) . '</span>';
                            $action = '<span style="font-size:11px;color:var(--sw-text-secondary);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>' . esc_html(cybersec_translate('Browser')) . '</span>';
                        }
                    ?>
                                        <tr>
                        <td style="font-size:11px;word-wrap:break-word;overflow-wrap:break-word;padding-right:8px;">
                            <?php echo esc_html(strlen($ua_text) > 150 ? substr($ua_text, 0, 147) . '...' : $ua_text); ?>
                            <?php echo wp_kses_post($badge); ?>
                        </td>
                        <td style="text-align:center;"><strong><?php echo (int)$data['count']; ?></strong></td>
<td style="color:var(--sw-danger);font-weight:600;text-align:center;"><?php echo (int)$data['blocked']; ?></td>
<td style="color:var(--sw-success);font-weight:600;text-align:center;"><?php echo (int)$data['allowed']; ?></td>
<td style="text-align:center;"><?php echo wp_kses_post($action); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top:12px;display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:11px;color:var(--sw-text-secondary);"><?php echo esc_html(cybersec_translate('Showing top 100 User-Agents by activity')); ?></span>
            <button type="button" class="button button-small" onclick="cybersecAjax.clearUAStatsAndUpdate(this)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg><?php echo esc_html(cybersec_translate('Clear Statistics')); ?></button>
        </div>
        <?php else: ?>
        <p style="color:var(--sw-text-secondary);text-align:center;padding:30px;"><?php echo esc_html(cybersec_translate('Statistics are empty. Data is collected as the plugin works.')); ?></p>
        <?php endif; ?>
    </div>

    <!-- Brute Force Protection -->
<div id="sw-brute-active" class="card" style="max-width:100%;padding:16px 20px;margin-bottom:16px;">
    <h2><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?php echo esc_html(cybersec_translate('Brute Force Protection')); ?></h2>
        <?php $n=time(); $total_brute_attempts = 0; $active_brute_ips = 0;
        foreach($brute_data as $ip=>$d) { $total_brute_attempts += (int)(isset($d['attempts']) ? $d['attempts'] : 0); if(isset($d['first']) && $d['first'] > $n - ((int)$st['brute_window_minutes']*60)) $active_brute_ips++; } ?>
        <p style="margin-bottom:8px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><strong><?php echo (int)$total_brute_attempts; ?></strong> <?php echo esc_html(cybersec_translate('total brute force attempts')); ?> | <strong><?php echo (int)$active_brute_ips; ?></strong> <?php echo esc_html(cybersec_translate('active IPs')); ?></p>
        <p style="color:var(--sw-warning);margin-bottom:12px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><?php echo esc_html(cybersec_translate('When limit is exceeded')); ?> (<strong><?php echo (int)$st['brute_max_attempts']; ?> <?php echo esc_html(cybersec_translate('attempts')); ?></strong>) <?php echo esc_html(cybersec_translate('IP goes straight to')); ?> <strong><?php echo esc_html(cybersec_translate('permanent block')); ?></strong> (<?php echo esc_html(cybersec_translate('/24 subnet')); ?>).</p>
        <?php $active_attempts = array_filter($brute_data, function($d) use($n, $st) { return isset($d['first']) && $d['first'] > $n - ((int)$st['brute_window_minutes']*60) && isset($d['attempts']) && $d['attempts'] > 0; });
        if(!empty($active_attempts)): uasort($active_attempts, function($a,$b){return $b['attempts']-$a['attempts'];});
        ?><table class="wp-list-table widefat fixed striped" style="margin-bottom:12px;"><thead><tr><th>IP</th><th><?php echo esc_html(cybersec_translate('Attempts')); ?></th><th><?php echo esc_html(cybersec_translate('Limit')); ?></th><th><?php echo esc_html(cybersec_translate('Progress')); ?></th><th><?php echo esc_html(cybersec_translate('Actions')); ?></th></tr></thead><tbody>
        <?php foreach($active_attempts as $ip=>$d): $pct = min(100, round(($d['attempts']/$st['brute_max_attempts'])*100)); $bar_color = $pct > 80 ? 'var(--sw-danger)' : ($pct > 50 ? 'var(--sw-warning)' : 'var(--sw-success)'); ?>
        <tr><td><code><?php echo esc_html($ip);?></code></td><td><strong><?php echo (int)$d['attempts'];?></strong></td><td><?php echo (int)$st['brute_max_attempts'];?></td><td><div style="background:var(--sw-input-bg);border-radius:4px;height:8px;max-width:200px;"><div style="width:<?php echo (int)$pct;?>%;height:8px;border-radius:4px;background:<?php echo esc_attr($bar_color);?>;"></div></div><span style="font-size:11px;color:var(--sw-text-secondary);"><?php echo (int)$pct;?>%</span></td><td><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cyber-security&cybersec_unblock_brute='.urlencode($ip)), 'cybersec_unblock_brute'));?>" class="button button-small" onclick="return confirm('<?php echo esc_js(cybersec_translate('Reset counter?')); ?>')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg><?php echo esc_html(cybersec_translate('Reset')); ?></a></td></tr>
        <?php endforeach;?></tbody></table><?php else: ?><p style="color:var(--sw-text-secondary);"><?php echo esc_html(cybersec_translate('No active brute force attempts.')); ?></p><?php endif; ?>
        <button type="button" class="button button-secondary" style="margin-top:8px;" onclick="cybersecAjax.clearBruteAndUpdate('sw-brute-active', this)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg><?php echo esc_html(cybersec_translate('Clear Statistics')); ?></button>
    </div>
    
    <!-- Temporarily Blocked IPs -->
    <div class="card" style="max-width:100%;padding:16px 20px;margin-bottom:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;"><h2 style="margin:0;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><?php echo esc_html(cybersec_translate('Temporarily Blocked IPs')); ?></h2><?php if(!empty($tb)): ?><form method="post" style="margin:0;" onsubmit="return confirm('<?php echo esc_js(cybersec_translate('Unblock ALL?')); ?>');" autocomplete="off"><?php wp_nonce_field('cybersec_action','cybersec_nonce');?><button type="submit" name="cybersec_unblock_all_temp" class="button button-primary"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg><?php echo esc_html(cybersec_translate('Unblock All')); ?> (<?php echo count($tb); ?>)</button></form><?php endif; ?></div>
        <?php if(empty($tb)):?><p><?php echo esc_html(cybersec_translate('No temporary blocks.')); ?></p><?php else:?>
        <div id="sw-temp-blocks-wrap" style="max-height:400px;overflow-y:auto;scrollbar-width:thin;scrollbar-color:var(--sw-accent) var(--sw-input-bg);"><table class="wp-list-table widefat fixed striped"><thead><tr><th>IP</th><th><?php echo esc_html(cybersec_translate('Country')); ?></th><th><?php echo esc_html(cybersec_translate('Expires')); ?></th><th><?php echo esc_html(cybersec_translate('Actions')); ?></th></tr></thead><tbody>
        <?php foreach($tb as $ip=>$exp): $c=cybersec_get_country_by_ip($ip); ?>
        <tr><td><code><?php echo esc_html($ip);?></code></td><td><?php echo esc_html($c);?></td><td style="font-size:11px;"><?php echo esc_html(gmdate('H:i:s',$exp));?> (<?php echo (int)max(0,ceil(($exp-time())/60));?> <?php echo esc_html(cybersec_translate('min')); ?>.)</td><td style="white-space:nowrap;"><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cyber-security&cybersec_unblock_temp='.urlencode($ip)),'cybersec_unblock_temp'));?>" class="button button-small"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg><?php echo esc_html(cybersec_translate('Unblock')); ?></a> <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cyber-security&cybersec_block_offender='.urlencode($ip)),'cybersec_block_offender'));?>" class="button button-small" style="background:var(--sw-danger);color:#fff;" onclick="return confirm('<?php echo esc_js(cybersec_translate('Block /24 subnet permanently?')); ?>')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg><?php echo esc_html(cybersec_translate('To Perm')); ?></a></td></tr>
        <?php endforeach;?></tbody></table></div>
<?php endif;?>
    </div>
</div>

<?php // ==================== TAB 3: WHITELIST ==================== ?>
<div id="tab-whitelist" class="sw-tab" style="display:<?php echo $active_tab==='whitelist'?'block':'none';?>;">
    
    <!-- WHITELIST -->
    <div class="card" style="max-width:100%;padding:20px 24px;margin-bottom:16px;">
        <h2><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg><?php echo esc_html(cybersec_translate('Whitelist')); ?></h2>
        <p style="color:var(--sw-text-secondary);font-size:13px;margin-bottom:16px;"><?php echo esc_html(cybersec_translate('Manage whitelisted IPs, subnets, hostnames, User-Agents, and URL paths. Whitelisted items bypass all security checks.')); ?></p>
        <form method="post" autocomplete="off"><?php wp_nonce_field('cybersec_action','cybersec_nonce'); ?>
            <div style="background:var(--sw-card-bg-alt);border:2px solid var(--sw-border);border-radius:var(--sw-radius);padding:14px 16px;">
                
                <!-- Auto + Manual Info Cards -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;margin-top:10px;">
                    <div style="background:var(--sw-success-light);border:2px solid var(--sw-success-border);border-radius:var(--sw-radius);padding:14px 16px;">
                        <strong style="color:var(--sw-success);">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><polyline points="20 6 9 17 4 12"/></svg>
                            <?php echo esc_html(cybersec_translate('Automatic Whitelist')); ?>
                        </strong>
                        <p style="margin:6px 0 0 0;font-size:12px;color:var(--sw-text-secondary);">
                            <strong><?php echo esc_html(cybersec_translate('Server IP')); ?> (<?php echo esc_html($sip); ?>)</strong> — <?php echo esc_html(cybersec_translate('added automatically.')); ?><br>
                            <strong><?php echo esc_html(cybersec_translate('Search engine subnets')); ?> (<?php echo (int)$known_bots; ?> <?php echo esc_html(cybersec_translate('total')); ?>)</strong> — Google, Bing, Yandex...<br>
                            <strong><?php echo esc_html(cybersec_translate('Administrator IPs')); ?></strong> — <?php echo esc_html(cybersec_translate('added automatically when admin logs in.')); ?>
                        </p>
                    </div>
                    <div style="background:var(--sw-info-light);border:2px solid var(--sw-info-border);border-radius:var(--sw-radius);padding:14px 16px;">
                        <strong style="color:var(--sw-info);">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <?php echo esc_html(cybersec_translate('Manual Whitelist')); ?>
                        </strong>
                        <p style="margin:6px 0 0 0;font-size:12px;color:var(--sw-text-secondary);">
                            <strong><?php echo esc_html(cybersec_translate('IP Address')); ?></strong> — <?php echo esc_html(cybersec_translate('specific IP.')); ?><br>
                            <strong><?php echo esc_html(cybersec_translate('Subnet (CIDR)')); ?></strong> — <?php echo esc_html(cybersec_translate('IP range.')); ?><br>
                            <strong><?php echo esc_html(cybersec_translate('Domain / PTR')); ?></strong> — <?php echo esc_html(cybersec_translate('domain name.')); ?><br>
                            <strong>User-Agent</strong> — <?php echo esc_html(cybersec_translate('UA fragment.')); ?><br>
                            <strong><?php echo esc_html(cybersec_translate('URL Path')); ?></strong> — <?php echo esc_html(cybersec_translate('exclude URL from checks (e.g.,')); ?> <code>/webhook</code>).
                        </p>
                    </div>
                </div>

                <!-- Add to Whitelist Form -->
                <div style="margin-top:16px;">
                    <h4>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><polyline points="20 6 9 17 4 12"/></svg>
                        <?php echo esc_html(cybersec_translate('Add to Whitelist')); ?>
                    </h4>
                    <table class="form-table">
                        <tr>
                            <th style="width:120px;"><?php echo esc_html(cybersec_translate('Type')); ?></th>
                            <td>
                                <select name="cybersec_wl_type" style="padding:6px 32px 6px 8px;min-width:200px;">
                                    <option value="ip"><?php echo esc_html(cybersec_translate('IP Address')); ?></option>
                                    <option value="subnet"><?php echo esc_html(cybersec_translate('Subnet (CIDR)')); ?></option>
                                    <option value="hostname"><?php echo esc_html(cybersec_translate('Domain / PTR')); ?></option>
                                    <option value="ua">User-Agent</option>
                                    <option value="url"><?php echo esc_html(cybersec_translate('URL Path')); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html(cybersec_translate('Value')); ?></th>
                            <td><input type="text" name="cybersec_wl_value" placeholder="66.249.64.0/19 or /webhook or googlebot.com" style="width:100%;max-width:500px;padding:6px;"></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html(cybersec_translate('Note')); ?></th>
                            <td><input type="text" name="cybersec_wl_note" placeholder="<?php echo esc_attr(cybersec_translate('Office IP')); ?>" style="width:100%;max-width:500px;padding:6px;"></td>
                        </tr>
                    </table>
                    <button type="submit" name="cybersec_add_whitelist" class="button button-primary" style="margin-top:8px;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><polyline points="20 6 9 17 4 12"/></svg>
                        <?php echo esc_html(cybersec_translate('Add')); ?>
                    </button>
                </div>

                <hr style="border:0;border-top:2px solid var(--sw-border-light);margin:20px 0;">

                <!-- Current Whitelist -->
                <div style="margin-top:16px;">
                    <h4>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        <?php echo esc_html(cybersec_translate('Current Whitelist')); ?>
                    </h4>
                    
                    <?php 
                    $auto_entries = array(); 
                    if($as) $auto_entries[] = array('type'=>'ip','value'=>$sip,'note'=>cybersec_translate('Auto: Server IP')); 
                    $auto_entries[] = array('type'=>'subnet','value'=>$known_bots.' '.cybersec_translate('subnets'),'note'=>cybersec_translate('Auto: Search engines')); 
                    ?>
                    
                    <!-- Automatic Whitelist -->
                    <div style="background:var(--sw-card-bg-alt);border:2px solid var(--sw-border);border-radius:var(--sw-radius);padding:14px 16px;margin-bottom:16px;">
                        <strong style="color:var(--sw-success);">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><polyline points="20 6 9 17 4 12"/></svg>
                            <?php echo esc_html(cybersec_translate('Automatic Whitelist')); ?>
                        </strong>
                        <table class="wp-list-table widefat fixed striped" style="margin-top:8px;">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html(cybersec_translate('Type')); ?></th>
                                    <th><?php echo esc_html(cybersec_translate('Value')); ?></th>
                                    <th><?php echo esc_html(cybersec_translate('Note')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($auto_entries as $ae): ?>
                                <tr>
                                    <td><?php echo esc_html($ae['type']); ?></td>
                                    <td><code><?php echo esc_html($ae['value']); ?></code></td>
                                    <td><?php echo esc_html($ae['note']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Manual Whitelist -->
                    <?php $manual_entries = array_filter($wl, function($i) { return !cybersec_is_server_ip($i['value']); }); $manual_count = count($manual_entries); ?>
                    <div style="background:var(--sw-card-bg-alt);border:2px solid var(--sw-border);border-radius:var(--sw-radius);padding:14px 16px;">
                        <strong style="color:var(--sw-info);">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <?php echo esc_html(cybersec_translate('Manual Whitelist')); ?> (<?php echo (int)$manual_count; ?> <?php echo esc_html(cybersec_translate('total')); ?>)
                        </strong>
                        <?php if(empty($manual_entries)): ?>
                            <p style="color:var(--sw-text-secondary);margin-top:8px;text-align:center;padding:20px;"><?php echo esc_html(cybersec_translate('No manual entries yet.')); ?></p>
                        <?php else: ?>
                            <div style="<?php echo $manual_count > 5 ? 'max-height:280px;overflow-y:auto;' : ''; ?>margin-top:8px;border-radius:var(--sw-radius);scrollbar-width:thin;scrollbar-color:var(--sw-accent) var(--sw-input-bg);">
                                <table class="wp-list-table widefat fixed striped" style="margin:0;">
                                    <thead style="position:sticky;top:0;z-index:2;">
                                        <tr>
                                            <th><?php echo esc_html(cybersec_translate('Type')); ?></th>
                                            <th><?php echo esc_html(cybersec_translate('Value')); ?></th>
                                            <th><?php echo esc_html(cybersec_translate('Note')); ?></th>
                                            <th style="width:100px;"><?php echo esc_html(cybersec_translate('Actions')); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($wl as $idx => $item): if(cybersec_is_server_ip($item['value'])) continue; ?>
                                        <tr>
                                            <td><?php echo esc_html($item['type']); ?></td>
                                            <td><code style="word-break:break-all;"><?php echo esc_html($item['value']); ?></code></td>
                                            <td style="font-size:11px;"><?php echo esc_html($item['note']); ?></td>
                                            <td>
                                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cyber-security&cybersec_wl_remove=' . $idx), 'cybersec_wl_remove')); ?>" class="button button-small" style="color:var(--sw-danger);" onclick="return confirm('<?php echo esc_js(cybersec_translate('Delete entry?')); ?>')">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                                    <?php echo esc_html(cybersec_translate('Delete')); ?>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- TEST MODE -->
    <div class="card" style="max-width:100%;padding:20px 24px;margin-bottom:16px;">
        <div class="sw-setting-block" id="test-mode-section">
            <div style="background:var(--sw-card-bg-alt);border:2px solid var(--sw-border);border-radius:var(--sw-radius);padding:14px 16px;">
                <strong style="color:var(--sw-warning);">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><path d="M9 3h6"/><path d="M10 3v4.29a7 7 0 0 0-3.71 6.71c0 4.42 2.69 8 5.71 8s5.71-3.58 5.71-8a7 7 0 0 0-3.71-6.71V3"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                    <?php echo esc_html(cybersec_translate('Test Mode')); ?>
                </strong>
                <p style="margin:6px 0 0 0;font-size:12px;color:var(--sw-text-secondary);line-height:1.5;">
                    <?php echo esc_html(cybersec_translate('Temporarily disables all administrator privileges (whitelist, block bypass) to test protection features.')); ?> 
                    <strong style="color:var(--sw-warning);">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <?php echo esc_html(cybersec_translate('Do not close the admin panel during the test!')); ?>
                    </strong> 
                    <?php echo esc_html(cybersec_translate('Privileges will be automatically restored in 5 minutes.')); ?>
                </p>
                <?php $test_mode = get_transient('cybersec_test_mode_' . cybersec_get_visitor_ip()); ?>
                <form method="post" style="margin:0;">
                    <?php wp_nonce_field('cybersec_action','cybersec_nonce'); ?>
                    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-top:10px;">
                        <?php if ($test_mode): ?>
                            <input type="hidden" name="cybersec_test_mode" value="0">
                            <button type="submit" class="button button-primary" style="background:var(--sw-danger);border-color:var(--sw-danger);color:#fff;" onclick="localStorage.setItem('cybersec_scroll', 'test-mode-section'); localStorage.setItem('cybersec_tab', 'whitelist');">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                                <?php echo esc_html(cybersec_translate('Disable Test')); ?>
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="cybersec_test_mode" value="1">
                            <button type="submit" class="button button-primary" style="background:var(--sw-warning);border-color:var(--sw-warning);color:var(--sw-bg);" onclick="localStorage.setItem('cybersec_scroll', 'test-mode-section'); localStorage.setItem('cybersec_tab', 'whitelist');">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><path d="M9 3h6"/><path d="M10 3v4.29a7 7 0 0 0-3.71 6.71c0 4.42 2.69 8 5.71 8s5.71-3.58 5.71-8a7 7 0 0 0-3.71-6.71V3"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                                <?php echo esc_html(cybersec_translate('Enable for 5 minutes')); ?>
                            </button>
                        <?php endif; ?>
                        <span id="test-mode-status" style="font-size:12px;">
                            <?php if ($test_mode): ?>
                                <span style="color:var(--sw-warning);display:inline-flex;align-items:center;gap:4px;">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <?php echo esc_html(cybersec_translate('Active')); ?> • ...
</span>
                            <?php else: ?>
                                <span style="color:var(--sw-text-secondary);display:inline-flex;align-items:center;gap:4px;">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
    <?php echo esc_html(cybersec_translate('Not active')); ?>
</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </form>
                <?php if ($test_mode): ?>
                    <div style="background:var(--sw-warning-light);border:2px solid var(--sw-warning-border);padding:10px 14px;border-radius:var(--sw-radius);margin-top:12px;">
                        <p style="margin:0;color:var(--sw-warning);font-size:11px;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><path d="M12 18h.01"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/></svg>
                            <?php echo esc_html(cybersec_translate('Open the site in incognito mode to test protection as a regular visitor.')); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php // ==================== TAB 4: SETTINGS ==================== ?>
<div id="tab-settings" class="sw-tab" style="display:<?php echo $active_tab==='settings'?'block':'none';?>;">
    
    <!-- MAIN SETTINGS FORM -->
    <div class="card" style="max-width:100%;padding:20px 24px;margin-bottom:16px;">
        <h2><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg><?php echo esc_html(cybersec_translate('Main Settings')); ?></h2>
        <form method="post" autocomplete="off" onsubmit="localStorage.setItem('cybersec_tab', 'settings');"><?php wp_nonce_field('cybersec_action','cybersec_nonce'); ?>
        <input type="hidden" name="cybersec_language" id="cybersec_language_hidden" value="<?php echo esc_attr($st['cybersec_language'] ?? 'auto'); ?>">
        
        <!-- Language Selection -->
        <div class="sw-setting-block">
            <div style="background:var(--sw-card-bg-alt);border:2px solid var(--sw-border);border-radius:var(--sw-radius);padding:14px 16px;">
                <strong style="color:var(--sw-accent);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg><?php echo esc_html(cybersec_translate('Language')); ?></strong>
                <p style="margin:6px 0 0 0;font-size:12px;color:var(--sw-text-secondary);line-height:1.5;"><?php echo esc_html(cybersec_translate('Select the plugin interface language. "Auto" uses your WordPress language settings.')); ?></p>
                <div style="position:relative;display:inline-block;margin-top:10px;">
                    <select name="cybersec_language" onchange="document.getElementById('cybersec_language_hidden').value = this.value;" style="min-width:200px;padding:8px 32px 8px 12px;appearance:none;-webkit-appearance:none;-moz-appearance:none;background:var(--sw-input-bg);color:var(--sw-accent);border:2px solid var(--sw-input-border);border-radius:var(--sw-radius-sm);font-family:var(--sw-font-mono);font-size:0.76rem;cursor:pointer;">
                        <option value="auto" <?php selected($st['cybersec_language'] ?? 'auto', 'auto'); ?>><?php echo esc_html(cybersec_translate('Auto (WordPress locale)')); ?></option>
                        <?php foreach ($available_languages as $code => $name): ?>
                        <option value="<?php echo esc_attr($code); ?>" <?php selected($st['cybersec_language'] ?? 'auto', $code); ?>><?php echo esc_html($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--sw-accent);font-size:10px;">▼</span>
                </div>
            </div>
        </div>

                <!-- Email Notifications -->
        <div class="sw-setting-block">
            <div style="background:var(--sw-card-bg-alt);border:2px solid var(--sw-border);border-radius:var(--sw-radius);padding:14px 16px;">
                <strong style="color:var(--sw-accent);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><?php echo esc_html(cybersec_translate('Email Notifications')); ?></strong>
                <p style="margin:6px 0 0 0;font-size:12px;color:var(--sw-text-secondary);line-height:1.5;"><?php echo esc_html(cybersec_translate('Manage all plugin email notifications. Emails are sent to')); ?> <strong><?php echo esc_html(cybersec_get_blocked_email()); ?></strong>.</p>
                <div style="margin-top:10px;">
                    <label><input type="checkbox" name="cybersec_alert_enabled" value="1" <?php checked($st['alert_enabled'], 1); ?>> <?php echo esc_html(cybersec_translate('Attack spike notifications')); ?></label>
                    <p style="font-size:11px;color:var(--sw-text-secondary);margin:2px 0 0 24px;"><?php echo esc_html(cybersec_translate('When a large number of blocks occur in a short time.')); ?></p>

                    <p style="font-size:10px;color:var(--sw-warning);margin:8px 0 0 24px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg><?php echo esc_html(cybersec_translate('Weekly security reports and file change notifications are available in the PRO version.')); ?> <a href="https://gataurus.github.io/cyberpulse#pricing" target="_blank" style="color:var(--sw-accent);"><?php echo esc_html(cybersec_translate('Upgrade to PRO')); ?> →</a></p>
                </div>
            </div>
        </div>

        <!-- Auto-Unblock -->
        <div class="sw-setting-block">
            <div style="background:var(--sw-card-bg-alt);border:2px solid var(--sw-border);border-radius:var(--sw-radius);padding:14px 16px;">
                <strong style="color:var(--sw-accent);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><?php echo esc_html(cybersec_translate('Auto-Unblock')); ?></strong>
                <p style="margin:6px 0 0 0;font-size:12px;color:var(--sw-text-secondary);line-height:1.5;"><?php echo esc_html(cybersec_translate('How many minutes before temporarily blocked IPs are automatically unblocked. Set 0 to disable auto-unblock — then IPs will be blocked forever and can only be unblocked manually.')); ?> <strong><?php echo esc_html(cybersec_translate('Recommended: 30 minutes.')); ?></strong></p>
                <label style="display:block;margin-bottom:4px;font-weight:500;margin-top:10px;"><?php echo esc_html(cybersec_translate('Auto-unblock after')); ?></label>
                <div style="display:flex;align-items:center;gap:8px;"><input type="number" name="cybersec_auto_unblock" value="<?php echo (int)($st['auto_unblock_minutes'] ?? 30); ?>" min="0" max="43200" style="width:100px;padding:8px;border-radius:4px;border:2px solid var(--sw-border);"><strong><?php echo esc_html(cybersec_translate('minutes')); ?></strong></div>
            </div>
        </div>

        <!-- Auto-Clean Logs -->
        <div class="sw-setting-block">
            <div style="background:var(--sw-card-bg-alt);border:2px solid var(--sw-border);border-radius:var(--sw-radius);padding:14px 16px;">
                <strong style="color:var(--sw-accent);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg><?php echo esc_html(cybersec_translate('Auto-Clean Logs')); ?></strong>
                <p style="margin:6px 0 0 0;font-size:12px;color:var(--sw-text-secondary);line-height:1.5;"><?php echo esc_html(cybersec_translate('Automatically delete logs and statistics older than the specified number of days. 0 = disable (logs will accumulate).')); ?> <strong><?php echo esc_html(cybersec_translate('Recommended: 30 days.')); ?></strong></p>
                <div style="display:flex;align-items:center;gap:8px;margin-top:10px;"><span><?php echo esc_html(cybersec_translate('Delete logs older than')); ?></span><input type="number" name="cybersec_auto_clean_logs_days" value="<?php echo (int)($st['auto_clean_logs_days'] ?? 30); ?>" min="0" max="365" style="width:80px;padding:6px;border-radius:4px;border:2px solid var(--sw-border);"><span><?php echo esc_html(cybersec_translate('days')); ?></span></div>
            </div>
        </div>

        <!-- Referer Check -->
        <div class="sw-setting-block">
            <div style="background:var(--sw-card-bg-alt);border:2px solid var(--sw-border);border-radius:var(--sw-radius);padding:14px 16px;">
                <strong style="color:var(--sw-accent);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><?php echo esc_html(cybersec_translate('Referer Check')); ?></strong>
                <p style="margin:6px 0 0 0;font-size:12px;color:var(--sw-text-secondary);line-height:1.5;"><?php echo esc_html(cybersec_translate('Blocks requests without a Referer or with suspicious values. Protects against direct attacks, parsing, and scraping.')); ?> <strong><?php echo esc_html(cybersec_translate('Note:')); ?></strong> <?php echo esc_html(cybersec_translate('only works for pages added in "Dashboard → Page Tracking". For other pages, Referer check is not performed.')); ?> <strong><?php echo esc_html(cybersec_translate('Recommended: enabled, moderate mode.')); ?></strong></p>
                <label style="margin-top:10px;display:block;"><input type="checkbox" name="cybersec_referer_safe_check" value="1" <?php checked($rs,1);?>> <?php echo esc_html(cybersec_translate('Enable Referer check')); ?></label>
                <div style="background:var(--sw-info-light);border:2px solid var(--sw-info-border);border-radius:var(--sw-radius);padding:14px 16px;margin-top:12px;">
                    <strong style="color:var(--sw-info);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><?php echo esc_html(cybersec_translate('Sensitivity Thresholds')); ?></strong>
                    <p style="margin:4px 0 0 0;font-size:12px;color:var(--sw-text-secondary);"><?php echo esc_html(cybersec_translate('Configure Referer check sensitivity. If any threshold is exceeded, the IP is blocked. Use presets for quick setup.')); ?></p>
                    <div style="display:flex;gap:8px;margin:10px 0;"><button type="button" onclick="setPreset('strict', this)" class="button button-small preset-btn" style="background:var(--sw-danger-light);border-color:var(--sw-danger-border);color:var(--sw-danger);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><?php echo esc_html(cybersec_translate('Strict')); ?></button><button type="button" onclick="setPreset('medium', this)" class="button button-small preset-btn" style="background:var(--sw-warning-light);border-color:var(--sw-warning-border);color:var(--sw-warning);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg><?php echo esc_html(cybersec_translate('Moderate')); ?></button><button type="button" onclick="setPreset('soft', this)" class="button button-small preset-btn" style="background:var(--sw-success-light);border-color:var(--sw-success-border);color:var(--sw-success);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><path d="M17.1 19.5c-2.8 2.8-7.4 2.8-10.2 0-2.8-2.8-2.8-7.4 0-10.2 2.8-2.8 7.4-2.8 10.2 0"/><path d="M22 12c0-5.5-4.5-10-10-10S2 6.5 2 12s4.5 10 10 10"/></svg><?php echo esc_html(cybersec_translate('Soft')); ?></button></div>
                    <table class="form-table" style="margin:0;">
                        <tr><th style="width:180px;vertical-align:top;"><?php echo esc_html(cybersec_translate('Block history')); ?></th><td><input type="number" name="cybersec_referer_history_threshold" value="<?php echo (int)($rs_defaults['history_threshold'] ?? 1); ?>" min="0" max="100" style="width:70px;"> <strong><?php echo esc_html(cybersec_translate('or more')); ?></strong><br><span style="font-size:11px;color:var(--sw-text-secondary);"><?php echo esc_html(cybersec_translate('Block IPs that have had this many temporary blocks previously (0 = don\'t check history).')); ?> <strong style="color:var(--sw-accent);"><?php echo esc_html(cybersec_translate('Recommended: 1.')); ?></strong></span></td></tr>
                        <tr><th style="vertical-align:top;"><?php echo esc_html(cybersec_translate('Requests per minute')); ?></th><td><input type="number" name="cybersec_referer_rate_1min" value="<?php echo (int)($rs_defaults['rate_1min'] ?? 5); ?>" min="1" max="100" style="width:70px;"> <strong><?php echo esc_html(cybersec_translate('or more')); ?></strong><br><span style="font-size:11px;color:var(--sw-text-secondary);"><?php echo esc_html(cybersec_translate('Block when exceeding request rate per minute without Referer.')); ?> <strong style="color:var(--sw-accent);"><?php echo esc_html(cybersec_translate('Recommended: 5.')); ?></strong></span></td></tr>
                        <tr><th style="vertical-align:top;"><?php echo esc_html(cybersec_translate('Requests per 5 minutes')); ?></th><td><input type="number" name="cybersec_referer_rate_5min" value="<?php echo (int)($rs_defaults['rate_5min'] ?? 10); ?>" min="1" max="500" style="width:70px;"> <strong><?php echo esc_html(cybersec_translate('or more')); ?></strong><br><span style="font-size:11px;color:var(--sw-text-secondary);"><?php echo esc_html(cybersec_translate('Block when exceeding total requests in 5 minutes.')); ?> <strong style="color:var(--sw-accent);"><?php echo esc_html(cybersec_translate('Recommended: 10.')); ?></strong></span></td></tr>
                        <tr><th style="vertical-align:top;"><?php echo esc_html(cybersec_translate('Hits per same page')); ?></th><td><input type="number" name="cybersec_referer_same_page" value="<?php echo (int)($rs_defaults['same_page'] ?? 3); ?>" min="1" max="50" style="width:70px;"> <strong><?php echo esc_html(cybersec_translate('or more')); ?></strong><br><span style="font-size:11px;color:var(--sw-text-secondary);"><?php echo esc_html(cybersec_translate('Block when repeatedly accessing the same page within 5 minutes.')); ?> <strong style="color:var(--sw-accent);"><?php echo esc_html(cybersec_translate('Recommended: 3.')); ?></strong></span></td></tr>
                        <tr><th style="vertical-align:top;"><?php echo esc_html(cybersec_translate('Activity without proxy')); ?></th><td><input type="number" name="cybersec_referer_no_proxy_rate" value="<?php echo (int)($rs_defaults['no_proxy_rate'] ?? 3); ?>" min="1" max="100" style="width:70px;"> <strong><?php echo esc_html(cybersec_translate('requests')); ?></strong><br><span style="font-size:11px;color:var(--sw-text-secondary);"><?php echo esc_html(cybersec_translate('Block requests without proxy signs (X-Forwarded-For, Via) when exceeding threshold in 5 minutes.')); ?> <strong style="color:var(--sw-accent);"><?php echo esc_html(cybersec_translate('Recommended: 3.')); ?></strong></span></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Hide WordPress -->
<div class="sw-setting-block" style="margin-top:16px;">
    <div style="background:var(--sw-card-bg-alt);border:2px solid var(--sw-border);border-radius:var(--sw-radius);padding:14px 16px;">
        <strong style="color:var(--sw-accent);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><?php echo esc_html(cybersec_translate('Hide WordPress')); ?></strong>
                <p style="margin:6px 0 0 0;font-size:12px;color:var(--sw-text-secondary);line-height:1.5;"><?php echo esc_html(cybersec_translate('Comprehensive protection against CMS detection and vulnerability scanning. Hides system files, WordPress version, login error details, protects REST API, and disables dangerous XML-RPC functions.')); ?> <strong><?php echo esc_html(cybersec_translate('Recommended: all enabled.')); ?></strong></p>
        
        <div class="sw-settings-grid" style="margin-top:10px;">
            <div class="sw-settings-card"><h4><?php echo esc_html(cybersec_translate('System Files')); ?> <span class="sw-status <?php echo $st['hide_wp'] ? 'sw-status-active' : 'sw-status-inactive'; ?>"><?php echo $st['hide_wp'] ? esc_html(cybersec_translate('Hidden')) : esc_html(cybersec_translate('Accessible')); ?></span></h4><p><?php echo esc_html(cybersec_translate('Blocks access to')); ?> <code>wp-login.php</code>, <code>wp-config.php</code>, <code>xmlrpc.php</code>, <code>/wp-content/plugins/</code>, <code>readme.html</code>, <code>.git</code>, <code>.env</code> <?php echo esc_html(cybersec_translate('and other system files.')); ?></p><label style="margin-top:8px;display:block;"><input type="checkbox" name="cybersec_hide_wp" value="1" <?php checked($st['hide_wp'],1);?>> <?php echo esc_html(cybersec_translate('Hide system files')); ?></label></div>
            <div class="sw-settings-card"><h4><?php echo esc_html(cybersec_translate('WordPress Version')); ?> <span class="sw-status <?php echo $st['hide_wp_version'] ? 'sw-status-active' : 'sw-status-inactive'; ?>"><?php echo $st['hide_wp_version'] ? esc_html(cybersec_translate('Hidden')) : esc_html(cybersec_translate('Visible')); ?></span></h4><p><?php echo esc_html(cybersec_translate('Removes')); ?> <code>&lt;meta name="generator"&gt;</code> <?php echo esc_html(cybersec_translate('from HTML, version from RSS feeds and REST API.')); ?></p><label style="margin-top:8px;display:block;"><input type="checkbox" name="cybersec_hide_wp_version" value="1" <?php checked($st['hide_wp_version'],1);?>> <?php echo esc_html(cybersec_translate('Hide WordPress version')); ?></label></div>
            <div class="sw-settings-card"><h4><?php echo esc_html(cybersec_translate('Versions in URLs')); ?> <span class="sw-status <?php echo $st['remove_script_versions'] ? 'sw-status-active' : 'sw-status-inactive'; ?>"><?php echo $st['remove_script_versions'] ? esc_html(cybersec_translate('Removed')) : esc_html(cybersec_translate('Visible')); ?></span></h4><p><?php echo esc_html(cybersec_translate('Removes')); ?> <code>?ver=X.X.X</code> <?php echo esc_html(cybersec_translate('from script and style URLs. Prevents determining WordPress and plugin versions from static resources.')); ?></p><label style="margin-top:8px;display:block;"><input type="checkbox" name="cybersec_remove_script_versions" value="1" <?php checked($st['remove_script_versions'],1);?>> <?php echo esc_html(cybersec_translate('Remove versions from URLs')); ?></label></div>
            <div class="sw-settings-card"><h4><?php echo esc_html(cybersec_translate('Login Errors')); ?> <span class="sw-status <?php echo $st['hide_login_errors'] ? 'sw-status-active' : 'sw-status-inactive'; ?>"><?php echo $st['hide_login_errors'] ? esc_html(cybersec_translate('Hidden')) : esc_html(cybersec_translate('Visible')); ?></span></h4><p><?php echo esc_html(cybersec_translate('Replaces detailed errors with a generic "Invalid login credentials" message. Attackers won\'t know if the username exists.')); ?></p><label style="margin-top:8px;display:block;"><input type="checkbox" name="cybersec_hide_login_errors" value="1" <?php checked($st['hide_login_errors'],1);?>> <?php echo esc_html(cybersec_translate('Hide error details')); ?></label></div>
            <div class="sw-settings-card"><h4>REST API <span class="sw-status <?php echo ($st['rest_api_protection'] ?? 1) ? 'sw-status-active' : 'sw-status-inactive'; ?>"><?php echo ($st['rest_api_protection'] ?? 1) ? esc_html(cybersec_translate('Protected')) : esc_html(cybersec_translate('Open')); ?></span></h4><p><?php echo esc_html(cybersec_translate('Hides user list via REST API')); ?> (<code>/wp-json/wp/v2/users</code>). <?php echo esc_html(cybersec_translate('Prevents bots from enumerating usernames.')); ?></p><label style="margin-top:8px;display:block;"><input type="checkbox" name="cybersec_rest_api_protection" value="1" <?php checked($st['rest_api_protection'] ?? 1, 1);?>> <?php echo esc_html(cybersec_translate('Hide user list')); ?></label></div>
            <div class="sw-settings-card"><h4><?php echo esc_html(cybersec_translate('XML-RPC Protection')); ?> <span class="sw-status <?php echo $st['disable_xmlrpc_pingbacks'] ? 'sw-status-active' : 'sw-status-inactive'; ?>"><?php echo $st['disable_xmlrpc_pingbacks'] ? esc_html(cybersec_translate('Protected')) : esc_html(cybersec_translate('Open')); ?></span></h4><p><?php echo esc_html(cybersec_translate('Disables pingback notifications (DDoS attacks) and removes')); ?> <code>X-Pingback</code> <?php echo esc_html(cybersec_translate('header. XML-RPC remains enabled for compatibility.')); ?></p><label style="margin-top:8px;display:block;"><input type="checkbox" name="cybersec_disable_xmlrpc_pingbacks" value="1" <?php checked($st['disable_xmlrpc_pingbacks'],1);?>> <?php echo esc_html(cybersec_translate('Disable XML-RPC pingbacks')); ?></label></div>
            <div class="sw-settings-card"><h4><?php echo esc_html(cybersec_translate('Secure Cookies')); ?> <span class="sw-status <?php echo $st['force_secure_cookies'] ? 'sw-status-active' : 'sw-status-inactive'; ?>"><?php echo $st['force_secure_cookies'] ? esc_html(cybersec_translate('Protected')) : esc_html(cybersec_translate('Standard')); ?></span></h4><p><?php echo esc_html(cybersec_translate('Forces')); ?> <code>Secure</code> <?php echo esc_html(cybersec_translate('and')); ?> <code>HttpOnly</code> <?php echo esc_html(cybersec_translate('flags for WordPress authorization cookies. Prevents session hijacking.')); ?></p><label style="margin-top:8px;display:block;"><input type="checkbox" name="cybersec_force_secure_cookies" value="1" <?php checked($st['force_secure_cookies'],1);?>> <?php echo esc_html(cybersec_translate('Protect cookies (Secure + HttpOnly)')); ?></label></div>
            <div class="sw-settings-card">
    <h4><?php echo esc_html(cybersec_translate('Plugins/Themes')); ?> 
        <span class="sw-status <?php echo ($st['hide_plugins_themes'] ?? 1) ? 'sw-status-active' : 'sw-status-inactive'; ?>">
            <?php echo ($st['hide_plugins_themes'] ?? 1) ? esc_html(cybersec_translate('Hidden')) : esc_html(cybersec_translate('Accessible')); ?>
        </span>
    </h4>
    <p><?php echo esc_html(cybersec_translate('Blocks direct access to plugin and theme directories. Bots often scan /wp-content/plugins/ to find vulnerable versions.')); ?></p>
    <label style="margin-top:8px;display:block;">
        <input type="checkbox" name="cybersec_hide_plugins_themes" value="1" <?php checked($st['hide_plugins_themes'] ?? 1, 1); ?>> 
        <?php echo esc_html(cybersec_translate('Hide plugins/themes directories')); ?>
    </label>
</div>
<div class="sw-settings-card" style="border-color: var(--sw-success-border);">
    <h4><?php echo esc_html(cybersec_translate('Auto-Protection')); ?> 
        <span class="sw-status sw-status-active"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><polyline points="20 6 9 17 4 12"/></svg></span>
    </h4>
    <p><?php echo esc_html(cybersec_translate('XSS attacks, hidden file scanners (.git, .env, wp-config.php), and backup folder protection work automatically. No setup needed.')); ?></p>
    <p style="margin-top:8px;font-size:11px;color:var(--sw-success);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><polyline points="20 6 9 17 4 12"/></svg><?php echo esc_html(cybersec_translate('Always active')); ?></p>
</div>
        </div>
    </div>
</div>

        <!-- Login Protection -->
<div class="sw-setting-block">
    <div style="background:var(--sw-card-bg-alt);border:2px solid var(--sw-border);border-radius:var(--sw-radius);padding:14px 16px;">
        <strong style="color:var(--sw-accent);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/><line x1="12" y1="14" x2="12" y2="18"/></svg><?php echo esc_html(cybersec_translate('Login Protection')); ?></strong>
                <p style="margin:6px 0 0 0;font-size:12px;color:var(--sw-text-secondary);line-height:1.5;"><?php echo esc_html(cybersec_translate('Comprehensive login page protection: brute force protection.')); ?> <strong><?php echo esc_html(cybersec_translate('Recommended: enabled.')); ?></strong></p>
        
        <div class="sw-settings-grid" style="margin-top:10px;">
            <div class="sw-settings-card"><h4><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg><?php echo esc_html(cybersec_translate('Brute Force Protection')); ?> <span class="sw-status <?php echo $st['brute_enabled'] ? 'sw-status-active' : 'sw-status-inactive'; ?>"><?php echo $st['brute_enabled'] ? esc_html(cybersec_translate('Enabled')) : esc_html(cybersec_translate('Disabled')); ?></span></h4><p><?php echo esc_html(cybersec_translate('Protects wp-login.php, xmlrpc.php, and /wp-admin/ from password guessing. When attempt limit is exceeded, the IP')); ?> <strong><?php echo esc_html(cybersec_translate('goes straight to permanent block')); ?></strong> (<?php echo esc_html(cybersec_translate('/24 subnet')); ?>), <?php echo esc_html(cybersec_translate('without intermediate temporary block.')); ?></p><label style="margin-top:8px;display:block;"><input type="checkbox" name="cybersec_brute_enabled" value="1" <?php checked($st['brute_enabled'],1);?>> <?php echo esc_html(cybersec_translate('Enable brute force protection')); ?></label><div style="margin-top:10px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;"><span><?php echo esc_html(cybersec_translate('Maximum')); ?></span><input type="number" name="cybersec_brute_max" value="<?php echo (int)$st['brute_max_attempts'];?>" min="1" max="100" style="width:70px;padding:4px;"><span><?php echo esc_html(cybersec_translate('attempts in')); ?></span><input type="number" name="cybersec_brute_window" value="<?php echo (int)$st['brute_window_minutes'];?>" min="1" max="1440" style="width:70px;padding:4px;"><span><?php echo esc_html(cybersec_translate('minutes')); ?></span></div><p style="font-size:11px;color:var(--sw-text-secondary);margin-top:6px;"><strong><?php echo esc_html(cybersec_translate('Recommended:')); ?></strong> 5 <?php echo esc_html(cybersec_translate('attempts in 15 minutes.')); ?></p></div>
        </div>
    </div>
</div>

        <!-- Scanning and API Protection -->
        <div class="sw-setting-block">
            <div style="background:var(--sw-card-bg-alt);border:2px solid var(--sw-border);border-radius:var(--sw-radius);padding:14px 16px;">
                <strong style="color:var(--sw-accent);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg><?php echo esc_html(cybersec_translate('Scanning and API Flood Protection')); ?></strong>
                <p style="margin:6px 0 0 0;font-size:12px;color:var(--sw-text-secondary);line-height:1.5;"><?php echo esc_html(cybersec_translate('Protection against vulnerability scanners and REST API request limiting.')); ?></p>
                <div style="background:var(--sw-success-light);border:2px solid var(--sw-success-border);border-radius:var(--sw-radius);padding:14px 16px;margin-top:12px;"><strong style="color:var(--sw-success);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><polyline points="20 6 9 17 4 12"/></svg><?php echo esc_html(cybersec_translate('Automatic Scanner Detection')); ?></strong><p style="margin:6px 0 0 0;font-size:12px;color:var(--sw-text-secondary);"><?php echo esc_html(cybersec_translate('Blocks IPs with a large number of 404 errors in a short time — a sign of vulnerability scanning. When the limit is exceeded, the IP is blocked')); ?> <strong><?php echo esc_html(cybersec_translate('forever')); ?></strong>.</p><label style="margin-top:10px;display:block;"><input type="checkbox" name="cybersec_404_enabled" value="1" <?php checked($st['404_detection_enabled'] ?? 1, 1); ?>> <?php echo esc_html(cybersec_translate('Enable automatic scanner detection')); ?></label><div style="margin-top:10px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;"><span><?php echo esc_html(cybersec_translate('Block at')); ?></span><input type="number" name="cybersec_404_threshold" value="<?php echo (int)($st['404_threshold'] ?? 10); ?>" min="1" max="100" style="width:70px;"><span><?php echo esc_html(cybersec_translate('404 errors in')); ?></span><input type="number" name="cybersec_404_window" value="<?php echo (int)($st['404_window'] ?? 5); ?>" min="1" max="60" style="width:70px;"><span><?php echo esc_html(cybersec_translate('minutes')); ?></span></div></div>
                <div style="background:var(--sw-info-light);border:2px solid var(--sw-info-border);border-radius:var(--sw-radius);padding:14px 16px;margin-top:12px;"><strong style="color:var(--sw-info);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg><?php echo esc_html(cybersec_translate('Manual URL Blocking (URL Firewall)')); ?></strong><p style="margin:6px 0 0 0;font-size:12px;color:var(--sw-text-secondary);"><?php echo esc_html(cybersec_translate('Blocks access to specified URL paths. Useful for closing specific files, folders, or API endpoints. One path per line.')); ?></p><label style="margin-top:10px;display:block;"><input type="checkbox" name="cybersec_url_firewall_enabled" value="1" <?php checked($st['url_firewall_enabled'] ?? 0, 1); ?>> <?php echo esc_html(cybersec_translate('Enable URL Firewall')); ?></label><div style="margin-top:10px;"><textarea name="cybersec_url_firewall_patterns" rows="5" style="width:100%;max-width:600px;font-family:monospace;font-size:12px;" placeholder="/wp-json/some-api&#10;/old-page&#10;wp-config-backup"><?php echo esc_textarea($st['url_firewall_patterns'] ?? ''); ?></textarea><p style="font-size:11px;color:var(--sw-text-secondary);margin-top:4px;"><?php echo esc_html(cybersec_translate('One path per line. Any URL containing the specified fragment is blocked.')); ?></p></div></div>
            </div>
        </div>

                        <!-- Flood and DDoS Protection -->
        <div class="sw-setting-block">
            <div style="background:var(--sw-card-bg-alt);border:2px solid var(--sw-border);border-radius:var(--sw-radius);padding:14px 16px;">
                <strong style="color:var(--sw-accent);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="13 2 9 10 12 10 8 18"/></svg><?php echo esc_html(cybersec_translate('Flood and DDoS Protection')); ?></strong>
                <p style="margin:6px 0 0 0;font-size:12px;color:var(--sw-text-secondary);line-height:1.5;"><?php echo esc_html(cybersec_translate('Three levels of application-layer attack protection.')); ?> <strong><?php echo esc_html(cybersec_translate('Basic')); ?></strong> — <?php echo esc_html(cybersec_translate('constant protection against scraping and flooding.')); ?> <strong><?php echo esc_html(cybersec_translate('Enhanced')); ?></strong> — <?php echo esc_html(cybersec_translate('for repelling DDoS attacks.')); ?> <strong>Anti-Slowloris</strong> — <?php echo esc_html(cybersec_translate('automatic protection against slow connections.')); ?></p>
                <div style="background:var(--sw-success-light);border:2px solid var(--sw-success-border);border-radius:var(--sw-radius);padding:14px 16px;margin-top:12px;"><strong style="color:var(--sw-success);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><polyline points="20 6 9 17 4 12"/></svg><?php echo esc_html(cybersec_translate('Basic Level (Rate Limiting)')); ?></strong><p style="margin:6px 0 0 0;font-size:12px;color:var(--sw-text-secondary);"><?php echo esc_html(cybersec_translate('Limits the number of requests from one IP to the entire site within a specified time interval. Protects against DDoS attacks and aggressive scraping. After 3 repeated violations, the subnet is automatically permanently banned.')); ?> <strong><?php echo esc_html(cybersec_translate('Recommended: enabled, 60 requests per 60 seconds.')); ?></strong></p><label style="margin-top:10px;display:block;"><input type="checkbox" name="cybersec_rate_limiting_enabled" value="1" <?php checked($st['rate_limiting_enabled'],1);?>> <?php echo esc_html(cybersec_translate('Enable basic protection')); ?></label><div style="margin-top:10px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;"><span><?php echo esc_html(cybersec_translate('Maximum')); ?></span><input type="number" name="cybersec_rate_limit_requests" value="<?php echo (int)$st['rate_limit_requests'];?>" min="1" max="1000" style="width:80px;padding:6px;border-radius:4px;border:2px solid var(--sw-border);"><span><?php echo esc_html(cybersec_translate('requests per')); ?></span><input type="number" name="cybersec_rate_limit_window" value="<?php echo (int)$st['rate_limit_window'];?>" min="1" max="3600" style="width:80px;padding:6px;border-radius:4px;border:2px solid var(--sw-border);"><span><?php echo esc_html(cybersec_translate('seconds')); ?></span></div></div>
                <div style="background:var(--sw-info-light);border:2px solid var(--sw-info-border);border-radius:var(--sw-radius);padding:14px 16px;margin-top:12px;"><strong style="color:var(--sw-info);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:1.5px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg><?php echo esc_html(cybersec_translate('Anti-Slowloris Protection')); ?></strong><p style="margin:6px 0 0 0;font-size:12px;color:var(--sw-text-secondary);"><?php echo esc_html(cybersec_translate('Automatically tracks and blocks slow connections (Slowloris attacks). Works automatically when enhanced DDoS protection is enabled. IP is blocked when exceeding')); ?> <strong><?php echo esc_html(cybersec_translate('20 simultaneous connections')); ?></strong>.</p></div>
            </div>
        </div>

                <!-- Bot Detection -->
        <div class="sw-setting-block">
            <div style="background:var(--sw-card-bg-alt);border:2px solid var(--sw-border);border-radius:var(--sw-radius);padding:14px 16px;">
                <strong style="color:var(--sw-accent);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><path d="M8 11h6"/><circle cx="11" cy="11" r="2"/></svg><?php echo esc_html(cybersec_translate('Bot Detection')); ?></strong>
                <p style="margin:6px 0 0 0;font-size:12px;color:var(--sw-text-secondary);line-height:1.5;"><?php echo esc_html(cybersec_translate('Fine-tune automatic request detection parameters.')); ?> <strong><?php echo esc_html(cybersec_translate('Disable only if you are sure of the consequences.')); ?></strong> <?php echo esc_html(cybersec_translate('Each parameter blocks a specific type of suspicious activity.')); ?> <strong><?php echo esc_html(cybersec_translate('Recommended: all')); ?> <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><polyline points="20 6 9 17 4 12"/></svg><?php echo esc_html(cybersec_translate('checks enabled')); ?>, <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> — <?php echo esc_html(cybersec_translate('as needed.')); ?></strong></p>
                <div style="overflow-x:auto;margin-top:10px;"><table class="wp-list-table widefat fixed striped"><thead><tr><th style="padding:14px;width:220px;"><?php echo esc_html(cybersec_translate('Parameter')); ?></th><th style="width:60px;text-align:center;"><?php echo esc_html(cybersec_translate('On')); ?></th><th><?php echo esc_html(cybersec_translate('Description')); ?></th></tr></thead><tbody>
                    <tr><td><strong><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><polyline points="20 6 9 17 4 12"/></svg><?php echo esc_html(cybersec_translate('Empty User-Agent')); ?></strong></td><td style="text-align:center;"><input type="checkbox" name="cybersec_bot_empty_user_agent" value="1" <?php checked($bo['empty_user_agent'],1); ?>></td><td><?php echo esc_html(cybersec_translate('Blocks requests without User-Agent.')); ?> <strong><?php echo esc_html(cybersec_translate('Every browser')); ?></strong> (Chrome, Firefox, Safari, Edge, Opera) <strong><?php echo esc_html(cybersec_translate('must')); ?></strong> <?php echo esc_html(cybersec_translate('send User-Agent by HTTP standard. If User-Agent is missing — this is 100% a bot, script, or command-line tool:')); ?> <code>curl</code>, <code>wget</code>, <code>python-requests</code>, <code>Go-http-client</code>, <code>axios</code>, <code>node-fetch</code>. <?php echo esc_html(cybersec_translate('Safe to always enable.')); ?></td></tr>
                    <tr><td><strong><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><polyline points="20 6 9 17 4 12"/></svg><?php echo esc_html(cybersec_translate('User-Agent Anomalies')); ?></strong></td><td style="text-align:center;"><input type="checkbox" name="cybersec_bot_user_agent_anomalies" value="1" <?php checked($bo['user_agent_anomalies'],1); ?>></td><td><?php echo esc_html(cybersec_translate('Detects anomalous User-Agents by several criteria:')); ?><br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg><strong><?php echo esc_html(cybersec_translate('Tools')); ?></strong> — <code>curl/</code>, <code>Wget/</code>, <code>python-requests/</code>, <code>Go-http-client/</code>, <code>Java/</code>, <code>libwww-perl/</code>, <code>WinHttp</code>, <code>okhttp/</code> — <?php echo esc_html(cybersec_translate('these are not browsers, but libraries for automation.')); ?><br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><strong><?php echo esc_html(cybersec_translate('Old browsers')); ?></strong> — Chrome &lt; 70 (2018), Firefox &lt; 60, Safari &lt; 12 — <?php echo esc_html(cybersec_translate('legitimate users have long updated, such versions are only used by bots with old signatures.')); ?><br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><strong><?php echo esc_html(cybersec_translate('Short UA')); ?></strong> — User-Agent shorter than 40 characters. <?php echo esc_html(cybersec_translate('Real browsers send long strings with detailed OS and engine information.')); ?><br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M9 3h6M10 3v4.29a7 7 0 0 0-3.71 6.71c0 4.42 2.69 8 5.71 8s5.71-3.58 5.71-8a7 7 0 0 0-3.71-6.71V3"/></svg><strong><?php echo esc_html(cybersec_translate('Unreadable')); ?></strong> — UA consisting of meaningless character sets (e.g., <code>abcd1234</code>).</td></tr>
                    <tr><td><strong><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><polyline points="20 6 9 17 4 12"/></svg><?php echo esc_html(cybersec_translate('Fake Referer')); ?></strong></td><td style="text-align:center;"><input type="checkbox" name="cybersec_bot_fake_referer" value="1" <?php checked($bo['fake_referer'],1); ?>></td><td><?php echo esc_html(cybersec_translate('Blocks requests with')); ?> <strong><?php echo esc_html(cybersec_translate('fake Referers')); ?></strong> <?php echo esc_html(cybersec_translate('that imitate referrals from known sites but cannot actually be generated by those sites:')); ?><br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><strong><?php echo esc_html(cybersec_translate('Social media')); ?></strong> — <code>facebook.com</code>, <code>instagram.com</code>, <code>vk.com</code>, <code>linkedin.com</code>, <code>twitter.com</code>, <code>t.co</code>, <code>reddit.com</code> — <?php echo esc_html(cybersec_translate('social networks do not generate direct HTTP referrals to APIs and hidden files.')); ?><br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><strong><?php echo esc_html(cybersec_translate('Video hosting')); ?></strong> — <code>youtube.com</code>, <code>youtu.be</code>, <code>vimeo.com</code>, <code>tiktok.com</code> — <?php echo esc_html(cybersec_translate('similarly, do not generate direct requests to other sites.')); ?><br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><strong><?php echo esc_html(cybersec_translate('Messengers')); ?></strong> — <code>web.whatsapp.com</code>, <code>web.telegram.org</code>.<br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><strong><?php echo esc_html(cybersec_translate('Search engines')); ?></strong> — <?php echo esc_html(cybersec_translate('requests with Referer from Google or Yandex, but the request itself is not from a search bot (signature forgery).')); ?></td></tr>
                    <tr><td><strong><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><polyline points="20 6 9 17 4 12"/></svg>HTTP/1.0</strong></td><td style="text-align:center;"><input type="checkbox" name="cybersec_bot_http_1_0" value="1" <?php checked($bo['http_1_0'],1); ?>></td><td><?php echo esc_html(cybersec_translate('Blocks requests using the')); ?> <strong>HTTP/1.0</strong> <?php echo esc_html(cybersec_translate('protocol (released in 1996, 29 years ago).')); ?><br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><polyline points="20 6 9 17 4 12"/></svg><strong><?php echo esc_html(cybersec_translate('Modern browsers')); ?></strong> <?php echo esc_html(cybersec_translate('use HTTP/1.1 (since 1997) or HTTP/2, HTTP/3 (QUIC).')); ?><br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg><strong>HTTP/1.0 <?php echo esc_html(cybersec_translate('does not support')); ?></strong>: <?php echo esc_html(cybersec_translate('keep-alive connections, chunked transfer encoding, virtual hosts (Host header is optional).')); ?><br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><strong><?php echo esc_html(cybersec_translate('Who uses HTTP/1.0')); ?></strong>: <?php echo esc_html(cybersec_translate('old vulnerability scanners, low-quality bots, scripts on outdated libraries. Legitimate traffic via HTTP/1.0 is practically non-existent.')); ?></td></tr>
                    <tr><td><strong><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><polyline points="20 6 9 17 4 12"/></svg><?php echo esc_html(cybersec_translate('Suspicious Headers')); ?></strong></td><td style="text-align:center;"><input type="checkbox" name="cybersec_bot_suspicious_headers" value="1" <?php checked($bo['suspicious_headers'],1); ?>></td><td><?php echo esc_html(cybersec_translate('Checks')); ?> <strong><?php echo esc_html(cybersec_translate('standard HTTP headers')); ?></strong> <?php echo esc_html(cybersec_translate('that must be present in browsers:')); ?><br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg><strong>Accept</strong> — <?php echo esc_html(cybersec_translate('browsers always send a list of supported MIME types')); ?> (<code>text/html, application/xhtml+xml...</code>). <?php echo esc_html(cybersec_translate('Absence — sign of a script.')); ?><br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg><strong>Accept-Encoding</strong> — <?php echo esc_html(cybersec_translate('browsers always indicate supported compression algorithms')); ?> (<code>gzip, deflate, br</code>).<br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg><strong>Accept-Language</strong> — <?php echo esc_html(cybersec_translate('user interface language (checked by separate setting below).')); ?><br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg><strong>Connection</strong> — <?php echo esc_html(cybersec_translate('should be')); ?> <code>keep-alive</code> <?php echo esc_html(cybersec_translate('for modern browsers.')); ?><br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg><strong>Sec-Fetch-*</strong> — <?php echo esc_html(cybersec_translate('series of security headers')); ?> (<code>Sec-Fetch-Dest</code>, <code>Sec-Fetch-Mode</code>, <code>Sec-Fetch-Site</code>, <code>Sec-Fetch-User</code>) — <?php echo esc_html(cybersec_translate('sent by all modern browsers since 2020.')); ?></td></tr>
                    <tr><td><strong><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><polyline points="20 6 9 17 4 12"/></svg><?php echo esc_html(cybersec_translate('Spam Referers')); ?></strong></td><td style="text-align:center;"><input type="checkbox" name="cybersec_bot_bad_referer_spam" value="1" <?php checked($bo['bad_referer_spam'],1); ?>></td><td><?php echo esc_html(cybersec_translate('Extended Referer check — blocks requests with referers from')); ?> <strong><?php echo esc_html(cybersec_translate('known spam databases')); ?></strong> <?php echo esc_html(cybersec_translate('that fake referrals from fake sites for stats inflation or SEO spam:')); ?><br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg><strong><?php echo esc_html(cybersec_translate('SEO spam')); ?></strong> — <code>semalt.com</code>, <code>buttons-for-website.com</code>, <code>darodar.com</code>, <code>ilovevitaly.com</code>, <code>priceg.com</code>, <code>hulfingtonpost.com</code>, <code>o-o-6-o-o.com</code>.<br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg><strong><?php echo esc_html(cybersec_translate('Doorways')); ?></strong> — <code>traffic2money.com</code>, <code>get-free-traffic-now.com</code>, <code>event-tracking.com</code>.<br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg><strong><?php echo esc_html(cybersec_translate('Fake directories')); ?></strong> — <code>sites.google.com/site/...</code>, <code>aliexpress.com</code> (<?php echo esc_html(cybersec_translate('fake referrals')); ?>).<br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg><strong><?php echo esc_html(cybersec_translate('Spam bots')); ?></strong> — <?php echo esc_html(cybersec_translate('Referer contains')); ?> <code>comment</code>, <code>seo</code>, <code>buy-</code>, <code>free-</code>, <code>cheap-</code>, <code>viagra</code>, <code>casino</code>, <code>poker</code>, <code>porn</code> <?php echo esc_html(cybersec_translate('in the domain.')); ?></td></tr>
                    <tr style="background:var(--sw-warning-light);"><td><strong><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><?php echo esc_html(cybersec_translate('Direct IP Access')); ?></strong></td><td style="text-align:center;"><input type="checkbox" name="cybersec_bot_direct_ip_access" value="1" <?php checked($bo['direct_ip_access'],1); ?>></td><td><?php echo esc_html(cybersec_translate('Blocks requests where the')); ?> <strong>Host <?php echo esc_html(cybersec_translate('header contains an IP address')); ?></strong> <?php echo esc_html(cybersec_translate('instead of a domain.')); ?><br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><strong><?php echo esc_html(cybersec_translate('People')); ?></strong> <?php echo esc_html(cybersec_translate('visit sites by domain, and Host contains the domain.')); ?><br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><rect x="3" y="11" width="18" height="11" rx="2"/><circle cx="8.5" cy="16.5" r="1.5"/><circle cx="15.5" cy="16.5" r="1.5"/><line x1="12" y1="2" x2="12" y2="6"/><line x1="9" y1="4" x2="15" y2="4"/><line x1="3" y1="19" x2="21" y2="19"/></svg><strong><?php echo esc_html(cybersec_translate('Scanners')); ?></strong> <?php echo esc_html(cybersec_translate('scan IP ranges and access directly by IP')); ?> (<code>Host: 123.45.67.89</code>).<br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><?php echo esc_html(cybersec_translate('May affect monitoring services (UptimeRobot, Pingdom) if configured to check by IP. Add monitoring IP to whitelist.')); ?></td></tr>
                    <tr style="background:var(--sw-warning-light);"><td><strong><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><?php echo esc_html(cybersec_translate('Empty Accept-Language')); ?></strong></td><td style="text-align:center;"><input type="checkbox" name="cybersec_bot_empty_accept_language" value="1" <?php checked($bo['empty_accept_language'],1); ?>></td><td><?php echo esc_html(cybersec_translate('Blocks requests')); ?> <strong><?php echo esc_html(cybersec_translate('without Accept-Language header')); ?></strong>.<br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg><strong><?php echo esc_html(cybersec_translate('Browsers always send')); ?></strong> <?php echo esc_html(cybersec_translate('user language preferences:')); ?> <code>ru-RU,ru;q=0.9,en-US;q=0.8</code>.<br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><strong><?php echo esc_html(cybersec_translate('May affect')); ?></strong>: <?php echo esc_html(cybersec_translate('some API clients, RSS readers, monitoring services.')); ?></td></tr>
                    <tr style="background:var(--sw-warning-light);"><td><strong><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>IPv6</strong></td><td style="text-align:center;"><input type="checkbox" name="cybersec_bot_ipv6_connection" value="1" <?php checked(isset($bo['ipv6_connection'])?$bo['ipv6_connection']:0,1); ?>></td><td><?php echo esc_html(cybersec_translate('Blocks')); ?> <strong><?php echo esc_html(cybersec_translate('all requests from IPv6 addresses')); ?></strong>.<br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><strong><?php echo esc_html(cybersec_translate('Caution')); ?></strong>: <?php echo esc_html(cybersec_translate('with growing IPv6 adoption, you may block real mobile network users.')); ?></td></tr>
                    <tr style="background:var(--sw-warning-light);"><td><strong><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><?php echo esc_html(cybersec_translate('Cloudflare Bypass')); ?></strong></td><td style="text-align:center;"><input type="checkbox" name="cybersec_bot_cloudflare_origin" value="1" <?php checked(isset($bo['cloudflare_origin'])?$bo['cloudflare_origin']:0,1); ?>></td><td><?php echo esc_html(cybersec_translate('Blocks requests that')); ?> <strong><?php echo esc_html(cybersec_translate('try to access the server directly, bypassing Cloudflare')); ?></strong>.<br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><strong><?php echo esc_html(cybersec_translate('Enable ONLY if')); ?></strong>: <?php echo esc_html(cybersec_translate('you use Cloudflare (orange cloud enabled in DNS).')); ?></td></tr>
                </tbody></table></div>
            </div>
        </div>

        <!-- SAVE BUTTON FOR MAIN FORM -->
        <br>
        <button type="submit" name="cybersec_save_settings" class="button button-primary" style="font-size:15px;padding:10px 24px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg><?php echo esc_html(cybersec_translate('Save Settings')); ?></button>
        </form>
    </div>

</div>

<?php // ==================== TAB 5: INFO ==================== ?>
<div id="tab-info" class="sw-tab" style="display:<?php echo $active_tab==='info'?'block':'none';?>;">
    <div class="card" style="max-width:100%;padding:24px 28px;margin-bottom:16px;">
        <div class="sw-info-grid">
            <div class="sw-info-card"><h3><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg><?php echo esc_html(cybersec_translate('General Information')); ?></h3><p style="color:var(--sw-text-secondary);font-size:11px;margin:0 0 10px 0;line-height:1.5;"><?php echo esc_html(cybersec_translate('CyberPulse Security — comprehensive WordPress protection system against bots, scanners, brute-force attacks, and unwanted traffic.')); ?></p><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Name')); ?></span><span class="sw-info-value">CyberPulse</span></div><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Version')); ?></span><span class="sw-info-value"><?php echo esc_html(CYBERSEC_VERSION); ?></span></div><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Server IP')); ?></span><span class="sw-info-value"><code><?php echo esc_html($sip); ?></code></span></div><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Auto-Unblock')); ?></span><span class="sw-info-value"><?php echo (int)$am; ?> <?php echo esc_html(cybersec_translate('min')); ?>.</span></div><div class="sw-info-row"><span class="sw-info-label">Email</span><span class="sw-info-value"><?php echo esc_html($st['blocked_email'] ?: get_option('admin_email')); ?></span></div></div>
            <div class="sw-info-card"><h3><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><?php echo esc_html(cybersec_translate('Protection Status')); ?></h3><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Referer Check')); ?></span><span class="sw-info-value"><?php echo $rs ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:2px;color:var(--sw-success);"><polyline points="20 6 9 17 4 12"/></svg>' : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:2px;color:var(--sw-danger);"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'; ?></span></div><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Hide WP')); ?></span><span class="sw-info-value"><?php echo $st['hide_wp'] ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:2px;color:var(--sw-success);"><polyline points="20 6 9 17 4 12"/></svg>' : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:2px;color:var(--sw-danger);"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'; ?></span></div><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Brute Force Protection')); ?></span><span class="sw-info-value"><?php echo $st['brute_enabled'] ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:2px;color:var(--sw-success);"><polyline points="20 6 9 17 4 12"/></svg>' : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:2px;color:var(--sw-danger);"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'; ?></span></div><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('AI Scraping')); ?></span><span class="sw-info-value"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:2px;color:var(--sw-success);"><polyline points="20 6 9 17 4 12"/></svg></span></div><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Rate Limiting')); ?></span><span class="sw-info-value"><?php echo $st['rate_limiting_enabled'] ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:2px;color:var(--sw-success);"><polyline points="20 6 9 17 4 12"/></svg>' : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:2px;color:var(--sw-danger);"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'; ?></span></div><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('404 Detection')); ?></span><span class="sw-info-value"><?php echo ($st['404_detection_enabled'] ?? 1) ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:2px;color:var(--sw-success);"><polyline points="20 6 9 17 4 12"/></svg>' : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:2px;color:var(--sw-danger);"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'; ?></span></div><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('XSS Protection')); ?></span><span class="sw-info-value"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:2px;color:var(--sw-success);"><polyline points="20 6 9 17 4 12"/></svg></span></div></div>
            <div class="sw-info-card"><h3><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><?php echo esc_html(cybersec_translate('Referer Thresholds')); ?></h3><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Block History')); ?></span><span class="sw-info-value">≥ <?php echo (int)$rs_defaults['history_threshold']; ?></span></div><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Requests per Minute')); ?></span><span class="sw-info-value">≥ <?php echo (int)$rs_defaults['rate_1min']; ?></span></div><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Requests per 5 Minutes')); ?></span><span class="sw-info-value">≥ <?php echo (int)$rs_defaults['rate_5min']; ?></span></div><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Same Page Hits')); ?></span><span class="sw-info-value">≥ <?php echo (int)$rs_defaults['same_page']; ?></span></div></div>
            <div class="sw-info-card"><h3><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg><?php echo esc_html(cybersec_translate('Statistics (Today)')); ?></h3><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Blocked')); ?></span><span class="sw-info-value" style="color:var(--sw-danger);"><?php echo (int)$dy['blocked']; ?></span></div><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Allowed')); ?></span><span class="sw-info-value" style="color:var(--sw-success);"><?php echo (int)$dy['allowed']; ?></span></div><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Brute Force')); ?></span><span class="sw-info-value" style="color:var(--sw-warning);"><?php echo (int)$dy['brute']; ?></span></div><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Permanent Ban')); ?></span><span class="sw-info-value"><?php echo count($permanent_blocked); ?> <?php echo esc_html(cybersec_translate('subnets')); ?></span></div></div>
            <div class="sw-info-card"><h3><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg><?php echo esc_html(cybersec_translate('System Information')); ?></h3><div class="sw-info-row"><span class="sw-info-label">WordPress</span><span class="sw-info-value"><?php echo esc_html($wp_version); ?></span></div><div class="sw-info-row"><span class="sw-info-label">PHP</span><span class="sw-info-value"><?php echo esc_html($php_version); ?></span></div><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Server')); ?></span><span class="sw-info-value"><?php echo esc_html($server_software); ?></span></div><div class="sw-info-row"><span class="sw-info-label">MySQL</span><span class="sw-info-value">v<?php echo esc_html($db_version); ?></span></div><div class="sw-info-row"><span class="sw-info-label">Memory Limit</span><span class="sw-info-value"><?php echo esc_html($memory_limit); ?></span></div></div>
            <div class="sw-info-card"><h3><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><?php echo esc_html(cybersec_translate('Signature Database')); ?></h3><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Datacenter Subnets')); ?></span><span class="sw-info-value"><?php echo (int)$datacenter_count; ?></span></div><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Known Bots')); ?></span><span class="sw-info-value"><?php echo count(cybersec_get_known_bot_subnets()); ?> <?php echo esc_html(cybersec_translate('subnets')); ?></span></div><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Blocked User-Agents')); ?></span><span class="sw-info-value"><?php echo count($blocked_uas); ?></span></div><div class="sw-info-row"><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Permanent Blocks')); ?></span><span class="sw-info-value"><?php echo count($permanent_blocked); ?></span></div></div>
            <div class="sw-info-card" style="grid-column: 1 / -1;"><h3><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:4px;position:relative;top:2px;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg><?php echo esc_html(cybersec_translate('Log Storage')); ?></h3><div style="margin-bottom:8px;word-break:break-all;"><span class="sw-info-label" style="display:block;margin-bottom:2px;"><?php echo esc_html(cybersec_translate('Directory')); ?></span><code style="font-size:10px;background:var(--sw-input-bg);padding:4px 8px;border-radius:3px;display:block;border:1px solid var(--sw-border-light);"><?php echo esc_html($log_dir); ?></code></div><div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:8px;"><div><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Writable')); ?></span><br><span class="sw-info-value"><?php echo $log_dir_writable ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;color:var(--sw-success);"><polyline points="20 6 9 17 4 12"/></svg>' . esc_html(cybersec_translate('Yes')) : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:2px;position:relative;top:1.5px;color:var(--sw-danger);"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' . esc_html(cybersec_translate('No')); ?></span></div><div><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Log Size')); ?></span><br><span class="sw-info-value"><?php echo $total_log_size > 1048576 ? esc_html(round($total_log_size/1048576,1)).' MB' : esc_html(round($total_log_size/1024,1)).' KB'; ?></span></div><div><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Search Subnets')); ?></span><br><span class="sw-info-value"><?php echo (int)$known_bots; ?></span></div><div><span class="sw-info-label"><?php echo esc_html(cybersec_translate('Blocked UAs')); ?></span><br><span class="sw-info-value"><?php echo count($blocked_uas); ?></span></div></div></div>
        </div>
    </div>
</div>

<!-- ===== PRO UPGRADE BANNER ===== -->
<div class="card" style="max-width:100%;padding:20px 24px;margin-bottom:20px;text-align:center;border:2px solid var(--sw-warning-border);background:var(--sw-card-bg-alt);">
    <div style="font-size:40px;margin-bottom:12px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="#f59e0b" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
    <h2 style="margin:0 0 8px 0;font-size:1rem;color:var(--sw-warning);"><?php echo esc_html(cybersec_translate('Upgrade to PRO')); ?></h2>
    <p style="color:var(--sw-text-secondary);font-size:0.8rem;margin-bottom:16px;max-width:600px;margin-left:auto;margin-right:auto;">
        <?php echo esc_html(cybersec_translate('Get advanced protection: Malware Scanner, DDoS Protection, Geo-blocking, 2FA, Content Protection, and more.')); ?>
    </p>
<a href="https://gataurus.github.io/cyberpulse#pricing" target="_blank" rel="noopener noreferrer"
       style="display:inline-flex;align-items:center;gap:8px;padding:12px 28px;font-size:0.85rem;font-weight:800;
              background:linear-gradient(135deg,#ffb800 0%,#f59e0b 100%);border:2px solid #e09600;color:#1a1e18;
              border-radius:var(--sw-radius);text-decoration:none;transition:all 0.2s;white-space:nowrap;
              box-shadow:0 4px 15px rgba(255,184,0,0.3);">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:baseline;margin-right:3px;position:relative;top:2px;"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><?php echo esc_html(cybersec_translate('View Plans & Pricing')); ?>
    </a>
</div>

<div class="sw-footer-brand">
    <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 36" width="20" height="20" class="cybersec-logo-footer">
  <path fill="none" stroke="#000000" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M 18.0,30.8 C 17.2,30.4 9.2,25.9 7.6,10.5 C 7.5,7.5 12.3,5.2 18.0,5.2 C 23.7,5.2 28.5,7.5 28.4,10.5 C 26.8,26.8 18.0,30.8 18.0,30.8 Z"/>
  <path fill="#D0CFCE" d="M 18.0,5.5 C 12.5,5.5 8.0,7.8 8.0,10.5 L 8.0,10.6 C 9.5,25.8 17.3,30.3 18.0,30.7 L 18.0,30.7 C 18.0,30.7 26.6,26.7 28.0,10.7 C 28.2,7.9 23.5,5.5 18.0,5.5 Z"/>
  <path fill="#D0CFCE" d="M 18.0,27.5 L 18.0,27.5 C 17.6,27.2 11.3,23.9 10.0,11.5 L 10.0,11.5 C 10.0,9.3 13.6,7.5 18.0,7.5 C 22.4,7.5 26.2,9.3 26.0,11.5 C 24.8,24.2 18.0,27.5 18.0,27.5 Z"/>
  <path fill="#E60012" d="M 17.6,9.0 L 19.0,11.0 L 17.6,13.1 L 19.0,15.0 L 17.6,17.0 L 19.0,19.0 L 17.6,21.0 L 19.0,23.1 L 17.6,25.1 L 18.0,27.7 C 18.0,27.7 10.5,22.2 10.0,11.7 C 10.0,11.7 10.0,7.7 18.0,7.7 L 17.6,9.0 Z"/>
  <path fill="#FFFFFF" d="M 18.0,7.7 L 17.6,9.0 L 19.0,11.0 L 17.6,13.1 L 19.0,15.0 L 17.6,17.0 L 19.0,19.0 L 17.6,21.0 L 19.0,23.1 L 17.6,25.1 L 18.0,27.7 C 18.0,27.7 25.5,23.1 26.0,11.7 C 26.0,11.5 25.5,7.7 18.0,7.7 Z"/>
</svg> CyberPulse Security v<?php echo esc_html(CYBERSEC_VERSION); ?></span>
</div>

<?php 
}
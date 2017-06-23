<?php

// The activation hook
function cron_activation(){
    if(!wp_next_scheduled('pull_changes_event')){
        wp_schedule_event(time(), '5min', 'pull_changes_event');
    }
    if(!wp_next_scheduled('pull_stock_event')){
        //wp_schedule_event(time(), '5min', 'pull_stock_event');
    }
}
// The deactivation hook
function cron_deactivation(){
    if(wp_next_scheduled('pull_changes_event')){ 
        wp_clear_scheduled_hook('pull_changes_event');
    }
    if(wp_next_scheduled('pull_stock_event')){
        wp_clear_scheduled_hook('pull_stock_event');
    }
} 

// every 5 mins
function schedule($schedules) {
    $schedules['5min'] = array(
            'interval'  => 30, //30 secs for debug //5 * 60, //5min
            'display'   => __('Every 5 Minutes', 'textdomain')
    );
    return $schedules;
}

?>
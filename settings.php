<?php

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    // Add a setting for the server URL
    $settings->add(new admin_setting_configtext(
        'block_actions_recommender/serverurl',
        get_string('serverurl', 'block_actions_recommender'),
        get_string('serverurl_description', 'block_actions_recommender'),
        'http://150.128.81.34:8000', // Default value
        PARAM_URL //type
    ));
}

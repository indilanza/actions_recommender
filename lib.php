<?php
defined('MOODLE_INTERNAL') || die();

function block_actions_recommender_pluginfile($course, $birec, $context, $filearea, $args, $forcedownload, array $options=array()) {
    // Function code here, if needed.
}

function block_actions_recommender_extend_settings_navigation(settings_navigation $settingsnav, context $context) {
    if (has_capability('moodle/site:config', $context)) {
        $url = new moodle_url('/blocks/actions_recommender/settings.php');
        $settingsnav->add(get_string('settings', 'block_actions_recommender'), $url);
    }
}

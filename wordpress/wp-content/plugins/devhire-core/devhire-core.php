<?php
/**
 * Plugin Name: DevHire Core
 * Description: Core functionality for the DevHire developer job platform.
 * Version: 1.0.0
 * Author: Manny Luzano
 * Text Domain: devhire-core
 */

if (!defined('ABSPATH')) {
    exit;
}

define(
    'DEVHIRE_CORE_PATH',
    plugin_dir_path(__FILE__)
);

define(
    'DEVHIRE_CORE_URL',
    plugin_dir_url(__FILE__)
);

define(
    'DEVHIRE_CORE_VERSION',
    '1.0.0'
);


/*
 * Core modules.
 */
require_once DEVHIRE_CORE_PATH . 'includes/post-types.php';
require_once DEVHIRE_CORE_PATH . 'includes/taxonomies.php';
require_once DEVHIRE_CORE_PATH . 'includes/job-meta.php';
require_once DEVHIRE_CORE_PATH . 'includes/company-meta.php';
require_once DEVHIRE_CORE_PATH . 'includes/applications.php';
require_once DEVHIRE_CORE_PATH . 'includes/saved-jobs.php';
require_once DEVHIRE_CORE_PATH . 'includes/rest-api.php';
require_once DEVHIRE_CORE_PATH . 'includes/job-search.php';
require_once DEVHIRE_CORE_PATH . 'includes/candidates.php';
require_once DEVHIRE_CORE_PATH . 'includes/candidate-profile.php';


/**
 * Plugin activation.
 */
function devhire_core_activate() {

    devhire_register_post_types();
    devhire_register_taxonomies();

    flush_rewrite_rules();
}

register_activation_hook(
    __FILE__,
    'devhire_core_activate'
);


/**
 * Plugin deactivation.
 */
function devhire_core_deactivate() {
    flush_rewrite_rules();
}

register_deactivation_hook(
    __FILE__,
    'devhire_core_deactivate'
);
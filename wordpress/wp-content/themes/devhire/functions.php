<?php

if (!defined('ABSPATH')) {
    exit;
}

function devhire_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');

    register_nav_menus([
        'primary' => __('Primary Menu', 'devhire'),
    ]);
}

add_action('after_setup_theme', 'devhire_setup');


function devhire_enqueue_assets() {
    wp_enqueue_style(
        'devhire-style',
        get_stylesheet_uri(),
        [],
        wp_get_theme()->get('Version')
    );
}

add_action('wp_enqueue_scripts', 'devhire_enqueue_assets');
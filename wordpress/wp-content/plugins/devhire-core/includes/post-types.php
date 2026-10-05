<?php

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Register DevHire post types.
 */
function devhire_register_post_types() {

    /*
     * Jobs
     */
    register_post_type('job', [
        'labels' => [
            'name'          => 'Jobs',
            'singular_name' => 'Job',
            'add_new_item'  => 'Add New Job',
            'edit_item'     => 'Edit Job',
            'all_items'     => 'All Jobs',
        ],

        'public'       => true,
        'has_archive'  => true,
        'show_in_rest' => true,

        'rewrite' => [
            'slug' => 'jobs',
        ],

        'menu_icon' =>
            'dashicons-businessperson',

        'supports' => [
            'title',
            'editor',
            'excerpt',
            'thumbnail',
            'author',
        ],
    ]);


    /*
     * Companies
     */
    register_post_type('company', [
        'labels' => [
            'name'          => 'Companies',
            'singular_name' => 'Company',
            'add_new_item'  => 'Add New Company',
            'edit_item'     => 'Edit Company',
            'all_items'     => 'All Companies',
        ],

        'public'       => true,
        'has_archive'  => true,
        'show_in_rest' => true,

        'rewrite' => [
            'slug' => 'companies',
        ],

        'menu_icon' =>
            'dashicons-building',

        'supports' => [
            'title',
            'editor',
            'excerpt',
            'thumbnail',
        ],
    ]);


    /*
     * Applications
     */
    register_post_type(
        'job_application',
        [
            'labels' => [
                'name' =>
                    'Applications',

                'singular_name' =>
                    'Application',

                'menu_name' =>
                    'Applications',

                'all_items' =>
                    'All Applications',

                'view_item' =>
                    'View Application',

                'search_items' =>
                    'Search Applications',

                'not_found' =>
                    'No applications found',
            ],

            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => false,
            'exclude_from_search' => true,

            'menu_icon' =>
                'dashicons-id',

            'supports' => [
                'title',
            ],
        ]
    );
}

add_action(
    'init',
    'devhire_register_post_types'
);
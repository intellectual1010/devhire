<?php

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Register Job taxonomies.
 */
function devhire_register_taxonomies() {

    register_taxonomy(
        'job_skill',
        ['job'],
        [
            'labels' => [
                'name' =>
                    'Skills',

                'singular_name' =>
                    'Skill',
            ],

            'public'            => true,
            'hierarchical'      => false,
            'show_admin_column' => true,
            'show_in_rest'      => true,

            'rewrite' => [
                'slug' => 'skill',
            ],
        ]
    );


    register_taxonomy(
        'job_type',
        ['job'],
        [
            'labels' => [
                'name' =>
                    'Job Types',

                'singular_name' =>
                    'Job Type',
            ],

            'public'            => true,
            'hierarchical'      => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,

            'rewrite' => [
                'slug' => 'job-type',
            ],
        ]
    );


    register_taxonomy(
        'job_location',
        ['job'],
        [
            'labels' => [
                'name' =>
                    'Locations',

                'singular_name' =>
                    'Location',
            ],

            'public'            => true,
            'hierarchical'      => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,

            'rewrite' => [
                'slug' => 'location',
            ],
        ]
    );
}

add_action(
    'init',
    'devhire_register_taxonomies'
);
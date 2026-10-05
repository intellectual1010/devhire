<?php

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Register DevHire REST routes.
 */
function devhire_register_rest_routes() {

    register_rest_route(
        'devhire/v1',
        '/jobs',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'devhire_api_get_jobs',
            'permission_callback' => '__return_true',
            'args'                => [
                'search' => [
                    'sanitize_callback' =>
                        'sanitize_text_field',
                ],

                'skill' => [
                    'sanitize_callback' =>
                        'sanitize_title',
                ],

                'job_type' => [
                    'sanitize_callback' =>
                        'sanitize_title',
                ],

                'location' => [
                    'sanitize_callback' =>
                        'sanitize_title',
                ],

                'page' => [
                    'default' => 1,

                    'sanitize_callback' =>
                        'absint',
                ],

                'per_page' => [
                    'default' => 10,

                    'sanitize_callback' =>
                        'absint',
                ],
            ],
        ]
    );


    register_rest_route(
        'devhire/v1',
        '/jobs/(?P<id>\d+)',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'devhire_api_get_job',
            'permission_callback' => '__return_true',

            'args' => [
                'id' => [
                    'validate_callback' =>
                        function ($value) {
                            return absint($value) > 0;
                        },
                ],
            ],
        ]
    );


    register_rest_route(
        'devhire/v1',
        '/companies',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'devhire_api_get_companies',
            'permission_callback' => '__return_true',
        ]
    );


    register_rest_route(
        'devhire/v1',
        '/companies/(?P<id>\d+)',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'devhire_api_get_company',
            'permission_callback' => '__return_true',
        ]
    );
}

add_action(
    'rest_api_init',
    'devhire_register_rest_routes'
);

/**
 * Convert WordPress terms into API-friendly data.
 */
function devhire_api_terms($post_id, $taxonomy) {

    $terms = get_the_terms(
        $post_id,
        $taxonomy
    );

    if (
        !$terms ||
        is_wp_error($terms)
    ) {
        return [];
    }


    return array_map(
        function ($term) {

            return [
                'id'   => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            ];
        },
        $terms
    );
}

/**
 * Format a Job for the API.
 */
function devhire_api_format_job($job_id) {

    $company_id = absint(
        get_post_meta(
            $job_id,
            '_devhire_company',
            true
        )
    );


    $company = null;

    if (
        $company_id &&
        get_post_status($company_id) === 'publish' &&
        get_post_type($company_id) === 'company'
    ) {

        $company = [
            'id'   => $company_id,

            'name' =>
                get_the_title($company_id),

            'url' =>
                get_permalink($company_id),
        ];
    }


    return [
        'id' => $job_id,

        'title' =>
            get_the_title($job_id),

        'slug' =>
            get_post_field(
                'post_name',
                $job_id
            ),

        'url' =>
            get_permalink($job_id),

        'excerpt' =>
            get_the_excerpt($job_id),

        'description' =>
            apply_filters(
                'the_content',
                get_post_field(
                    'post_content',
                    $job_id
                )
            ),

        'company' =>
            $company,

        'salary' =>
            get_post_meta(
                $job_id,
                '_devhire_salary',
                true
            ),

        'experience_level' =>
            get_post_meta(
                $job_id,
                '_devhire_experience',
                true
            ),

        'remote' =>
            get_post_meta(
                $job_id,
                '_devhire_remote',
                true
            ),

        'application_deadline' =>
            get_post_meta(
                $job_id,
                '_devhire_deadline',
                true
            ),

        'skills' =>
            devhire_api_terms(
                $job_id,
                'job_skill'
            ),

        'job_types' =>
            devhire_api_terms(
                $job_id,
                'job_type'
            ),

        'locations' =>
            devhire_api_terms(
                $job_id,
                'job_location'
            ),

        'published_at' =>
            get_post_time(
                DATE_ATOM,
                true,
                $job_id
            ),

        'modified_at' =>
            get_post_modified_time(
                DATE_ATOM,
                true,
                $job_id
            ),
    ];
}

/**
 * GET /devhire/v1/jobs
 */
function devhire_api_get_jobs(
    WP_REST_Request $request
) {

    $page = max(
        1,
        absint($request->get_param('page'))
    );

    $per_page = absint(
        $request->get_param('per_page')
    );

    $per_page = min(
        max($per_page, 1),
        50
    );


    $args = [
        'post_type'      => 'job',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];


    $search = $request->get_param(
        'search'
    );

    if ($search) {
        $args['s'] = $search;
    }


    /*
     * Taxonomy filters.
     */
    $tax_query = [];


    $filters = [
        'skill' => 'job_skill',

        'job_type' =>
            'job_type',

        'location' =>
            'job_location',
    ];


    foreach (
        $filters as
        $parameter => $taxonomy
    ) {

        $value = $request->get_param(
            $parameter
        );

        if (!$value) {
            continue;
        }


        $tax_query[] = [
            'taxonomy' => $taxonomy,
            'field'    => 'slug',
            'terms'    => $value,
        ];
    }


    if ($tax_query) {

        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }

        $args['tax_query'] =
            $tax_query;
    }


    $query = new WP_Query($args);


    $jobs = array_map(
        'devhire_api_format_job',
        wp_list_pluck(
            $query->posts,
            'ID'
        )
    );


    $response = rest_ensure_response([
        'data' => $jobs,

        'pagination' => [
            'page' =>
                $page,

            'per_page' =>
                $per_page,

            'total' =>
                (int) $query->found_posts,

            'total_pages' =>
                (int) $query->max_num_pages,
        ],
    ]);


    return $response;
}

/**
 * GET /devhire/v1/jobs/{id}
 */
function devhire_api_get_job(
    WP_REST_Request $request
) {

    $job_id = absint(
        $request['id']
    );


    if (
        get_post_type($job_id) !== 'job' ||
        get_post_status($job_id) !== 'publish'
    ) {

        return new WP_Error(
            'devhire_job_not_found',
            'Job not found.',
            [
                'status' => 404,
            ]
        );
    }


    return rest_ensure_response(
        devhire_api_format_job(
            $job_id
        )
    );
}

/**
 * Format Company API response.
 */
function devhire_api_format_company(
    $company_id,
    $include_jobs = false
) {

    $data = [
        'id' =>
            $company_id,

        'name' =>
            get_the_title($company_id),

        'slug' =>
            get_post_field(
                'post_name',
                $company_id
            ),

        'url' =>
            get_permalink($company_id),

        'description' =>
            apply_filters(
                'the_content',
                get_post_field(
                    'post_content',
                    $company_id
                )
            ),

        'website' =>
            get_post_meta(
                $company_id,
                '_devhire_company_website',
                true
            ),

        'location' =>
            get_post_meta(
                $company_id,
                '_devhire_company_location',
                true
            ),

        'industry' =>
            get_post_meta(
                $company_id,
                '_devhire_company_industry',
                true
            ),

        'company_size' =>
            get_post_meta(
                $company_id,
                '_devhire_company_size',
                true
            ),

        'logo' =>
            get_the_post_thumbnail_url(
                $company_id,
                'medium'
            ) ?: null,
    ];


    if ($include_jobs) {

        $jobs = get_posts([
            'post_type'      => 'job',
            'post_status'    => 'publish',
            'posts_per_page' => -1,

            'meta_key' =>
                '_devhire_company',

            'meta_value' =>
                $company_id,

            'orderby' =>
                'date',

            'order' =>
                'DESC',
        ]);


        $data['jobs'] = array_map(
            'devhire_api_format_job',
            wp_list_pluck(
                $jobs,
                'ID'
            )
        );
    }


    return $data;
}

/**
 * GET /devhire/v1/companies
 */
function devhire_api_get_companies() {

    $companies = get_posts([
        'post_type'      => 'company',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);


    $data = array_map(
        function ($company) {

            return devhire_api_format_company(
                $company->ID,
                false
            );
        },
        $companies
    );


    return rest_ensure_response([
        'data' => $data,
        'total' => count($data),
    ]);
}


/**
 * GET /devhire/v1/companies/{id}
 */
function devhire_api_get_company(
    WP_REST_Request $request
) {

    $company_id = absint(
        $request['id']
    );


    if (
        get_post_type($company_id) !==
            'company' ||

        get_post_status($company_id) !==
            'publish'
    ) {

        return new WP_Error(
            'devhire_company_not_found',
            'Company not found.',
            [
                'status' => 404,
            ]
        );
    }


    return rest_ensure_response(
        devhire_api_format_company(
            $company_id,
            true
        )
    );
}
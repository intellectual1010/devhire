<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validate a job before saving it.
 */
function devhire_ajax_validate_job() {

    check_ajax_referer(
        'devhire_saved_jobs',
        'nonce'
    );

    $job_id = isset($_POST['job_id'])
        ? absint($_POST['job_id'])
        : 0;

    if (
        !$job_id ||
        get_post_type($job_id) !== 'job' ||
        get_post_status($job_id) !== 'publish'
    ) {
        wp_send_json_error([
            'message' => 'Invalid job.',
        ]);
    }

    wp_send_json_success([
        'job_id'  => $job_id,
        'title'   => get_the_title($job_id),
        'url'     => get_permalink($job_id),
        'message' => 'Job validated.',
    ]);
}

add_action(
    'wp_ajax_devhire_validate_job',
    'devhire_ajax_validate_job'
);

add_action(
    'wp_ajax_nopriv_devhire_validate_job',
    'devhire_ajax_validate_job'
);

/**
 * Saved Jobs assets.
 */
function devhire_saved_jobs_assets() {

    if (
        !is_singular('job') &&
        !is_page_template('page-saved-jobs.php')
    ) {
        return;
    }

    wp_enqueue_script(
        'devhire-saved-jobs',
        DEVHIRE_CORE_URL . 'assets/js/saved-jobs.js',
        [],
        '1.0.0',
        true
    );

    wp_localize_script(
        'devhire-saved-jobs',
        'devhireSavedJobs',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),

            'nonce' => wp_create_nonce(
                'devhire_saved_jobs'
            ),
        ]
    );
}

add_action(
    'wp_enqueue_scripts',
    'devhire_saved_jobs_assets'
);
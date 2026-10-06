<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check whether a job application deadline has expired.
 */
function devhire_job_is_expired($job_id) {

    $job_id = absint($job_id);

    if (!$job_id) {
        return false;
    }

    $deadline = get_post_meta(
        $job_id,
        '_devhire_deadline',
        true
    );

    if (!$deadline) {
        return false;
    }

    $deadline_date = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i:s',
        $deadline . ' 23:59:59',
        wp_timezone()
    );

    if (!$deadline_date) {
        return false;
    }

    $now = new DateTimeImmutable(
        'now',
        wp_timezone()
    );

    return $deadline_date < $now;
}

/**
 * Check whether a candidate has already applied to a job.
 */
function devhire_candidate_has_applied($job_id, $user_id) {

    $job_id  = absint($job_id);
    $user_id = absint($user_id);

    if (!$job_id || !$user_id) {
        return false;
    }

    $existing_application = get_posts([
        'post_type'              => 'job_application',
        'post_status'            => 'private',
        'posts_per_page'         => 1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'meta_query'             => [
            'relation' => 'AND',
            [
                'key'     => '_devhire_application_job',
                'value'   => $job_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
            [
                'key'     => '_devhire_candidate_user',
                'value'   => $user_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
        ],
    ]);

    return !empty($existing_application);
}

/**
 * Job Application Form shortcode.
 *
 * Usage:
 * [devhire_application_form]
 */
function devhire_application_form_shortcode() {

    if (!is_singular('job')) {
        return '';
    }

    $job_id = get_the_ID();

    ob_start();

    $name     = '';
    $email    = '';
    $phone    = '';
    $linkedin = '';
    $resume_id = 0;
    $resume_url = '';
    $resume_name = '';

    if (is_user_logged_in()) {

        $current_user = wp_get_current_user();
        $user_id      = $current_user->ID;

        $name  = $current_user->display_name;
        $email = $current_user->user_email;

        $phone = get_user_meta(
            $user_id,
            '_devhire_phone',
            true
        );

        $linkedin = get_user_meta(
            $user_id,
            '_devhire_linkedin',
            true
        );

        $resume_id = absint(
            get_user_meta(
                $user_id,
                '_devhire_resume_id',
                true
            )
        );

        if ($resume_id) {
            $resume_url  = wp_get_attachment_url($resume_id);
            $resume_name = basename(
                get_attached_file($resume_id)
            );
        }
    }

    $deadline = get_post_meta(
        $job_id,
        '_devhire_deadline',
        true
    );

    $is_expired = devhire_job_is_expired($job_id);

    $deadline_date = null;

    if ($deadline) {
        $deadline_date = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $deadline . ' 23:59:59',
            wp_timezone()
        );
    }

    if ($is_expired) {
        ?>

        <div class="application-closed">

            <span class="application-closed-badge">
                Applications Closed
            </span>

            <h3>This job is no longer accepting applications.</h3>

            <p>
                The application deadline was
                <strong>
                    <?php
                    echo esc_html(
                        wp_date(
                            get_option('date_format'),
                            $deadline_date->getTimestamp(),
                            wp_timezone()
                        )
                    );
                    ?>
                </strong>.
            </p>

            <a
                href="<?php echo esc_url(
                    get_post_type_archive_link('job')
                ); ?>"
                class="secondary-button"
            >
                Browse Other Jobs
            </a>

        </div>

        <?php
        return ob_get_clean();
    }

    if (!is_user_logged_in()) {
        ?>

        <div class="application-login-required">

            <span class="application-login-badge">
                Candidate Account Required
            </span>

            <h3>Sign in to apply for this job.</h3>

            <p>
                Create a free candidate account or sign in to submit
                your application and track its progress.
            </p>

            <div class="form-actions">

                <a
                    href="<?php echo esc_url(
                        home_url('/candidate-login/')
                    ); ?>"
                    class="primary-button"
                >
                    Candidate Login
                </a>

                <a
                    href="<?php echo esc_url(
                        home_url('/candidate-register/')
                    ); ?>"
                    class="secondary-button"
                >
                    Create Account
                </a>

            </div>

        </div>

        <?php
        return ob_get_clean();
    }

    $current_user = wp_get_current_user();

    if (!in_array('candidate', (array) $current_user->roles, true)) {
        ?>

        <div class="application-login-required">

            <h3>Candidate account required.</h3>

            <p>
                Job applications can only be submitted using
                a candidate account.
            </p>

        </div>

        <?php
        return ob_get_clean();
    }

    $already_applied = false;

    $already_applied = devhire_candidate_has_applied(
        $job_id,
        $current_user->ID
    );

    $application_success = (
        isset($_GET['application']) &&
        sanitize_key(wp_unslash($_GET['application'])) === 'success'
    );

    $application_error = isset($_GET['application_error'])
        ? sanitize_key(wp_unslash($_GET['application_error']))
        : '';

    if ($application_success && $already_applied) {
        ?>

        <div class="application-already-applied">

            <span class="application-applied-badge">
                Application Submitted
            </span>

            <h3>Your application was submitted successfully.</h3>

            <p>
                The employer can now review your application.
                You can track its status from your candidate dashboard.
            </p>

            <a
                href="<?php echo esc_url(
                    home_url('/candidate-dashboard/')
                ); ?>"
                class="primary-button"
            >
                View Candidate Dashboard
            </a>

        </div>

        <?php
        return ob_get_clean();
    }

    if ($application_error === 'invalid_account') {
        ?>

        <div class="application-login-required">

            <span class="application-login-badge">
                Candidate Account Required
            </span>

            <h3>Please use a candidate account to apply.</h3>

            <p>
                Employer and administrator accounts cannot submit job applications.
                Sign in with a candidate account to continue.
            </p>

            <a
                href="<?php echo esc_url(home_url('/candidate-login/')); ?>"
                class="button button-primary"
            >
                Candidate Login
            </a>

        </div>

        <?php

        return ob_get_clean();
    }

    if ($application_error === 'invalid_request') {
        ?>

        <div class="application-login-required">

            <span class="application-login-badge">
                Application Error
            </span>

            <h3>Your application request could not be verified.</h3>

            <p>
                Please refresh this page and submit your application again.
            </p>

        </div>

        <?php
    }

    if ($application_error === 'invalid_fields') {
        ?>

        <div class="application-login-required">

            <span class="application-login-badge">
                Application Error
            </span>

            <h3>Please complete the required application fields.</h3>

            <p>
                Your application was not submitted. Please enter your
                application message and try again.
            </p>

        </div>

        <?php
    }

    if (!$resume_url) {
        ?>

        <div class="application-resume-required">

            <span class="application-login-badge">
                Resume Required
            </span>

            <h3>Add your resume before applying.</h3>

            <p>
                Complete your candidate profile and upload a resume
                before submitting an application.
            </p>

            <a
                href="<?php echo esc_url(
                    home_url('/candidate-profile/')
                ); ?>"
                class="primary-button"
            >
                Complete Candidate Profile
            </a>

        </div>

        <?php
        return ob_get_clean();
    }
    ?>

    <?php if ($already_applied) : ?>

        <div class="application-already-applied">

            <span class="application-applied-badge">
                Application Submitted
            </span>

            <h3>You already applied to this job.</h3>

            <p>
                You can track the progress of your application
                from your candidate dashboard.
            </p>

            <a
                href="<?php echo esc_url(
                    home_url('/candidate-dashboard/')
                ); ?>"
                class="primary-button"
            >
                View My Applications
            </a>

        </div>

        <?php
        return ob_get_clean();
        ?>

    <?php endif; ?>  

    <div
        class="devhire-application-form"
        id="apply"
    >

        <div class="application-form-heading">
            <h2>Apply for this position</h2>

            <p>
                Complete the form below to submit your
                application for
                <strong>
                    <?php echo esc_html(get_the_title($job_id)); ?>
                </strong>.
            </p>
        </div>


        <?php if (
            isset($_GET['application']) &&
            $_GET['application'] === 'error'
        ) : ?>

            <div class="application-error">
                <strong>Unable to submit application.</strong>

                <p>
                    Please check your information and try again.
                </p>
            </div>

        <?php endif; ?>


        <form
            method="post"
            action="<?php echo esc_url(
                admin_url('admin-post.php')
            ); ?>"
        >

            <input
                type="hidden"
                name="action"
                value="devhire_submit_application"
            >

            <input
                type="hidden"
                name="job_id"
                value="<?php echo esc_attr($job_id); ?>"
            >

            <?php
            wp_nonce_field(
                'devhire_submit_application',
                'devhire_application_nonce'
            );
            ?>


            <div class="application-fields">

                <div class="application-full candidate-application-summary">

                    <div>
                        <span>Name</span>
                        <strong><?php echo esc_html($name); ?></strong>
                    </div>

                    <div>
                        <span>Email</span>
                        <strong><?php echo esc_html($email); ?></strong>
                    </div>

                    <?php if ($phone) : ?>
                        <div>
                            <span>Phone</span>
                            <strong><?php echo esc_html($phone); ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if ($linkedin) : ?>
                        <div>
                            <span>LinkedIn / Portfolio</span>
                            <a
                                href="<?php echo esc_url($linkedin); ?>"
                                target="_blank"
                                rel="noopener"
                            >
                                View Profile
                            </a>
                        </div>
                    <?php endif; ?>

                    <a
                        href="<?php echo esc_url(
                            home_url('/candidate-profile/')
                        ); ?>"
                        class="candidate-edit-profile"
                    >
                        Edit Candidate Profile
                    </a>

                </div>


                <div class="application-field application-full">

                    <label for="applicant_message">
                        Cover Message *
                    </label>

                    <textarea
                        id="applicant_message"
                        name="applicant_message"
                        rows="7"
                        maxlength="3000"
                        required
                        placeholder="Tell us why you're interested in this position..."
                    ></textarea>

                </div>

                <?php if ($resume_url) : ?>

                    <div class="candidate-profile-resume">

                        <p>
                            Profile Resume:
                            <a
                                href="<?php echo esc_url($resume_url); ?>"
                                target="_blank"
                                rel="noopener"
                            >
                                <?php echo esc_html($resume_name); ?>
                            </a>
                        </p>

                    </div>

                <?php endif; ?>

                <!-- Honeypot -->
                <div
                    class="devhire-honeypot"
                    aria-hidden="true"
                >
                    <label for="company_website">
                        Company Website
                    </label>

                    <input
                        id="company_website"
                        name="company_website"
                        type="text"
                        tabindex="-1"
                        autocomplete="off"
                    >
                </div>


                <div class="application-full">

                    <button
                        type="submit"
                        class="primary-button application-submit"
                    >
                        Submit Application
                    </button>

                </div>

            </div>

        </form>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode(
    'devhire_application_form',
    'devhire_application_form_shortcode'
);

/**
 * Process application submission.
 */
function devhire_handle_application_submission() {

    $job_id = isset($_POST['job_id'])
        ? absint($_POST['job_id'])
        : 0;


    $fallback_url = home_url('/jobs/');

    $job_url = (
        $job_id &&
        get_post_type($job_id) === 'job'
    )
        ? get_permalink($job_id)
        : $fallback_url;


    /*
     * The application target must still be a published Job.
     * This prevents direct POST requests from applying to draft,
     * pending, trashed, deleted, or non-job content.
     */
    if (
        !$job_id ||
        get_post_type($job_id) !== 'job' ||
        get_post_status($job_id) !== 'publish'
    ) {
        wp_safe_redirect(
            add_query_arg(
                'application_error',
                'job_unavailable',
                $fallback_url
            )
        );
        exit;
    }


    /*
     * Reject expired jobs at the backend as well.
     * The frontend already hides the form, but this prevents bypassing
     * that UI with a manually crafted POST request.
     */
    if (devhire_job_is_expired($job_id)) {
        wp_safe_redirect(
            add_query_arg(
                'application_error',
                'job_expired',
                $job_url
            )
        );
        exit;
    }


    /*
     * Verify nonce.
     */
    if (
        !isset($_POST['devhire_application_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(
                wp_unslash(
                    $_POST['devhire_application_nonce']
                )
            ),
            'devhire_submit_application'
        )
    ) {
        wp_safe_redirect(
            add_query_arg(
                'application_error',
                'invalid_request',
                $job_url
            )
        );

        exit;
    }


    /*
     * Honeypot spam protection.
     */
    if (
        !empty($_POST['company_website'])
    ) {
        wp_safe_redirect($job_url);
        exit;
    }


    /*
     * Validate job.
     */
    if (
        !$job_id ||
        get_post_type($job_id) !== 'job' ||
        get_post_status($job_id) !== 'publish'
    ) {
        wp_safe_redirect($fallback_url);
        exit;
    }

    /*
    * Only logged-in candidate accounts can apply.
    */
    if (!is_user_logged_in()) {

        wp_safe_redirect(
            add_query_arg(
                [
                    'application_error' => 'login_required',
                    'redirect_to'       => get_permalink($job_id),
                ],
                home_url('/candidate-login/')
            )
        );

        exit;
    }

    $current_user = wp_get_current_user();

    if (!in_array('candidate', (array) $current_user->roles, true)) {

        wp_safe_redirect(
            add_query_arg(
                'application_error',
                'invalid_account',
                $job_url
            )
        );

        exit;
    }

    /*
     * Prevent applications after the job deadline.
     */
    if (devhire_job_is_expired($job_id)) {

        wp_safe_redirect(
            add_query_arg(
                'application_error',
                'expired',
                get_permalink($job_id)
            )
        );

        exit;
    }

    /*
    * Prevent duplicate applications.
    */

    if (
        devhire_candidate_has_applied(
            $job_id,
            $current_user->ID
        )
    ) {

        wp_safe_redirect(
            add_query_arg(
                'application_error',
                'already_applied',
                get_permalink($job_id)
            )
        );

        exit;
    }

    /*
     * Sanitize fields.
     */
    /*
    * Name and email come from the authenticated
    * candidate account, not submitted form values.
    */
    $name = sanitize_text_field(
        $current_user->display_name
    );

    $email = sanitize_email(
        $current_user->user_email
    );

    $phone = sanitize_text_field(
        get_user_meta(
            $current_user->ID,
            '_devhire_phone',
            true
        )
    );

    $linkedin = esc_url_raw(
        get_user_meta(
            $current_user->ID,
            '_devhire_linkedin',
            true
        )
    );

    $message = isset($_POST['applicant_message'])
        ? sanitize_textarea_field(
            wp_unslash($_POST['applicant_message'])
        )
        : '';


    /*
     * Required fields.
     */
    if (
        !$name ||
        !$email ||
        !is_email($email) ||
        !$message
    ) {
        wp_safe_redirect(
            add_query_arg(
                'application_error',
                'invalid_fields',
                $job_url
            )
        );

        exit;
    }


    /*
    * Get resume from the authenticated candidate profile.
    */
    $resume_id = absint(
        get_user_meta(
            $current_user->ID,
            '_devhire_resume_id',
            true
        )
    );

    $resume_url = $resume_id
        ? wp_get_attachment_url($resume_id)
        : '';

    if (!$resume_url) {

        /*
         * Clean up a stale resume attachment ID so the profile page
         * correctly treats the candidate as having no usable resume.
         */
        if ($resume_id) {
            delete_user_meta(
                $current_user->ID,
                '_devhire_resume_id'
            );
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'application_error' => 'resume_required',
                    'redirect_to'       => $job_url,
                ],
                home_url('/candidate-profile/')
            )
        );

        exit;
    }

    $candidate_title = sanitize_text_field(
        get_user_meta(
            $current_user->ID,
            '_devhire_professional_title',
            true
        )
    );

    $candidate_location = sanitize_text_field(
        get_user_meta(
            $current_user->ID,
            '_devhire_location',
            true
        )
    );

    /*
     * Create private application record.
     */
    $application_id = wp_insert_post([
        'post_type'   => 'job_application',
        'post_status' => 'private',

        'post_title' =>
            $name . ' — ' . get_the_title($job_id),
    ]);


    if (
        !$application_id ||
        is_wp_error($application_id)
    ) {
        wp_safe_redirect(
            add_query_arg(
                'application',
                'error',
                $job_url
            )
        );

        exit;
    }


    /*
     * Store structured application data.
     */
    update_post_meta(
        $application_id,
        '_devhire_application_job',
        $job_id
    );

    update_post_meta(
        $application_id,
        '_devhire_applicant_name',
        $name
    );

    update_post_meta(
        $application_id,
        '_devhire_applicant_email',
        $email
    );

    update_post_meta(
        $application_id,
        '_devhire_candidate_user',
        $current_user->ID
    );

    update_post_meta(
        $application_id,
        '_devhire_applicant_phone',
        $phone
    );

    update_post_meta(
        $application_id,
        '_devhire_applicant_linkedin',
        $linkedin
    );

    update_post_meta(
        $application_id,
        '_devhire_applicant_message',
        $message
    );

    update_post_meta(
        $application_id,
        '_devhire_applicant_resume',
        esc_url_raw($resume_url)
    );

    update_post_meta(
        $application_id,
        '_devhire_applicant_resume_id',
        $resume_id
    );

    update_post_meta(
        $application_id,
        '_devhire_application_status',
        'New'
    );

    update_post_meta(
        $application_id,
        '_devhire_candidate_title',
        $candidate_title
    );

    update_post_meta(
        $application_id,
        '_devhire_candidate_location',
        $candidate_location
    );

    /*
     * Success.
     */
    wp_safe_redirect(
        add_query_arg(
            'application',
            'success',
            $job_url . '#apply'
        )
    );

    exit;
}


/*
 * Logged-in visitors.
 */
add_action(
    'admin_post_devhire_submit_application',
    'devhire_handle_application_submission'
);


/*
 * Logged-out visitors.
 */
add_action(
    'admin_post_nopriv_devhire_submit_application',
    'devhire_handle_application_submission'
);

/**
 * Application admin columns.
 */
function devhire_application_columns($columns) {

    return [
        'cb'        => $columns['cb'],
        'title'     => 'Applicant',
        'job'       => 'Job',
        'email'     => 'Email',
        'status'    => 'Status',
        'resume'    => 'Resume',
        'submitted' => 'Submitted',
    ];
}

add_filter(
    'manage_job_application_posts_columns',
    'devhire_application_columns'
);


/**
 * Render application admin columns.
 */
function devhire_application_column_content(
    $column,
    $post_id
) {

    $job_id = absint(
        get_post_meta(
            $post_id,
            '_devhire_application_job',
            true
        )
    );

    $email = get_post_meta(
        $post_id,
        '_devhire_applicant_email',
        true
    );

    $status = get_post_meta(
        $post_id,
        '_devhire_application_status',
        true
    );

    $resume = get_post_meta(
        $post_id,
        '_devhire_applicant_resume',
        true
    );


    switch ($column) {

        case 'job':

            if ($job_id) {

                echo '<a href="' .
                    esc_url(
                        get_edit_post_link($job_id)
                    ) .
                    '">';

                echo esc_html(
                    get_the_title($job_id)
                );

                echo '</a>';

            } else {
                echo '—';
            }

            break;


        case 'email':

            if ($email) {

                echo '<a href="mailto:' .
                    esc_attr($email) .
                    '">';

                echo esc_html($email);

                echo '</a>';

            } else {
                echo '—';
            }

            break;


        case 'status':

            $status = $status ?: 'New';

            $class = sanitize_html_class(
                strtolower($status)
            );

            echo '<span class="devhire-status devhire-status-' .
                esc_attr($class) .
                '">';

            echo esc_html($status);

            echo '</span>';

            break;


        case 'resume':

            if ($resume) {

                echo '<a href="' .
                    esc_url($resume) .
                    '" target="_blank" rel="noopener noreferrer">';

                echo 'View Resume';

                echo '</a>';

            } else {
                echo '—';
            }

            break;


        case 'submitted':

            echo esc_html(
                get_the_date(
                    'M j, Y g:i A',
                    $post_id
                )
            );

            break;
    }
}

add_action(
    'manage_job_application_posts_custom_column',
    'devhire_application_column_content',
    10,
    2
);

/**
 * Application admin styling.
 */
function devhire_application_admin_styles() {

    $screen = get_current_screen();

    if (
        !$screen ||
        $screen->post_type !== 'job_application'
    ) {
        return;
    }
    ?>

    <style>

        .devhire-status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .devhire-status-new {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .devhire-status-reviewing {
            background: #fef3c7;
            color: #92400e;
        }

        .devhire-status-interview {
            background: #ede9fe;
            color: #6d28d9;
        }

        .devhire-status-hired {
            background: #dcfce7;
            color: #166534;
        }

        .devhire-status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

    </style>

    <?php
}

add_action(
    'admin_head',
    'devhire_application_admin_styles'
);

/**
 * Application Details meta box.
 */
function devhire_add_application_meta_box() {

    add_meta_box(
        'devhire_application_details',
        'Application Details',
        'devhire_application_details_callback',
        'job_application',
        'normal',
        'high'
    );
}

add_action(
    'add_meta_boxes',
    'devhire_add_application_meta_box'
);


function devhire_application_details_callback($post) {

    wp_nonce_field(
        'devhire_save_application_details',
        'devhire_application_details_nonce'
    );


    $job_id = absint(
        get_post_meta(
            $post->ID,
            '_devhire_application_job',
            true
        )
    );

    $name = get_post_meta(
        $post->ID,
        '_devhire_applicant_name',
        true
    );

    $email = get_post_meta(
        $post->ID,
        '_devhire_applicant_email',
        true
    );

    $phone = get_post_meta(
        $post->ID,
        '_devhire_applicant_phone',
        true
    );

    $linkedin = get_post_meta(
        $post->ID,
        '_devhire_applicant_linkedin',
        true
    );

    $message = get_post_meta(
        $post->ID,
        '_devhire_applicant_message',
        true
    );

    $resume = get_post_meta(
        $post->ID,
        '_devhire_applicant_resume',
        true
    );

    $candidate_title = get_post_meta(
        $post->ID,
        '_devhire_candidate_title',
        true
    );

    $candidate_location = get_post_meta(
        $post->ID,
        '_devhire_candidate_location',
        true
    );

    $status = get_post_meta(
        $post->ID,
        '_devhire_application_status',
        true
    );

    $status = $status ?: 'New';
    ?>

    <style>

        .devhire-application-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .devhire-admin-field {
            padding: 16px;

            border: 1px solid #ddd;
            border-radius: 5px;

            background: #fff;
        }

        .devhire-admin-field-full {
            grid-column: 1 / -1;
        }

        .devhire-admin-label {
            display: block;

            margin-bottom: 7px;

            color: #646970;

            font-size: 12px;
            font-weight: 600;

            text-transform: uppercase;
        }

        .devhire-admin-value {
            font-size: 15px;
        }

        .devhire-admin-message {
            white-space: pre-wrap;
            line-height: 1.6;
        }

        .devhire-status-select {
            width: 100%;
            max-width: 300px;
        }

        .devhire-resume-button {
            display: inline-block;

            padding: 9px 14px;

            border-radius: 4px;

            background: #2271b1;
            color: #fff;

            text-decoration: none;
        }

        .devhire-resume-button:hover {
            background: #135e96;
            color: #fff;
        }

        @media (max-width: 782px) {

            .devhire-application-details {
                grid-template-columns: 1fr;
            }

            .devhire-admin-field-full {
                grid-column: auto;
            }
        }

    </style>


    <div class="devhire-application-details">


        <div class="devhire-admin-field">

            <span class="devhire-admin-label">
                Applicant
            </span>

            <div class="devhire-admin-value">
                <?php echo esc_html($name); ?>
            </div>

        </div>


        <div class="devhire-admin-field">

            <span class="devhire-admin-label">
                Professional Title
            </span>

            <div class="devhire-admin-value">
                <?php echo $candidate_title
                    ? esc_html($candidate_title)
                    : '—'; ?>
            </div>

        </div>


        <div class="devhire-admin-field">

            <span class="devhire-admin-label">
                Location
            </span>

            <div class="devhire-admin-value">
                <?php echo $candidate_location
                    ? esc_html($candidate_location)
                    : '—'; ?>
            </div>

        </div>


        <div class="devhire-admin-field">

            <span class="devhire-admin-label">
                Applied For
            </span>

            <div class="devhire-admin-value">

                <?php if ($job_id) : ?>

                    <a href="<?php echo esc_url(
                        get_edit_post_link($job_id)
                    ); ?>">

                        <?php
                        echo esc_html(
                            get_the_title($job_id)
                        );
                        ?>

                    </a>

                <?php else : ?>

                    —

                <?php endif; ?>

            </div>

        </div>


        <div class="devhire-admin-field">

            <span class="devhire-admin-label">
                Email
            </span>

            <div class="devhire-admin-value">

                <?php if ($email) : ?>

                    <a href="mailto:<?php echo esc_attr($email); ?>">
                        <?php echo esc_html($email); ?>
                    </a>

                <?php else : ?>

                    —

                <?php endif; ?>

            </div>

        </div>


        <div class="devhire-admin-field">

            <span class="devhire-admin-label">
                Phone
            </span>

            <div class="devhire-admin-value">
                <?php echo $phone ? esc_html($phone) : '—'; ?>
            </div>

        </div>


        <div class="devhire-admin-field">

            <span class="devhire-admin-label">
                LinkedIn / Portfolio
            </span>

            <div class="devhire-admin-value">

                <?php if ($linkedin) : ?>

                    <a
                        href="<?php echo esc_url($linkedin); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Open Profile
                    </a>

                <?php else : ?>

                    —

                <?php endif; ?>

            </div>

        </div>


        <div class="devhire-admin-field">

            <span class="devhire-admin-label">
                Resume
            </span>

            <div class="devhire-admin-value">

                <?php if ($resume) : ?>

                    <a
                        class="devhire-resume-button"
                        href="<?php echo esc_url($resume); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        View Resume
                    </a>

                <?php else : ?>

                    No resume uploaded.

                <?php endif; ?>

            </div>

        </div>


        <div class="devhire-admin-field devhire-admin-field-full">

            <span class="devhire-admin-label">
                Cover Message
            </span>

            <div class="devhire-admin-message">
                <?php echo esc_html($message); ?>
            </div>

        </div>


        <div class="devhire-admin-field devhire-admin-field-full">

            <label
                class="devhire-admin-label"
                for="devhire_application_status"
            >
                Application Status
            </label>

            <select
                class="devhire-status-select"
                id="devhire_application_status"
                name="devhire_application_status"
            >

                <?php
                $statuses = [
                    'New',
                    'Reviewing',
                    'Interview',
                    'Hired',
                    'Rejected',
                ];

                foreach ($statuses as $option) :
                ?>

                    <option
                        value="<?php echo esc_attr($option); ?>"
                        <?php selected($status, $option); ?>
                    >
                        <?php echo esc_html($option); ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

    </div>

    <?php
}

/**
 * Save recruiter-managed application fields.
 */
function devhire_save_application_details($post_id) {

    if (
        !isset($_POST['devhire_application_details_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(
                wp_unslash(
                    $_POST['devhire_application_details_nonce']
                )
            ),
            'devhire_save_application_details'
        )
    ) {
        return;
    }


    if (
        defined('DOING_AUTOSAVE') &&
        DOING_AUTOSAVE
    ) {
        return;
    }


    if (!current_user_can('edit_post', $post_id)) {
        return;
    }


    if (
        get_post_type($post_id) !==
        'job_application'
    ) {
        return;
    }


    if (!isset($_POST['devhire_application_status'])) {
        return;
    }


    $status = sanitize_text_field(
        wp_unslash(
            $_POST['devhire_application_status']
        )
    );


    $allowed_statuses = [
        'New',
        'Reviewing',
        'Interview',
        'Hired',
        'Rejected',
    ];


    if (!in_array(
        $status,
        $allowed_statuses,
        true
    )) {
        return;
    }


    update_post_meta(
        $post_id,
        '_devhire_application_status',
        $status
    );
}

add_action(
    'save_post_job_application',
    'devhire_save_application_details'
);

/**
 * Application status filter.
 */
function devhire_application_status_filter() {

    global $typenow;

    if ($typenow !== 'job_application') {
        return;
    }


    $selected = isset($_GET['application_status'])
        ? sanitize_text_field(
            wp_unslash(
                $_GET['application_status']
            )
        )
        : '';


    $statuses = [
        'New',
        'Reviewing',
        'Interview',
        'Hired',
        'Rejected',
    ];
    ?>

    <select name="application_status">

        <option value="">
            All Statuses
        </option>

        <?php foreach ($statuses as $status) : ?>

            <option
                value="<?php echo esc_attr($status); ?>"
                <?php selected($selected, $status); ?>
            >
                <?php echo esc_html($status); ?>
            </option>

        <?php endforeach; ?>

    </select>

    <?php
}

add_action(
    'restrict_manage_posts',
    'devhire_application_status_filter'
);

/**
 * Filter applications by status.
 */
function devhire_filter_applications_by_status($query) {

    if (
        !is_admin() ||
        !$query->is_main_query()
    ) {
        return;
    }


    $post_type = $query->get('post_type');

    if ($post_type !== 'job_application') {
        return;
    }


    if (empty($_GET['application_status'])) {
        return;
    }


    $status = sanitize_text_field(
        wp_unslash(
            $_GET['application_status']
        )
    );


    $allowed_statuses = [
        'New',
        'Reviewing',
        'Interview',
        'Hired',
        'Rejected',
    ];


    if (!in_array(
        $status,
        $allowed_statuses,
        true
    )) {
        return;
    }


    $query->set(
        'meta_query',
        [
            [
                'key' =>
                    '_devhire_application_status',

                'value' => $status,

                'compare' => '=',
            ],
        ]
    );
}

add_action(
    'pre_get_posts',
    'devhire_filter_applications_by_status'
);

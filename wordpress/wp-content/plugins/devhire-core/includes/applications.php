<?php

if (!defined('ABSPATH')) {
    exit;
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

    ob_start();
    ?>

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
            $_GET['application'] === 'success'
        ) : ?>

            <div class="application-success">
                <strong>Application submitted!</strong>

                <p>
                    Thank you. Your application has been
                    received successfully.
                </p>
            </div>

        <?php endif; ?>


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
            enctype="multipart/form-data"
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

                <div class="application-field">

                    <label for="applicant_name">
                        Full Name *
                    </label>

                    <input
                        type="text"
                        name="name"
                        value="<?php echo esc_attr($name); ?>"
                        required
                    >

                </div>


                <div class="application-field">

                    <label for="applicant_email">
                        Email Address *
                    </label>

                    <input
                        type="email"
                        name="email"
                        value="<?php echo esc_attr($email); ?>"
                        required
                    >

                </div>


                <div class="application-field">

                    <label for="applicant_phone">
                        Phone
                    </label>

                    <input
                        type="text"
                        name="phone"
                        value="<?php echo esc_attr($phone); ?>"
                    >

                </div>


                <div class="application-field">

                    <label for="applicant_linkedin">
                        LinkedIn / Portfolio
                    </label>

                    <input
                        type="url"
                        name="linkedin"
                        value="<?php echo esc_attr($linkedin); ?>"
                    >

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

                        <label>
                            <input
                                type="checkbox"
                                name="use_profile_resume"
                                value="1"
                                checked
                            >
                            Use my profile resume
                        </label>

                    </div>

                <?php endif; ?>

                <div class="application-field application-full">

                    <label for="applicant_resume">
                        Resume *
                    </label>

                    <input
                        id="applicant_resume"
                        name="applicant_resume"
                        type="file"
                        accept=".pdf,.doc,.docx"
                    >

                    <small>
                        PDF, DOC or DOCX. Maximum 5 MB.
                    </small>

                </div>


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
                'application',
                'error',
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
     * Sanitize fields.
     */
    $name = isset($_POST['name'])
        ? sanitize_text_field(
            wp_unslash($_POST['name'])
        )
        : '';

    $email = isset($_POST['email'])
        ? sanitize_email(
            wp_unslash($_POST['email'])
        )
        : '';

    $phone = isset($_POST['phone'])
        ? sanitize_text_field(
            wp_unslash($_POST['phone'])
        )
        : '';

    $linkedin = isset($_POST['linkedin'])
        ? esc_url_raw(
            wp_unslash($_POST['linkedin'])
        )
        : '';

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
                'application',
                'error',
                $job_url
            )
        );

        exit;
    }


    /*
    * Resolve application resume.
    */
    $resume_url = '';

    $use_profile_resume =
        isset($_POST['use_profile_resume']) &&
        sanitize_text_field(
            wp_unslash($_POST['use_profile_resume'])
        ) === '1';


    /*
    * Use the logged-in candidate's profile resume.
    */
    if (
        $use_profile_resume &&
        is_user_logged_in()
    ) {

        $profile_resume_id = absint(
            get_user_meta(
                get_current_user_id(),
                '_devhire_resume_id',
                true
            )
        );

        if ($profile_resume_id) {

            $profile_resume_url =
                wp_get_attachment_url(
                    $profile_resume_id
                );

            if ($profile_resume_url) {
                $resume_url =
                    $profile_resume_url;
            }
        }
    }


    /*
    * If no profile resume was selected,
    * process a newly uploaded resume.
    */
    if (!$resume_url) {

        if (
            empty($_FILES['applicant_resume']) ||
            !isset(
                $_FILES['applicant_resume']['error']
            ) ||
            $_FILES['applicant_resume']['error']
                !== UPLOAD_ERR_OK
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


        $resume =
            $_FILES['applicant_resume'];


        /*
        * Maximum 5 MB.
        */
        if (
            $resume['size'] >
            5 * 1024 * 1024
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


        $allowed_mimes = [
            'pdf' =>
                'application/pdf',

            'doc' =>
                'application/msword',

            'docx' =>
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];


        $file_check =
            wp_check_filetype_and_ext(
                $resume['tmp_name'],
                $resume['name'],
                $allowed_mimes
            );


        if (
            empty($file_check['ext']) ||
            empty($file_check['type'])
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


        require_once ABSPATH .
            'wp-admin/includes/file.php';


        $uploaded_file =
            wp_handle_upload(
                $resume,
                [
                    'test_form' => false,
                    'mimes'     => $allowed_mimes,
                ]
            );


        if (
            isset($uploaded_file['error']) ||
            empty($uploaded_file['url'])
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


        $resume_url =
            $uploaded_file['url'];
    }


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

    if (is_user_logged_in()) {

        update_post_meta(
            $application_id,
            '_devhire_candidate_user',
            get_current_user_id()
        );

    }

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
        '_devhire_application_status',
        'New'
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

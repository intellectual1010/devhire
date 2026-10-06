<?php

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Candidate Profile Shortcode
 */
function devhire_candidate_profile_shortcode() {

    $redirect_to = isset($_REQUEST['redirect_to'])
        ? wp_validate_redirect(
            esc_url_raw(
                wp_unslash($_REQUEST['redirect_to'])
            ),
            home_url('/candidate-dashboard/')
        )
        : home_url('/candidate-dashboard/');

    $application_error = isset($_REQUEST['application_error'])
        ? sanitize_key(
            wp_unslash($_REQUEST['application_error'])
        )
        : '';

    if (
        $application_error === 'resume_required' &&
        $redirect_to !== home_url('/candidate-dashboard/')
    ) {
        $redirect_job_id = url_to_postid($redirect_to);

        if (
            !$redirect_job_id ||
            get_post_type($redirect_job_id) !== 'job' ||
            get_post_status($redirect_job_id) !== 'publish'
        ) {
            $redirect_to = home_url('/candidate-dashboard/');
        }
    }

    if (!is_user_logged_in()) {

        return sprintf(
            '<div class="candidate-login-required">
                <h2>Candidate Profile</h2>
                <p>Please sign in to manage your profile.</p>
                <a class="primary-button" href="%s">
                    Sign In
                </a>
            </div>',
            esc_url(home_url('/candidate-login/'))
        );
    }


    $user_id = get_current_user_id();
    $user    = wp_get_current_user();

    if (!in_array('candidate', (array) $user->roles, true)) {

        return sprintf(
            '<div class="candidate-login-required">
                <h2>Candidate Account Required</h2>
                <p>
                    Employer and administrator accounts cannot access
                    or edit candidate profiles.
                </p>
                <a class="primary-button" href="%s">
                    Sign Out and Use Candidate Account
                </a>
            </div>',
            esc_url(
                wp_logout_url(
                    home_url('/candidate-login/')
                )
            )
        );
    }

    $message = '';


    /**
     * Save profile.
     */
    if (
        isset($_POST['devhire_profile_nonce']) &&
        wp_verify_nonce(
            sanitize_text_field(
                wp_unslash($_POST['devhire_profile_nonce'])
            ),
            'devhire_save_candidate_profile'
        )
    ) {

        $full_name = isset($_POST['full_name'])
            ? sanitize_text_field(
                wp_unslash($_POST['full_name'])
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

        $location = isset($_POST['location'])
            ? sanitize_text_field(
                wp_unslash($_POST['location'])
            )
            : '';

        $professional_title =
            isset($_POST['professional_title'])
                ? sanitize_text_field(
                    wp_unslash(
                        $_POST['professional_title']
                    )
                )
                : '';

        $bio = isset($_POST['bio'])
            ? sanitize_textarea_field(
                wp_unslash($_POST['bio'])
            )
            : '';


        /**
         * Update WordPress user.
         */
        wp_update_user([
            'ID'           => $user_id,
            'display_name' => $full_name,
            'first_name'   => $full_name,
        ]);


        /**
         * Save candidate profile fields.
         */
        update_user_meta(
            $user_id,
            '_devhire_phone',
            $phone
        );

        update_user_meta(
            $user_id,
            '_devhire_linkedin',
            $linkedin
        );

        update_user_meta(
            $user_id,
            '_devhire_location',
            $location
        );

        update_user_meta(
            $user_id,
            '_devhire_professional_title',
            $professional_title
        );

        update_user_meta(
            $user_id,
            '_devhire_bio',
            $bio
        );

        /**
         * Resume upload.
         */
        if (
            !empty($_FILES['resume']['name']) &&
            isset($_FILES['resume']['tmp_name'])
        ) {

            $file = $_FILES['resume'];

            $allowed_types = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ];

            $max_size = 5 * 1024 * 1024;

            $upload_error = isset($file['error'])
                ? absint($file['error'])
                : UPLOAD_ERR_NO_FILE;


            if ($upload_error !== UPLOAD_ERR_OK) {

                switch ($upload_error) {

                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $upload_error_message =
                            'Resume exceeds the server upload size limit.';
                        break;

                    case UPLOAD_ERR_PARTIAL:
                        $upload_error_message =
                            'Resume upload was interrupted. Please try again.';
                        break;

                    case UPLOAD_ERR_NO_TMP_DIR:
                    case UPLOAD_ERR_CANT_WRITE:
                    case UPLOAD_ERR_EXTENSION:
                        $upload_error_message =
                            'The server could not save the resume. Please try again.';
                        break;

                    default:
                        $upload_error_message =
                            'Resume upload failed. Please try again.';
                        break;
                }

                $message = sprintf(
                    '<div class="devhire-notice error">%s</div>',
                    esc_html($upload_error_message)
                );

            } elseif ($file['size'] > $max_size) {

                $message =
                    '<div class="devhire-notice error">
                        Resume must be smaller than 5MB.
                    </div>';

            } else {

                $allowed_extensions = [
                    'pdf',
                    'doc',
                    'docx',
                ];

                $filename_extension = strtolower(
                    (string) pathinfo(
                        sanitize_file_name($file['name']),
                        PATHINFO_EXTENSION
                    )
                );

                $file_check = wp_check_filetype_and_ext(
                    $file['tmp_name'],
                    $file['name']
                );

                if (
                    !$filename_extension ||
                    !in_array(
                        $filename_extension,
                        $allowed_extensions,
                        true
                    ) ||
                    empty($file_check['type']) ||
                    empty($file_check['ext']) ||
                    !in_array(
                        strtolower($file_check['ext']),
                        $allowed_extensions,
                        true
                    ) ||
                    !in_array(
                        $file_check['type'],
                        $allowed_types,
                        true
                    )
                ) {

                    $message =
                        '<div class="devhire-notice error">
                            Resume must be a PDF, DOC, or DOCX file.
                        </div>';

                } else {

                    require_once ABSPATH .
                        'wp-admin/includes/file.php';

                    require_once ABSPATH .
                        'wp-admin/includes/media.php';

                    require_once ABSPATH .
                        'wp-admin/includes/image.php';


                    $attachment_id = media_handle_upload(
                        'resume',
                        0
                    );


                    if (is_wp_error($attachment_id)) {

                        $message =
                            '<div class="devhire-notice error">
                                Resume upload failed.
                            </div>';

                    } else {

                        $old_resume_id = absint(
                            get_user_meta(
                                $user_id,
                                '_devhire_resume_id',
                                true
                            )
                        );


                        update_user_meta(
                            $user_id,
                            '_devhire_resume_id',
                            $attachment_id
                        );


                        /*
                        * Remove old resume after successful replacement.
                        */
                        if (
                            $old_resume_id &&
                            $old_resume_id !== $attachment_id
                        ) {
                            wp_delete_attachment(
                                $old_resume_id,
                                true
                            );
                        }


                        if (
                            $application_error === 'resume_required' &&
                            $redirect_to !== home_url('/candidate-dashboard/')
                        ) {
                            wp_safe_redirect($redirect_to);
                            exit;
                        }

                        $message =
                            '<div class="devhire-notice success">
                                Profile and resume updated successfully.
                            </div>';
                    }
                }
            }

        } // <-- closes the resume upload if


        /*
         * Remove the current resume when explicitly requested.
         * Do not run this when a new resume was uploaded in the same request.
         */
        $remove_resume = isset($_POST['remove_resume']) &&
            sanitize_text_field(
                wp_unslash($_POST['remove_resume'])
            ) === '1';

        $uploaded_new_resume =
            !empty($_FILES['resume']['name']);

        if ($remove_resume && !$uploaded_new_resume) {

            $current_resume_id = absint(
                get_user_meta(
                    $user_id,
                    '_devhire_resume_id',
                    true
                )
            );

            if ($current_resume_id) {
                wp_delete_attachment(
                    $current_resume_id,
                    true
                );
            }

            delete_user_meta(
                $user_id,
                '_devhire_resume_id'
            );

            if ($application_error === 'resume_required') {
                $message =
                    '<div class="devhire-notice error">
                        Your resume was removed, but a resume is required
                        before you can apply for this job.
                    </div>';
            } else {
                $message =
                    '<div class="devhire-notice success">
                        Resume removed successfully.
                    </div>';
            }
        }


        /*
        * Only show the normal success message
        * when no resume-specific message was set.
        */
        if (!$message) {

            $message =
                '<div class="devhire-notice success">
                    Profile updated successfully.
                </div>';
        }


        /*
        * Refresh user object after update.
        */
        $user = wp_get_current_user();
    }


    /**
     * Current values.
     */
    $full_name =
        $user->display_name;

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

    $location = get_user_meta(
        $user_id,
        '_devhire_location',
        true
    );

    $professional_title = get_user_meta(
        $user_id,
        '_devhire_professional_title',
        true
    );

    $bio = get_user_meta(
        $user_id,
        '_devhire_bio',
        true
    );

    $resume_id = absint(
        get_user_meta(
            $user_id,
            '_devhire_resume_id',
            true
        )
    );

    $resume_url = $resume_id
        ? wp_get_attachment_url($resume_id)
        : '';

    if ($resume_id && !$resume_url) {
        delete_user_meta(
            $user_id,
            '_devhire_resume_id'
        );

        $resume_id = 0;
    }

    $resume_name = $resume_id
        ? basename((string) get_attached_file($resume_id))
        : '';

    ob_start();

    echo wp_kses_post($message);

    if (
        $application_error === 'resume_required' &&
        !$resume_url
    ) {
        ?>
        <div class="devhire-notice error">
            A resume is required before you can apply for this job.
            Upload your resume below, then save your profile.
        </div>
        <?php
    } elseif (
        $application_error === 'resume_required' &&
        $resume_url
    ) {
        ?>
        <div class="devhire-notice success">
            Your resume is ready.
            <a href="<?php echo esc_url($redirect_to); ?>">
                Return to the job
            </a>
        </div>
        <?php
    }
    ?>

    <div class="candidate-profile">

        <div class="profile-header">

            <div>

                <span class="hero-badge">
                    Candidate Portal
                </span>

                <h1>
                    My Profile
                </h1>

                <p>
                    Keep your professional information
                    up to date.
                </p>

            </div>

        </div>

        <nav class="candidate-dashboard-nav">

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/candidate-dashboard/')
                ); ?>"
            >
                My Applications
            </a>

            <a
                class="candidate-nav-link active"
                href="<?php echo esc_url(
                    home_url('/candidate-profile/')
                ); ?>"
            >
                My Profile
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    get_post_type_archive_link('job')
                ); ?>"
            >
                Browse Jobs
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    wp_logout_url(
                        home_url('/candidate-login/')
                    )
                ); ?>"
            >
                Sign Out
            </a>

        </nav>

        <form
            method="post"
            enctype="multipart/form-data"
            class="candidate-profile-form"
        >

            <?php
            wp_nonce_field(
                'devhire_save_candidate_profile',
                'devhire_profile_nonce'
            );
            ?>

            <input
                type="hidden"
                name="redirect_to"
                value="<?php echo esc_attr($redirect_to); ?>"
            >

            <input
                type="hidden"
                name="application_error"
                value="<?php echo esc_attr($application_error); ?>"
            >


            <div class="form-field">

                <label for="profile-name">
                    Full Name
                </label>

                <input
                    id="profile-name"
                    name="full_name"
                    type="text"
                    value="<?php
                    echo esc_attr($full_name);
                    ?>"
                    required
                >

            </div>


            <div class="form-field">

                <label for="profile-email">
                    Email
                </label>

                <input
                    id="profile-email"
                    type="email"
                    value="<?php
                    echo esc_attr($user->user_email);
                    ?>"
                    disabled
                >

                <small>
                    Your account email cannot be changed here.
                </small>

            </div>


            <div class="form-field">

                <label for="profile-title">
                    Professional Title
                </label>

                <input
                    id="profile-title"
                    name="professional_title"
                    type="text"
                    placeholder="e.g. Full Stack Developer"
                    value="<?php
                    echo esc_attr(
                        $professional_title
                    );
                    ?>"
                >

            </div>


            <div class="form-field">

                <label for="profile-phone">
                    Phone
                </label>

                <input
                    id="profile-phone"
                    name="phone"
                    type="text"
                    value="<?php
                    echo esc_attr($phone);
                    ?>"
                >

            </div>


            <div class="form-field">

                <label for="profile-location">
                    Location
                </label>

                <input
                    id="profile-location"
                    name="location"
                    type="text"
                    placeholder="e.g. Philippines"
                    value="<?php
                    echo esc_attr($location);
                    ?>"
                >

            </div>


            <div class="form-field">

                <label for="profile-linkedin">
                    LinkedIn
                </label>

                <input
                    id="profile-linkedin"
                    name="linkedin"
                    type="url"
                    placeholder="https://linkedin.com/in/..."
                    value="<?php
                    echo esc_attr($linkedin);
                    ?>"
                >

            </div>


            <div class="form-field">

                <label for="profile-bio">
                    Professional Bio
                </label>

                <textarea
                    id="profile-bio"
                    name="bio"
                    rows="6"
                    placeholder="Tell employers about your experience..."
                ><?php
                    echo esc_textarea($bio);
                ?></textarea>

            </div>

            <div class="form-field">

                <label for="profile-resume">
                    Resume
                </label>

                <?php if ($resume_url) : ?>

                    <div class="current-resume">

                        <span>
                            Current Resume:
                        </span>

                        <a
                            href="<?php echo esc_url($resume_url); ?>"
                            target="_blank"
                            rel="noopener"
                        >
                            <?php echo esc_html($resume_name); ?>
                        </a>

                    </div>

                <?php endif; ?>


                <input
                    id="profile-resume"
                    name="resume"
                    type="file"
                    accept=".pdf,.doc,.docx"
                >

                <small>
                    PDF, DOC or DOCX. Maximum 5MB.
                    Uploading a new resume replaces the existing one.
                </small>

                <?php if (
                    $resume_url &&
                    $application_error !== 'resume_required'
                ) : ?>

                    <label class="resume-remove-option">
                        <input
                            type="checkbox"
                            name="remove_resume"
                            value="1"
                        >
                        Remove current resume
                    </label>

                <?php endif; ?>

            </div>

            <button
                type="submit"
                class="primary-button"
            >
                Save Profile
            </button>

        </form>

    </div>

    <?php

    return ob_get_clean();
}


add_shortcode(
    'devhire_candidate_profile',
    'devhire_candidate_profile_shortcode'
);
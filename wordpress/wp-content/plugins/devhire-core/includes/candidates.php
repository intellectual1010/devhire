<?php

if (!defined('ABSPATH')) {
    exit;
}


/**
 * ============================================================
 * Candidate Role
 * ============================================================
 */

function devhire_register_candidate_role() {

    if (!get_role('candidate')) {

        add_role(
            'candidate',
            'Candidate',
            [
                'read' => true,
            ]
        );
    }
}

add_action(
    'init',
    'devhire_register_candidate_role'
);


/**
 * ============================================================
 * Registration Error Redirect
 * ============================================================
 */

function devhire_registration_redirect($error) {

    $url = add_query_arg(
        'registration_error',
        sanitize_key($error),
        home_url('/candidate-register/')
    );

    wp_safe_redirect($url);
    exit;
}


/**
 * ============================================================
 * Candidate Registration Handler
 * ============================================================
 */

function devhire_handle_candidate_registration() {

    /*
     * Verify nonce.
     */
    if (
        !isset($_POST['devhire_register_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(
                wp_unslash($_POST['devhire_register_nonce'])
            ),
            'devhire_candidate_register'
        )
    ) {
        wp_die('Invalid registration request.');
    }


    /*
     * Read and sanitize fields.
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

    $password = isset($_POST['password'])
        ? (string) wp_unslash($_POST['password'])
        : '';


    /*
     * Validate fields.
     */
    if (!$name || !$email || !$password) {

        devhire_registration_redirect(
            'missing_fields'
        );
    }


    if (!is_email($email)) {

        devhire_registration_redirect(
            'invalid_email'
        );
    }


    if (email_exists($email)) {

        devhire_registration_redirect(
            'email_exists'
        );
    }


    if (strlen($password) < 8) {

        devhire_registration_redirect(
            'weak_password'
        );
    }


    /*
     * Generate unique username from email.
     */
    $email_parts = explode('@', $email);

    $base_username = sanitize_user(
        $email_parts[0],
        true
    );


    if (!$base_username) {
        $base_username = 'candidate';
    }


    $username = $base_username;
    $counter  = 1;


    while (username_exists($username)) {

        $username =
            $base_username . $counter;

        $counter++;
    }


    /*
     * Create candidate.
     */
    $user_id = wp_insert_user([
        'user_login'   => $username,
        'user_pass'    => $password,
        'user_email'   => $email,
        'display_name' => $name,
        'first_name'   => $name,
        'role'         => 'candidate',
    ]);


    if (is_wp_error($user_id)) {

        devhire_registration_redirect(
            'registration_failed'
        );
    }


    /*
     * Automatically log candidate in.
     */
    wp_set_current_user($user_id);

    wp_set_auth_cookie(
        $user_id,
        true
    );


    /*
     * Return to the requested page after registration.
     */
    $redirect_to = isset($_POST['redirect_to'])
        ? wp_validate_redirect(
            esc_url_raw(
                wp_unslash($_POST['redirect_to'])
            ),
            home_url('/candidate-dashboard/')
        )
        : home_url('/candidate-dashboard/');

    wp_safe_redirect($redirect_to);

    exit;
}


/*
 * Registration for logged-out users.
 */
add_action(
    'admin_post_nopriv_devhire_candidate_register',
    'devhire_handle_candidate_registration'
);


/*
 * Registration for logged-in users.
 */
add_action(
    'admin_post_devhire_candidate_register',
    'devhire_handle_candidate_registration'
);


/**
 * ============================================================
 * Candidate Registration Shortcode
 * ============================================================
 */

function devhire_candidate_register_shortcode() {

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

    /*
     * Already logged in.
     */
    if (is_user_logged_in()) {

        $current_user = wp_get_current_user();

        if (in_array('candidate', (array) $current_user->roles, true)) {

            return sprintf(
                '<div class="devhire-notice success">
                    You already have a candidate account.
                    <a href="%s">Continue</a>
                </div>',
                esc_url($redirect_to)
            );
        }

        return sprintf(
            '<div class="devhire-notice error">
                You are currently signed in with a non-candidate account.
                <a href="%s">
                    Log out and create a candidate account
                </a>
            </div>',
            esc_url(
                wp_logout_url(
                    add_query_arg(
                        'redirect_to',
                        $redirect_to,
                        home_url('/candidate-register/')
                    )
                )
            )
        );
    }


    /*
     * Registration error message.
     */
    $message = '';

    $error = isset($_GET['registration_error'])
        ? sanitize_key(
            wp_unslash(
                $_GET['registration_error']
            )
        )
        : '';


    switch ($error) {

        case 'missing_fields':

            $message =
                'Please complete all fields.';

            break;


        case 'invalid_email':

            $message =
                'Please enter a valid email address.';

            break;


        case 'email_exists':

            $message =
                'An account already exists with this email.';

            break;


        case 'weak_password':

            $message =
                'Password must contain at least 8 characters.';

            break;


        case 'registration_failed':

            $message =
                'Registration failed. Please try again.';

            break;
    }


    ob_start();

    if ($application_error === 'login_required') {
        ?>
        <div class="devhire-notice error">
            Create a candidate account to apply for this job.
            After registration, you will return to the job automatically.
        </div>
        <?php
    }
    ?>

    <div class="candidate-auth">

        <h1>
            Create Candidate Account
        </h1>

        <p>
            Create an account to track your
            DevHire applications.
        </p>


        <?php if ($message) : ?>

            <div class="devhire-notice error">

                <?php
                echo esc_html($message);
                ?>

            </div>

        <?php endif; ?>


        <form
            method="post"
            action="<?php
            echo esc_url(
                admin_url('admin-post.php')
            );
            ?>"
        >

            <input
                type="hidden"
                name="action"
                value="devhire_candidate_register"
            >


            <?php
            wp_nonce_field(
                'devhire_candidate_register',
                'devhire_register_nonce'
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

                <label for="candidate-name">
                    Full Name
                </label>

                <input
                    id="candidate-name"
                    name="name"
                    type="text"
                    autocomplete="name"
                    required
                >

            </div>


            <div class="form-field">

                <label for="candidate-email">
                    Email
                </label>

                <input
                    id="candidate-email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    required
                >

            </div>


            <div class="form-field">

                <label for="candidate-password">
                    Password
                </label>

                <input
                    id="candidate-password"
                    name="password"
                    type="password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

            </div>


            <button
                type="submit"
                class="primary-button"
            >
                Create Account
            </button>

        </form>


        <p class="auth-switch">

            Already registered?

            <a href="<?php
            echo esc_url(
                add_query_arg(
                    [
                        'redirect_to'       => $redirect_to,
                        'application_error' => $application_error,
                    ],
                    home_url('/candidate-login/')
                )
            );
            ?>">
                Sign in
            </a>

        </p>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode(
    'devhire_candidate_register',
    'devhire_candidate_register_shortcode'
);


/**
 * ============================================================
 * Candidate Login
 * ============================================================
 */

function devhire_candidate_login_shortcode() {

    $redirect_to = isset($_REQUEST['redirect_to'])
        ? wp_validate_redirect(
            esc_url_raw(
                wp_unslash($_REQUEST['redirect_to'])
            ),
            home_url('/candidate-dashboard/')
        )
        : home_url('/candidate-dashboard/');

    if (is_user_logged_in()) {

        $current_user = wp_get_current_user();

        if (in_array('candidate', (array) $current_user->roles, true)) {

            return sprintf(
                '<div class="devhire-notice success">
                    You are logged in.
                    <a href="%s">Continue</a>
                </div>',
                esc_url($redirect_to)
            );
        }

        return sprintf(
            '<div class="devhire-notice error">
                This page requires a candidate account.
                <a href="%s">View Candidate Login</a>
            </div>',
            esc_url(
                wp_logout_url(
                    add_query_arg(
                        'redirect_to',
                        $redirect_to,
                        home_url('/candidate-login/')
                    )
                )
            )
        );
    }


    $error = '';

    $application_error = isset($_REQUEST['application_error'])
        ? sanitize_key(
            wp_unslash($_REQUEST['application_error'])
        )
        : '';


    if (
        isset($_POST['devhire_login_nonce']) &&
        wp_verify_nonce(
            sanitize_text_field(
                wp_unslash(
                    $_POST['devhire_login_nonce']
                )
            ),
            'devhire_candidate_login'
        )
    ) {

        $email = isset($_POST['email'])
            ? sanitize_email(
                wp_unslash($_POST['email'])
            )
            : '';

        $password = isset($_POST['password'])
            ? (string) wp_unslash(
                $_POST['password']
            )
            : '';


        $user = get_user_by(
            'email',
            $email
        );


        if ($user) {

            $credentials = [
                'user_login'    =>
                    $user->user_login,

                'user_password' =>
                    $password,

                'remember'      =>
                    true,
            ];


            $login = wp_signon(
                $credentials,
                is_ssl()
            );


            if (!is_wp_error($login)) {

                wp_safe_redirect($redirect_to);

                exit;
            }
        }


        $error =
            '<div class="devhire-notice error">
                Invalid email or password.
            </div>';
    }


    ob_start();

    echo wp_kses_post($error);

    if ($application_error === 'login_required') {
        ?>
        <div class="devhire-notice error">
            Please sign in with your candidate account to apply for this job.
            After signing in, you will return to the job automatically.
        </div>
        <?php
    }
    ?>

    <div class="candidate-auth">

        <h1>
            Candidate Login
        </h1>

        <p>
            Sign in to track your applications.
        </p>


        <form method="post">

            <?php
            wp_nonce_field(
                'devhire_candidate_login',
                'devhire_login_nonce'
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

                <label for="login-email">
                    Email
                </label>

                <input
                    id="login-email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    required
                >

            </div>


            <div class="form-field">

                <label for="login-password">
                    Password
                </label>

                <input
                    id="login-password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                >

            </div>


            <button
                type="submit"
                class="primary-button"
            >
                Sign In
            </button>

        </form>


        <p class="auth-switch">

            Don't have an account?

            <a href="<?php
            echo esc_url(
                add_query_arg(
                    [
                        'redirect_to'       => $redirect_to,
                        'application_error' => $application_error,
                    ],
                    home_url('/candidate-register/')
                )
            );
            ?>">
                Create account
            </a>

        </p>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode(
    'devhire_candidate_login',
    'devhire_candidate_login_shortcode'
);


/**
 * ============================================================
 * Candidate Dashboard
 * ============================================================
 */

function devhire_candidate_dashboard_shortcode() {

    if (!is_user_logged_in()) {

        return sprintf(
            '<div class="candidate-login-required">

                <h2>
                    Candidate Dashboard
                </h2>

                <p>
                    Please sign in to view your applications.
                </p>

                <a
                    class="primary-button"
                    href="%s"
                >
                    Sign In
                </a>

            </div>',
            esc_url(
                home_url('/candidate-login/')
            )
        );
    }


    $user = wp_get_current_user();


    /*
     * Candidate profile summary.
     */
    $candidate_phone = sanitize_text_field(
        get_user_meta(
            $user->ID,
            '_devhire_phone',
            true
        )
    );

    $candidate_linkedin = esc_url_raw(
        get_user_meta(
            $user->ID,
            '_devhire_linkedin',
            true
        )
    );

    $candidate_location = sanitize_text_field(
        get_user_meta(
            $user->ID,
            '_devhire_location',
            true
        )
    );

    $candidate_title = sanitize_text_field(
        get_user_meta(
            $user->ID,
            '_devhire_professional_title',
            true
        )
    );

    $candidate_bio = sanitize_textarea_field(
        get_user_meta(
            $user->ID,
            '_devhire_bio',
            true
        )
    );

    $candidate_resume_id = absint(
        get_user_meta(
            $user->ID,
            '_devhire_resume_id',
            true
        )
    );

    $candidate_resume_url = $candidate_resume_id
        ? wp_get_attachment_url($candidate_resume_id)
        : '';

    /*
     * Calculate profile completeness from the fields used
     * by the DevHire candidate profile.
     */
    $profile_fields = [
        trim((string) $user->display_name),
        trim((string) $user->user_email),
        $candidate_phone,
        $candidate_linkedin,
        $candidate_location,
        $candidate_title,
        $candidate_bio,
        $candidate_resume_url,
    ];

    $completed_profile_fields = 0;

    foreach ($profile_fields as $profile_field) {
        if (!empty($profile_field)) {
            $completed_profile_fields++;
        }
    }

    $profile_completeness = (int) round(
        ($completed_profile_fields / count($profile_fields)) * 100
    );

    $profile_is_complete =
        $profile_completeness === 100;


    /*
     * Find applications belonging to this candidate.
     *
     * We support:
     * 1. Direct user relationship.
     * 2. Email relationship for older applications.
     */
    $applications = new WP_Query([
        'post_type'      => 'job_application',
        'post_status'    => 'private',
        'posts_per_page' => -1,

        'meta_query' => [
            'relation' => 'OR',

            [
                'key'     =>
                    '_devhire_candidate_user',

                'value'   =>
                    $user->ID,

                'compare' =>
                    '=',

                'type'    =>
                    'NUMERIC',
            ],

            [
                'key'     =>
                    '_devhire_applicant_email',

                'value'   =>
                    $user->user_email,

                'compare' =>
                    '=',
            ],
        ],

        'orderby' => 'date',
        'order'   => 'DESC',
    ]);

    /*
    * Candidate application statistics.
    */
    $total_applications = $applications->found_posts;

    $reviewing_count = 0;
    $interview_count = 0;
    $hired_count = 0;

    foreach ($applications->posts as $application) {

        $application_status = get_post_meta(
            $application->ID,
            '_devhire_application_status',
            true
        );

        switch ($application_status) {

            case 'Reviewing':
                $reviewing_count++;
                break;

            case 'Interview':
                $interview_count++;
                break;

            case 'Hired':
                $hired_count++;
                break;
        }
    }

    ob_start();
    ?>

    <div class="candidate-dashboard">

        <div class="dashboard-header">

            <div>

                <span class="hero-badge">
                    Candidate Portal
                </span>

                <h1>

                    Welcome,

                    <?php
                    echo esc_html(
                        $user->display_name
                    );
                    ?>

                </h1>

                <p>
                    Track your submitted job
                    applications.
                </p>

            </div>

        </div>

        <nav class="candidate-dashboard-nav">

            <a
                class="candidate-nav-link active"
                href="<?php echo esc_url(
                    home_url('/candidate-dashboard/')
                ); ?>"
            >
                My Applications
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/candidate-profile/')
                ); ?>"
            >
                My Profile
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/saved-jobs/')
                ); ?>"
            >
                Saved Jobs
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

        <section class="candidate-account-summary">

            <div class="candidate-account-summary-main">

                <div class="candidate-account-avatar" aria-hidden="true">
                    <?php
                    echo esc_html(
                        strtoupper(
                            substr(
                                trim($user->display_name)
                                    ? $user->display_name
                                    : $user->user_login,
                                0,
                                1
                            )
                        )
                    );
                    ?>
                </div>

                <div class="candidate-account-details">

                    <span class="application-label">
                        Candidate Profile
                    </span>

                    <h2>
                        <?php
                        echo esc_html(
                            $candidate_title
                                ? $candidate_title
                                : 'Add your professional title'
                        );
                        ?>
                    </h2>

                    <div class="candidate-account-meta">

                        <span>
                            <?php
                            echo esc_html(
                                $candidate_location
                                    ? $candidate_location
                                    : 'Location not added'
                            );
                            ?>
                        </span>

                        <span>
                            <?php
                            echo $candidate_resume_url
                                ? 'Resume uploaded'
                                : 'Resume missing';
                            ?>
                        </span>

                    </div>

                </div>

            </div>

            <div class="candidate-profile-completeness">

                <div class="candidate-profile-completeness-heading">

                    <span>
                        Profile completeness
                    </span>

                    <strong>
                        <?php
                        echo esc_html(
                            $profile_completeness . '%'
                        );
                        ?>
                    </strong>

                </div>

                <div
                    class="candidate-profile-progress"
                    role="progressbar"
                    aria-label="Candidate profile completeness"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="<?php
                    echo esc_attr($profile_completeness);
                    ?>"
                >
                    <span
                        style="<?php
                        echo esc_attr(
                            'width: ' .
                            $profile_completeness .
                            '%;'
                        );
                        ?>"
                    ></span>
                </div>

                <p>
                    <?php if ($profile_is_complete) : ?>

                        Your profile is ready for applications.

                    <?php else : ?>

                        Complete your profile to give employers
                        more information about your experience.

                    <?php endif; ?>
                </p>

                <a
                    class="secondary-button"
                    href="<?php echo esc_url(
                        home_url('/candidate-profile/')
                    ); ?>"
                >
                    <?php
                    echo $profile_is_complete
                        ? 'View Profile'
                        : 'Complete Profile';
                    ?>
                </a>

            </div>

        </section>

        <div class="candidate-saved-jobs-summary">

            <div>
                <span class="application-label">
                    Job Shortlist
                </span>

                <h2>
                    <span
                        data-devhire-saved-jobs-count
                    >0</span>
                    Saved Jobs
                </h2>

                <p>
                    Keep interesting opportunities in one place
                    and return when you're ready to apply.
                </p>
            </div>

            <a
                class="secondary-button"
                href="<?php echo esc_url(
                    home_url('/saved-jobs/')
                ); ?>"
            >
                View Saved Jobs
            </a>

        </div>

        <div class="dashboard-stats">

            <div class="dashboard-stat">
                <span>Total Applications</span>

                <strong>
                    <?php echo esc_html($total_applications); ?>
                </strong>
            </div>

            <div class="dashboard-stat">
                <span>Reviewing</span>

                <strong>
                    <?php echo esc_html($reviewing_count); ?>
                </strong>
            </div>

            <div class="dashboard-stat">
                <span>Interviews</span>

                <strong>
                    <?php echo esc_html($interview_count); ?>
                </strong>
            </div>

            <div class="dashboard-stat">
                <span>Hired</span>

                <strong>
                    <?php echo esc_html($hired_count); ?>
                </strong>
            </div>

        </div>


        <div class="application-list">

            <?php
            if ($applications->have_posts()) :

                while (
                    $applications->have_posts()
                ) :

                    $applications->the_post();


                    $application_id =
                        get_the_ID();


                    $job_id = absint(
                        get_post_meta(
                            $application_id,
                            '_devhire_application_job',
                            true
                        )
                    );


                    $status = get_post_meta(
                        $application_id,
                        '_devhire_application_status',
                        true
                    );


                    if (!$status) {
                        $status = 'New';
                    }


                    $status_class =
                        'status-' .
                        sanitize_html_class(
                            strtolower(
                                str_replace(
                                    ' ',
                                    '-',
                                    $status
                                )
                            )
                        );
                    ?>

                    <article class="application-card">

                        <div>

                            <span class="application-label">
                                Applied for
                            </span>


                            <h2>

                                <?php if ($job_id) : ?>

                                    <a href="<?php
                                    echo esc_url(
                                        get_permalink(
                                            $job_id
                                        )
                                    );
                                    ?>">

                                        <?php
                                        echo esc_html(
                                            get_the_title(
                                                $job_id
                                            )
                                        );
                                        ?>

                                    </a>

                                <?php else : ?>

                                    Job unavailable

                                <?php endif; ?>

                            </h2>


                            <span class="application-date">

                                Applied

                                <?php
                                echo esc_html(
                                    get_the_date(
                                        'M j, Y'
                                    )
                                );
                                ?>

                            </span>

                        </div>


                        <span
                            class="<?php
                            echo esc_attr(
                                'application-status ' .
                                $status_class
                            );
                            ?>"
                        >

                            <?php
                            echo esc_html($status);
                            ?>

                        </span>

                    </article>

                    <?php

                endwhile;


                wp_reset_postdata();

            else :
                ?>

                <div class="no-applications">

                    <h2>
                        No applications yet
                    </h2>

                    <p>
                        Find a position you're
                        interested in and submit
                        your first application.
                    </p>


                    <a
                        class="primary-button"
                        href="<?php
                        echo esc_url(
                            get_post_type_archive_link(
                                'job'
                            )
                        );
                        ?>"
                    >
                        Browse Jobs
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode(
    'devhire_candidate_dashboard',
    'devhire_candidate_dashboard_shortcode'
);
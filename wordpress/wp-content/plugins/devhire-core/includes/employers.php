<?php

if (!defined('ABSPATH')) {
    exit;
}


/**
 * ============================================================
 * Employer Role
 * ============================================================
 */

function devhire_register_employer_role() {

    if (!get_role('employer')) {

        add_role(
            'employer',
            'Employer',
            [
                'read' => true,
            ]
        );
    }
}

add_action(
    'init',
    'devhire_register_employer_role'
);

/**
 * ============================================================
 * Employer Company Ownership
 * ============================================================
 */

/**
 * Get the company owned by an employer.
 *
 * The relationship is stored on the company post using
 * _devhire_company_owner. This keeps company ownership separate
 * from the employer's job ownership, which continues to use
 * WordPress post_author.
 */
function devhire_get_employer_company_id($user_id = 0) {

    $user_id = $user_id
        ? absint($user_id)
        : get_current_user_id();

    if (!$user_id) {
        return 0;
    }

    $company_ids = get_posts([
        'post_type'              => 'company',
        'post_status'            => ['publish', 'draft', 'pending', 'private'],
        'posts_per_page'         => 1,
        'fields'                 => 'ids',
        'orderby'                => 'ID',
        'order'                  => 'ASC',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'meta_query'             => [
            [
                'key'     => '_devhire_company_owner',
                'value'   => $user_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
        ],
    ]);

    return !empty($company_ids)
        ? (int) $company_ids[0]
        : 0;
}


/**
 * Check whether an employer owns a specific company.
 */
function devhire_employer_owns_company($company_id, $user_id = 0) {

    $company_id = absint($company_id);

    $user_id = $user_id
        ? absint($user_id)
        : get_current_user_id();

    if (!$company_id || !$user_id) {
        return false;
    }

    if (get_post_type($company_id) !== 'company') {
        return false;
    }

    $owner_id = (int) get_post_meta(
        $company_id,
        '_devhire_company_owner',
        true
    );

    return $owner_id === $user_id;
}


/**
 * Assign a company to an employer.
 *
 * A company can only be assigned when it does not already belong
 * to another employer. An employer may own only one company in
 * the current DevHire portal model.
 */
function devhire_assign_company_to_employer($company_id, $user_id = 0) {

    $company_id = absint($company_id);

    $user_id = $user_id
        ? absint($user_id)
        : get_current_user_id();

    if (!$company_id || !$user_id) {
        return false;
    }

    if (get_post_type($company_id) !== 'company') {
        return false;
    }

    $user = get_userdata($user_id);

    if (
        !$user ||
        !in_array('employer', (array) $user->roles, true)
    ) {
        return false;
    }

    $existing_company_id = devhire_get_employer_company_id($user_id);

    if (
        $existing_company_id &&
        $existing_company_id !== $company_id
    ) {
        return false;
    }

    $existing_owner_id = (int) get_post_meta(
        $company_id,
        '_devhire_company_owner',
        true
    );

    if (
        $existing_owner_id &&
        $existing_owner_id !== $user_id
    ) {
        return false;
    }

    update_post_meta(
        $company_id,
        '_devhire_company_owner',
        $user_id
    );

    return true;
}


/**
 * Synchronize every employer-owned job with the employer's company.
 * This also repairs older jobs created before company profiles existed.
 */
function devhire_sync_employer_jobs_to_company(
    $user_id = 0,
    $company_id = 0
) {
    $user_id = $user_id
        ? absint($user_id)
        : get_current_user_id();

    if (!$user_id) {
        return 0;
    }

    $company_id = $company_id
        ? absint($company_id)
        : devhire_get_employer_company_id($user_id);

    if (
        !$company_id ||
        !devhire_employer_owns_company(
            $company_id,
            $user_id
        )
    ) {
        return 0;
    }

    $job_ids = get_posts([
        'post_type'      => 'job',
        'post_status'    => [
            'publish',
            'draft',
            'pending',
            'private',
            'future',
        ],
        'author'         => $user_id,
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);

    $updated = 0;

    foreach ($job_ids as $job_id) {
        $current_company_id = (int) get_post_meta(
            $job_id,
            '_devhire_company',
            true
        );

        if ($current_company_id !== $company_id) {
            update_post_meta(
                $job_id,
                '_devhire_company',
                $company_id
            );

            $updated++;
        }
    }

    return $updated;
}


/**
 * ============================================================
 * Employer Registration
 * ============================================================
 */

function devhire_handle_employer_registration() {

    if (
        !isset($_POST['devhire_employer_register_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(
                wp_unslash($_POST['devhire_employer_register_nonce'])
            ),
            'devhire_employer_register'
        )
    ) {
        wp_die('Invalid registration request.');
    }

    $name = isset($_POST['name'])
        ? sanitize_text_field(wp_unslash($_POST['name']))
        : '';

    $email = isset($_POST['email'])
        ? sanitize_email(wp_unslash($_POST['email']))
        : '';

    $password = isset($_POST['password'])
        ? (string) wp_unslash($_POST['password'])
        : '';

    if (!$name || !$email || !$password) {
        wp_safe_redirect(
            add_query_arg(
                'registration_error',
                'missing_fields',
                home_url('/employer-register/')
            )
        );
        exit;
    }

    if (!is_email($email)) {
        wp_safe_redirect(
            add_query_arg(
                'registration_error',
                'invalid_email',
                home_url('/employer-register/')
            )
        );
        exit;
    }

    if (email_exists($email)) {
        wp_safe_redirect(
            add_query_arg(
                'registration_error',
                'email_exists',
                home_url('/employer-register/')
            )
        );
        exit;
    }

    if (strlen($password) < 8) {
        wp_safe_redirect(
            add_query_arg(
                'registration_error',
                'weak_password',
                home_url('/employer-register/')
            )
        );
        exit;
    }

    $email_parts = explode('@', $email);

    $base_username = sanitize_user(
        $email_parts[0],
        true
    );

    if (!$base_username) {
        $base_username = 'employer';
    }

    $username = $base_username;
    $counter  = 1;

    while (username_exists($username)) {
        $username = $base_username . $counter;
        $counter++;
    }

    $user_id = wp_insert_user([
        'user_login'   => $username,
        'user_pass'    => $password,
        'user_email'   => $email,
        'display_name' => $name,
        'first_name'   => $name,
        'role'         => 'employer',
    ]);

    if (is_wp_error($user_id)) {
        wp_safe_redirect(
            add_query_arg(
                'registration_error',
                'registration_failed',
                home_url('/employer-register/')
            )
        );
        exit;
    }

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    wp_safe_redirect(
        home_url('/employer-dashboard/')
    );

    exit;
}


add_action(
    'admin_post_nopriv_devhire_employer_register',
    'devhire_handle_employer_registration'
);

/**
 * ============================================================
 * Employer Registration Shortcode
 * ============================================================
 */

function devhire_employer_register_shortcode() {

    if (is_user_logged_in()) {
        return sprintf(
            '<div class="devhire-notice success">
                You are already logged in.
                <a href="%s">View Employer Dashboard</a>
            </div>',
            esc_url(home_url('/employer-dashboard/'))
        );
    }

    $error = isset($_GET['registration_error'])
        ? sanitize_key(
            wp_unslash($_GET['registration_error'])
        )
        : '';

    $message = '';

    switch ($error) {

        case 'missing_fields':
            $message = 'Please complete all fields.';
            break;

        case 'invalid_email':
            $message = 'Please enter a valid email address.';
            break;

        case 'email_exists':
            $message = 'An account already exists with this email.';
            break;

        case 'weak_password':
            $message = 'Password must contain at least 8 characters.';
            break;

        case 'registration_failed':
            $message = 'Registration failed. Please try again.';
            break;
    }

    ob_start();
    ?>

    <div class="candidate-auth employer-auth">

        <span class="hero-badge">
            Employer Portal
        </span>

        <h1>Create Employer Account</h1>

        <p>
            Create an account to post jobs and manage applicants.
        </p>

        <?php if ($message) : ?>

            <div class="devhire-notice error">
                <?php echo esc_html($message); ?>
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
                value="devhire_employer_register"
            >

            <?php
            wp_nonce_field(
                'devhire_employer_register',
                'devhire_employer_register_nonce'
            );
            ?>

            <div class="form-field">

                <label for="employer-name">
                    Full Name
                </label>

                <input
                    id="employer-name"
                    name="name"
                    type="text"
                    autocomplete="name"
                    required
                >

            </div>

            <div class="form-field">

                <label for="employer-email">
                    Email
                </label>

                <input
                    id="employer-email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    required
                >

            </div>

            <div class="form-field">

                <label for="employer-password">
                    Password
                </label>

                <input
                    id="employer-password"
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
                Create Employer Account
            </button>

        </form>

        <p class="auth-switch">
            Already have an employer account?

            <a href="<?php echo esc_url(
                home_url('/employer-login/')
            ); ?>">
                Sign in
            </a>
        </p>

    </div>

    <?php

    return ob_get_clean();
}


add_shortcode(
    'devhire_employer_register',
    'devhire_employer_register_shortcode'
);

/**
 * ============================================================
 * Employer Login
 * ============================================================
 */

function devhire_employer_login_shortcode() {

    if (is_user_logged_in()) {
        return sprintf(
            '<div class="devhire-notice success">
                You are logged in.
                <a href="%s">View Employer Dashboard</a>
            </div>',
            esc_url(home_url('/employer-dashboard/'))
        );
    }

    $error = '';

    if (
        isset($_POST['devhire_employer_login_nonce']) &&
        wp_verify_nonce(
            sanitize_text_field(
                wp_unslash($_POST['devhire_employer_login_nonce'])
            ),
            'devhire_employer_login'
        )
    ) {

        $email = isset($_POST['email'])
            ? sanitize_email(wp_unslash($_POST['email']))
            : '';

        $password = isset($_POST['password'])
            ? (string) wp_unslash($_POST['password'])
            : '';

        $user = get_user_by('email', $email);

        /*
         * Only employer accounts may use this login.
         */
        if (
            $user &&
            in_array('employer', (array) $user->roles, true)
        ) {

            $credentials = [
                'user_login'    => $user->user_login,
                'user_password' => $password,
                'remember'      => true,
            ];

            $login = wp_signon(
                $credentials,
                is_ssl()
            );

            if (!is_wp_error($login)) {

                wp_safe_redirect(
                    home_url('/employer-dashboard/')
                );

                exit;
            }
        }

        $error =
            '<div class="devhire-notice error">
                Invalid employer email or password.
            </div>';
    }

    ob_start();

    echo wp_kses_post($error);
    ?>

    <div class="candidate-auth employer-auth">

        <span class="hero-badge">
            Employer Portal
        </span>

        <h1>Employer Login</h1>

        <p>
            Sign in to manage your jobs and applicants.
        </p>

        <form method="post">

            <?php
            wp_nonce_field(
                'devhire_employer_login',
                'devhire_employer_login_nonce'
            );
            ?>

            <div class="form-field">

                <label for="employer-login-email">
                    Email
                </label>

                <input
                    id="employer-login-email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    required
                >

            </div>

            <div class="form-field">

                <label for="employer-login-password">
                    Password
                </label>

                <input
                    id="employer-login-password"
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

            Don't have an employer account?

            <a href="<?php echo esc_url(
                home_url('/employer-register/')
            ); ?>">
                Create account
            </a>

        </p>

    </div>

    <?php

    return ob_get_clean();
}


add_shortcode(
    'devhire_employer_login',
    'devhire_employer_login_shortcode'
);

/**
 * ============================================================
 * Employer Dashboard
 * ============================================================
 */

function devhire_employer_dashboard_shortcode() {

    if (!is_user_logged_in()) {
        return sprintf(
            '<div class="devhire-notice error">
                Please <a href="%s">sign in as an employer</a>
                to access the dashboard.
            </div>',
            esc_url(home_url('/employer-login/'))
        );
    }

    $user = wp_get_current_user();

    $job_search = isset($_GET['job_search'])
        ? sanitize_text_field(wp_unslash($_GET['job_search']))
        : '';

    if (!in_array('employer', (array) $user->roles, true)) {
        return '<div class="devhire-notice error">
            This dashboard is available only to employer accounts.
        </div>';
    }


    /*
     * Self-heal company relationships for older employer jobs.
     */
    $company_id = devhire_get_employer_company_id($user->ID);

    if ($company_id) {
        devhire_sync_employer_jobs_to_company(
            $user->ID,
            $company_id
        );
    }

    /*
     * Employer/company account summary.
     */
    $employer_name = $user->display_name
        ? $user->display_name
        : $user->user_login;

    $employer_email = $user->user_email;

    $company_name        = '';
    $company_website     = '';
    $company_location    = '';
    $company_industry    = '';
    $company_size        = '';
    $company_description = '';
    $company_logo_id     = 0;

    if ($company_id) {
        $company_name = get_the_title($company_id);

        $company_website = get_post_meta(
            $company_id,
            '_devhire_company_website',
            true
        );

        $company_location = get_post_meta(
            $company_id,
            '_devhire_company_location',
            true
        );

        $company_industry = get_post_meta(
            $company_id,
            '_devhire_company_industry',
            true
        );

        $company_size = get_post_meta(
            $company_id,
            '_devhire_company_size',
            true
        );

        $company_description = get_post_field(
            'post_content',
            $company_id
        );

        $company_logo_id = get_post_thumbnail_id(
            $company_id
        );
    }

    $company_profile_fields = [
        'Company Name' => $company_name,
        'Website'      => $company_website,
        'Location'     => $company_location,
        'Industry'     => $company_industry,
        'Company Size' => $company_size,
        'Description'  => trim(
            wp_strip_all_tags($company_description)
        ),
        'Company Logo' => $company_logo_id,
    ];

    $completed_company_fields = 0;
    $missing_company_fields   = [];

    foreach (
        $company_profile_fields as $label => $value
    ) {
        if (!empty($value)) {
            $completed_company_fields++;
        } else {
            $missing_company_fields[] = $label;
        }
    }

    $company_profile_completeness = $company_id
        ? (int) round(
            (
                $completed_company_fields /
                count($company_profile_fields)
            ) * 100
        )
        : 0;

    $company_profile_is_complete =
        $company_profile_completeness === 100;

    /*
     * Jobs belonging to this employer.
     *
     * We use post_author so WordPress itself owns the
     * employer -> job relationship.
     */
    $all_jobs = new WP_Query([
        'post_type'      => 'job',
        'post_status'    => ['publish', 'draft', 'pending'],
        'author'         => $user->ID,
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    $jobs = new WP_Query([
        'post_type'      => 'job',
        'post_status'    => ['publish', 'draft', 'pending'],
        'author'         => $user->ID,
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        's'              => $job_search,
    ]);

    $total_jobs     = 0;
    $published_jobs = 0;
    $draft_jobs     = 0;
    $job_ids        = [];

    foreach ($all_jobs->posts as $job) {

        $job_id = $job->ID;

        $deadline = get_post_meta(
            $job_id,
            '_devhire_deadline',
            true
        );

        $is_expired = false;

        if ($deadline) {
            $deadline_timestamp = strtotime($deadline . ' 23:59:59');

            if (
                $deadline_timestamp &&
                $deadline_timestamp < current_time('timestamp')
            ) {
                $is_expired = true;
            }
        }

        $job_ids[] = $job_id;
        $total_jobs++;

        if ($job->post_status === 'publish') {
            $published_jobs++;
        }

        if ($job->post_status === 'draft') {
            $draft_jobs++;
        }
    }

    /*
     * Count applications belonging to the employer's jobs.
     */
    $total_applications = 0;

    if ($job_ids) {

        $application_query = new WP_Query([
            'post_type'      => 'job_application',
            'post_status'    => 'private',
            'posts_per_page' => 1,

            'meta_query' => [
                [
                    'key'     => '_devhire_application_job',
                    'value'   => $job_ids,
                    'compare' => 'IN',
                    'type'    => 'NUMERIC',
                ],
            ],
        ]);

        $total_applications = $application_query->found_posts;
    }

    ob_start();
    ?>

    <div class="candidate-dashboard employer-dashboard">

        <div class="candidate-dashboard-header">

            <div>

                <span class="hero-badge">
                    Employer Portal
                </span>

                <h1>
                    Employer Dashboard
                </h1>

                <p>
                    Welcome,
                    <?php echo esc_html($user->display_name); ?>.
                    Manage your jobs and applicants here.
                </p>

            </div>

        </div>


        <nav class="candidate-dashboard-nav">

            <a
                class="candidate-nav-link active"
                href="<?php echo esc_url(
                    home_url('/employer-dashboard/')
                ); ?>"
            >
                My Jobs
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/employer-post-job/')
                ); ?>"
            >
                Post Job
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/employer-applicants/')
                ); ?>"
            >
                Applicants
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/employer-company-profile/')
                ); ?>"
            >
                Company Profile
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    wp_logout_url(
                        home_url('/employer-login/')
                    )
                ); ?>"
            >
                Sign Out
            </a>

        </nav>

        <section class="employer-account-summary">

            <div class="employer-account-summary-main">

                <div class="employer-account-avatar">
                    <?php
                    echo esc_html(
                        strtoupper(
                            substr($employer_name, 0, 1)
                        )
                    );
                    ?>
                </div>

                <div class="employer-account-details">

                    <span class="application-job-label">
                        Employer Account
                    </span>

                    <h2>
                        <?php echo esc_html($employer_name); ?>
                    </h2>

                    <p>
                        <?php echo esc_html($employer_email); ?>
                    </p>

                    <?php if ($company_id) : ?>

                        <div class="employer-account-company">

                            <div class="employer-account-company-logo">

                                <?php if (
                                    has_post_thumbnail($company_id)
                                ) : ?>

                                    <?php
                                    echo get_the_post_thumbnail(
                                        $company_id,
                                        'thumbnail',
                                        [
                                            'class' =>
                                                'company-logo-image',
                                            'alt' =>
                                                $company_name,
                                            'loading' =>
                                                'lazy',
                                        ]
                                    );
                                    ?>

                                <?php else : ?>

                                    <span aria-hidden="true">
                                        <?php
                                        echo esc_html(
                                            strtoupper(
                                                substr(
                                                    $company_name,
                                                    0,
                                                    1
                                                )
                                            )
                                        );
                                        ?>
                                    </span>

                                <?php endif; ?>

                            </div>

                            <div>
                                <strong>
                                    <?php
                                    echo esc_html(
                                        $company_name
                                    );
                                    ?>
                                </strong>

                                <?php if (
                                    $company_industry ||
                                    $company_location
                                ) : ?>

                                    <span>
                                        <?php
                                        echo esc_html(
                                            implode(
                                                ' · ',
                                                array_filter([
                                                    $company_industry,
                                                    $company_location,
                                                ])
                                            )
                                        );
                                        ?>
                                    </span>

                                <?php endif; ?>
                            </div>

                        </div>

                    <?php else : ?>

                        <p class="employer-account-warning">
                            Create your company profile before
                            publishing jobs.
                        </p>

                    <?php endif; ?>

                </div>

            </div>

            <div class="employer-company-completeness">

                <div class="employer-company-completeness-heading">

                    <span>Company Profile</span>

                    <strong>
                        <?php
                        echo esc_html(
                            $company_profile_completeness
                        );
                        ?>%
                    </strong>

                </div>

                <div
                    class="candidate-profile-progress"
                    role="progressbar"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="<?php echo esc_attr(
                        $company_profile_completeness
                    ); ?>"
                    aria-label="<?php esc_attr_e(
                        'Company profile completeness',
                        'devhire'
                    ); ?>"
                >
                    <span
                        style="width: <?php echo esc_attr(
                            $company_profile_completeness
                        ); ?>%;"
                    ></span>
                </div>

                <?php if (
                    $company_profile_is_complete
                ) : ?>

                    <p class="complete">
                        Your company profile is complete.
                    </p>

                <?php elseif ($company_id) : ?>

                    <p>
                        Missing:
                        <?php
                        echo esc_html(
                            implode(
                                ', ',
                                $missing_company_fields
                            )
                        );
                        ?>
                    </p>

                <?php else : ?>

                    <p>
                        Add your company information and logo
                        to complete your employer profile.
                    </p>

                <?php endif; ?>

                <div class="employer-account-actions">

                    <a
                        class="secondary-button"
                        href="<?php echo esc_url(
                            home_url(
                                '/employer-company-profile/'
                            )
                        ); ?>"
                    >
                        <?php
                        echo $company_id
                            ? 'Edit Company Profile'
                            : 'Create Company Profile';
                        ?>
                    </a>

                    <?php if ($company_id) : ?>

                        <a
                            class="primary-button"
                            href="<?php echo esc_url(
                                home_url(
                                    '/employer-post-job/'
                                )
                            ); ?>"
                        >
                            Post Job
                        </a>

                    <?php endif; ?>

                </div>

            </div>

        </section>
        
        <?php if (
            isset($_GET['job_created']) &&
            $_GET['job_created'] === '1'
        ) : ?>

            <div class="devhire-notice success">
                Job created successfully. It has been saved as a draft.
            </div>

        <?php endif; ?>


        <?php if (
            isset($_GET['job_updated']) &&
            $_GET['job_updated'] === '1'
        ) : ?>

            <div class="devhire-notice success">
                Job updated successfully.
            </div>

        <?php endif; ?>


        <?php if (
            isset($_GET['job_status_updated']) &&
            $_GET['job_status_updated'] === '1'
        ) : ?>

            <div class="devhire-notice success">
                Job status updated successfully.
            </div>

        <?php endif; ?>


        <?php if (
            isset($_GET['job_deleted']) &&
            $_GET['job_deleted'] === '1'
        ) : ?>

            <div class="devhire-notice success">
                Job moved to Trash successfully.
            </div>

        <?php endif; ?>


        <?php if (
            isset($_GET['job_delete_error']) &&
            $_GET['job_delete_error'] === '1'
        ) : ?>

            <div class="devhire-notice error">
                Unable to delete the job. Please try again.
            </div>

        <?php endif; ?>

        <?php
        $company_id = devhire_get_employer_company_id($user->ID);

        if ($company_id) :

            $company_name = get_the_title($company_id);

            $company_industry = get_post_meta(
                $company_id,
                '_devhire_company_industry',
                true
            );

            $company_location = get_post_meta(
                $company_id,
                '_devhire_company_location',
                true
            );
        ?>

            <div class="employer-company-summary">

                <div class="company-heading">

                    <div class="company-profile-logo">
                        <?php if (has_post_thumbnail($company_id)) : ?>

                            <?php
                            echo get_the_post_thumbnail(
                                $company_id,
                                'thumbnail',
                                [
                                    'class'   => 'company-logo-image',
                                    'alt'     => $company_name,
                                    'loading' => 'lazy',
                                ]
                            );
                            ?>

                        <?php else : ?>

                            <span aria-hidden="true">
                                <?php
                                echo esc_html(
                                    strtoupper(
                                        substr($company_name, 0, 1)
                                    )
                                );
                                ?>
                            </span>

                        <?php endif; ?>
                    </div>

                    <div>
                        <span class="application-job-label">
                            Company
                        </span>

                        <h2>
                            <?php echo esc_html($company_name); ?>
                        </h2>

                        <?php if (
                            $company_industry ||
                            $company_location
                        ) : ?>

                            <p>
                                <?php
                                echo esc_html(
                                    implode(
                                        ' · ',
                                        array_filter([
                                            $company_industry,
                                            $company_location,
                                        ])
                                    )
                                );
                                ?>
                            </p>

                        <?php endif; ?>
                    </div>

                </div>

                <div class="application-card-actions">

                    <a
                        class="secondary-button"
                        href="<?php echo esc_url(
                            home_url('/employer-company-profile/')
                        ); ?>"
                    >
                        Edit Company
                    </a>

                    <?php if (
                        get_post_status($company_id) === 'publish'
                    ) : ?>

                        <a
                            class="secondary-button"
                            href="<?php echo esc_url(
                                get_permalink($company_id)
                            ); ?>"
                        >
                            View Company
                        </a>

                    <?php endif; ?>

                </div>

            </div>

        <?php else : ?>

            <div class="devhire-notice error">
                Your employer account does not have a company profile yet.
                <a href="<?php echo esc_url(
                    home_url('/employer-company-profile/')
                ); ?>">
                    Create Company Profile
                </a>
            </div>

        <?php endif; ?>

        <div class="dashboard-stats">

            <div class="dashboard-stat">
                <span>Total Jobs</span>
                <strong>
                    <?php echo esc_html($total_jobs); ?>
                </strong>
            </div>

            <div class="dashboard-stat">
                <span>Published</span>
                <strong>
                    <?php echo esc_html($published_jobs); ?>
                </strong>
            </div>

            <div class="dashboard-stat">
                <span>Drafts</span>
                <strong>
                    <?php echo esc_html($draft_jobs); ?>
                </strong>
            </div>

            <div class="dashboard-stat">
                <span>Applications</span>
                <strong>
                    <?php echo esc_html($total_applications); ?>
                </strong>
            </div>

        </div>

        <form
            method="get"
            action="<?php echo esc_url(
                home_url('/employer-dashboard/')
            ); ?>"
            class="employer-job-search"
        >
            <input
                type="search"
                name="job_search"
                value="<?php echo esc_attr($job_search); ?>"
                placeholder="Search your jobs..."
            >

            <button
                type="submit"
                class="primary-button"
            >
                Search
            </button>

            <?php if ($job_search) : ?>

                <a
                    href="<?php echo esc_url(
                        home_url('/employer-dashboard/')
                    ); ?>"
                    class="secondary-button"
                >
                    Clear
                </a>

            <?php endif; ?>

        </form>


        <div class="employer-jobs">

            <div class="dashboard-section-header">

                <div>
                    <h2>My Jobs</h2>
                    <p>
                        Jobs posted from your employer account.
                    </p>
                </div>

            </div>


            <?php if ($jobs->have_posts()) : ?>

                <div class="application-list">

                    <?php
                    while ($jobs->have_posts()) :
                        $jobs->the_post();

                        $job_id = get_the_ID();

                        $job_applications = new WP_Query([
                            'post_type'      => 'job_application',
                            'post_status'    => 'private',
                            'posts_per_page' => -1,
                            'fields'         => 'ids',
                            'meta_query'     => [
                                [
                                    'key'     => '_devhire_application_job',
                                    'value'   => $job_id,
                                    'compare' => '=',
                                    'type'    => 'NUMERIC',
                                ],
                            ],
                        ]);

                        $job_application_count = $job_applications->found_posts;

                        $job_reviewing_count = 0;
                        $job_interview_count = 0;
                        $job_hired_count = 0;

                        foreach ($job_applications->posts as $application_id) {

                            $application_status = get_post_meta(
                                $application_id,
                                '_devhire_application_status',
                                true
                            );

                            switch ($application_status) {
                                case 'Reviewing':
                                    $job_reviewing_count++;
                                    break;

                                case 'Interview':
                                    $job_interview_count++;
                                    break;

                                case 'Hired':
                                    $job_hired_count++;
                                    break;
                            }
                        }

                        $status = get_post_status($job_id);

                        switch ($status) {
                            case 'publish':
                                $status_label = 'Published';
                                $status_class = 'published';
                                break;

                            case 'pending':
                                $status_label = 'Pending Review';
                                $status_class = 'pending';
                                break;

                            default:
                                $status_label = 'Draft';
                                $status_class = 'draft';
                                break;
                        }

                        $application_count = new WP_Query([
                            'post_type'      => 'job_application',
                            'post_status'    => 'private',
                            'posts_per_page' => 1,

                            'meta_query' => [
                                [
                                    'key'     => '_devhire_application_job',
                                    'value'   => $job_id,
                                    'compare' => '=',
                                    'type'    => 'NUMERIC',
                                ],
                            ],
                        ]);
                        ?>

                        <article class="application-card">

                            <div>

                                <span class="employer-job-status <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($status_label); ?>
                                </span>

                                <?php if ($is_expired) : ?>

                                    <span class="employer-job-status expired">
                                        Expired
                                    </span>

                                <?php elseif ($deadline) : ?>

                                    <span class="employer-job-deadline">
                                        Deadline:
                                        <?php
                                        echo esc_html(
                                            date_i18n(
                                                get_option('date_format'),
                                                strtotime($deadline)
                                            )
                                        );
                                        ?>
                                    </span>

                                <?php endif; ?>

                                <h3>
                                    <?php the_title(); ?>
                                </h3>

                                <p>
                                    <?php
                                    echo esc_html(
                                        $application_count->found_posts
                                    );
                                    ?>
                                    application(s)
                                </p>

                            </div>

                            <div class="employer-job-stats">

                                <a href="<?php echo esc_url(
                                    add_query_arg(
                                        'job_id',
                                        $job_id,
                                        home_url('/employer-applicants/')
                                    )
                                ); ?>">
                                    <strong>
                                        <?php echo esc_html($job_application_count); ?>
                                    </strong>
                                    Applicants
                                </a>

                                <a href="<?php echo esc_url(
                                    add_query_arg(
                                        [
                                            'job_id' => $job_id,
                                            'status' => 'reviewing',
                                        ],
                                        home_url('/employer-applicants/')
                                    )
                                ); ?>">
                                    <strong>
                                        <?php echo esc_html($job_reviewing_count); ?>
                                    </strong>
                                    Reviewing
                                </a>

                                <a href="<?php echo esc_url(
                                    add_query_arg(
                                        [
                                            'job_id' => $job_id,
                                            'status' => 'interview',
                                        ],
                                        home_url('/employer-applicants/')
                                    )
                                ); ?>">
                                    <strong>
                                        <?php echo esc_html($job_interview_count); ?>
                                    </strong>
                                    Interviews
                                </a>

                                <a href="<?php echo esc_url(
                                    add_query_arg(
                                        [
                                            'job_id' => $job_id,
                                            'status' => 'hired',
                                        ],
                                        home_url('/employer-applicants/')
                                    )
                                ); ?>">
                                    <strong>
                                        <?php echo esc_html($job_hired_count); ?>
                                    </strong>
                                    Hired
                                </a>

                            </div>

                            <div class="application-card-actions">
                                <a
                                    class="secondary-button"
                                    href="<?php
                                    echo esc_url(
                                        add_query_arg(
                                            'job_id',
                                            $job_id,
                                            home_url('/employer-edit-job/')
                                        )
                                    );
                                    ?>"
                                >
                                    Edit Job
                                </a>

                                <?php if ($status === 'publish') : ?>

                                    <a
                                        class="secondary-button"
                                        href="<?php the_permalink(); ?>"
                                    >
                                        View Job
                                    </a>

                                <?php endif; ?>


                                <form
                                    method="post"
                                    action="<?php echo esc_url(
                                        admin_url('admin-post.php')
                                    ); ?>"
                                    class="job-status-form"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="devhire_employer_job_status"
                                    >

                                    <input
                                        type="hidden"
                                        name="job_id"
                                        value="<?php echo esc_attr($job_id); ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="job_status"
                                        value="<?php
                                        echo esc_attr(
                                            $status === 'publish'
                                                ? 'draft'
                                                : 'publish'
                                        );
                                        ?>"
                                    >

                                    <?php
                                    wp_nonce_field(
                                        'devhire_job_status_' . $job_id,
                                        'devhire_job_status_nonce'
                                    );
                                    ?>

                                    <button
                                        type="submit"
                                        class="<?php
                                        echo $status === 'publish'
                                            ? 'secondary-button'
                                            : 'primary-button';
                                        ?>"
                                    >
                                        <?php
                                        echo $status === 'publish'
                                            ? 'Unpublish'
                                            : 'Publish';
                                        ?>
                                    </button>

                                </form>

                                <form
                                    method="post"
                                    action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                    class="job-delete-form"
                                    onsubmit="return confirm('Are you sure you want to delete this job?');"
                                >
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="devhire_employer_delete_job"
                                    >

                                    <input
                                        type="hidden"
                                        name="job_id"
                                        value="<?php echo esc_attr($job_id); ?>"
                                    >

                                    <?php
                                    wp_nonce_field(
                                        'devhire_delete_job_' . $job_id,
                                        'devhire_delete_job_nonce'
                                    );
                                    ?>

                                    <button
                                        type="submit"
                                        class="secondary-button delete-job-button"
                                    >
                                        Delete
                                    </button>
                                </form>

                            </div>

                        </article>

                    <?php endwhile; ?>

                </div>

            <?php else : ?>

                <div class="dashboard-empty-state">

                    <?php if ($job_search) : ?>

                        <h3>No matching jobs</h3>

                        <p>
                            No jobs matched
                            <strong>
                                "<?php echo esc_html($job_search); ?>"
                            </strong>.
                        </p>

                        <a
                            class="secondary-button"
                            href="<?php echo esc_url(
                                home_url('/employer-dashboard/')
                            ); ?>"
                        >
                            Clear Search
                        </a>

                    <?php else : ?>

                        <h3>No jobs yet</h3>

                        <p>
                            You haven't created any job postings yet.
                        </p>

                        <a
                            class="primary-button"
                            href="<?php echo esc_url(
                                home_url('/employer-post-job/')
                            ); ?>"
                        >
                            Post Your First Job
                        </a>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

            <?php wp_reset_postdata(); ?>

        </div>

    </div>

    <?php

    return ob_get_clean();
}


add_shortcode(
    'devhire_employer_dashboard',
    'devhire_employer_dashboard_shortcode'
);

/**
 * ============================================================
 * Employer - Create Job
 * ============================================================
 */

function devhire_handle_employer_create_job() {

    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/employer-login/'));
        exit;
    }

    $user = wp_get_current_user();

    if (!in_array('employer', (array) $user->roles, true)) {
        wp_die('You are not allowed to create jobs.');
    }

    $company_id = devhire_get_employer_company_id($user->ID);

    if (!$company_id) {
        wp_safe_redirect(
            add_query_arg(
                'company_required',
                '1',
                home_url('/employer-company-profile/')
            )
        );
        exit;
    }

    if (
        !isset($_POST['devhire_create_job_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(
                wp_unslash($_POST['devhire_create_job_nonce'])
            ),
            'devhire_create_job'
        )
    ) {
        wp_die('Invalid job submission.');
    }

    $title = isset($_POST['job_title'])
        ? sanitize_text_field(wp_unslash($_POST['job_title']))
        : '';

    $description = isset($_POST['job_description'])
        ? wp_kses_post(wp_unslash($_POST['job_description']))
        : '';

    $salary = isset($_POST['salary'])
        ? sanitize_text_field(wp_unslash($_POST['salary']))
        : '';

    $experience = isset($_POST['experience'])
        ? sanitize_text_field(wp_unslash($_POST['experience']))
        : '';

    $deadline = isset($_POST['deadline'])
        ? sanitize_text_field(wp_unslash($_POST['deadline']))
        : '';

    $remote = isset($_POST['remote'])
        ? '1'
        : '0';

    if (!$title || !$description) {

        wp_safe_redirect(
            add_query_arg(
                'job_error',
                'missing_fields',
                home_url('/employer-post-job/')
            )
        );

        exit;
    }

    /*
     * Create the job as a draft first.
     */
    $job_id = wp_insert_post([
        'post_type'    => 'job',
        'post_status'  => 'draft',
        'post_title'   => $title,
        'post_content' => $description,
        'post_author'  => $user->ID,
    ]);

    if (is_wp_error($job_id)) {

        wp_safe_redirect(
            add_query_arg(
                'job_error',
                'create_failed',
                home_url('/employer-post-job/')
            )
        );

        exit;
    }

    /*
     * Save DevHire job fields.
     */
    update_post_meta(
        $job_id,
        '_devhire_salary',
        $salary
    );

    update_post_meta(
        $job_id,
        '_devhire_experience',
        $experience
    );

    update_post_meta(
        $job_id,
        '_devhire_deadline',
        $deadline
    );

    update_post_meta(
        $job_id,
        '_devhire_remote',
        $remote
    );

    /*
     * Connect this job to the employer's company profile.
     */
    $company_id = devhire_get_employer_company_id($user->ID);

    if ($company_id) {
        update_post_meta(
            $job_id,
            '_devhire_company',
            $company_id
        );
    } else {
        delete_post_meta(
            $job_id,
            '_devhire_company'
        );
    }

    /*
     * Taxonomies
     */
    $skill_ids = isset($_POST['skills'])
        ? array_map(
            'absint',
            (array) wp_unslash($_POST['skills'])
        )
        : [];

    $job_type = isset($_POST['job_type'])
        ? absint($_POST['job_type'])
        : 0;

    $location = isset($_POST['location'])
        ? absint($_POST['location'])
        : 0;

    if ($skill_ids) {
        wp_set_object_terms(
            $job_id,
            $skill_ids,
            'job_skill'
        );
    }

    if ($job_type) {
        wp_set_object_terms(
            $job_id,
            [$job_type],
            'job_type'
        );
    }

    if ($location) {
        wp_set_object_terms(
            $job_id,
            [$location],
            'job_location'
        );
    }

    wp_safe_redirect(
        add_query_arg(
            'job_created',
            '1',
            home_url('/employer-dashboard/')
        )
    );

    exit;
}


add_action(
    'admin_post_devhire_employer_create_job',
    'devhire_handle_employer_create_job'
);

/**
 * ============================================================
 * Employer - Post Job Form
 * ============================================================
 */

function devhire_employer_post_job_shortcode() {

    if (!is_user_logged_in()) {
        return sprintf(
            '<div class="devhire-notice error">
                Please <a href="%s">sign in as an employer</a>
                to post a job.
            </div>',
            esc_url(home_url('/employer-login/'))
        );
    }

    $user = wp_get_current_user();

    if (!in_array('employer', (array) $user->roles, true)) {
        return '<div class="devhire-notice error">
            Only employer accounts can post jobs.
        </div>';
    }

    $company_id = devhire_get_employer_company_id($user->ID);

    if (!$company_id) {
        return sprintf(
            '<div class="candidate-dashboard employer-dashboard">
                <div class="devhire-notice error">
                    <strong>Company profile required.</strong>
                    Create your company profile before posting a job.
                </div>
                <a class="primary-button" href="%s">
                    Create Company Profile
                </a>
            </div>',
            esc_url(home_url('/employer-company-profile/'))
        );
    }

    $skills = get_terms([
        'taxonomy'   => 'job_skill',
        'hide_empty' => false,
    ]);

    $job_types = get_terms([
        'taxonomy'   => 'job_type',
        'hide_empty' => false,
    ]);

    $locations = get_terms([
        'taxonomy'   => 'job_location',
        'hide_empty' => false,
    ]);

    $error = isset($_GET['job_error'])
        ? sanitize_key(wp_unslash($_GET['job_error']))
        : '';

    ob_start();
    ?>

    <div class="candidate-dashboard employer-dashboard">

        <div class="candidate-dashboard-header">
            <div>
                <span class="hero-badge">
                    Employer Portal
                </span>

                <h1>Post a Job</h1>

                <p>
                    Create a new job listing for candidates.
                </p>
            </div>
        </div>


        <nav class="candidate-dashboard-nav">

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/employer-dashboard/')
                ); ?>"
            >
                My Jobs
            </a>

            <a
                class="candidate-nav-link active"
                href="<?php echo esc_url(
                    home_url('/employer-post-job/')
                ); ?>"
            >
                Post Job
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/employer-applicants/')
                ); ?>"
            >
                Applicants
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/employer-company-profile/')
                ); ?>"
            >
                Company Profile
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    wp_logout_url(
                        home_url('/employer-login/')
                    )
                ); ?>"
            >
                Sign Out
            </a>

        </nav>


        <?php if ($error === 'missing_fields') : ?>

            <div class="devhire-notice error">
                Job title and description are required.
            </div>

        <?php elseif ($error === 'create_failed') : ?>

            <div class="devhire-notice error">
                Unable to create the job. Please try again.
            </div>

        <?php endif; ?>


        <?php
        $company_name = get_the_title($company_id);

        $company_location = get_post_meta(
            $company_id,
            '_devhire_company_location',
            true
        );

        $company_industry = get_post_meta(
            $company_id,
            '_devhire_company_industry',
            true
        );
        ?>

        <div class="employer-company-summary">

            <div class="employer-company-summary-logo" aria-hidden="true">
                <?php if (has_post_thumbnail($company_id)) : ?>
                    <?php
                    echo get_the_post_thumbnail(
                        $company_id,
                        'thumbnail',
                        [
                            'class' => 'company-logo-image',
                            'alt'   => '',
                        ]
                    );
                    ?>
                <?php else : ?>
                    <span class="company-logo-fallback">
                        <?php echo esc_html(
                            strtoupper(substr($company_name, 0, 1))
                        ); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="employer-company-summary-content">
                <span class="application-job-label">
                    Posting as
                </span>

                <h2>
                    <?php echo esc_html($company_name); ?>
                </h2>

                <?php if (
                    $company_industry ||
                    $company_location
                ) : ?>

                    <p>
                        <?php
                        echo esc_html(
                            implode(
                                ' · ',
                                array_filter([
                                    $company_industry,
                                    $company_location,
                                ])
                            )
                        );
                        ?>
                    </p>

                <?php endif; ?>
            </div>

            <div class="application-card-actions">

                <a
                    class="secondary-button"
                    href="<?php echo esc_url(
                        home_url('/employer-company-profile/')
                    ); ?>"
                >
                    Edit Company
                </a>

            </div>

        </div>

        <form
            class="candidate-profile-form employer-job-form"
            method="post"
            action="<?php echo esc_url(
                admin_url('admin-post.php')
            ); ?>"
        >

            <input
                type="hidden"
                name="action"
                value="devhire_employer_create_job"
            >

            <?php
            wp_nonce_field(
                'devhire_create_job',
                'devhire_create_job_nonce'
            );
            ?>


            <div class="form-field">

                <label for="job-title">
                    Job Title
                </label>

                <input
                    id="job-title"
                    name="job_title"
                    type="text"
                    placeholder="Senior Full Stack Developer"
                    required
                >

            </div>


            <div class="form-field">

                <label for="job-description">
                    Job Description
                </label>

                <textarea
                    id="job-description"
                    name="job_description"
                    rows="10"
                    placeholder="Describe the role, responsibilities and requirements..."
                    required
                ></textarea>

            </div>


            <div class="form-field">

                <label for="salary">
                    Salary
                </label>

                <input
                    id="salary"
                    name="salary"
                    type="text"
                    placeholder="$80,000 - $110,000"
                >

            </div>


            <div class="form-field">

                <label for="experience">
                    Experience
                </label>

                <input
                    id="experience"
                    name="experience"
                    type="text"
                    placeholder="3+ years"
                >

            </div>


            <div class="form-field">

                <label for="job-type">
                    Job Type
                </label>

                <select
                    id="job-type"
                    name="job_type"
                >

                    <option value="">
                        Select Job Type
                    </option>

                    <?php if (!is_wp_error($job_types)) : ?>

                        <?php foreach ($job_types as $type) : ?>

                            <option
                                value="<?php echo esc_attr($type->term_id); ?>"
                            >
                                <?php echo esc_html($type->name); ?>
                            </option>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </select>

            </div>


            <div class="form-field">

                <label for="job-location">
                    Location
                </label>

                <select
                    id="job-location"
                    name="location"
                >

                    <option value="">
                        Select Location
                    </option>

                    <?php if (!is_wp_error($locations)) : ?>

                        <?php foreach ($locations as $location) : ?>

                            <option
                                value="<?php echo esc_attr($location->term_id); ?>"
                            >
                                <?php echo esc_html($location->name); ?>
                            </option>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </select>

            </div>


            <div class="form-field">

                <label>Skills</label>

                <div class="employer-skills-grid">

                    <?php if (!is_wp_error($skills)) : ?>

                        <?php foreach ($skills as $skill) : ?>

                            <label class="employer-skill-option">

                                <input
                                    type="checkbox"
                                    name="skills[]"
                                    value="<?php
                                    echo esc_attr($skill->term_id);
                                    ?>"
                                >

                                <span>
                                    <?php echo esc_html($skill->name); ?>
                                </span>

                            </label>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>


            <div class="form-field">

                <label class="employer-skill-option">

                    <input
                        type="checkbox"
                        name="remote"
                        value="1"
                    >

                    <span>Remote position</span>

                </label>

            </div>


            <div class="form-field">

                <label for="deadline">
                    Application Deadline
                </label>

                <input
                    id="deadline"
                    name="deadline"
                    type="date"
                >

            </div>


            <div class="form-actions">

                <button
                    type="submit"
                    class="primary-button"
                >
                    Create Job
                </button>

                <a
                    class="secondary-button"
                    href="<?php echo esc_url(
                        home_url('/employer-dashboard/')
                    ); ?>"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

    <?php

    return ob_get_clean();
}


add_shortcode(
    'devhire_employer_post_job',
    'devhire_employer_post_job_shortcode'
);

/**
 * ============================================================
 * Employer - Change Job Status
 * ============================================================
 */

function devhire_handle_employer_job_status() {

    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/employer-login/'));
        exit;
    }

    $user = wp_get_current_user();

    if (!in_array('employer', (array) $user->roles, true)) {
        wp_die('You are not allowed to manage jobs.');
    }

    $job_id = isset($_POST['job_id'])
        ? absint($_POST['job_id'])
        : 0;

    $new_status = isset($_POST['job_status'])
        ? sanitize_key(wp_unslash($_POST['job_status']))
        : '';

    if (
        !$job_id ||
        !isset($_POST['devhire_job_status_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(
                wp_unslash($_POST['devhire_job_status_nonce'])
            ),
            'devhire_job_status_' . $job_id
        )
    ) {
        wp_die('Invalid request.');
    }

    $job = get_post($job_id);

    /*
     * Security:
     * employer can only modify their own jobs.
     */
    if (
        !$job ||
        $job->post_type !== 'job' ||
        (int) $job->post_author !== (int) $user->ID
    ) {
        wp_die('You are not allowed to manage this job.');
    }

    if (!in_array($new_status, ['draft', 'publish'], true)) {
        wp_die('Invalid job status.');
    }

    $result = wp_update_post([
        'ID'          => $job_id,
        'post_status' => $new_status,
    ], true);

    if (is_wp_error($result)) {
        wp_die('Unable to update the job.');
    }

    wp_safe_redirect(
        add_query_arg(
            'job_status_updated',
            '1',
            home_url('/employer-dashboard/')
        )
    );

    exit;
}

add_action(
    'admin_post_devhire_employer_job_status',
    'devhire_handle_employer_job_status'
);

/**
 * ============================================================
 * Employer - Update Job
 * ============================================================
 */

function devhire_handle_employer_update_job() {

    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/employer-login/'));
        exit;
    }

    $user = wp_get_current_user();

    if (!in_array('employer', (array) $user->roles, true)) {
        wp_die('You are not allowed to edit jobs.');
    }

    $job_id = isset($_POST['job_id'])
        ? absint($_POST['job_id'])
        : 0;

    if (
        !$job_id ||
        !isset($_POST['devhire_update_job_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(
                wp_unslash($_POST['devhire_update_job_nonce'])
            ),
            'devhire_update_job_' . $job_id
        )
    ) {
        wp_die('Invalid request.');
    }

    $job = get_post($job_id);

    /*
     * Employer may edit only their own job.
     */
    if (
        !$job ||
        $job->post_type !== 'job' ||
        (int) $job->post_author !== (int) $user->ID
    ) {
        wp_die('You are not allowed to edit this job.');
    }

    $title = isset($_POST['job_title'])
        ? sanitize_text_field(wp_unslash($_POST['job_title']))
        : '';

    $description = isset($_POST['job_description'])
        ? wp_kses_post(wp_unslash($_POST['job_description']))
        : '';

    $salary = isset($_POST['salary'])
        ? sanitize_text_field(wp_unslash($_POST['salary']))
        : '';

    $experience = isset($_POST['experience'])
        ? sanitize_text_field(wp_unslash($_POST['experience']))
        : '';

    $deadline = isset($_POST['deadline'])
        ? sanitize_text_field(wp_unslash($_POST['deadline']))
        : '';

    $remote = isset($_POST['remote'])
        ? '1'
        : '0';

    if (!$title || !$description) {

        wp_safe_redirect(
            add_query_arg(
                [
                    'job_id'    => $job_id,
                    'job_error' => 'missing_fields',
                ],
                home_url('/employer-edit-job/')
            )
        );

        exit;
    }

    $result = wp_update_post([
        'ID'           => $job_id,
        'post_title'   => $title,
        'post_content' => $description,
    ], true);

    if (is_wp_error($result)) {

        wp_safe_redirect(
            add_query_arg(
                [
                    'job_id'    => $job_id,
                    'job_error' => 'update_failed',
                ],
                home_url('/employer-edit-job/')
            )
        );

        exit;
    }

    update_post_meta(
        $job_id,
        '_devhire_salary',
        $salary
    );

    update_post_meta(
        $job_id,
        '_devhire_experience',
        $experience
    );

    update_post_meta(
        $job_id,
        '_devhire_deadline',
        $deadline
    );

    update_post_meta(
        $job_id,
        '_devhire_remote',
        $remote
    );

    /*
     * Keep this job connected to the employer's company profile.
     */
    $company_id = devhire_get_employer_company_id($user->ID);

    if ($company_id) {
        update_post_meta(
            $job_id,
            '_devhire_company',
            $company_id
        );
    } else {
        delete_post_meta(
            $job_id,
            '_devhire_company'
        );
    }

    /*
     * Update taxonomies.
     */
    $skill_ids = isset($_POST['skills'])
        ? array_map(
            'absint',
            (array) wp_unslash($_POST['skills'])
        )
        : [];

    $job_type = isset($_POST['job_type'])
        ? absint($_POST['job_type'])
        : 0;

    $location = isset($_POST['location'])
        ? absint($_POST['location'])
        : 0;

    wp_set_object_terms(
        $job_id,
        $skill_ids,
        'job_skill'
    );

    wp_set_object_terms(
        $job_id,
        $job_type ? [$job_type] : [],
        'job_type'
    );

    wp_set_object_terms(
        $job_id,
        $location ? [$location] : [],
        'job_location'
    );

    wp_safe_redirect(
        add_query_arg(
            'job_updated',
            '1',
            home_url('/employer-dashboard/')
        )
    );

    exit;
}

add_action(
    'admin_post_devhire_employer_update_job',
    'devhire_handle_employer_update_job'
);

/**
 * ============================================================
 * Employer - Edit Job Form
 * ============================================================
 */

function devhire_employer_edit_job_shortcode() {

    if (!is_user_logged_in()) {
        return sprintf(
            '<div class="devhire-notice error">
                Please <a href="%s">sign in as an employer</a>.
            </div>',
            esc_url(home_url('/employer-login/'))
        );
    }

    $user = wp_get_current_user();

    if (!in_array('employer', (array) $user->roles, true)) {
        return '<div class="devhire-notice error">
            Only employer accounts can edit jobs.
        </div>';
    }

    $job_id = isset($_GET['job_id'])
        ? absint($_GET['job_id'])
        : 0;

    $job = $job_id
        ? get_post($job_id)
        : null;

    /*
     * Security: employer can edit only their own jobs.
     */
    if (
        !$job ||
        $job->post_type !== 'job' ||
        (int) $job->post_author !== (int) $user->ID
    ) {
        return '<div class="devhire-notice error">
            Job not found or you do not have permission to edit it.
        </div>';
    }

    /*
     * Existing custom fields.
     */
    $salary = get_post_meta(
        $job_id,
        '_devhire_salary',
        true
    );

    $experience = get_post_meta(
        $job_id,
        '_devhire_experience',
        true
    );

    $deadline = get_post_meta(
        $job_id,
        '_devhire_deadline',
        true
    );

    $remote = get_post_meta(
        $job_id,
        '_devhire_remote',
        true
    );

    /*
     * Available taxonomy terms.
     */
    $skills = get_terms([
        'taxonomy'   => 'job_skill',
        'hide_empty' => false,
    ]);

    $job_types = get_terms([
        'taxonomy'   => 'job_type',
        'hide_empty' => false,
    ]);

    $locations = get_terms([
        'taxonomy'   => 'job_location',
        'hide_empty' => false,
    ]);

    /*
     * Existing selections.
     */
    $selected_skills = wp_get_object_terms(
        $job_id,
        'job_skill',
        [
            'fields' => 'ids',
        ]
    );

    if (is_wp_error($selected_skills)) {
        $selected_skills = [];
    }

    $selected_job_types = wp_get_object_terms(
        $job_id,
        'job_type',
        [
            'fields' => 'ids',
        ]
    );

    if (is_wp_error($selected_job_types)) {
        $selected_job_types = [];
    }

    $selected_locations = wp_get_object_terms(
        $job_id,
        'job_location',
        [
            'fields' => 'ids',
        ]
    );

    if (is_wp_error($selected_locations)) {
        $selected_locations = [];
    }

    $selected_job_type = !empty($selected_job_types)
        ? (int) $selected_job_types[0]
        : 0;

    $selected_location = !empty($selected_locations)
        ? (int) $selected_locations[0]
        : 0;

    $error = isset($_GET['job_error'])
        ? sanitize_key(wp_unslash($_GET['job_error']))
        : '';

    ob_start();
    ?>

    <div class="candidate-dashboard employer-dashboard">

        <div class="candidate-dashboard-header">

            <div>

                <span class="hero-badge">
                    Employer Portal
                </span>

                <h1>Edit Job</h1>

                <p>
                    Update your job listing.
                </p>

            </div>

        </div>


        <nav class="candidate-dashboard-nav">

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/employer-dashboard/')
                ); ?>"
            >
                My Jobs
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/employer-post-job/')
                ); ?>"
            >
                Post Job
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/employer-applicants/')
                ); ?>"
            >
                Applicants
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/employer-company-profile/')
                ); ?>"
            >
                Company Profile
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    wp_logout_url(
                        home_url('/employer-login/')
                    )
                ); ?>"
            >
                Sign Out
            </a>

        </nav>


        <?php if ($error === 'missing_fields') : ?>

            <div class="devhire-notice error">
                Job title and description are required.
            </div>

        <?php elseif ($error === 'update_failed') : ?>

            <div class="devhire-notice error">
                Unable to update the job.
            </div>

        <?php endif; ?>


        <?php
        $company_id = devhire_get_employer_company_id($user->ID);

        if ($company_id) :

            $company_name = get_the_title($company_id);

            $company_location = get_post_meta(
                $company_id,
                '_devhire_company_location',
                true
            );

            $company_industry = get_post_meta(
                $company_id,
                '_devhire_company_industry',
                true
            );
        ?>

            <div class="employer-company-summary">

                <div class="employer-company-summary-logo" aria-hidden="true">
                    <?php if (has_post_thumbnail($company_id)) : ?>
                        <?php
                        echo get_the_post_thumbnail(
                            $company_id,
                            'thumbnail',
                            [
                                'class' => 'company-logo-image',
                                'alt'   => '',
                            ]
                        );
                        ?>
                    <?php else : ?>
                        <span class="company-logo-fallback">
                            <?php echo esc_html(
                                strtoupper(substr($company_name, 0, 1))
                            ); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="employer-company-summary-content">
                    <span class="application-job-label">
                        Company
                    </span>

                    <h2>
                        <?php echo esc_html($company_name); ?>
                    </h2>

                    <?php if (
                        $company_industry ||
                        $company_location
                    ) : ?>

                        <p>
                            <?php
                            echo esc_html(
                                implode(
                                    ' · ',
                                    array_filter([
                                        $company_industry,
                                        $company_location,
                                    ])
                                )
                            );
                            ?>
                        </p>

                    <?php endif; ?>
                </div>

                <div class="application-card-actions">

                    <a
                        class="secondary-button"
                        href="<?php echo esc_url(
                            home_url('/employer-company-profile/')
                        ); ?>"
                    >
                        Edit Company
                    </a>

                </div>

            </div>

        <?php endif; ?>

        <form
            class="candidate-profile-form employer-job-form"
            method="post"
            action="<?php echo esc_url(
                admin_url('admin-post.php')
            ); ?>"
        >

            <input
                type="hidden"
                name="action"
                value="devhire_employer_update_job"
            >

            <input
                type="hidden"
                name="job_id"
                value="<?php echo esc_attr($job_id); ?>"
            >

            <?php
            wp_nonce_field(
                'devhire_update_job_' . $job_id,
                'devhire_update_job_nonce'
            );
            ?>


            <div class="form-field">

                <label for="job-title">
                    Job Title
                </label>

                <input
                    id="job-title"
                    name="job_title"
                    type="text"
                    value="<?php
                    echo esc_attr($job->post_title);
                    ?>"
                    required
                >

            </div>


            <div class="form-field">

                <label for="job-description">
                    Job Description
                </label>

                <textarea
                    id="job-description"
                    name="job_description"
                    rows="10"
                    required
                ><?php
                    echo esc_textarea($job->post_content);
                ?></textarea>

            </div>


            <div class="form-field">

                <label for="salary">
                    Salary
                </label>

                <input
                    id="salary"
                    name="salary"
                    type="text"
                    value="<?php echo esc_attr($salary); ?>"
                >

            </div>


            <div class="form-field">

                <label for="experience">
                    Experience
                </label>

                <input
                    id="experience"
                    name="experience"
                    type="text"
                    value="<?php
                    echo esc_attr($experience);
                    ?>"
                >

            </div>


            <div class="form-field">

                <label for="job-type">
                    Job Type
                </label>

                <select
                    id="job-type"
                    name="job_type"
                >

                    <option value="">
                        Select Job Type
                    </option>

                    <?php if (!is_wp_error($job_types)) : ?>

                        <?php foreach ($job_types as $type) : ?>

                            <option
                                value="<?php
                                echo esc_attr($type->term_id);
                                ?>"
                                <?php
                                selected(
                                    $selected_job_type,
                                    $type->term_id
                                );
                                ?>
                            >
                                <?php echo esc_html($type->name); ?>
                            </option>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </select>

            </div>


            <div class="form-field">

                <label for="job-location">
                    Location
                </label>

                <select
                    id="job-location"
                    name="location"
                >

                    <option value="">
                        Select Location
                    </option>

                    <?php if (!is_wp_error($locations)) : ?>

                        <?php foreach ($locations as $location) : ?>

                            <option
                                value="<?php
                                echo esc_attr($location->term_id);
                                ?>"
                                <?php
                                selected(
                                    $selected_location,
                                    $location->term_id
                                );
                                ?>
                            >
                                <?php echo esc_html($location->name); ?>
                            </option>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </select>

            </div>


            <div class="form-field">

                <label>Skills</label>

                <div class="employer-skills-grid">

                    <?php if (!is_wp_error($skills)) : ?>

                        <?php foreach ($skills as $skill) : ?>

                            <label class="employer-skill-option">

                                <input
                                    type="checkbox"
                                    name="skills[]"
                                    value="<?php
                                    echo esc_attr(
                                        $skill->term_id
                                    );
                                    ?>"
                                    <?php
                                    checked(
                                        in_array(
                                            $skill->term_id,
                                            $selected_skills,
                                            true
                                        )
                                    );
                                    ?>
                                >

                                <span>
                                    <?php
                                    echo esc_html($skill->name);
                                    ?>
                                </span>

                            </label>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>


            <div class="form-field">

                <label class="employer-skill-option">

                    <input
                        type="checkbox"
                        name="remote"
                        value="1"
                        <?php checked($remote, '1'); ?>
                    >

                    <span>Remote position</span>

                </label>

            </div>


            <div class="form-field">

                <label for="deadline">
                    Application Deadline
                </label>

                <input
                    id="deadline"
                    name="deadline"
                    type="date"
                    value="<?php echo esc_attr($deadline); ?>"
                >

            </div>


            <div class="form-actions">

                <button
                    type="submit"
                    class="primary-button"
                >
                    Save Changes
                </button>

                <a
                    class="secondary-button"
                    href="<?php echo esc_url(
                        home_url('/employer-dashboard/')
                    ); ?>"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

    <?php

    return ob_get_clean();
}


add_shortcode(
    'devhire_employer_edit_job',
    'devhire_employer_edit_job_shortcode'
);

/**
 * ============================================================
 * Employer - Applicants
 * ============================================================
 */


/**
 * Return a short private employer-note preview.
 */
function devhire_get_employer_note_preview(
    $application_id,
    $length = 95
) {

    $notes = get_post_meta(
        $application_id,
        '_devhire_employer_notes',
        true
    );

    if (!$notes) {
        return '';
    }

    $notes = trim(wp_strip_all_tags($notes));

    if (function_exists('mb_strlen')) {
        if (mb_strlen($notes) <= $length) {
            return $notes;
        }

        return rtrim(
            mb_substr($notes, 0, $length)
        ) . '…';
    }

    if (strlen($notes) <= $length) {
        return $notes;
    }

    return rtrim(substr($notes, 0, $length)) . '…';
}


/**
 * Employer bulk applicant status update - Step 22.28.
 */
function devhire_handle_employer_bulk_application_status() {
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/employer-login/'));
        exit;
    }

    $user = wp_get_current_user();

    if (!in_array('employer', (array) $user->roles, true)) {
        wp_die('You are not allowed to perform this action.');
    }

    check_admin_referer(
        'devhire_bulk_application_status',
        'devhire_bulk_application_nonce'
    );

    $allowed_statuses = [
        'New',
        'Reviewing',
        'Interview',
        'Hired',
        'Rejected',
    ];

    $new_status = isset($_POST['bulk_status'])
        ? sanitize_text_field(wp_unslash($_POST['bulk_status']))
        : '';

    if (!in_array($new_status, $allowed_statuses, true)) {
        wp_safe_redirect(add_query_arg(
            'bulk_error',
            'invalid_status',
            home_url('/employer-applicants/')
        ));
        exit;
    }

    $ids = isset($_POST['application_ids'])
        ? array_map('absint', (array) wp_unslash($_POST['application_ids']))
        : [];
    $ids = array_values(array_unique(array_filter($ids)));

    if (!$ids) {
        wp_safe_redirect(add_query_arg(
            'bulk_error',
            'no_selection',
            home_url('/employer-applicants/')
        ));
        exit;
    }

    $updated = 0;

    foreach ($ids as $application_id) {
        if (get_post_type($application_id) !== 'job_application') {
            continue;
        }

        $job_id = (int) get_post_meta(
            $application_id,
            '_devhire_application_job',
            true
        );

        if (
            !$job_id ||
            (int) get_post_field('post_author', $job_id) !== (int) $user->ID
        ) {
            continue;
        }

        $old_status = get_post_meta(
            $application_id,
            '_devhire_application_status',
            true
        );

        if (!$old_status) {
            $old_status = 'New';
        }

        if ($old_status === $new_status) {
            continue;
        }

        update_post_meta(
            $application_id,
            '_devhire_application_status',
            $new_status
        );

        $history = get_post_meta(
            $application_id,
            '_devhire_application_history',
            true
        );

        if (!is_array($history)) {
            $history = [];
        }

        if (!$history) {
            $history[] = [
                'status' => $old_status,
                'timestamp' => get_post_time('U', true, $application_id),
                'user_id' => 0,
            ];
        }

        $history[] = [
            'status' => $new_status,
            'timestamp' => current_time('timestamp'),
            'user_id' => (int) $user->ID,
        ];

        update_post_meta(
            $application_id,
            '_devhire_application_history',
            $history
        );

        $updated++;
    }

    $args = ['bulk_updated' => $updated];

    foreach (
        ['status','job_id','applicant_search','sort','applicant_page']
        as $field
    ) {
        if (isset($_POST[$field]) && $_POST[$field] !== '') {
            $args[$field] = sanitize_text_field(
                wp_unslash($_POST[$field])
            );
        }
    }

    wp_safe_redirect(add_query_arg(
        $args,
        home_url('/employer-applicants/')
    ));
    exit;
}

add_action(
    'admin_post_devhire_employer_bulk_application_status',
    'devhire_handle_employer_bulk_application_status'
);


/**
 * Return a compact human-readable application age.
 * Step 22.36.
 */
function devhire_get_application_age($application_id) {

    $submitted_timestamp = (int) get_post_time(
        'U',
        true,
        $application_id
    );

    if (!$submitted_timestamp) {
        return '';
    }

    $current_timestamp = (int) current_time('timestamp');
    $difference = max(
        0,
        $current_timestamp - $submitted_timestamp
    );

    if ($difference < HOUR_IN_SECONDS) {
        return 'Just now';
    }

    if ($difference < DAY_IN_SECONDS) {
        $hours = max(
            1,
            (int) floor($difference / HOUR_IN_SECONDS)
        );

        return sprintf(
            _n(
                '%d hour ago',
                '%d hours ago',
                $hours,
                'devhire'
            ),
            $hours
        );
    }

    $days = max(
        1,
        (int) floor($difference / DAY_IN_SECONDS)
    );

    if ($days < 30) {
        return sprintf(
            _n(
                '%d day ago',
                '%d days ago',
                $days,
                'devhire'
            ),
            $days
        );
    }

    return human_time_diff(
        $submitted_timestamp,
        $current_timestamp
    ) . ' ago';
}


/**
 * Return time spent in the application's current status.
 * Step 22.38.
 */
function devhire_get_application_status_age($application_id) {

    $current_status = get_post_meta(
        $application_id,
        '_devhire_application_status',
        true
    );

    if (!$current_status) {
        $current_status = 'New';
    }

    $history = get_post_meta(
        $application_id,
        '_devhire_application_history',
        true
    );

    $status_timestamp = 0;

    if (is_array($history) && $history) {
        for ($index = count($history) - 1; $index >= 0; $index--) {
            $entry = $history[$index];

            if (
                isset($entry['status'], $entry['timestamp']) &&
                $entry['status'] === $current_status
            ) {
                $status_timestamp = (int) $entry['timestamp'];
                break;
            }
        }
    }

    if (!$status_timestamp) {
        $status_timestamp = (int) get_post_time(
            'U',
            true,
            $application_id
        );
    }

    if (!$status_timestamp) {
        return '';
    }

    return human_time_diff(
        $status_timestamp,
        (int) current_time('timestamp')
    );
}


/**
 * Determine whether an application needs follow-up.
 * Step 22.39.
 */
function devhire_application_needs_attention($application_id) {

    $status = get_post_meta(
        $application_id,
        '_devhire_application_status',
        true
    );

    if (!$status) {
        $status = 'New';
    }

    if (!in_array(
        $status,
        ['New', 'Reviewing', 'Interview'],
        true
    )) {
        return false;
    }

    $history = get_post_meta(
        $application_id,
        '_devhire_application_history',
        true
    );

    $status_timestamp = 0;

    if (is_array($history) && $history) {
        for ($index = count($history) - 1; $index >= 0; $index--) {
            $entry = $history[$index];

            if (
                isset($entry['status'], $entry['timestamp']) &&
                $entry['status'] === $status
            ) {
                $status_timestamp = (int) $entry['timestamp'];
                break;
            }
        }
    }

    if (!$status_timestamp) {
        $status_timestamp = (int) get_post_time(
            'U',
            true,
            $application_id
        );
    }

    if (!$status_timestamp) {
        return false;
    }

    $age = (int) current_time('timestamp') - $status_timestamp;

    return $age >= (3 * DAY_IN_SECONDS);
}


/**
 * Return the latest application pipeline activity timestamp.
 * Step 22.42.
 */
function devhire_get_application_last_activity($application_id) {

    $history = get_post_meta(
        $application_id,
        '_devhire_application_history',
        true
    );

    $latest_timestamp = 0;

    if (is_array($history)) {
        foreach ($history as $entry) {
            if (!empty($entry['timestamp'])) {
                $latest_timestamp = max(
                    $latest_timestamp,
                    (int) $entry['timestamp']
                );
            }
        }
    }

    $notes_updated = (int) get_post_meta(
        $application_id,
        '_devhire_employer_notes_updated',
        true
    );

    $latest_timestamp = max(
        $latest_timestamp,
        $notes_updated
    );

    if (!$latest_timestamp) {
        $latest_timestamp = (int) get_post_time(
            'U',
            true,
            $application_id
        );
    }

    return $latest_timestamp;
}


function devhire_employer_applicants_shortcode() {

    if (!is_user_logged_in()) {
        return sprintf(
            '<div class="devhire-notice error">
                Please <a href="%s">sign in as an employer</a>.
            </div>',
            esc_url(home_url('/employer-login/'))
        );
    }

    $user = wp_get_current_user();

    $allowed_filters = [
        'all',
        'new',
        'reviewing',
        'interview',
        'hired',
        'rejected',
        'attention',
    ];

    $current_filter = isset($_GET['status'])
        ? sanitize_key(wp_unslash($_GET['status']))
        : 'all';

    if (!in_array($current_filter, $allowed_filters, true)) {
        $current_filter = 'all';
    }

    $current_job = isset($_GET['job_id'])
        ? absint($_GET['job_id'])
        : 0;


    $applicant_search = isset($_GET['applicant_search'])
        ? sanitize_text_field(
            wp_unslash($_GET['applicant_search'])
        )
        : '';


    $allowed_sorts = [
        'newest',
        'oldest',
        'name_asc',
        'name_desc',
        'attention',
        'activity',
    ];

    $current_sort = isset($_GET['sort'])
        ? sanitize_key(wp_unslash($_GET['sort']))
        : 'newest';

    if (!in_array($current_sort, $allowed_sorts, true)) {
        $current_sort = 'newest';
    }


    $applicants_per_page = 10;

    $current_page = isset($_GET['applicant_page'])
        ? max(1, absint($_GET['applicant_page']))
        : 1;

    if (!in_array('employer', (array) $user->roles, true)) {
        return '<div class="devhire-notice error">
            Only employer accounts can view applicants.
        </div>';
    }

    /*
     * Get IDs of jobs owned by this employer.
     */
    $job_ids = get_posts([
        'post_type'      => 'job',
        'post_status'    => ['publish', 'draft', 'pending'],
        'author'         => $user->ID,
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    if (
        $current_job &&
        !in_array($current_job, array_map('intval', $job_ids), true)
    ) {
        $current_job = 0;
    }

    /*
     * Get applications belonging only to those jobs.
     */
    $applications = null;

    if ($job_ids) {

        $applications = new WP_Query([
            'post_type'      => 'job_application',
            'post_status'    => 'private',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',

            'meta_query' => [
                [
                    'key'     => '_devhire_application_job',
                    'value'   => $job_ids,
                    'compare' => 'IN',
                    'type'    => 'NUMERIC',
                ],
            ],
        ]);
    }

    $total_applicants = 0;
    $new_applicants = 0;
    $reviewing_applicants = 0;
    $interview_applicants = 0;
    $hired_applicants = 0;
    $rejected_applicants = 0;
    $attention_applicants = 0;

    if ($applications && $applications->have_posts()) {

        foreach ($applications->posts as $application) {

            $total_applicants++;

            $application_status = get_post_meta(
                $application->ID,
                '_devhire_application_status',
                true
            );

            if (!$application_status) {
                $application_status = 'New';
            }

            switch ($application_status) {

                case 'New':
                    $new_applicants++;
                    break;

                case 'Reviewing':
                    $reviewing_applicants++;
                    break;

                case 'Interview':
                    $interview_applicants++;
                    break;

                case 'Hired':
                    $hired_applicants++;
                    break;

                case 'Rejected':
                    $rejected_applicants++;
                    break;
            }

            if (devhire_application_needs_attention($application->ID)) {
                $attention_applicants++;
            }
        }
    }

    ob_start();

    $bulk_updated = isset($_GET['bulk_updated'])
        ? absint($_GET['bulk_updated'])
        : null;

    $bulk_error = isset($_GET['bulk_error'])
        ? sanitize_key($_GET['bulk_error'])
        : '';

    ?>

    <div class="candidate-dashboard employer-dashboard">

        <?php if ($bulk_updated !== null) : ?>
            <div class="dashboard-notice success">
                <?php echo esc_html(
                    $bulk_updated > 0
                        ? sprintf(
                            _n(
                                '%d applicant updated successfully.',
                                '%d applicants updated successfully.',
                                $bulk_updated,
                                'devhire'
                            ),
                            $bulk_updated
                        )
                        : 'No applicant statuses needed to be changed.'
                ); ?>
            </div>
        <?php endif; ?>

        <?php if ($bulk_error === 'no_selection') : ?>
            <div class="dashboard-notice error">
                Select at least one applicant before applying a bulk action.
            </div>
        <?php elseif ($bulk_error === 'invalid_status') : ?>
            <div class="dashboard-notice error">
                Please choose a valid bulk status.
            </div>
        <?php endif; ?>


        <div class="candidate-dashboard-header">

            <div>

                <span class="hero-badge">
                    Employer Portal
                </span>

                <h1>Applicants</h1>

                <p>
                    Review candidates who applied to your jobs.
                </p>

            </div>

        </div>


        <nav class="candidate-dashboard-nav">

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/employer-dashboard/')
                ); ?>"
            >
                My Jobs
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/employer-post-job/')
                ); ?>"
            >
                Post Job
            </a>

            <a
                class="candidate-nav-link active"
                href="<?php echo esc_url(
                    home_url('/employer-applicants/')
                ); ?>"
            >
                Applicants
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/employer-company-profile/')
                ); ?>"
            >
                Company Profile
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    wp_logout_url(
                        home_url('/employer-login/')
                    )
                ); ?>"
            >
                Sign Out
            </a>

        </nav>

        <?php
        $pipeline_stats = [
            'all' => [
                'label' => 'Total Applicants',
                'count' => $total_applicants,
            ],
            'new' => [
                'label' => 'New',
                'count' => $new_applicants,
            ],
            'reviewing' => [
                'label' => 'Reviewing',
                'count' => $reviewing_applicants,
            ],
            'interview' => [
                'label' => 'Interviews',
                'count' => $interview_applicants,
            ],
            'hired' => [
                'label' => 'Hired',
                'count' => $hired_applicants,
            ],
            'rejected' => [
                'label' => 'Rejected',
                'count' => $rejected_applicants,
            ],
            'attention' => [
                'label' => 'Needs Attention',
                'count' => $attention_applicants,
            ],
        ];
        ?>

        <div class="dashboard-stats applicant-pipeline-stats">

            <?php foreach (
                $pipeline_stats as $pipeline_key => $pipeline_stat
            ) :

                $pipeline_args = [];

                if ($pipeline_key !== 'all') {
                    $pipeline_args['status'] = $pipeline_key;
                }

                if ($current_job) {
                    $pipeline_args['job_id'] = $current_job;
                }

                if ($applicant_search !== '') {
                    $pipeline_args['applicant_search'] =
                        $applicant_search;
                }

                if ($current_sort !== 'newest') {
                    $pipeline_args['sort'] = $current_sort;
                }

                $pipeline_url = $pipeline_args
                    ? add_query_arg(
                        $pipeline_args,
                        home_url('/employer-applicants/')
                    )
                    : home_url('/employer-applicants/');
                ?>

                <a
                    class="dashboard-stat applicant-pipeline-stat <?php
                    echo $current_filter === $pipeline_key
                        ? 'active'
                        : '';
                    ?>"
                    href="<?php echo esc_url($pipeline_url); ?>"
                >
                    <span>
                        <?php echo esc_html(
                            $pipeline_stat['label']
                        ); ?>
                    </span>

                    <strong>
                        <?php echo esc_html(
                            $pipeline_stat['count']
                        ); ?>
                    </strong>
                </a>

            <?php endforeach; ?>

        </div>

        <?php
        if (
            $applications &&
            $applications->have_posts()
        ) :
        ?>
            <form
                method="get"
                action="<?php echo esc_url(
                    home_url('/employer-applicants/')
                ); ?>"
                class="applicant-job-filter"
            >

                <?php if ($current_filter !== 'all') : ?>
                    <input
                        type="hidden"
                        name="status"
                        value="<?php echo esc_attr($current_filter); ?>"
                    >
                <?php endif; ?>

                <div class="applicant-search-field">

                    <label for="applicant-search">
                        Search applicants
                    </label>

                    <input
                        id="applicant-search"
                        type="search"
                        name="applicant_search"
                        value="<?php echo esc_attr(
                            $applicant_search
                        ); ?>"
                        placeholder="Name, email, title, or location"
                    >

                </div>

                <button
                    class="secondary-button applicant-search-button"
                    type="submit"
                >
                    Search
                </button>

                <?php if ($applicant_search !== '') : ?>
                    <a
                        class="applicant-search-clear"
                        href="<?php echo esc_url(
                            add_query_arg(
                                array_filter([
                                    'status' => $current_filter !== 'all'
                                        ? $current_filter
                                        : null,
                                    'job_id' => $current_job ?: null,
                                    'sort' => $current_sort !== 'newest'
                                        ? $current_sort
                                        : null,
                                ]),
                                home_url('/employer-applicants/')
                            )
                        ); ?>"
                    >
                        Clear Search
                    </a>
                <?php endif; ?>

                <label for="applicant-job-filter">
                    Job
                </label>

                <select
                    id="applicant-job-filter"
                    name="job_id"
                    onchange="this.form.submit()"
                >
                    <option value="0">
                        All Jobs
                    </option>

                    <?php foreach ($job_ids as $employer_job_id) : ?>

                        <option
                            value="<?php echo esc_attr($employer_job_id); ?>"
                            <?php selected(
                                $current_job,
                                $employer_job_id
                            ); ?>
                        >
                            <?php echo esc_html(
                                get_the_title($employer_job_id)
                            ); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <label for="applicant-sort">
                    Sort
                </label>

                <select
                    id="applicant-sort"
                    name="sort"
                    onchange="this.form.submit()"
                >
                    <option
                        value="newest"
                        <?php selected(
                            $current_sort,
                            'newest'
                        ); ?>
                    >
                        Newest First
                    </option>

                    <option
                        value="oldest"
                        <?php selected(
                            $current_sort,
                            'oldest'
                        ); ?>
                    >
                        Oldest First
                    </option>

                    <option
                        value="name_asc"
                        <?php selected(
                            $current_sort,
                            'name_asc'
                        ); ?>
                    >
                        Name A-Z
                    </option>

                    <option
                        value="name_desc"
                        <?php selected(
                            $current_sort,
                            'name_desc'
                        ); ?>
                    >
                        Name Z-A
                    </option>

                    <option
                        value="attention"
                        <?php selected(
                            $current_sort,
                            'attention'
                        ); ?>
                    >
                        Needs Attention First
                    </option>

                    <option
                        value="activity"
                        <?php selected(
                            $current_sort,
                            'activity'
                        ); ?>
                    >
                        Recent Activity
                    </option>
                </select>

            </form>

            <div class="applicant-filters">

                <?php
                $filters = [
                    'all'       => 'All',
                    'new'       => 'New',
                    'reviewing' => 'Reviewing',
                    'interview' => 'Interview',
                    'hired'     => 'Hired',
                    'rejected'  => 'Rejected',
                    'attention' => 'Needs Attention',
                ];

                foreach ($filters as $filter_key => $filter_label) :

                    $filter_args = [];

                    if ($filter_key !== 'all') {
                        $filter_args['status'] = $filter_key;
                    }

                    if ($current_job) {
                        $filter_args['job_id'] = $current_job;
                    }

                    if ($applicant_search !== '') {
                        $filter_args['applicant_search'] =
                            $applicant_search;
                    }

                    if ($current_sort !== 'newest') {
                        $filter_args['sort'] = $current_sort;
                    }

                    $filter_url = $filter_args
                        ? add_query_arg(
                            $filter_args,
                            home_url('/employer-applicants/')
                        )
                        : home_url('/employer-applicants/');
                    ?>

                    <a
                        class="applicant-filter <?php
                        echo $current_filter === $filter_key
                            ? 'active'
                            : '';
                        ?>"
                        href="<?php echo esc_url($filter_url); ?>"
                    >
                        <?php echo esc_html($filter_label); ?>
                    </a>

                <?php endforeach; ?>

            </div>
            
            <form
                class="applicant-bulk-form"
                method="post"
                action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
            >
                <input type="hidden" name="action"
                    value="devhire_employer_bulk_application_status">
                <?php wp_nonce_field(
                    'devhire_bulk_application_status',
                    'devhire_bulk_application_nonce'
                ); ?>

                <input type="hidden" name="status"
                    value="<?php echo esc_attr($current_filter); ?>">
                <input type="hidden" name="job_id"
                    value="<?php echo esc_attr($current_job); ?>">
                <input type="hidden" name="applicant_search"
                    value="<?php echo esc_attr($applicant_search); ?>">
                <input type="hidden" name="sort"
                    value="<?php echo esc_attr($current_sort); ?>">
                <input type="hidden" name="applicant_page"
                    value="<?php echo esc_attr($current_page); ?>">

                <div class="applicant-bulk-toolbar">
                    <div class="applicant-bulk-selection">
                        <label class="applicant-select-all">
                            <input type="checkbox"
                                class="applicant-select-all-input">
                            <span>Select page</span>
                        </label>

                        <span
                            class="applicant-selected-count"
                            aria-live="polite"
                        >
                            0 selected
                        </span>

                        <button
                            type="button"
                            class="applicant-clear-selection"
                            hidden
                        >
                            Clear selection
                        </button>
                    </div>

                    <div class="applicant-bulk-controls">
                        <label for="bulk-status">Bulk action</label>
                        <select id="bulk-status" name="bulk_status" required>
                            <option value="">Change status...</option>
                            <option value="New">New</option>
                            <option value="Reviewing">Reviewing</option>
                            <option value="Interview">Interview</option>
                            <option value="Hired">Hired</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                        <button type="submit"
                            class="primary-button applicant-bulk-apply">
                            Apply
                        </button>
                    </div>
                </div>

                <div class="application-list">

                <?php
                if (
                    $applications &&
                    !empty($applications->posts)
                ) {
                    if ($current_sort === 'oldest') {
                        usort(
                            $applications->posts,
                            static function ($a, $b) {
                                return strcmp(
                                    $a->post_date,
                                    $b->post_date
                                );
                            }
                        );
                    } elseif (
                        $current_sort === 'name_asc' ||
                        $current_sort === 'name_desc'
                    ) {
                        usort(
                            $applications->posts,
                            static function ($a, $b) use (
                                $current_sort
                            ) {
                                $name_a = (string) get_post_meta(
                                    $a->ID,
                                    '_devhire_applicant_name',
                                    true
                                );

                                $name_b = (string) get_post_meta(
                                    $b->ID,
                                    '_devhire_applicant_name',
                                    true
                                );

                                $comparison = strcasecmp(
                                    $name_a,
                                    $name_b
                                );

                                return $current_sort === 'name_desc'
                                    ? -$comparison
                                    : $comparison;
                            }
                        );
                    } elseif ($current_sort === 'attention') {
                        usort(
                            $applications->posts,
                            static function ($a, $b) {
                                $attention_a =
                                    devhire_application_needs_attention(
                                        $a->ID
                                    );

                                $attention_b =
                                    devhire_application_needs_attention(
                                        $b->ID
                                    );

                                if ($attention_a !== $attention_b) {
                                    return $attention_a ? -1 : 1;
                                }

                                return strcmp(
                                    $b->post_date,
                                    $a->post_date
                                );
                            }
                        );
                    } elseif ($current_sort === 'activity') {
                        usort(
                            $applications->posts,
                            static function ($a, $b) {
                                return
                                    devhire_get_application_last_activity(
                                        $b->ID
                                    )
                                    <=>
                                    devhire_get_application_last_activity(
                                        $a->ID
                                    );
                            }
                        );
                    }
                }

                $filtered_application_ids = [];

                foreach ($applications->posts as $application_post) {

                    $filter_id = (int) $application_post->ID;
                    $filter_job_id = (int) get_post_meta(
                        $filter_id,
                        '_devhire_application_job',
                        true
                    );
                    $filter_status = get_post_meta(
                        $filter_id,
                        '_devhire_application_status',
                        true
                    );

                    if (!$filter_status) {
                        $filter_status = 'New';
                    }

                    if (
                        $current_job &&
                        $filter_job_id !== $current_job
                    ) {
                        continue;
                    }

                    if ($current_filter === 'attention') {
                        if (
                            !devhire_application_needs_attention(
                                $filter_id
                            )
                        ) {
                            continue;
                        }
                    } elseif (
                        $current_filter !== 'all' &&
                        strtolower($filter_status) !==
                        $current_filter
                    ) {
                        continue;
                    }

                    if ($applicant_search !== '') {
                        $filter_name = get_post_meta(
                            $filter_id,
                            '_devhire_applicant_name',
                            true
                        );
                        $filter_email = get_post_meta(
                            $filter_id,
                            '_devhire_applicant_email',
                            true
                        );
                        $filter_title = get_post_meta(
                            $filter_id,
                            '_devhire_candidate_title',
                            true
                        );
                        $filter_location = '';
                        $filter_user_id = (int) get_post_meta(
                            $filter_id,
                            '_devhire_candidate_user',
                            true
                        );

                        if ($filter_user_id) {
                            $filter_location = get_user_meta(
                                $filter_user_id,
                                '_devhire_location',
                                true
                            );
                        }

                        if (
                            stripos(
                                implode(
                                    ' ',
                                    [
                                        $filter_name,
                                        $filter_email,
                                        $filter_title,
                                        $filter_location,
                                    ]
                                ),
                                $applicant_search
                            ) === false
                        ) {
                            continue;
                        }
                    }

                    $filtered_application_ids[] = $filter_id;
                }

                $total_visible_applicants =
                    count($filtered_application_ids);

                $total_applicant_pages = max(
                    1,
                    (int) ceil(
                        $total_visible_applicants /
                        $applicants_per_page
                    )
                );

                $current_page = min(
                    $current_page,
                    $total_applicant_pages
                );

                $page_application_ids = array_slice(
                    $filtered_application_ids,
                    ($current_page - 1) * $applicants_per_page,
                    $applicants_per_page
                );

                $result_start = $total_visible_applicants > 0
                    ? (($current_page - 1) * $applicants_per_page) + 1
                    : 0;

                $result_end = $total_visible_applicants > 0
                    ? min(
                        $result_start +
                        count($page_application_ids) - 1,
                        $total_visible_applicants
                    )
                    : 0;

                $visible_applicants = 0;

                ?>

                <div class="applicant-results-summary">

                    <p>
                        <?php if ($total_visible_applicants > 0) : ?>
                            Showing
                            <strong>
                                <?php echo esc_html($result_start); ?>
                            </strong>
                            –
                            <strong>
                                <?php echo esc_html($result_end); ?>
                            </strong>
                            of
                            <strong>
                                <?php echo esc_html(
                                    $total_visible_applicants
                                ); ?>
                            </strong>
                            <?php echo esc_html(
                                _n(
                                    'applicant',
                                    'applicants',
                                    $total_visible_applicants,
                                    'devhire'
                                )
                            ); ?>
                        <?php else : ?>
                            No applicants match the current filters.
                        <?php endif; ?>
                    </p>

                    <?php if (
                        $applicant_search !== '' ||
                        $current_job ||
                        $current_filter !== 'all'
                    ) : ?>

                        <span class="applicant-results-filtered">
                            Filtered results
                        </span>

                    <?php endif; ?>

                </div>

                <?php

                while ($applications->have_posts()) :
                    $applications->the_post();

                    $application_id = get_the_ID();

                    if (
                        !in_array(
                            $application_id,
                            $page_application_ids,
                            true
                        )
                    ) {
                        continue;
                    }

                    $job_id = (int) get_post_meta(
                        $application_id,
                        '_devhire_application_job',
                        true
                    );

                    $name = get_post_meta(
                        $application_id,
                        '_devhire_applicant_name',
                        true
                    );

                    $email = get_post_meta(
                        $application_id,
                        '_devhire_applicant_email',
                        true
                    );

                    $candidate_title = get_post_meta(
                        $application_id,
                        '_devhire_candidate_title',
                        true
                    );

                    $candidate_location = '';

                    $candidate_user_id = (int) get_post_meta(
                        $application_id,
                        '_devhire_candidate_user',
                        true
                    );

                    if ($candidate_user_id) {
                        $candidate_location = get_user_meta(
                            $candidate_user_id,
                            '_devhire_location',
                            true
                        );
                    }

                    $applied_date = get_the_date(
                        get_option('date_format'),
                        $application_id
                    );

                    $status = get_post_meta(
                        $application_id,
                        '_devhire_application_status',
                        true
                    );

                    if (!$status) {
                        $status = 'New';
                    }


                    $employer_note_preview =
                        devhire_get_employer_note_preview(
                            $application_id
                        );

                    $employer_note_updated = (int) get_post_meta(
                        $application_id,
                        '_devhire_employer_notes_updated',
                        true
                    );

                    if (
                        $current_job &&
                        $job_id !== $current_job
                    ) {
                        continue;
                    }

                    if (
                        $current_filter !== 'all' &&
                        strtolower($status) !== $current_filter
                    ) {
                        continue;
                    }

                    if ($applicant_search !== '') {

                        $search_haystack = implode(
                            ' ',
                            [
                                $name,
                                $email,
                                $candidate_title,
                                $candidate_location,
                            ]
                        );

                        if (
                            stripos(
                                $search_haystack,
                                $applicant_search
                            ) === false
                        ) {
                            continue;
                        }
                    }

                    $visible_applicants++;

                    $job_title = get_the_title($job_id);

                    $application_age =
                        devhire_get_application_age(
                            $application_id
                        );

                    $status_age =
                        devhire_get_application_status_age(
                            $application_id
                        );

                    $last_activity_timestamp =
                        devhire_get_application_last_activity(
                            $application_id
                        );

                    $last_activity_age =
                        $last_activity_timestamp
                            ? human_time_diff(
                                $last_activity_timestamp,
                                (int) current_time('timestamp')
                            ) . ' ago'
                            : '';

                    $needs_attention =
                        devhire_application_needs_attention(
                            $application_id
                        );

                    $resume_id = (int) get_post_meta(
                        $application_id,
                        '_devhire_applicant_resume_id',
                        true
                    );

                    $resume_url = $resume_id
                        ? wp_get_attachment_url($resume_id)
                        : '';

                    if (!$resume_url) {
                        $resume_url = get_post_meta(
                            $application_id,
                            '_devhire_applicant_resume',
                            true
                        );
                    }

                    $applicant_email = sanitize_email(
                        get_post_meta(
                            $application_id,
                            '_devhire_applicant_email',
                            true
                        )
                    );

                    $applicant_phone = sanitize_text_field(
                        get_post_meta(
                            $application_id,
                            '_devhire_applicant_phone',
                            true
                        )
                    );

                    $applicant_linkedin = esc_url_raw(
                        get_post_meta(
                            $application_id,
                            '_devhire_applicant_linkedin',
                            true
                        )
                    );

                    $email_subject = sprintf(
                        'Your application for %s',
                        get_the_title($job_id)
                    );

                    $email_url = $applicant_email
                        ? 'mailto:' . $applicant_email .
                            '?subject=' . rawurlencode($email_subject)
                        : '';

                    $profile_fields = [
                        $applicant_email,
                        $applicant_phone,
                        $applicant_linkedin,
                        $candidate_title,
                        $candidate_location,
                        $resume_url,
                    ];

                    $completed_profile_fields = count(
                        array_filter(
                            $profile_fields,
                            static function ($value) {
                                return !empty($value);
                            }
                        )
                    );

                    $profile_completeness = (int) round(
                        (
                            $completed_profile_fields /
                            count($profile_fields)
                        ) * 100
                    );

                    $candidate_profile_url = $candidate_user_id
                        ? add_query_arg(
                            'candidate_id',
                            $candidate_user_id,
                            home_url(
                                '/employer-candidate-profile/'
                            )
                        )
                        : '';
                    ?>

                    <article class="application-card applicant-card-v2">

                        <div class="applicant-card-v2-header">

                            <label
                                class="applicant-card-select"
                                aria-label="<?php echo esc_attr(
                                    'Select this application'
                                ); ?>"
                            >
                                <input
                                    type="checkbox"
                                    name="application_ids[]"
                                    value="<?php echo esc_attr(
                                        $application_id
                                    ); ?>"
                                    class="applicant-select-input"
                                >
                            </label>

                            <div class="applicant-card-v2-job">
                                <span class="application-job-label">
                                    Applied for
                                    <strong>
                                        <?php echo esc_html(
                                            $job_title
                                        ); ?>
                                    </strong>
                                </span>
                            </div>

                            <?php if ($application_age) : ?>
                                <span
                                    class="application-age"
                                    title="<?php echo esc_attr(
                                        get_the_date(
                                            'F j, Y g:i a',
                                            $application_id
                                        )
                                    ); ?>"
                                >
                                    <?php echo esc_html(
                                        $application_age
                                    ); ?>
                                </span>
                            <?php endif; ?>

                        </div>

                        <div class="applicant-card-v2-main">

                            <div class="applicant-card-v2-candidate">

                                <h3>
                                    <?php echo esc_html($name); ?>
                                </h3>

                                <p class="applicant-card-v2-email">
                                    <?php echo esc_html($email); ?>
                                </p>

                                <?php if (
                                    $candidate_title ||
                                    $candidate_location
                                ) : ?>
                                    <p class="application-candidate-meta">
                                        <?php
                                        echo esc_html(
                                            implode(
                                                ' · ',
                                                array_filter([
                                                    $candidate_title,
                                                    $candidate_location,
                                                ])
                                            )
                                        );
                                        ?>
                                    </p>
                                <?php endif; ?>

                                <span class="application-applied-date">
                                    Applied <?php echo esc_html(
                                        $applied_date
                                    ); ?>
                                </span>

                            </div>

                            <div class="applicant-card-v2-workflow">

                                <div class="applicant-card-v2-status">
                                    <span class="application-status">
                                        <?php echo esc_html(
                                            $status
                                        ); ?>
                                    </span>

                                    <?php if ($status_age) : ?>
                                        <span
                                            class="application-status-age"
                                            title="<?php echo esc_attr(
                                                sprintf(
                                                    'Time in %s status',
                                                    $status
                                                )
                                            ); ?>"
                                        >
                                            <?php echo esc_html(
                                                $status_age
                                            ); ?>
                                            in status
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($needs_attention) : ?>
                                        <span
                                            class="application-attention-badge"
                                            title="This applicant has remained in the current status for at least 3 days."
                                        >
                                            Needs attention
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="applicant-card-v2-actions">

                                    <?php if ($email_url) : ?>
                                        <a
                                            class="secondary-button applicant-email-button"
                                            href="<?php echo esc_attr(
                                                $email_url
                                            ); ?>"
                                        >
                                            Email Candidate
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($resume_url) : ?>
                                        <a
                                            class="secondary-button applicant-resume-button"
                                            href="<?php echo esc_url(
                                                $resume_url
                                            ); ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            View Resume
                                        </a>
                                    <?php endif; ?>

                                    <?php if (
                                        $candidate_profile_url
                                    ) : ?>
                                        <a
                                            class="secondary-button applicant-profile-button"
                                            href="<?php echo esc_url(
                                                $candidate_profile_url
                                            ); ?>"
                                        >
                                            Candidate Profile
                                        </a>
                                    <?php endif; ?>

                                    <a
                                        class="secondary-button"
                                        href="<?php
                                        echo esc_url(
                                            add_query_arg(
                                                'application_id',
                                                $application_id,
                                                home_url(
                                                    '/employer-view-application/'
                                                )
                                            )
                                        );
                                        ?>"
                                    >
                                        View Application
                                    </a>

                                    <?php if (
                                        $applicant_phone ||
                                        $applicant_linkedin
                                    ) : ?>
                                        <details
                                            class="applicant-more-menu"
                                        >
                                            <summary
                                                class="secondary-button"
                                            >
                                                More
                                            </summary>

                                            <div
                                                class="applicant-more-dropdown"
                                            >
                                                <?php if (
                                                    $applicant_phone
                                                ) : ?>
                                                    <a
                                                        href="<?php echo esc_attr(
                                                            'tel:' .
                                                            preg_replace(
                                                                '/[^0-9+]/',
                                                                '',
                                                                $applicant_phone
                                                            )
                                                        ); ?>"
                                                    >
                                                        Call
                                                    </a>
                                                <?php endif; ?>

                                                <?php if (
                                                    $applicant_linkedin
                                                ) : ?>
                                                    <a
                                                        href="<?php echo esc_url(
                                                            $applicant_linkedin
                                                        ); ?>"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        LinkedIn
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                        <div class="applicant-card-v2-footer">

                            <?php if ($employer_note_preview) : ?>
                                <div class="applicant-private-note-preview">
                                    <div class="applicant-private-note-heading">
                                        <span>Private note</span>

                                        <?php if (
                                            $employer_note_updated
                                        ) : ?>
                                            <small>
                                                <?php echo esc_html(
                                                    wp_date(
                                                        get_option(
                                                            'date_format'
                                                        ),
                                                        $employer_note_updated
                                                    )
                                                ); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>

                                    <p>
                                        <?php echo esc_html(
                                            $employer_note_preview
                                        ); ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <div class="applicant-card-v2-meta">

                                <?php if ($last_activity_age) : ?>
                                    <span
                                        class="application-last-activity"
                                        title="<?php echo esc_attr(
                                            wp_date(
                                                'F j, Y g:i a',
                                                $last_activity_timestamp
                                            )
                                        ); ?>"
                                    >
                                        Last activity:
                                        <?php echo esc_html(
                                            $last_activity_age
                                        ); ?>
                                    </span>
                                <?php endif; ?>

                                <span
                                    class="applicant-profile-completeness"
                                    title="Based on email, phone, LinkedIn, professional title, location, and resume."
                                >
                                    Profile:
                                    <strong>
                                        <?php echo esc_html(
                                            $profile_completeness
                                        ); ?>%
                                    </strong>
                                </span>

                            </div>

                        </div>

                    </article>

                <?php endwhile; ?>

                <?php if ($visible_applicants === 0) : ?>

                    <div class="dashboard-empty-state">

                        <h3>No matching applicants</h3>

                        <p>
                            There are currently no applicants with the
                            <strong>
                                <?php echo esc_html(
                                    ucfirst($current_filter)
                                ); ?>
                            </strong>
                            status.
                        </p>

                        <a
                            class="secondary-button"
                            href="<?php echo esc_url(
                                home_url('/employer-applicants/')
                            ); ?>"
                        >
                            View All Applicants
                        </a>

                    </div>

                <?php endif; ?>

            </div>

            <?php if ($total_applicant_pages > 1) : ?>

                <nav
                    class="applicant-pagination"
                    aria-label="Applicant pages"
                >
                    <?php
                    $pagination_args = array_filter([
                        'status' => $current_filter !== 'all'
                            ? $current_filter
                            : null,
                        'job_id' => $current_job ?: null,
                        'applicant_search' =>
                            $applicant_search !== ''
                                ? $applicant_search
                                : null,
                        'sort' => $current_sort !== 'newest'
                            ? $current_sort
                            : null,
                    ]);

                    if ($current_page > 1) :
                        $previous_args = $pagination_args;

                        if (($current_page - 1) > 1) {
                            $previous_args['applicant_page'] =
                                $current_page - 1;
                        }

                        $previous_url = $previous_args
                            ? add_query_arg(
                                $previous_args,
                                home_url('/employer-applicants/')
                            )
                            : home_url('/employer-applicants/');
                        ?>

                        <a
                            class="applicant-page-link applicant-page-direction"
                            href="<?php echo esc_url($previous_url); ?>"
                            aria-label="Previous applicant page"
                        >
                            &larr;
                            <span>Previous</span>
                        </a>

                        <?php
                    endif;

                    for (
                        $page_number = 1;
                        $page_number <= $total_applicant_pages;
                        $page_number++
                    ) :
                        $page_args = $pagination_args;

                        if ($page_number > 1) {
                            $page_args['applicant_page'] =
                                $page_number;
                        }

                        $page_url = $page_args
                            ? add_query_arg(
                                $page_args,
                                home_url('/employer-applicants/')
                            )
                            : home_url('/employer-applicants/');
                        ?>

                        <a
                            class="applicant-page-link <?php
                            echo $page_number === $current_page
                                ? 'active'
                                : '';
                            ?>"
                            href="<?php echo esc_url($page_url); ?>"
                        >
                            <?php echo esc_html($page_number); ?>
                        </a>

                    <?php endfor; ?>

                    <?php
                    if ($current_page < $total_applicant_pages) :
                        $next_args = $pagination_args;
                        $next_args['applicant_page'] =
                            $current_page + 1;

                        $next_url = add_query_arg(
                            $next_args,
                            home_url('/employer-applicants/')
                        );
                        ?>

                        <a
                            class="applicant-page-link applicant-page-direction"
                            href="<?php echo esc_url($next_url); ?>"
                            aria-label="Next applicant page"
                        >
                            <span>Next</span>
                            &rarr;
                        </a>

                    <?php endif; ?>
                </nav>

            <?php endif; ?>

            </form>

            <?php wp_reset_postdata(); ?>

        <?php else : ?>

            <div class="dashboard-empty-state">

                <h3>No applicants yet</h3>

                <p>
                    Applications submitted to your jobs will
                    appear here.
                </p>

            </div>

        <?php endif; ?>

    </div>

    <?php

    return ob_get_clean();
}


add_shortcode(
    'devhire_employer_applicants',
    'devhire_employer_applicants_shortcode'
);

/**
 * ============================================================
 * Employer - View Application
 * ============================================================
 */

/**
 * Employer-facing candidate profile.
 * Step 22.54.
 */
function devhire_employer_candidate_profile_shortcode() {

    if (!is_user_logged_in()) {
        return '<p>Please log in to view candidate profiles.</p>';
    }

    $current_user_id = get_current_user_id();
    $user = wp_get_current_user();

    if (!in_array('employer', (array) $user->roles, true)) {
        return '<p>This page is available to employers only.</p>';
    }

    $candidate_id = isset($_GET['candidate_id'])
        ? absint($_GET['candidate_id'])
        : 0;

    $candidate = $candidate_id
        ? get_userdata($candidate_id)
        : false;

    if (!$candidate) {
        return '<p>Candidate not found.</p>';
    }

    $application_ids = get_posts([
        'post_type'      => 'job_application',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_key'       => '_devhire_candidate_user',
        'meta_value'     => $candidate_id,
        'fields'         => 'ids',
    ]);

    $authorized = false;

    foreach ($application_ids as $application_id) {
        $job_id = (int) get_post_meta(
            $application_id,
            '_devhire_application_job',
            true
        );

        if (
            $job_id &&
            (int) get_post_field(
                'post_author',
                $job_id
            ) === $current_user_id
        ) {
            $authorized = true;
            break;
        }
    }

    if (!$authorized) {
        return '<p>You do not have permission to view this candidate.</p>';
    }

    $title = get_user_meta(
        $candidate_id,
        '_devhire_professional_title',
        true
    );
    $location = get_user_meta(
        $candidate_id,
        '_devhire_location',
        true
    );
    $phone = get_user_meta(
        $candidate_id,
        '_devhire_phone',
        true
    );
    $linkedin = get_user_meta(
        $candidate_id,
        '_devhire_linkedin',
        true
    );
    $bio = get_user_meta(
        $candidate_id,
        '_devhire_bio',
        true
    );
    $resume_id = (int) get_user_meta(
        $candidate_id,
        '_devhire_resume_id',
        true
    );
    $resume_url = $resume_id
        ? wp_get_attachment_url($resume_id)
        : '';

    $employer_candidate_applications = [];

    foreach ($application_ids as $application_id) {
        $job_id = (int) get_post_meta(
            $application_id,
            '_devhire_application_job',
            true
        );

        if (
            !$job_id ||
            (int) get_post_field(
                'post_author',
                $job_id
            ) !== $current_user_id
        ) {
            continue;
        }

        $application_status = get_post_meta(
            $application_id,
            '_devhire_application_status',
            true
        );

        if (!$application_status) {
            $application_status = 'New';
        }

        $employer_candidate_applications[] = [
            'application_id' => $application_id,
            'job_id'         => $job_id,
            'job_title'      => get_the_title($job_id),
            'status'         => $application_status,
            'date'           => get_the_date(
                get_option('date_format'),
                $application_id
            ),
        ];
    }

    usort(
        $employer_candidate_applications,
        static function ($a, $b) {
            return $b['application_id'] <=> $a['application_id'];
        }
    );

    $candidate_application_stats = [
        'total'     => count($employer_candidate_applications),
        'active'    => 0,
        'interview' => 0,
        'hired'     => 0,
    ];

    foreach (
        $employer_candidate_applications as
        $candidate_application
    ) {
        $candidate_status =
            $candidate_application['status'];

        if (
            !in_array(
                $candidate_status,
                ['Hired', 'Rejected'],
                true
            )
        ) {
            $candidate_application_stats['active']++;
        }

        if ($candidate_status === 'Interview') {
            $candidate_application_stats['interview']++;
        }

        if ($candidate_status === 'Hired') {
            $candidate_application_stats['hired']++;
        }
    }

    ob_start();
    ?>
    <div class="candidate-dashboard employer-candidate-profile">
        <div class="candidate-profile-card">
            <span class="candidate-profile-eyebrow">
                Candidate Profile
            </span>

            <h1><?php echo esc_html($candidate->display_name); ?></h1>

            <?php if ($title || $location) : ?>
                <p class="candidate-profile-headline">
                    <?php echo esc_html(
                        implode(
                            ' · ',
                            array_filter([$title, $location])
                        )
                    ); ?>
                </p>
            <?php endif; ?>

            <div class="candidate-profile-stats">
                <div>
                    <strong>
                        <?php echo esc_html(
                            $candidate_application_stats['total']
                        ); ?>
                    </strong>
                    <span>Total Applications</span>
                </div>

                <div>
                    <strong>
                        <?php echo esc_html(
                            $candidate_application_stats['active']
                        ); ?>
                    </strong>
                    <span>Active</span>
                </div>

                <div>
                    <strong>
                        <?php echo esc_html(
                            $candidate_application_stats['interview']
                        ); ?>
                    </strong>
                    <span>Interviews</span>
                </div>

                <div>
                    <strong>
                        <?php echo esc_html(
                            $candidate_application_stats['hired']
                        ); ?>
                    </strong>
                    <span>Hired</span>
                </div>
            </div>

            <div class="candidate-profile-contact">
                <a href="<?php echo esc_attr(
                    'mailto:' . $candidate->user_email
                ); ?>">
                    <?php echo esc_html($candidate->user_email); ?>
                </a>

                <?php if ($phone) : ?>
                    <a href="<?php echo esc_attr(
                        'tel:' . preg_replace(
                            '/[^0-9+]/',
                            '',
                            $phone
                        )
                    ); ?>">
                        <?php echo esc_html($phone); ?>
                    </a>
                <?php endif; ?>

                <?php if ($linkedin) : ?>
                    <a
                        href="<?php echo esc_url($linkedin); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        LinkedIn
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($bio) : ?>
                <div class="candidate-profile-section">
                    <h2>About</h2>
                    <p><?php echo nl2br(esc_html($bio)); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($employer_candidate_applications) : ?>
                <div class="candidate-profile-section">
                    <h2>Applications with Your Company</h2>

                    <div class="candidate-application-history">
                        <?php foreach (
                            $employer_candidate_applications as
                            $candidate_application
                        ) : ?>
                            <div class="candidate-application-history-item">
                                <div>
                                    <strong>
                                        <?php echo esc_html(
                                            $candidate_application[
                                                'job_title'
                                            ]
                                        ); ?>
                                    </strong>

                                    <span>
                                        Applied
                                        <?php echo esc_html(
                                            $candidate_application[
                                                'date'
                                            ]
                                        ); ?>
                                    </span>
                                </div>

                                <div
                                    class="candidate-application-history-actions"
                                >
                                    <span class="application-status">
                                        <?php echo esc_html(
                                            $candidate_application[
                                                'status'
                                            ]
                                        ); ?>
                                    </span>

                                    <a
                                        class="secondary-button"
                                        href="<?php echo esc_url(
                                            add_query_arg(
                                                'application_id',
                                                $candidate_application[
                                                    'application_id'
                                                ],
                                                home_url(
                                                    '/employer-view-application/'
                                                )
                                            )
                                        ); ?>"
                                    >
                                        View Application
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="candidate-profile-actions">
                <?php if ($resume_url) : ?>
                    <a
                        class="secondary-button"
                        href="<?php echo esc_url($resume_url); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        View Resume
                    </a>
                <?php endif; ?>

                <a
                    class="secondary-button"
                    href="<?php echo esc_url(
                        home_url('/employer-applicants/')
                    ); ?>"
                >
                    Back to Applicants
                </a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode(
    'devhire_employer_candidate_profile',
    'devhire_employer_candidate_profile_shortcode'
);


/**
 * Employer application detail.
 */
function devhire_employer_view_application_shortcode() {

    if (!is_user_logged_in()) {
        return sprintf(
            '<div class="devhire-notice error">
                Please <a href="%s">sign in as an employer</a>.
            </div>',
            esc_url(home_url('/employer-login/'))
        );
    }

    $user = wp_get_current_user();

    if (!in_array('employer', (array) $user->roles, true)) {
        return '<div class="devhire-notice error">
            Only employer accounts can view applications.
        </div>';
    }

    $application_id = isset($_GET['application_id'])
        ? absint($_GET['application_id'])
        : 0;

    $application = $application_id
        ? get_post($application_id)
        : null;

    if (
        !$application ||
        $application->post_type !== 'job_application'
    ) {
        return '<div class="devhire-notice error">
            Application not found.
        </div>';
    }

    /*
     * Get the job associated with this application.
     */
    $job_id = (int) get_post_meta(
        $application_id,
        '_devhire_application_job',
        true
    );

    $job = $job_id
        ? get_post($job_id)
        : null;

    /*
     * Critical ownership check.
     *
     * The employer can view the application only when
     * they own the associated job.
     */
    if (
        !$job ||
        $job->post_type !== 'job' ||
        (int) $job->post_author !== (int) $user->ID
    ) {
        return '<div class="devhire-notice error">
            You do not have permission to view this application.
        </div>';
    }

    /*
     * Applicant information.
     */
    $name = get_post_meta(
        $application_id,
        '_devhire_applicant_name',
        true
    );

    $email = get_post_meta(
        $application_id,
        '_devhire_applicant_email',
        true
    );

    $phone = get_post_meta(
        $application_id,
        '_devhire_applicant_phone',
        true
    );

    $linkedin = get_post_meta(
        $application_id,
        '_devhire_applicant_linkedin',
        true
    );

    $message = get_post_meta(
        $application_id,
        '_devhire_applicant_message',
        true
    );

    $resume = get_post_meta(
        $application_id,
        '_devhire_applicant_resume',
        true
    );

    $candidate_title = get_post_meta(
        $application_id,
        '_devhire_candidate_title',
        true
    );

    $candidate_location = get_post_meta(
        $application_id,
        '_devhire_candidate_location',
        true
    );

    $status = get_post_meta(
        $application_id,
        '_devhire_application_status',
        true
    );

    if (!$status) {
        $status = 'New';
    }


    $employer_notes = get_post_meta(
        $application_id,
        '_devhire_employer_notes',
        true
    );


    $employer_notes_updated = (int) get_post_meta(
        $application_id,
        '_devhire_employer_notes_updated',
        true
    );


    $application_history = get_post_meta(
        $application_id,
        '_devhire_application_history',
        true
    );

    if (!is_array($application_history)) {
        $application_history = [];
    }

    if (empty($application_history)) {
        $application_history[] = [
            'status' => 'New',
            'timestamp' => get_post_time(
                'U',
                true,
                $application_id
            ),
            'changed_by' => 0,
        ];
    }

    ob_start();
    ?>

    <div class="candidate-dashboard employer-dashboard">

        <?php if (
            isset($_GET['notes_updated']) &&
            $_GET['notes_updated'] === '1'
        ) : ?>
            <div class="devhire-notice success">
                Employer notes updated successfully.
            </div>
        <?php endif; ?>


        <div class="candidate-dashboard-header">

            <div>

                <span class="hero-badge">
                    Employer Portal
                </span>

                <h1>
                    <?php echo esc_html($name ?: 'Application'); ?>
                </h1>

                <p>
                    Application for
                    <strong>
                        <?php echo esc_html($job->post_title); ?>
                    </strong>
                </p>

            </div>

        </div>


        <nav class="candidate-dashboard-nav">

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/employer-dashboard/')
                ); ?>"
            >
                My Jobs
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/employer-post-job/')
                ); ?>"
            >
                Post Job
            </a>

            <a
                class="candidate-nav-link active"
                href="<?php echo esc_url(
                    home_url('/employer-applicants/')
                ); ?>"
            >
                Applicants
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/employer-company-profile/')
                ); ?>"
            >
                Company Profile
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    wp_logout_url(
                        home_url('/employer-login/')
                    )
                ); ?>"
            >
                Sign Out
            </a>

        </nav>


        <?php
        $company_id = (int) get_post_meta(
            $job_id,
            '_devhire_company',
            true
        );

        if (
            $company_id &&
            devhire_employer_owns_company(
                $company_id,
                $user->ID
            )
        ) :

            $company_name = get_the_title($company_id);
        ?>

            <div class="employer-company-summary">

                <div>
                    <span class="application-job-label">
                        Hiring for
                    </span>

                    <h2>
                        <?php echo esc_html($company_name); ?>
                    </h2>

                    <p>
                        <?php echo esc_html($job->post_title); ?>
                    </p>
                </div>

                <?php if (
                    get_post_status($company_id) === 'publish'
                ) : ?>

                    <div class="application-card-actions">

                        <a
                            class="secondary-button"
                            href="<?php echo esc_url(
                                get_permalink($company_id)
                            ); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            View Company
                        </a>

                    </div>

                <?php endif; ?>

            </div>

        <?php endif; ?>

        <?php
        $workflow_statuses = [
            'New',
            'Reviewing',
            'Interview',
            'Hired',
        ];

        $current_workflow_index = array_search(
            $status,
            $workflow_statuses,
            true
        );

        $is_rejected = $status === 'Rejected';
        ?>

        <section class="application-workflow">

            <div class="application-workflow-heading">

                <div>
                    <span class="application-job-label">
                        Hiring Pipeline
                    </span>

                    <h2>Application Progress</h2>
                </div>

                <span class="application-status">
                    <?php echo esc_html($status); ?>
                </span>

            </div>

            <div class="application-workflow-steps">

                <?php foreach (
                    $workflow_statuses as $workflow_index => $workflow_status
                ) :

                    $step_class = 'upcoming';

                    if (!$is_rejected) {
                        if ($workflow_index < $current_workflow_index) {
                            $step_class = 'complete';
                        } elseif (
                            $workflow_index === $current_workflow_index
                        ) {
                            $step_class = 'current';
                        }
                    }
                    ?>

                    <div class="application-workflow-step <?php
                    echo esc_attr($step_class);
                    ?>">

                        <span class="application-workflow-dot">
                            <?php echo esc_html(
                                $workflow_index + 1
                            ); ?>
                        </span>

                        <strong>
                            <?php echo esc_html(
                                $workflow_status
                            ); ?>
                        </strong>

                    </div>

                <?php endforeach; ?>

            </div>

            <?php if ($is_rejected) : ?>

                <div class="application-workflow-rejected">
                    This application has been marked as Rejected.
                </div>

            <?php endif; ?>

        </section>

        <section class="application-notes-card">

            <div class="application-notes-header">
                <div>
                    <span class="application-label">Private</span>
                    <h2>Employer Notes</h2>
                    <p>
                        These notes are visible only to the employer.
                    </p>


                    <?php if ($employer_notes_updated) : ?>
                        <small class="application-notes-updated">
                            Last updated
                            <?php echo esc_html(
                                wp_date(
                                    get_option('date_format') .
                                    ' ' .
                                    get_option('time_format'),
                                    $employer_notes_updated
                                )
                            ); ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <form
                class="application-notes-form"
                method="post"
                action="<?php echo esc_url(
                    admin_url('admin-post.php')
                ); ?>"
            >
                <input
                    type="hidden"
                    name="action"
                    value="devhire_employer_application_notes"
                >

                <input
                    type="hidden"
                    name="application_id"
                    value="<?php echo esc_attr(
                        $application_id
                    ); ?>"
                >

                <?php wp_nonce_field(
                    'devhire_application_notes_' .
                    $application_id,
                    'devhire_application_notes_nonce'
                ); ?>

                <label for="employer_notes">
                    Internal notes
                </label>

                <textarea
                    id="employer_notes"
                    name="employer_notes"
                    rows="6"
                    maxlength="5000"
                    placeholder="Add interview feedback, follow-up reminders, candidate strengths, or other private hiring notes."
                ><?php echo esc_textarea(
                    $employer_notes
                ); ?></textarea>

                <div class="application-notes-actions">
                    <button
                        class="primary-button"
                        type="submit"
                    >
                        Save Notes
                    </button>
                </div>
            </form>

        </section>

        <section class="application-history-card">

            <div class="application-history-header">
                <span class="application-label">Activity</span>
                <h2>Application Timeline</h2>
            </div>

            <div class="application-history-list">

                <?php foreach (
                    array_reverse($application_history)
                    as $history_item
                ) :

                    $history_status = isset($history_item['status'])
                        ? sanitize_text_field($history_item['status'])
                        : 'New';

                    $history_timestamp = isset(
                        $history_item['timestamp']
                    )
                        ? absint($history_item['timestamp'])
                        : 0;

                    $history_user_id = isset(
                        $history_item['changed_by']
                    )
                        ? absint($history_item['changed_by'])
                        : 0;

                    $history_user = $history_user_id
                        ? get_userdata($history_user_id)
                        : null;
                    ?>

                    <div class="application-history-item">

                        <span class="application-history-dot"></span>

                        <div>
                            <strong>
                                <?php echo esc_html($history_status); ?>
                            </strong>

                            <?php if ($history_timestamp) : ?>
                                <span>
                                    <?php echo esc_html(
                                        wp_date(
                                            get_option('date_format') .
                                            ' ' .
                                            get_option('time_format'),
                                            $history_timestamp
                                        )
                                    ); ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($history_user) : ?>
                                <small>
                                    Updated by
                                    <?php echo esc_html(
                                        $history_user->display_name
                                    ); ?>
                                </small>
                            <?php endif; ?>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </section>

        <div class="application-detail-card">

            <div class="application-detail-header">

                <div>
                    <span class="application-job-label">
                        Candidate
                    </span>

                    <h2>
                        <?php echo esc_html($name); ?>
                    </h2>
                </div>

                <span class="application-status">
                    <?php echo esc_html($status); ?>
                </span>

            </div>


            <div class="application-detail-grid">

                <?php if ($candidate_title) : ?>

                    <div>
                        <span>Professional Title</span>

                        <strong>
                            <?php echo esc_html($candidate_title); ?>
                        </strong>
                    </div>

                <?php endif; ?>


                <?php if ($candidate_location) : ?>

                    <div>
                        <span>Location</span>

                        <strong>
                            <?php echo esc_html($candidate_location); ?>
                        </strong>
                    </div>

                <?php endif; ?>

                <div>
                    <span>Email</span>

                    <strong>
                        <a href="mailto:<?php
                        echo esc_attr($email);
                        ?>">
                            <?php echo esc_html($email); ?>
                        </a>
                    </strong>
                </div>


                <div>
                    <span>Phone</span>

                    <strong>
                        <?php
                        echo esc_html(
                            $phone ?: 'Not provided'
                        );
                        ?>
                    </strong>
                </div>


                <div>
                    <span>Job</span>

                    <strong>
                        <?php echo esc_html($job->post_title); ?>
                    </strong>
                </div>


                <div>
                    <span>Applied</span>

                    <strong>
                        <?php
                        echo esc_html(
                            get_the_date(
                                'M j, Y',
                                $application_id
                            )
                        );
                        ?>
                    </strong>
                </div>

            </div>


            <?php if ($linkedin) : ?>

                <div class="application-detail-section">

                    <h3>LinkedIn</h3>

                    <a
                        href="<?php echo esc_url($linkedin); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        View LinkedIn Profile
                    </a>

                </div>

            <?php endif; ?>


            <div class="application-detail-section">

                <h3>Cover Message</h3>

                <?php if ($message) : ?>

                    <div class="application-message">
                        <?php
                        echo wp_kses_post(
                            wpautop($message)
                        );
                        ?>
                    </div>

                <?php else : ?>

                    <p>No cover message provided.</p>

                <?php endif; ?>

            </div>


            <?php if ($resume) : ?>

                <div class="application-detail-section">

                    <h3>Resume</h3>

                    <a
                        class="secondary-button"
                        href="<?php echo esc_url($resume); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        View Resume
                    </a>

                </div>

            <?php endif; ?>

            <div class="application-detail-section">

                <h3>Application Status</h3>

                <?php if (
                    isset($_GET['status_updated']) &&
                    $_GET['status_updated'] === '1'
                ) : ?>

                    <div class="devhire-notice success">
                        Application status updated successfully.
                    </div>

                <?php endif; ?>

                <div class="application-status-quick-actions">

                    <span>Quick actions</span>

                    <div class="application-status-action-buttons">

                        <?php
                        $quick_statuses = [
                            'Reviewing' => 'Move to Reviewing',
                            'Interview' => 'Move to Interview',
                            'Hired'     => 'Mark Hired',
                            'Rejected'  => 'Reject',
                        ];

                        foreach (
                            $quick_statuses as $quick_status => $quick_label
                        ) :

                            if ($status === $quick_status) {
                                continue;
                            }
                            ?>

                            <form
                                method="post"
                                action="<?php echo esc_url(
                                    admin_url('admin-post.php')
                                ); ?>"
                                class="application-quick-status-form"
                            >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="devhire_employer_application_status"
                                >

                                <input
                                    type="hidden"
                                    name="application_id"
                                    value="<?php echo esc_attr(
                                        $application_id
                                    ); ?>"
                                >

                                <input
                                    type="hidden"
                                    name="application_status"
                                    value="<?php echo esc_attr(
                                        $quick_status
                                    ); ?>"
                                >

                                <?php
                                wp_nonce_field(
                                    'devhire_application_status_' .
                                    $application_id,
                                    'devhire_application_status_nonce'
                                );
                                ?>

                                <button
                                    type="submit"
                                    class="<?php echo esc_attr(
                                        $quick_status === 'Rejected'
                                            ? 'secondary-button application-reject-button'
                                            : 'secondary-button'
                                    ); ?>"
                                >
                                    <?php echo esc_html(
                                        $quick_label
                                    ); ?>
                                </button>

                            </form>

                        <?php endforeach; ?>

                    </div>

                </div>

                <form
                    method="post"
                    action="<?php echo esc_url(
                        admin_url('admin-post.php')
                    ); ?>"
                    class="application-status-form"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="devhire_employer_application_status"
                    >

                    <input
                        type="hidden"
                        name="application_id"
                        value="<?php echo esc_attr($application_id); ?>"
                    >

                    <?php
                    wp_nonce_field(
                        'devhire_application_status_' . $application_id,
                        'devhire_application_status_nonce'
                    );
                    ?>

                    <div class="form-field">

                        <label for="application-status">
                            Status
                        </label>

                        <select
                            id="application-status"
                            name="application_status"
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

                    <button
                        type="submit"
                        class="primary-button"
                    >
                        Update Status
                    </button>

                </form>

            </div>


            <div class="form-actions">

                <a
                    class="secondary-button"
                    href="<?php echo esc_url(
                        home_url('/employer-applicants/')
                    ); ?>"
                >
                    Back to Applicants
                </a>

            </div>

        </div>

    </div>

    <?php

    return ob_get_clean();
}


add_shortcode(
    'devhire_employer_view_application',
    'devhire_employer_view_application_shortcode'
);

/**
 * ============================================================
 * Employer - Update Application Status
 * ============================================================
 */


/**
 * Save private employer notes for an application.
 */
function devhire_handle_employer_application_notes() {

    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/employer-login/'));
        exit;
    }

    $user = wp_get_current_user();

    if (!in_array('employer', (array) $user->roles, true)) {
        wp_die(
            esc_html__(
                'You do not have permission to update these notes.',
                'devhire'
            )
        );
    }

    $application_id = isset($_POST['application_id'])
        ? absint($_POST['application_id'])
        : 0;

    if (
        !$application_id ||
        get_post_type($application_id) !== 'job_application'
    ) {
        wp_die(
            esc_html__('Invalid application.', 'devhire')
        );
    }

    $nonce = isset(
        $_POST['devhire_application_notes_nonce']
    )
        ? sanitize_text_field(
            wp_unslash(
                $_POST['devhire_application_notes_nonce']
            )
        )
        : '';

    if (
        !$nonce ||
        !wp_verify_nonce(
            $nonce,
            'devhire_application_notes_' . $application_id
        )
    ) {
        wp_die(
            esc_html__('Security check failed.', 'devhire')
        );
    }

    $job_id = (int) get_post_meta(
        $application_id,
        '_devhire_application_job',
        true
    );

    if (
        !$job_id ||
        (int) get_post_field(
            'post_author',
            $job_id
        ) !== (int) $user->ID
    ) {
        wp_die(
            esc_html__(
                'You do not have permission to update this application.',
                'devhire'
            )
        );
    }

    $notes = isset($_POST['employer_notes'])
        ? sanitize_textarea_field(
            wp_unslash($_POST['employer_notes'])
        )
        : '';

    if (strlen($notes) > 5000) {
        $notes = substr($notes, 0, 5000);
    }

    if ($notes === '') {
        delete_post_meta(
            $application_id,
            '_devhire_employer_notes'
        );

        delete_post_meta(
            $application_id,
            '_devhire_employer_notes_updated'
        );
    } else {
        update_post_meta(
            $application_id,
            '_devhire_employer_notes',
            $notes
        );

        update_post_meta(
            $application_id,
            '_devhire_employer_notes_updated',
            current_time('timestamp')
        );
    }

    $redirect_url = add_query_arg(
        [
            'application_id' => $application_id,
            'notes_updated' => '1',
        ],
        home_url('/employer-view-application/')
    );

    wp_safe_redirect($redirect_url);
    exit;
}

add_action(
    'admin_post_devhire_employer_application_notes',
    'devhire_handle_employer_application_notes'
);


function devhire_handle_employer_application_status() {

    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/employer-login/'));
        exit;
    }

    $user = wp_get_current_user();

    if (!in_array('employer', (array) $user->roles, true)) {
        wp_die('You are not allowed to manage applications.');
    }

    $application_id = isset($_POST['application_id'])
        ? absint($_POST['application_id'])
        : 0;

    $new_status = isset($_POST['application_status'])
        ? sanitize_text_field(
            wp_unslash($_POST['application_status'])
        )
        : '';

    if (
        !$application_id ||
        !isset($_POST['devhire_application_status_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(
                wp_unslash($_POST['devhire_application_status_nonce'])
            ),
            'devhire_application_status_' . $application_id
        )
    ) {
        wp_die('Invalid request.');
    }

    /*
     * Validate application.
     */
    $application = get_post($application_id);

    if (
        !$application ||
        $application->post_type !== 'job_application'
    ) {
        wp_die('Application not found.');
    }

    /*
     * Find the job connected to the application.
     */
    $job_id = (int) get_post_meta(
        $application_id,
        '_devhire_application_job',
        true
    );

    $job = $job_id
        ? get_post($job_id)
        : null;

    /*
     * Critical ownership check.
     *
     * Employer can update applications only
     * for jobs they own.
     */
    if (
        !$job ||
        $job->post_type !== 'job' ||
        (int) $job->post_author !== (int) $user->ID
    ) {
        wp_die(
            'You are not allowed to manage this application.'
        );
    }

    /*
     * Allowed workflow statuses.
     */
    $allowed_statuses = [
        'New',
        'Reviewing',
        'Interview',
        'Hired',
        'Rejected',
    ];

    if (
        !in_array(
            $new_status,
            $allowed_statuses,
            true
        )
    ) {
        wp_die('Invalid application status.');
    }

    $old_status = get_post_meta(
        $application_id,
        '_devhire_application_status',
        true
    );

    if (!$old_status) {
        $old_status = 'New';
    }

    if ($old_status !== $new_status) {

        update_post_meta(
            $application_id,
            '_devhire_application_status',
            $new_status
        );

        $history = get_post_meta(
            $application_id,
            '_devhire_application_history',
            true
        );

        if (!is_array($history)) {
            $history = [];
        }

        if (empty($history)) {
            $history[] = [
                'status' => $old_status,
                'timestamp' => get_post_time(
                    'U',
                    true,
                    $application_id
                ),
                'changed_by' => 0,
            ];
        }

        $history[] = [
            'status' => $new_status,
            'timestamp' => current_time('timestamp'),
            'changed_by' => $user->ID,
        ];

        update_post_meta(
            $application_id,
            '_devhire_application_history',
            $history
        );
    }

    wp_safe_redirect(
        add_query_arg(
            [
                'application_id' => $application_id,
                'status_updated' => '1',
            ],
            home_url('/employer-view-application/')
        )
    );

    exit;
}


add_action(
    'admin_post_devhire_employer_application_status',
    'devhire_handle_employer_application_status'
);

/**
 * ============================================================
 * Employer - Delete Job
 * ============================================================
 */

function devhire_handle_employer_delete_job() {

    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/employer-login/'));
        exit;
    }

    $user = wp_get_current_user();

    if (!in_array('employer', (array) $user->roles, true)) {
        wp_die('You are not allowed to delete jobs.');
    }

    $job_id = isset($_POST['job_id'])
        ? absint($_POST['job_id'])
        : 0;

    if (
        !$job_id ||
        !isset($_POST['devhire_delete_job_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(
                wp_unslash($_POST['devhire_delete_job_nonce'])
            ),
            'devhire_delete_job_' . $job_id
        )
    ) {
        wp_die('Invalid request.');
    }

    $job = get_post($job_id);

    if (!$job || $job->post_type !== 'job') {
        wp_die('Job not found.');
    }

    /*
     * Critical ownership check.
     * Employers may delete only their own jobs.
     */
    if ((int) $job->post_author !== (int) $user->ID) {
        wp_die('You are not allowed to delete this job.');
    }

    /*
     * Move to Trash rather than permanently deleting it.
     */
    $trashed = wp_trash_post($job_id);

    if (!$trashed) {
        wp_safe_redirect(
            add_query_arg(
                'job_delete_error',
                '1',
                home_url('/employer-dashboard/')
            )
        );
        exit;
    }

    wp_safe_redirect(
        add_query_arg(
            'job_deleted',
            '1',
            home_url('/employer-dashboard/')
        )
    );

    exit;
}

add_action(
    'admin_post_devhire_employer_delete_job',
    'devhire_handle_employer_delete_job'
);

/**
 * ============================================================
 * Employer - Save Company Profile
 * ============================================================
 */

function devhire_handle_employer_company_profile() {

    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/employer-login/'));
        exit;
    }

    $user = wp_get_current_user();

    if (!in_array('employer', (array) $user->roles, true)) {
        wp_die('Only employer accounts can manage a company profile.');
    }

    if (
        !isset($_POST['devhire_company_profile_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(
                wp_unslash($_POST['devhire_company_profile_nonce'])
            ),
            'devhire_company_profile'
        )
    ) {
        wp_die('Invalid company profile request.');
    }

    $company_name = isset($_POST['company_name'])
        ? sanitize_text_field(wp_unslash($_POST['company_name']))
        : '';

    $description = isset($_POST['company_description'])
        ? wp_kses_post(wp_unslash($_POST['company_description']))
        : '';

    $website = isset($_POST['company_website'])
        ? esc_url_raw(wp_unslash($_POST['company_website']))
        : '';

    $location = isset($_POST['company_location'])
        ? sanitize_text_field(wp_unslash($_POST['company_location']))
        : '';

    $size = isset($_POST['company_size'])
        ? sanitize_text_field(wp_unslash($_POST['company_size']))
        : '';

    $industry = isset($_POST['company_industry'])
        ? sanitize_text_field(wp_unslash($_POST['company_industry']))
        : '';

    if (!$company_name) {
        wp_safe_redirect(
            add_query_arg(
                'company_error',
                'missing_name',
                home_url('/employer-company-profile/')
            )
        );
        exit;
    }

    $company_id = devhire_get_employer_company_id($user->ID);

    if ($company_id) {

        if (!devhire_employer_owns_company($company_id, $user->ID)) {
            wp_die('You are not allowed to edit this company.');
        }

        $result = wp_update_post(
            [
                'ID'           => $company_id,
                'post_title'   => $company_name,
                'post_content' => $description,
            ],
            true
        );

    } else {

        $result = wp_insert_post(
            [
                'post_type'    => 'company',
                'post_status'  => 'publish',
                'post_title'   => $company_name,
                'post_content' => $description,
                'post_author'  => $user->ID,
            ],
            true
        );

        if (!is_wp_error($result)) {
            $company_id = (int) $result;

            if (
                !devhire_assign_company_to_employer(
                    $company_id,
                    $user->ID
                )
            ) {
                wp_delete_post($company_id, true);
                $company_id = 0;
                $result = new WP_Error(
                    'company_ownership_failed',
                    'Unable to assign company ownership.'
                );
            }
        }
    }

    if (is_wp_error($result) || !$company_id) {
        wp_safe_redirect(
            add_query_arg(
                'company_error',
                'save_failed',
                home_url('/employer-company-profile/')
            )
        );
        exit;
    }

    update_post_meta(
        $company_id,
        '_devhire_company_website',
        $website
    );

    update_post_meta(
        $company_id,
        '_devhire_company_location',
        $location
    );

    update_post_meta(
        $company_id,
        '_devhire_company_size',
        $size
    );

    update_post_meta(
        $company_id,
        '_devhire_company_industry',
        $industry
    );

    /*
     * Company logo.
     * The logo is stored as the company's featured image.
     */
    $remove_logo = isset($_POST['remove_company_logo'])
        && $_POST['remove_company_logo'] === '1';

    if ($remove_logo) {
        delete_post_thumbnail($company_id);
    }

    if (
        isset($_FILES['company_logo']) &&
        is_array($_FILES['company_logo']) &&
        isset($_FILES['company_logo']['error']) &&
        (int) $_FILES['company_logo']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        $logo_error = (int) $_FILES['company_logo']['error'];

        if ($logo_error !== UPLOAD_ERR_OK) {
            wp_safe_redirect(
                add_query_arg(
                    'company_error',
                    'logo_upload_failed',
                    home_url('/employer-company-profile/')
                )
            );
            exit;
        }

        $logo_size = isset($_FILES['company_logo']['size'])
            ? (int) $_FILES['company_logo']['size']
            : 0;

        if ($logo_size <= 0 || $logo_size > 2 * MB_IN_BYTES) {
            wp_safe_redirect(
                add_query_arg(
                    'company_error',
                    'logo_too_large',
                    home_url('/employer-company-profile/')
                )
            );
            exit;
        }

        $logo_name = isset($_FILES['company_logo']['name'])
            ? sanitize_file_name(
                wp_unslash($_FILES['company_logo']['name'])
            )
            : '';

        $logo_extension = strtolower(
            (string) pathinfo(
                $logo_name,
                PATHINFO_EXTENSION
            )
        );

        $allowed_extensions = [
            'jpg',
            'jpeg',
            'png',
            'webp',
        ];

        if (
            !$logo_name ||
            !in_array(
                $logo_extension,
                $allowed_extensions,
                true
            )
        ) {
            wp_safe_redirect(
                add_query_arg(
                    'company_error',
                    'invalid_logo',
                    home_url('/employer-company-profile/')
                )
            );
            exit;
        }

        $file_check = wp_check_filetype_and_ext(
            $_FILES['company_logo']['tmp_name'],
            $logo_name,
            [
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png'          => 'image/png',
                'webp'         => 'image/webp',
            ]
        );

        $allowed_mimes = [
            'image/jpeg',
            'image/png',
            'image/webp',
        ];

        if (
            empty($file_check['type']) ||
            !in_array(
                $file_check['type'],
                $allowed_mimes,
                true
            )
        ) {
            wp_safe_redirect(
                add_query_arg(
                    'company_error',
                    'invalid_logo',
                    home_url('/employer-company-profile/')
                )
            );
            exit;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $logo_id = media_handle_upload(
            'company_logo',
            $company_id,
            [],
            [
                'test_form' => false,
                'mimes'     => [
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'png'          => 'image/png',
                    'webp'         => 'image/webp',
                ],
            ]
        );

        if (is_wp_error($logo_id)) {
            wp_safe_redirect(
                add_query_arg(
                    'company_error',
                    'logo_upload_failed',
                    home_url('/employer-company-profile/')
                )
            );
            exit;
        }

        set_post_thumbnail(
            $company_id,
            $logo_id
        );
    }

    /*
     * Synchronize and repair all existing jobs owned by this employer.
     */
    devhire_sync_employer_jobs_to_company(
        $user->ID,
        $company_id
    );

    wp_safe_redirect(
        add_query_arg(
            'company_updated',
            '1',
            home_url('/employer-company-profile/')
        )
    );

    exit;
}


add_action(
    'admin_post_devhire_employer_company_profile',
    'devhire_handle_employer_company_profile'
);


/**
 * ============================================================
 * Employer - Company Profile
 * ============================================================
 */

function devhire_employer_company_profile_shortcode() {

    if (!is_user_logged_in()) {
        return sprintf(
            '<div class="devhire-notice error">
                Please <a href="%s">sign in as an employer</a>
                to manage your company profile.
            </div>',
            esc_url(home_url('/employer-login/'))
        );
    }

    $user = wp_get_current_user();

    if (!in_array('employer', (array) $user->roles, true)) {
        return '<div class="devhire-notice error">
            Only employer accounts can manage company profiles.
        </div>';
    }

    $company_id = devhire_get_employer_company_id($user->ID);
    $company    = $company_id ? get_post($company_id) : null;

    $company_name = $company
        ? $company->post_title
        : '';

    $description = $company
        ? $company->post_content
        : '';

    $website = $company_id
        ? get_post_meta(
            $company_id,
            '_devhire_company_website',
            true
        )
        : '';

    $location = $company_id
        ? get_post_meta(
            $company_id,
            '_devhire_company_location',
            true
        )
        : '';

    $size = $company_id
        ? get_post_meta(
            $company_id,
            '_devhire_company_size',
            true
        )
        : '';

    $industry = $company_id
        ? get_post_meta(
            $company_id,
            '_devhire_company_industry',
            true
        )
        : '';

    $company_logo_id = $company_id
        ? get_post_thumbnail_id($company_id)
        : 0;

    $company_profile_fields = [
        'Company Name' => $company_name,
        'Website'      => $website,
        'Location'     => $location,
        'Industry'     => $industry,
        'Company Size' => $size,
        'Description'  => trim(
            wp_strip_all_tags($description)
        ),
        'Company Logo' => $company_logo_id,
    ];

    $completed_company_fields = 0;
    $missing_company_fields   = [];

    foreach (
        $company_profile_fields as $label => $value
    ) {
        if (!empty($value)) {
            $completed_company_fields++;
        } else {
            $missing_company_fields[] = $label;
        }
    }

    $company_profile_completeness = (int) round(
        (
            $completed_company_fields /
            count($company_profile_fields)
        ) * 100
    );

    $company_profile_is_complete =
        $company_profile_completeness === 100;

    $error = isset($_GET['company_error'])
        ? sanitize_key(wp_unslash($_GET['company_error']))
        : '';

    ob_start();
    ?>

    <div class="candidate-dashboard employer-dashboard">

        <div class="candidate-dashboard-header">
            <div>
                <span class="hero-badge">
                    Employer Portal
                </span>

                <h1>Company Profile</h1>

                <p>
                    Create or update the company candidates see
                    with your job listings.
                </p>
            </div>
        </div>

        <nav class="candidate-dashboard-nav">

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/employer-dashboard/')
                ); ?>"
            >
                My Jobs
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/employer-post-job/')
                ); ?>"
            >
                Post Job
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    home_url('/employer-applicants/')
                ); ?>"
            >
                Applicants
            </a>

            <a
                class="candidate-nav-link active"
                href="<?php echo esc_url(
                    home_url('/employer-company-profile/')
                ); ?>"
            >
                Company Profile
            </a>

            <a
                class="candidate-nav-link"
                href="<?php echo esc_url(
                    wp_logout_url(
                        home_url('/employer-login/')
                    )
                ); ?>"
            >
                Sign Out
            </a>

        </nav>

        <section class="employer-profile-guidance">

            <div class="employer-profile-guidance-header">

                <div>
                    <span class="application-job-label">
                        Company Profile Strength
                    </span>

                    <h2>
                        Build candidate trust
                    </h2>
                </div>

                <strong class="employer-profile-percentage">
                    <?php
                    echo esc_html(
                        $company_profile_completeness
                    );
                    ?>%
                </strong>

            </div>

            <div
                class="candidate-profile-progress"
                role="progressbar"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="<?php echo esc_attr(
                    $company_profile_completeness
                ); ?>"
                aria-label="<?php esc_attr_e(
                    'Company profile completeness',
                    'devhire'
                ); ?>"
            >
                <span
                    style="width: <?php echo esc_attr(
                        $company_profile_completeness
                    ); ?>%;"
                ></span>
            </div>

            <?php if ($company_profile_is_complete) : ?>

                <p class="employer-profile-guidance-message complete">
                    Your company profile is complete and ready
                    for candidates to view.
                </p>

            <?php else : ?>

                <p class="employer-profile-guidance-message">
                    Complete the remaining details so candidates
                    get a stronger picture of your company.
                </p>

                <div
                    class="employer-missing-fields"
                    aria-label="<?php esc_attr_e(
                        'Missing company profile fields',
                        'devhire'
                    ); ?>"
                >
                    <?php foreach (
                        $missing_company_fields as $missing_field
                    ) : ?>

                        <span>
                            <?php echo esc_html($missing_field); ?>
                        </span>

                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

            <div class="employer-logo-status">

                <span>
                    Company Logo
                </span>

                <strong>
                    <?php echo $company_logo_id
                        ? 'Uploaded'
                        : 'Missing'; ?>
                </strong>

                <?php if ($company_logo_id) : ?>

                    <a
                        class="secondary-button"
                        href="<?php echo esc_url(
                            wp_get_attachment_url(
                                $company_logo_id
                            )
                        ); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        View Logo
                    </a>

                <?php endif; ?>

            </div>

        </section>




        <?php if (
            isset($_GET['company_updated']) &&
            $_GET['company_updated'] === '1'
        ) : ?>

            <div class="devhire-notice success">
                Company profile saved successfully.
            </div>

        <?php endif; ?>

        <?php if (
            isset($_GET['company_required']) &&
            $_GET['company_required'] === '1'
        ) : ?>

            <div class="devhire-notice error">
                Please create your company profile before posting a job.
            </div>

        <?php endif; ?>

        <?php if ($error === 'missing_name') : ?>

            <div class="devhire-notice error">
                Company name is required.
            </div>

        <?php elseif ($error === 'save_failed') : ?>

            <div class="devhire-notice error">
                Unable to save the company profile. Please try again.
            </div>

        <?php elseif ($error === 'logo_too_large') : ?>

            <div class="devhire-notice error">
                Company logo must be 2 MB or smaller.
            </div>

        <?php elseif ($error === 'invalid_logo') : ?>

            <div class="devhire-notice error">
                Company logo must be a JPG, PNG, or WebP image.
            </div>

        <?php elseif ($error === 'logo_upload_failed') : ?>

            <div class="devhire-notice error">
                Unable to upload the company logo. Please try again.
            </div>

        <?php endif; ?>

        <form
            class="candidate-profile-form employer-company-form"
            method="post"
            enctype="multipart/form-data"
            action="<?php echo esc_url(
                admin_url('admin-post.php')
            ); ?>"
        >

            <input
                type="hidden"
                name="action"
                value="devhire_employer_company_profile"
            >

            <?php
            wp_nonce_field(
                'devhire_company_profile',
                'devhire_company_profile_nonce'
            );
            ?>

            <div class="form-field">
                <label for="company-name">
                    Company Name
                </label>

                <input
                    id="company-name"
                    name="company_name"
                    type="text"
                    value="<?php echo esc_attr($company_name); ?>"
                    placeholder="CloudNova"
                    required
                >
            </div>

            <div class="form-field">
                <label for="company-website">
                    Website
                </label>

                <input
                    id="company-website"
                    name="company_website"
                    type="url"
                    value="<?php echo esc_attr($website); ?>"
                    placeholder="https://example.com"
                >
            </div>

            <div class="form-field">
                <label for="company-location">
                    Location
                </label>

                <input
                    id="company-location"
                    name="company_location"
                    type="text"
                    value="<?php echo esc_attr($location); ?>"
                    placeholder="San Francisco, CA"
                >
            </div>

            <div class="form-field">
                <label for="company-industry">
                    Industry
                </label>

                <input
                    id="company-industry"
                    name="company_industry"
                    type="text"
                    value="<?php echo esc_attr($industry); ?>"
                    placeholder="Software Development"
                >
            </div>

            <div class="form-field">
                <label for="company-size">
                    Company Size
                </label>

                <input
                    id="company-size"
                    name="company_size"
                    type="text"
                    value="<?php echo esc_attr($size); ?>"
                    placeholder="51-200 employees"
                >
            </div>

            <div class="form-field company-logo-field">

                <label for="company-logo">
                    Company Logo
                </label>

                <?php if (
                    $company_id &&
                    has_post_thumbnail($company_id)
                ) : ?>

                    <div class="company-logo-preview">
                        <?php
                        echo get_the_post_thumbnail(
                            $company_id,
                            'thumbnail',
                            [
                                'class' => 'company-logo-preview-image',
                                'alt'   => $company_name
                                    ? $company_name . ' logo'
                                    : 'Company logo',
                            ]
                        );
                        ?>

                        <label class="company-logo-remove">
                            <input
                                type="checkbox"
                                name="remove_company_logo"
                                value="1"
                            >
                            Remove current logo
                        </label>
                    </div>

                <?php endif; ?>

                <input
                    id="company-logo"
                    name="company_logo"
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                >

                <small>
                    JPG, PNG, or WebP. Maximum file size: 2 MB.
                    Uploading a new image replaces the current logo.
                </small>

            </div>

            <div class="form-field">
                <label for="company-description">
                    Company Description
                </label>

                <textarea
                    id="company-description"
                    name="company_description"
                    rows="8"
                    placeholder="Tell candidates about your company..."
                ><?php echo esc_textarea($description); ?></textarea>
            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="primary-button"
                >
                    <?php echo $company_id
                        ? 'Save Changes'
                        : 'Create Company Profile'; ?>
                </button>

                <?php if (
                    $company_id &&
                    get_post_status($company_id) === 'publish'
                ) : ?>

                    <a
                        class="secondary-button"
                        href="<?php echo esc_url(
                            get_permalink($company_id)
                        ); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        View Public Profile
                    </a>

                <?php endif; ?>

            </div>

        </form>

    </div>

    <?php

    return ob_get_clean();
}


add_shortcode(
    'devhire_employer_company_profile',
    'devhire_employer_company_profile_shortcode'
);


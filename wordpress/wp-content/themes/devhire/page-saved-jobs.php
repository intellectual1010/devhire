<?php
/*
Template Name: Saved Jobs
*/

get_header();

$is_candidate = false;

if (is_user_logged_in()) {
    $current_user = wp_get_current_user();
    $is_candidate = in_array(
        'candidate',
        (array) $current_user->roles,
        true
    );
}
?>

<section class="saved-jobs-page">

    <div class="container">

        <?php if ($is_candidate) : ?>

            <nav
                class="candidate-dashboard-nav"
                aria-label="<?php esc_attr_e(
                    'Candidate navigation',
                    'devhire'
                ); ?>"
            >

                <a
                    class="candidate-nav-link"
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
                    class="candidate-nav-link active"
                    href="<?php echo esc_url(
                        home_url('/saved-jobs/')
                    ); ?>"
                    aria-current="page"
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
                    class="candidate-nav-link candidate-nav-logout"
                    href="<?php echo esc_url(
                        wp_logout_url(home_url('/'))
                    ); ?>"
                >
                    Sign Out
                </a>

            </nav>

        <?php endif; ?>

        <div class="saved-jobs-heading">

            <span class="section-label">
                Your shortlist
            </span>

            <h1>Saved Jobs</h1>

            <p>
                Jobs saved in this browser will appear here.
            </p>

        </div>

        <div id="devhire-saved-jobs">

            <div class="saved-jobs-loading">
                Loading saved jobs...
            </div>

        </div>

    </div>

</section>

<?php get_footer(); ?>

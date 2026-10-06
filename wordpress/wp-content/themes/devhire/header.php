<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>

<?php wp_body_open(); ?>

<?php
$current_user = wp_get_current_user();
$is_logged_in = is_user_logged_in();

$is_candidate = $is_logged_in &&
    in_array('candidate', (array) $current_user->roles, true);

$is_employer = $is_logged_in &&
    in_array('employer', (array) $current_user->roles, true);

$logout_url = wp_logout_url(home_url('/'));

$display_name = '';

if ($is_logged_in) {
    $display_name = trim($current_user->display_name);

    if ($display_name === '') {
        $display_name = trim($current_user->user_login);
    }

    if ($display_name === '') {
        $display_name = __('Account', 'devhire');
    }
}
?>

<header class="site-header">
    <div class="container navbar">

        <a class="logo" href="<?php echo esc_url(home_url('/')); ?>">
            Dev<span>Hire</span>
        </a>

        <nav class="main-nav">
            <?php
            wp_nav_menu([
                'theme_location' => 'primary',
                'container'      => false,
                'fallback_cb'    => false,
            ]);
            ?>
        </nav>

        <div class="header-account-actions">

            <?php if (!$is_logged_in) : ?>

                <a
                    class="header-account-link"
                    href="<?php echo esc_url(
                        home_url('/candidate-login/')
                    ); ?>"
                >
                    Candidate Login
                </a>

                <a
                    class="header-account-link"
                    href="<?php echo esc_url(
                        home_url('/employer-login/')
                    ); ?>"
                >
                    Employer Login
                </a>

                <a
                    class="nav-button"
                    href="<?php echo esc_url(
                        get_post_type_archive_link('job')
                    ); ?>"
                >
                    Find Jobs
                </a>

            <?php elseif ($is_candidate) : ?>

                <div class="header-account-menu">
                    <button
                        class="header-account-toggle"
                        type="button"
                        aria-expanded="false"
                        aria-haspopup="true"
                    >
                        <span class="header-account-avatar" aria-hidden="true">
                            <?php
                            echo esc_html(
                                strtoupper(
                                    substr($display_name, 0, 1)
                                )
                            );
                            ?>
                        </span>

                        <span class="header-account-name">
                            <?php echo esc_html($display_name); ?>
                        </span>

                        <span
                            class="header-account-chevron"
                            aria-hidden="true"
                        >
                            ▾
                        </span>
                    </button>

                    <div class="header-account-dropdown">
                        <div class="header-account-dropdown-heading">
                            <strong>
                                <?php echo esc_html($display_name); ?>
                            </strong>

                            <?php if (!empty($current_user->user_email)) : ?>
                                <span>
                                    <?php
                                    echo esc_html(
                                        $current_user->user_email
                                    );
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <a
                            href="<?php echo esc_url(
                                home_url('/candidate-dashboard/')
                            ); ?>"
                        >
                            Dashboard
                        </a>

                        <a
                            href="<?php echo esc_url(
                                home_url('/candidate-profile/')
                            ); ?>"
                        >
                            Profile
                        </a>

                        <a
                            href="<?php echo esc_url(
                                home_url('/saved-jobs/')
                            ); ?>"
                        >
                            Saved Jobs
                        </a>

                        <div class="header-account-divider"></div>

                        <a
                            class="header-account-logout"
                            href="<?php echo esc_url($logout_url); ?>"
                        >
                            Logout
                        </a>
                    </div>
                </div>

            <?php elseif ($is_employer) : ?>

                <div class="header-account-menu">
                    <button
                        class="header-account-toggle"
                        type="button"
                        aria-expanded="false"
                        aria-haspopup="true"
                    >
                        <span class="header-account-avatar" aria-hidden="true">
                            <?php
                            echo esc_html(
                                strtoupper(
                                    substr($display_name, 0, 1)
                                )
                            );
                            ?>
                        </span>

                        <span class="header-account-name">
                            <?php echo esc_html($display_name); ?>
                        </span>

                        <span
                            class="header-account-chevron"
                            aria-hidden="true"
                        >
                            ▾
                        </span>
                    </button>

                    <div class="header-account-dropdown">
                        <div class="header-account-dropdown-heading">
                            <strong>
                                <?php echo esc_html($display_name); ?>
                            </strong>

                            <?php if (!empty($current_user->user_email)) : ?>
                                <span>
                                    <?php
                                    echo esc_html(
                                        $current_user->user_email
                                    );
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <a
                            href="<?php echo esc_url(
                                home_url('/employer-dashboard/')
                            ); ?>"
                        >
                            Dashboard
                        </a>

                        <a
                            href="<?php echo esc_url(
                                home_url('/employer-company-profile/')
                            ); ?>"
                        >
                            Company Profile
                        </a>

                        <a
                            href="<?php echo esc_url(
                                home_url('/employer-post-job/')
                            ); ?>"
                        >
                            Post Job
                        </a>

                        <a
                            href="<?php echo esc_url(
                                home_url('/employer-applicants/')
                            ); ?>"
                        >
                            Applicants
                        </a>

                        <div class="header-account-divider"></div>

                        <a
                            class="header-account-logout"
                            href="<?php echo esc_url($logout_url); ?>"
                        >
                            Logout
                        </a>
                    </div>
                </div>

            <?php else : ?>

                <a
                    class="header-account-link"
                    href="<?php echo esc_url(
                        get_post_type_archive_link('job')
                    ); ?>"
                >
                    Find Jobs
                </a>

                <a
                    class="nav-button"
                    href="<?php echo esc_url($logout_url); ?>"
                >
                    Logout
                </a>

            <?php endif; ?>

        </div>

    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const menus = document.querySelectorAll('.header-account-menu');

    menus.forEach(function (menu) {
        const toggle = menu.querySelector('.header-account-toggle');

        if (!toggle) {
            return;
        }

        toggle.addEventListener('click', function (event) {
            event.stopPropagation();

            menus.forEach(function (otherMenu) {
                if (otherMenu !== menu) {
                    otherMenu.classList.remove('is-open');

                    const otherToggle = otherMenu.querySelector(
                        '.header-account-toggle'
                    );

                    if (otherToggle) {
                        otherToggle.setAttribute(
                            'aria-expanded',
                            'false'
                        );
                    }
                }
            });

            const isOpen = menu.classList.toggle('is-open');

            toggle.setAttribute(
                'aria-expanded',
                isOpen ? 'true' : 'false'
            );
        });
    });

    document.addEventListener('click', function () {
        menus.forEach(function (menu) {
            menu.classList.remove('is-open');

            const toggle = menu.querySelector(
                '.header-account-toggle'
            );

            if (toggle) {
                toggle.setAttribute(
                    'aria-expanded',
                    'false'
                );
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        menus.forEach(function (menu) {
            menu.classList.remove('is-open');

            const toggle = menu.querySelector(
                '.header-account-toggle'
            );

            if (toggle) {
                toggle.setAttribute(
                    'aria-expanded',
                    'false'
                );
            }
        });
    });
});
</script>

<main>

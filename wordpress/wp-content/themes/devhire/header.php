<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>

<?php wp_body_open(); ?>

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

        <a
            class="nav-button"
            href="<?php echo esc_url(home_url('/jobs')); ?>"
        >
            Find Jobs
        </a>

    </div>
</header>

<main>
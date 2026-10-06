<?php
get_header();
?>

<main class="page-content">
    <div class="container">

        <?php if (have_posts()) : ?>

            <?php while (have_posts()) : the_post(); ?>

                <article <?php post_class(); ?>>

                    <header class="page-header">
                        <h1><?php the_title(); ?></h1>
                    </header>

                    <div class="entry-content">
                        <?php the_content(); ?>
                    </div>

                </article>

            <?php endwhile; ?>

        <?php endif; ?>

    </div>
</main>

<?php
get_footer();
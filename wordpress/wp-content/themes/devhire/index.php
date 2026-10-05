<?php get_header(); ?>

<section class="section">
    <div class="container">

        <div class="section-heading">
            <h1><?php bloginfo('name'); ?></h1>
            <p><?php bloginfo('description'); ?></p>
        </div>

        <?php if (have_posts()) : ?>

            <?php while (have_posts()) : the_post(); ?>

                <article class="card">
                    <h2>
                        <a href="<?php the_permalink(); ?>">
                            <?php the_title(); ?>
                        </a>
                    </h2>

                    <?php the_excerpt(); ?>
                </article>

            <?php endwhile; ?>

        <?php else : ?>

            <p>No content found.</p>

        <?php endif; ?>

    </div>
</section>

<?php get_footer(); ?>
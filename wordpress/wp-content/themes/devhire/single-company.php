<?php
get_header();

while (have_posts()) :
    the_post();

    $company_id = get_the_ID();

    $website = get_post_meta(
        $company_id,
        '_devhire_company_website',
        true
    );

    $location = get_post_meta(
        $company_id,
        '_devhire_company_location',
        true
    );

    $industry = get_post_meta(
        $company_id,
        '_devhire_company_industry',
        true
    );

    $size = get_post_meta(
        $company_id,
        '_devhire_company_size',
        true
    );


    /*
     * Find all jobs connected to this company.
     */
    $company_jobs = new WP_Query([
        'post_type'      => 'job',
        'post_status'    => 'publish',
        'posts_per_page' => -1,

        'meta_query' => [
            [
                'key'     => '_devhire_company',
                'value'   => $company_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
        ],

        'orderby' => 'date',
        'order'   => 'DESC',
    ]);
?>

<section class="company-header">

    <div class="container">

        <a
            class="back-link"
            href="<?php echo esc_url(
                get_post_type_archive_link('company')
            ); ?>"
        >
            &larr; All Companies
        </a>


        <div class="company-heading">

            <div class="company-profile-logo">

                <?php if (has_post_thumbnail()) : ?>

                    <?php
                    the_post_thumbnail(
                        'thumbnail',
                        [
                            'class' => 'company-logo-image',
                        ]
                    );
                    ?>

                <?php else : ?>

                    <?php
                    echo esc_html(
                        strtoupper(
                            substr(get_the_title(), 0, 1)
                        )
                    );
                    ?>

                <?php endif; ?>

            </div>


            <div>

                <h1>
                    <?php the_title(); ?>
                </h1>


                <div class="company-meta">

                    <?php if ($industry) : ?>
                        <span>
                            <?php echo esc_html($industry); ?>
                        </span>
                    <?php endif; ?>


                    <?php if ($location) : ?>
                        <span>
                            <?php echo esc_html($location); ?>
                        </span>
                    <?php endif; ?>


                    <?php if ($size) : ?>
                        <span>
                            <?php echo esc_html($size); ?>
                        </span>
                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</section>


<section class="company-content-section">

    <div class="container company-layout">

        <div class="company-main">

            <article class="company-card">

                <h2>
                    About <?php the_title(); ?>
                </h2>

                <div class="company-description">
                    <?php the_content(); ?>
                </div>

            </article>


            <section class="company-card company-jobs">

                <div class="company-jobs-heading">

                    <div>
                        <h2>Open Positions</h2>

                        <p>
                            <?php
                            printf(
                                esc_html(
                                    _n(
                                        '%s open position',
                                        '%s open positions',
                                        $company_jobs->found_posts,
                                        'devhire'
                                    )
                                ),
                                esc_html(
                                    number_format_i18n(
                                        $company_jobs->found_posts
                                    )
                                )
                            );
                            ?>
                        </p>
                    </div>

                </div>


                <?php if ($company_jobs->have_posts()) : ?>

                    <div class="company-job-list">

                        <?php
                        while ($company_jobs->have_posts()) :
                            $company_jobs->the_post();

                            $types = get_the_terms(
                                get_the_ID(),
                                'job_type'
                            );

                            $locations = get_the_terms(
                                get_the_ID(),
                                'job_location'
                            );
                        ?>

                            <article class="company-job">

                                <div>

                                    <h3>
                                        <a href="<?php the_permalink(); ?>">
                                            <?php the_title(); ?>
                                        </a>
                                    </h3>


                                    <div class="job-meta">

                                        <?php if (
                                            $types &&
                                            !is_wp_error($types)
                                        ) : ?>

                                            <span>
                                                <?php
                                                echo esc_html(
                                                    $types[0]->name
                                                );
                                                ?>
                                            </span>

                                        <?php endif; ?>


                                        <?php if (
                                            $locations &&
                                            !is_wp_error($locations)
                                        ) : ?>

                                            <span>
                                                <?php
                                                echo esc_html(
                                                    $locations[0]->name
                                                );
                                                ?>
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <a
                                    class="secondary-button"
                                    href="<?php the_permalink(); ?>"
                                >
                                    View Job
                                </a>

                            </article>

                        <?php endwhile; ?>

                    </div>

                <?php else : ?>

                    <p class="empty-message">
                        This company currently has no open positions.
                    </p>

                <?php endif; ?>

                <?php wp_reset_postdata(); ?>

            </section>

        </div>


        <aside class="company-sidebar">

            <div class="company-card">

                <h3>Company Overview</h3>


                <?php if ($industry) : ?>

                    <div class="summary-item">
                        <span class="summary-label">
                            Industry
                        </span>

                        <strong>
                            <?php echo esc_html($industry); ?>
                        </strong>
                    </div>

                <?php endif; ?>


                <?php if ($location) : ?>

                    <div class="summary-item">
                        <span class="summary-label">
                            Location
                        </span>

                        <strong>
                            <?php echo esc_html($location); ?>
                        </strong>
                    </div>

                <?php endif; ?>


                <?php if ($size) : ?>

                    <div class="summary-item">
                        <span class="summary-label">
                            Company Size
                        </span>

                        <strong>
                            <?php echo esc_html($size); ?>
                        </strong>
                    </div>

                <?php endif; ?>


                <?php if ($website) : ?>

                    <a
                        class="primary-button company-website-button"
                        href="<?php echo esc_url($website); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Visit Website
                    </a>

                <?php endif; ?>

            </div>

        </aside>

    </div>

</section>

<?php
endwhile;

get_footer();
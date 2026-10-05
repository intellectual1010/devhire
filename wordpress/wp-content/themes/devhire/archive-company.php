<?php
get_header();

$keyword = isset($_GET['company_search'])
    ? sanitize_text_field(wp_unslash($_GET['company_search']))
    : '';

$paged = max(1, get_query_var('paged'));

$companies = new WP_Query([
    'post_type'      => 'company',
    'post_status'    => 'publish',
    'posts_per_page' => 9,
    'paged'          => $paged,
    's'              => $keyword,
    'orderby'        => 'title',
    'order'          => 'ASC',
]);
?>

<section class="companies-hero">
    <div class="container">

        <span class="hero-badge">
            Technology Companies
        </span>

        <h1>Discover companies hiring developers</h1>

        <p>
            Explore technology companies, learn about their teams
            and discover their current engineering opportunities.
        </p>

    </div>
</section>


<section class="companies-section">

    <div class="container">

        <form
            class="company-search"
            method="get"
            action="<?php echo esc_url(
                get_post_type_archive_link('company')
            ); ?>"
        >

            <input
                type="search"
                name="company_search"
                placeholder="Search companies..."
                value="<?php echo esc_attr($keyword); ?>"
            >

            <button
                type="submit"
                class="primary-button"
            >
                Search
            </button>

            <?php if ($keyword) : ?>

                <a
                    class="clear-button"
                    href="<?php echo esc_url(
                        get_post_type_archive_link('company')
                    ); ?>"
                >
                    Clear
                </a>

            <?php endif; ?>

        </form>


        <div class="companies-toolbar">

            <div>
                <strong>
                    <?php
                    echo esc_html(
                        number_format_i18n(
                            $companies->found_posts
                        )
                    );
                    ?>
                </strong>

                <?php
                echo esc_html(
                    _n(
                        'company found',
                        'companies found',
                        $companies->found_posts,
                        'devhire'
                    )
                );
                ?>
            </div>

        </div>


        <?php if ($companies->have_posts()) : ?>

            <div class="company-grid">

                <?php
                while ($companies->have_posts()) :
                    $companies->the_post();

                    $company_id = get_the_ID();

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
                     * Count jobs assigned to this company.
                     */
                    $open_jobs = new WP_Query([
                        'post_type'      => 'job',
                        'post_status'    => 'publish',
                        'posts_per_page' => 1,
                        'fields'         => 'ids',

                        'meta_query' => [
                            [
                                'key'     => '_devhire_company',
                                'value'   => $company_id,
                                'compare' => '=',
                                'type'    => 'NUMERIC',
                            ],
                        ],
                    ]);

                    $job_count = $open_jobs->found_posts;
                ?>

                    <article class="company-directory-card">

                        <div class="company-card-header">

                            <div class="company-directory-logo">

                                <?php if (has_post_thumbnail()) : ?>

                                    <?php
                                    the_post_thumbnail(
                                        'thumbnail',
                                        [
                                            'class' =>
                                                'company-logo-image',
                                        ]
                                    );
                                    ?>

                                <?php else : ?>

                                    <?php
                                    echo esc_html(
                                        strtoupper(
                                            substr(
                                                get_the_title(),
                                                0,
                                                1
                                            )
                                        )
                                    );
                                    ?>

                                <?php endif; ?>

                            </div>


                            <div class="company-card-title">

                                <h2>
                                    <a href="<?php the_permalink(); ?>">
                                        <?php the_title(); ?>
                                    </a>
                                </h2>

                                <?php if ($industry) : ?>

                                    <span>
                                        <?php
                                        echo esc_html($industry);
                                        ?>
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>


                        <div class="company-card-description">

                            <?php
                            echo esc_html(
                                wp_trim_words(
                                    get_the_excerpt()
                                        ?: wp_strip_all_tags(
                                            get_the_content()
                                        ),
                                    22
                                )
                            );
                            ?>

                        </div>


                        <div class="company-card-details">

                            <?php if ($location) : ?>

                                <div>
                                    <span>Location</span>

                                    <strong>
                                        <?php
                                        echo esc_html($location);
                                        ?>
                                    </strong>
                                </div>

                            <?php endif; ?>


                            <?php if ($size) : ?>

                                <div>
                                    <span>Company Size</span>

                                    <strong>
                                        <?php
                                        echo esc_html($size);
                                        ?>
                                    </strong>
                                </div>

                            <?php endif; ?>

                        </div>


                        <div class="company-card-footer">

                            <span class="open-jobs-count">

                                <?php
                                printf(
                                    esc_html(
                                        _n(
                                            '%s open position',
                                            '%s open positions',
                                            $job_count,
                                            'devhire'
                                        )
                                    ),
                                    esc_html(
                                        number_format_i18n(
                                            $job_count
                                        )
                                    )
                                );
                                ?>

                            </span>


                            <a
                                class="secondary-button"
                                href="<?php the_permalink(); ?>"
                            >
                                View Company
                            </a>

                        </div>

                    </article>

                    <?php wp_reset_postdata(); ?>

                <?php endwhile; ?>

            </div>


            <div class="pagination">

                <?php
                echo wp_kses_post(
                    paginate_links([
                        'total'   => $companies->max_num_pages,
                        'current' => $paged,

                        'add_args' => array_filter([
                            'company_search' => $keyword,
                        ]),

                        'prev_text' => '&larr; Previous',
                        'next_text' => 'Next &rarr;',
                    ])
                );
                ?>

            </div>


        <?php else : ?>

            <div class="no-jobs">

                <h2>No companies found</h2>

                <p>
                    Try another company name.
                </p>

                <a
                    class="primary-button"
                    href="<?php echo esc_url(
                        get_post_type_archive_link('company')
                    ); ?>"
                >
                    View All Companies
                </a>

            </div>

        <?php endif; ?>

        <?php wp_reset_postdata(); ?>

    </div>

</section>

<?php get_footer(); ?>
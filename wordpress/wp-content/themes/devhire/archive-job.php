<?php
get_header();

$keyword = isset($_GET['keyword'])
    ? sanitize_text_field(wp_unslash($_GET['keyword']))
    : '';

$skill = isset($_GET['skill'])
    ? sanitize_text_field(wp_unslash($_GET['skill']))
    : '';

$job_type = isset($_GET['job_type'])
    ? sanitize_text_field(wp_unslash($_GET['job_type']))
    : '';

$location = isset($_GET['location'])
    ? sanitize_text_field(wp_unslash($_GET['location']))
    : '';

$paged = max(1, get_query_var('paged'));

$tax_query = [];

if ($skill) {
    $tax_query[] = [
        'taxonomy' => 'job_skill',
        'field'    => 'slug',
        'terms'    => $skill,
    ];
}

if ($job_type) {
    $tax_query[] = [
        'taxonomy' => 'job_type',
        'field'    => 'slug',
        'terms'    => $job_type,
    ];
}

if ($location) {
    $tax_query[] = [
        'taxonomy' => 'job_location',
        'field'    => 'slug',
        'terms'    => $location,
    ];
}

if (count($tax_query) > 1) {
    $tax_query['relation'] = 'AND';
}

$args = [
    'post_type'      => 'job',
    'post_status'    => 'publish',
    'posts_per_page' => 6,
    'paged'          => $paged,
    's'              => $keyword,
];

if (!empty($tax_query)) {
    $args['tax_query'] = $tax_query;
}

$jobs = new WP_Query($args);
?>

<section class="jobs-hero">
    <div class="container">

        <span class="hero-badge">
            Developer Opportunities
        </span>

        <h1>Find your next developer job</h1>

        <p>
            Search opportunities across software engineering,
            cloud, WordPress and artificial intelligence.
        </p>

    </div>
</section>


<section class="jobs-section">
    <div class="container">

        <form
            class="job-filters"
            method="get"
            action="<?php echo esc_url(get_post_type_archive_link('job')); ?>"
        >

            <div class="filter-field filter-search">
                <label for="keyword">Search</label>

                <input
                    id="keyword"
                    type="search"
                    name="keyword"
                    placeholder="Job title or keyword..."
                    value="<?php echo esc_attr($keyword); ?>"
                >
            </div>


            <div class="filter-field">
                <label for="skill">Skill</label>

                <?php
                wp_dropdown_categories([
                    'taxonomy'        => 'job_skill',
                    'name'            => 'skill',
                    'id'              => 'skill',
                    'show_option_all' => 'All Skills',
                    'hide_empty'      => false,
                    'value_field'     => 'slug',
                    'selected'        => $skill,
                ]);
                ?>
            </div>


            <div class="filter-field">
                <label for="job_type">Job Type</label>

                <?php
                wp_dropdown_categories([
                    'taxonomy'        => 'job_type',
                    'name'            => 'job_type',
                    'id'              => 'job_type',
                    'show_option_all' => 'All Types',
                    'hide_empty'      => false,
                    'value_field'     => 'slug',
                    'selected'        => $job_type,
                ]);
                ?>
            </div>


            <div class="filter-field">
                <label for="location">Location</label>

                <?php
                wp_dropdown_categories([
                    'taxonomy'        => 'job_location',
                    'name'            => 'location',
                    'id'              => 'location',
                    'show_option_all' => 'All Locations',
                    'hide_empty'      => false,
                    'value_field'     => 'slug',
                    'selected'        => $location,
                ]);
                ?>
            </div>


            <div class="filter-actions">

                <button class="primary-button" type="submit">
                    Search Jobs
                </button>

                <a
                    class="clear-button"
                    href="<?php echo esc_url(
                        get_post_type_archive_link('job')
                    ); ?>"
                >
                    Clear
                </a>

            </div>

        </form>


        <div class="jobs-toolbar">

            <div>
                <strong>
                    <?php echo esc_html($jobs->found_posts); ?>
                </strong>

                <?php
                echo esc_html(
                    _n(
                        'job found',
                        'jobs found',
                        $jobs->found_posts,
                        'devhire'
                    )
                );
                ?>
            </div>

        </div>


        <?php if ($jobs->have_posts()) : ?>

            <div class="job-list">

                <?php while ($jobs->have_posts()) : $jobs->the_post(); ?>

                    <?php
                    $skills = get_the_terms(
                        get_the_ID(),
                        'job_skill'
                    );

                    $types = get_the_terms(
                        get_the_ID(),
                        'job_type'
                    );

                    $locations = get_the_terms(
                        get_the_ID(),
                        'job_location'
                    );
                    ?>

                    <article class="job-card">

                        <div class="job-card-main">

                            <div class="job-company-icon">
                                <?php
                                echo esc_html(
                                    strtoupper(
                                        substr(get_the_title(), 0, 1)
                                    )
                                );
                                ?>
                            </div>

                            <div class="job-content">

                                <div class="job-card-top">

                                    <div>
                                        <h2>
                                            <a href="<?php the_permalink(); ?>">
                                                <?php the_title(); ?>
                                            </a>
                                        </h2>

                                        <div class="job-meta">

                                            <?php if ($types && !is_wp_error($types)) : ?>
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

                                    <span class="posted-date">
                                        <?php
                                        echo esc_html(
                                            human_time_diff(
                                                get_the_time('U'),
                                                current_time('timestamp')
                                            )
                                        );
                                        ?> ago
                                    </span>

                                </div>


                                <div class="job-description">
                                    <?php
                                    echo esc_html(
                                        wp_trim_words(
                                            get_the_excerpt(),
                                            24
                                        )
                                    );
                                    ?>
                                </div>


                                <?php if (
                                    $skills &&
                                    !is_wp_error($skills)
                                ) : ?>

                                    <div class="skill-tags">

                                        <?php foreach ($skills as $term) : ?>

                                            <span class="skill-tag">
                                                <?php
                                                echo esc_html(
                                                    $term->name
                                                );
                                                ?>
                                            </span>

                                        <?php endforeach; ?>

                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>


                        <div class="job-card-action">

                            <a
                                class="secondary-button"
                                href="<?php the_permalink(); ?>"
                            >
                                View Job
                            </a>

                        </div>

                    </article>

                <?php endwhile; ?>

            </div>


            <div class="pagination">

                <?php
                echo wp_kses_post(
                    paginate_links([
                        'total'   => $jobs->max_num_pages,
                        'current' => $paged,

                        'add_args' => array_filter([
                            'keyword'  => $keyword,
                            'skill'    => $skill,
                            'job_type' => $job_type,
                            'location' => $location,
                        ]),

                        'prev_text' => '&larr; Previous',
                        'next_text' => 'Next &rarr;',
                    ])
                );
                ?>

            </div>


        <?php else : ?>

            <div class="no-jobs">

                <h2>No jobs found</h2>

                <p>
                    Try changing your search or filters.
                </p>

                <a
                    class="primary-button"
                    href="<?php echo esc_url(
                        get_post_type_archive_link('job')
                    ); ?>"
                >
                    View All Jobs
                </a>

            </div>

        <?php endif; ?>

        <?php wp_reset_postdata(); ?>

    </div>
</section>

<?php get_footer(); ?>
<?php
get_header();

/*
 * Real platform statistics.
 */
$job_counts = wp_count_posts('job');
$company_counts = wp_count_posts('company');

$total_jobs = isset($job_counts->publish)
    ? (int) $job_counts->publish
    : 0;

$total_companies = isset($company_counts->publish)
    ? (int) $company_counts->publish
    : 0;

$total_skills = wp_count_terms([
    'taxonomy'   => 'job_skill',
    'hide_empty' => true,
]);

if (is_wp_error($total_skills)) {
    $total_skills = 0;
}


/*
 * Latest Jobs.
 */
$latest_jobs = new WP_Query([
    'post_type'      => 'job',
    'post_status'    => 'publish',
    'posts_per_page' => 6,
    'orderby'        => 'date',
    'order'          => 'DESC',
]);


/*
 * Companies.
 */
$featured_companies = new WP_Query([
    'post_type'      => 'company',
    'post_status'    => 'publish',
    'posts_per_page' => 6,
    'orderby'        => 'date',
    'order'          => 'DESC',
]);


/*
 * Popular Skills.
 */
$popular_skills = get_terms([
    'taxonomy'   => 'job_skill',
    'hide_empty' => true,
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 12,
]);
?>


<!-- HERO -->

<section class="hero">

    <div class="container">

        <div class="hero-badge">
            Developer opportunities worldwide
        </div>

        <h1>
            Find your next
            <span class="gradient-text">
                developer opportunity
            </span>
        </h1>

        <p>
            Discover engineering opportunities from companies
            looking for talented developers across frontend,
            backend, cloud, WordPress and AI.
        </p>


        <form
            class="hero-job-search"
            method="get"
            action="<?php echo esc_url(
                get_post_type_archive_link('job')
            ); ?>"
        >

            <input
                type="search"
                name="keyword"
                placeholder="Search React, .NET, WordPress, AI..."
                aria-label="Search jobs"
            >

            <button
                class="primary-button"
                type="submit"
            >
                Search Jobs
            </button>

        </form>


        <div class="hero-actions">

            <a
                class="secondary-button"
                href="<?php echo esc_url(
                    get_post_type_archive_link('company')
                ); ?>"
            >
                Explore Companies
            </a>

        </div>

    </div>

</section>


<!-- REAL STATISTICS -->

<section class="stats">

    <div class="container stats-grid">

        <div class="stat">

            <strong>
                <?php echo esc_html(
                    number_format_i18n($total_jobs)
                ); ?>
            </strong>

            <span>
                Open Jobs
            </span>

        </div>


        <div class="stat">

            <strong>
                <?php echo esc_html(
                    number_format_i18n($total_companies)
                ); ?>
            </strong>

            <span>
                Companies
            </span>

        </div>


        <div class="stat">

            <strong>
                <?php echo esc_html(
                    number_format_i18n($total_skills)
                ); ?>
            </strong>

            <span>
                Technology Skills
            </span>

        </div>

    </div>

</section>


<!-- LATEST JOBS -->

<section class="section">

    <div class="container">

        <div class="home-section-heading">

            <div>

                <span class="section-label">
                    Latest opportunities
                </span>

                <h2>
                    Developer Jobs
                </h2>

                <p>
                    Explore recently published engineering
                    opportunities.
                </p>

            </div>


            <a
                class="secondary-button"
                href="<?php echo esc_url(
                    get_post_type_archive_link('job')
                ); ?>"
            >
                View All Jobs
            </a>

        </div>


        <?php if ($latest_jobs->have_posts()) : ?>

            <div class="home-job-grid">

                <?php
                while ($latest_jobs->have_posts()) :
                    $latest_jobs->the_post();

                    $job_id = get_the_ID();

                    $company_id = absint(
                        get_post_meta(
                            $job_id,
                            '_devhire_company',
                            true
                        )
                    );

                    $company_name = $company_id
                        ? get_the_title($company_id)
                        : '';

                    $salary = get_post_meta(
                        $job_id,
                        '_devhire_salary',
                        true
                    );

                    $types = get_the_terms(
                        $job_id,
                        'job_type'
                    );

                    $locations = get_the_terms(
                        $job_id,
                        'job_location'
                    );

                    $skills = get_the_terms(
                        $job_id,
                        'job_skill'
                    );
                ?>

                    <article class="home-job-card">

                        <div class="home-job-header">

                            <div class="job-company-icon">

                                <?php if (
                                    $company_id &&
                                    has_post_thumbnail($company_id)
                                ) : ?>

                                    <?php
                                    echo get_the_post_thumbnail(
                                        $company_id,
                                        'thumbnail',
                                        [
                                            'class' => 'company-logo-image',
                                            'alt'   => $company_name
                                                ? $company_name . ' logo'
                                                : 'Company logo',
                                        ]
                                    );
                                    ?>

                                <?php else : ?>

                                    <?php
                                    echo esc_html(
                                        strtoupper(
                                            substr(
                                                $company_name
                                                    ?: get_the_title(),
                                                0,
                                                1
                                            )
                                        )
                                    );
                                    ?>

                                <?php endif; ?>

                            </div>


                            <div>

                                <?php if ($company_name) : ?>

                                    <span class="home-job-company">
                                        <?php
                                        echo esc_html(
                                            $company_name
                                        );
                                        ?>
                                    </span>

                                <?php endif; ?>


                                <h3>
                                    <a href="<?php the_permalink(); ?>">
                                        <?php the_title(); ?>
                                    </a>
                                </h3>

                            </div>

                        </div>


                        <div class="home-job-meta">

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

                        </div>


                        <?php if ($salary) : ?>

                            <div class="home-job-salary">
                                <?php echo esc_html($salary); ?>
                            </div>

                        <?php endif; ?>


                        <?php if (
                            $skills &&
                            !is_wp_error($skills)
                        ) : ?>

                            <div class="skill-tags">

                                <?php
                                foreach (
                                    array_slice($skills, 0, 4)
                                    as $skill
                                ) :
                                ?>

                                    <span class="skill-tag">
                                        <?php
                                        echo esc_html(
                                            $skill->name
                                        );
                                        ?>
                                    </span>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>


                        <a
                            class="home-job-link"
                            href="<?php the_permalink(); ?>"
                        >
                            View Position &rarr;
                        </a>

                    </article>

                <?php endwhile; ?>

            </div>

        <?php else : ?>

            <div class="no-jobs">
                <h3>No jobs available yet.</h3>
            </div>

        <?php endif; ?>

        <?php wp_reset_postdata(); ?>

    </div>

</section>


<!-- POPULAR SKILLS -->

<?php if (
    $popular_skills &&
    !is_wp_error($popular_skills)
) : ?>

<section class="section skills-section">

    <div class="container">

        <div class="section-heading">

            <span class="section-label">
                Explore by technology
            </span>

            <h2>
                Popular Skills
            </h2>

            <p>
                Find opportunities matching your technology stack.
            </p>

        </div>


        <div class="popular-skills">

            <?php foreach ($popular_skills as $skill) : ?>

                <a
                    class="popular-skill"
                    href="<?php echo esc_url(
                        add_query_arg(
                            'skill',
                            $skill->slug,
                            get_post_type_archive_link('job')
                        )
                    ); ?>"
                >

                    <span>
                        <?php echo esc_html($skill->name); ?>
                    </span>

                    <strong>
                        <?php echo esc_html($skill->count); ?>
                    </strong>

                </a>

            <?php endforeach; ?>

        </div>

    </div>

</section>

<?php endif; ?>


<!-- COMPANIES -->

<section
    class="section"
    id="companies"
>

    <div class="container">

        <div class="home-section-heading">

            <div>

                <span class="section-label">
                    Companies
                </span>

                <h2>
                    Meet the teams hiring
                </h2>

                <p>
                    Explore technology companies and their
                    current engineering opportunities.
                </p>

            </div>


            <a
                class="secondary-button"
                href="<?php echo esc_url(
                    get_post_type_archive_link('company')
                ); ?>"
            >
                View Companies
            </a>

        </div>


        <?php if (
            $featured_companies->have_posts()
        ) : ?>

            <div class="home-company-grid">

                <?php
                while (
                    $featured_companies->have_posts()
                ) :
                    $featured_companies->the_post();

                    $company_id = get_the_ID();

                    $industry = get_post_meta(
                        $company_id,
                        '_devhire_company_industry',
                        true
                    );

                    $location = get_post_meta(
                        $company_id,
                        '_devhire_company_location',
                        true
                    );


                    $company_jobs = new WP_Query([
                        'post_type'      => 'job',
                        'post_status'    => 'publish',
                        'posts_per_page' => 1,
                        'fields'         => 'ids',

                        'meta_query' => [
                            [
                                'key' =>
                                    '_devhire_company',

                                'value' =>
                                    $company_id,

                                'compare' => '=',

                                'type' =>
                                    'NUMERIC',
                            ],
                        ],
                    ]);

                    $open_jobs =
                        $company_jobs->found_posts;
                ?>

                    <article class="home-company-card">

                        <div class="company-directory-logo">

                            <?php if (
                                has_post_thumbnail()
                            ) : ?>

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


                        <h3>
                            <a href="<?php the_permalink(); ?>">
                                <?php the_title(); ?>
                            </a>
                        </h3>


                        <?php if ($industry) : ?>

                            <div class="home-company-industry">
                                <?php
                                echo esc_html($industry);
                                ?>
                            </div>

                        <?php endif; ?>


                        <?php if ($location) : ?>

                            <div class="home-company-location">
                                <?php
                                echo esc_html($location);
                                ?>
                            </div>

                        <?php endif; ?>


                        <div class="home-company-footer">

                            <span>
                                <?php
                                printf(
                                    esc_html(
                                        _n(
                                            '%s open job',
                                            '%s open jobs',
                                            $open_jobs,
                                            'devhire'
                                        )
                                    ),
                                    esc_html(
                                        number_format_i18n(
                                            $open_jobs
                                        )
                                    )
                                );
                                ?>
                            </span>


                            <a href="<?php the_permalink(); ?>">
                                View &rarr;
                            </a>

                        </div>

                    </article>

                    <?php wp_reset_postdata(); ?>

                <?php endwhile; ?>

            </div>

        <?php endif; ?>

        <?php wp_reset_postdata(); ?>

    </div>

</section>


<!-- CTA -->

<section class="home-cta">

    <div class="container">

        <div class="home-cta-inner">

            <div>

                <span class="section-label">
                    Find your next opportunity
                </span>

                <h2>
                    Ready for your next developer role?
                </h2>

                <p>
                    Explore available engineering positions and
                    apply directly through DevHire.
                </p>

            </div>


            <a
                class="primary-button"
                href="<?php echo esc_url(
                    get_post_type_archive_link('job')
                ); ?>"
            >
                Browse All Jobs
            </a>

        </div>

    </div>

</section>


<?php
get_footer();
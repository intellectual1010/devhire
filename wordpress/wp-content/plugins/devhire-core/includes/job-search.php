<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render a reusable job card.
 */
function devhire_render_job_card($job_id) {

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

    $remote = get_post_meta(
        $job_id,
        '_devhire_remote',
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

    <article class="job-card">

        <div class="job-card-header">

            <div>

                <?php if ($company_name) : ?>

                    <span class="job-company">
                        <?php
                        echo esc_html(
                            $company_name
                        );
                        ?>
                    </span>

                <?php endif; ?>


                <h2>

                    <a href="<?php echo esc_url(
                        get_permalink($job_id)
                    ); ?>">

                        <?php
                        echo esc_html(
                            get_the_title($job_id)
                        );
                        ?>

                    </a>

                </h2>

            </div>


            <span class="job-date">

                <?php
                echo esc_html(
                    human_time_diff(
                        get_the_time(
                            'U',
                            $job_id
                        ),
                        current_time('timestamp')
                    )
                );
                ?> ago

            </span>

        </div>


        <div class="job-meta">

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


            <?php if ($remote) : ?>

                <span>
                    <?php
                    echo esc_html(
                        ucfirst($remote)
                    );
                    ?>
                </span>

            <?php endif; ?>

        </div>


        <?php if ($salary) : ?>

            <div class="job-card-salary">
                <?php echo esc_html($salary); ?>
            </div>

        <?php endif; ?>


        <p class="job-card-description">

            <?php
            echo esc_html(
                wp_trim_words(
                    get_the_excerpt($job_id),
                    24
                )
            );
            ?>

        </p>


        <?php if (
            $skills &&
            !is_wp_error($skills)
        ) : ?>

            <div class="skill-tags">

                <?php
                foreach (
                    array_slice($skills, 0, 5)
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
            class="job-card-link"
            href="<?php echo esc_url(
                get_permalink($job_id)
            ); ?>"
        >
            View Position &rarr;
        </a>

    </article>

    <?php
}

/**
 * AJAX Job Search.
 */
function devhire_ajax_job_search() {

    check_ajax_referer(
        'devhire_job_search',
        'nonce'
    );

    $keyword = isset($_POST['keyword'])
        ? sanitize_text_field(
            wp_unslash($_POST['keyword'])
        )
        : '';

    $skill = isset($_POST['skill'])
        ? sanitize_title(
            wp_unslash($_POST['skill'])
        )
        : '';

    $job_type = isset($_POST['job_type'])
        ? sanitize_title(
            wp_unslash($_POST['job_type'])
        )
        : '';

    $location = isset($_POST['location'])
        ? sanitize_title(
            wp_unslash($_POST['location'])
        )
        : '';

    $page = isset($_POST['page'])
        ? max(1, absint($_POST['page']))
        : 1;


    $args = [
        'post_type'      => 'job',
        'post_status'    => 'publish',
        'posts_per_page' => 6,
        'paged'          => $page,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];


    if ($keyword) {
        $args['s'] = $keyword;
    }


    $tax_query = [];


    $filters = [
        'job_skill'    => $skill,
        'job_type'     => $job_type,
        'job_location' => $location,
    ];


    foreach ($filters as $taxonomy => $value) {

        if (!$value) {
            continue;
        }

        $tax_query[] = [
            'taxonomy' => $taxonomy,
            'field'    => 'slug',
            'terms'    => $value,
        ];
    }


    if ($tax_query) {

        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }

        $args['tax_query'] = $tax_query;
    }


    $query = new WP_Query($args);


    ob_start();


    if ($query->have_posts()) {

        echo '<div class="jobs-grid">';

        while ($query->have_posts()) {

            $query->the_post();

            devhire_render_job_card(
                get_the_ID()
            );
        }

        echo '</div>';


        if ($query->max_num_pages > 1) {

            echo '<div class="ajax-pagination">';

            for (
                $i = 1;
                $i <= $query->max_num_pages;
                $i++
            ) {

                $class =
                    $i === $page
                        ? 'ajax-page active'
                        : 'ajax-page';

                printf(
                    '<button type="button" class="%s" data-page="%d">%d</button>',
                    esc_attr($class),
                    absint($i),
                    absint($i)
                );
            }

            echo '</div>';
        }

    } else {

        echo '
            <div class="no-jobs">
                <h3>No jobs found</h3>
                <p>
                    Try changing your search
                    or filters.
                </p>
            </div>
        ';
    }


    $html = ob_get_clean();


    wp_reset_postdata();


    wp_send_json_success([
        'html'  => $html,
        'total' => (int) $query->found_posts,
        'page'  => $page,
    ]);
}


add_action(
    'wp_ajax_devhire_job_search',
    'devhire_ajax_job_search'
);

add_action(
    'wp_ajax_nopriv_devhire_job_search',
    'devhire_ajax_job_search'
);

/**
 * Job Search assets.
 */
function devhire_job_search_assets() {

    if (!is_post_type_archive('job')) {
        return;
    }


    wp_enqueue_script(
        'devhire-job-search',
        DEVHIRE_CORE_URL .
            'assets/js/job-search.js',
        [],
        DEVHIRE_CORE_VERSION,
        true
    );


    wp_localize_script(
        'devhire-job-search',
        'devhireJobSearch',
        [
            'ajaxUrl' =>
                admin_url('admin-ajax.php'),

            'nonce' =>
                wp_create_nonce(
                    'devhire_job_search'
                ),
        ]
    );
}

add_action(
    'wp_enqueue_scripts',
    'devhire_job_search_assets'
);
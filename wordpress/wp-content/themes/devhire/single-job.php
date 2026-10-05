<?php
get_header();

while (have_posts()) :
    the_post();

    $job_id = get_the_ID();

    $company_id = absint(
        get_post_meta(
            $job_id,
            '_devhire_company',
            true
        )
    );

    $salary = get_post_meta(
        $job_id,
        '_devhire_salary',
        true
    );

    $experience = get_post_meta(
        $job_id,
        '_devhire_experience',
        true
    );

    $remote = get_post_meta(
        $job_id,
        '_devhire_remote',
        true
    );

    $deadline = get_post_meta(
        $job_id,
        '_devhire_deadline',
        true
    );

    $apply_url = get_post_meta(
        $job_id,
        '_devhire_apply_url',
        true
    );

    $skills = get_the_terms(
        $job_id,
        'job_skill'
    );

    $types = get_the_terms(
        $job_id,
        'job_type'
    );

    $locations = get_the_terms(
        $job_id,
        'job_location'
    );

    $company_name = $company_id
        ? get_the_title($company_id)
        : '';
?>

<section class="job-detail-header">

    <div class="container">

        <a
            class="back-link"
            href="<?php echo esc_url(
                get_post_type_archive_link('job')
            ); ?>"
        >
            &larr; Back to Jobs
        </a>


        <div class="job-detail-heading">

            <div class="company-logo-large">

                <?php if ($company_name) : ?>

                    <?php
                    echo esc_html(
                        strtoupper(
                            substr($company_name, 0, 1)
                        )
                    );
                    ?>

                <?php else : ?>

                    J

                <?php endif; ?>

            </div>


            <div>

                <?php if ($company_name) : ?>

                    <div class="job-company-name">

                        <?php if (
                            get_post_status($company_id) === 'publish'
                        ) : ?>

                            <a href="<?php echo esc_url(
                                get_permalink($company_id)
                            ); ?>">
                                <?php
                                echo esc_html($company_name);
                                ?>
                            </a>

                        <?php else : ?>

                            <?php
                            echo esc_html($company_name);
                            ?>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>


                <h1>
                    <?php the_title(); ?>
                </h1>


                <div class="job-detail-meta">

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
                            if ($remote === 'yes') {
                                echo 'Remote';
                            } elseif ($remote === 'hybrid') {
                                echo 'Hybrid';
                            } else {
                                echo 'On-site';
                            }
                            ?>
                        </span>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</section>


<section class="job-detail-section">

    <div class="container job-detail-layout">


        <article class="job-main-content">

            <div class="job-content-card">

                <h2>About the role</h2>

                <div class="job-body">
                    <?php the_content(); ?>
                </div>

            </div>


            <?php if (
                $skills &&
                !is_wp_error($skills)
            ) : ?>

                <div class="job-content-card">

                    <h2>Skills & Technologies</h2>

                    <div class="skill-tags">

                        <?php foreach ($skills as $skill) : ?>

                            <span class="skill-tag skill-tag-large">

                                <?php
                                echo esc_html(
                                    $skill->name
                                );
                                ?>

                            </span>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endif; ?>

            <?php
                echo do_shortcode(
                    '[devhire_application_form]'
                );
            ?>

        </article>


        <aside class="job-sidebar">

            <div class="job-summary-card">

                <h3>Job Overview</h3>


                <?php if ($company_name) : ?>

                    <div class="summary-item">

                        <span class="summary-label">
                            Company
                        </span>

                        <strong>
                            <?php
                            echo esc_html(
                                $company_name
                            );
                            ?>
                        </strong>

                    </div>

                <?php endif; ?>


                <?php if ($salary) : ?>

                    <div class="summary-item">

                        <span class="summary-label">
                            Salary
                        </span>

                        <strong>
                            <?php echo esc_html($salary); ?>
                        </strong>

                    </div>

                <?php endif; ?>


                <?php if ($experience) : ?>

                    <div class="summary-item">

                        <span class="summary-label">
                            Experience
                        </span>

                        <strong>
                            <?php
                            echo esc_html(
                                $experience
                            );
                            ?>
                        </strong>

                    </div>

                <?php endif; ?>


                <?php if (
                    $locations &&
                    !is_wp_error($locations)
                ) : ?>

                    <div class="summary-item">

                        <span class="summary-label">
                            Location
                        </span>

                        <strong>
                            <?php
                            echo esc_html(
                                $locations[0]->name
                            );
                            ?>
                        </strong>

                    </div>

                <?php endif; ?>


                <?php if (
                    $types &&
                    !is_wp_error($types)
                ) : ?>

                    <div class="summary-item">

                        <span class="summary-label">
                            Job Type
                        </span>

                        <strong>
                            <?php
                            echo esc_html(
                                $types[0]->name
                            );
                            ?>
                        </strong>

                    </div>

                <?php endif; ?>


                <?php if ($deadline) : ?>

                    <div class="summary-item">

                        <span class="summary-label">
                            Application Deadline
                        </span>

                        <strong>
                            <?php
                            echo esc_html(
                                date_i18n(
                                    get_option('date_format'),
                                    strtotime($deadline)
                                )
                            );
                            ?>
                        </strong>

                    </div>

                <?php endif; ?>


                <div class="summary-item">

                    <span class="summary-label">
                        Posted
                    </span>

                    <strong>
                        <?php
                        echo esc_html(
                            get_the_date()
                        );
                        ?>
                    </strong>

                </div>


                <a
                    class="primary-button apply-button"
                    href="#apply"
                >
                    Apply for this Job
                </a>

                <button
                    type="button"
                    class="devhire-save-job"
                    data-job-id="<?php echo esc_attr(
                        get_the_ID()
                    ); ?>"
                >
                    ♡ Save Job
                </button>

                <?php if ($apply_url) : ?>

                    <a
                        class="external-apply-link"
                        href="<?php echo esc_url($apply_url); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Apply on company website &rarr;
                    </a>

                <?php endif; ?>

            </div>

        </aside>

    </div>

</section>

<?php
endwhile;

get_footer();
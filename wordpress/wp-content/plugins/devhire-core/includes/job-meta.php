<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Job Details meta box
 * and save handlers.
 */

/**
 * Render Job Details fields.
 */
function devhire_job_details_callback($post) {

    wp_nonce_field(
        'devhire_save_job_details',
        'devhire_job_details_nonce'
    );

    $company = get_post_meta(
        $post->ID,
        '_devhire_company',
        true
    );

    $salary = get_post_meta(
        $post->ID,
        '_devhire_salary',
        true
    );

    $experience = get_post_meta(
        $post->ID,
        '_devhire_experience',
        true
    );

    $remote = get_post_meta(
        $post->ID,
        '_devhire_remote',
        true
    );

    $deadline = get_post_meta(
        $post->ID,
        '_devhire_deadline',
        true
    );

    $apply_url = get_post_meta(
        $post->ID,
        '_devhire_apply_url',
        true
    );

    $companies = get_posts([
        'post_type'      => 'company',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);
    ?>

    <style>
        .devhire-meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .devhire-meta-field label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .devhire-meta-field input,
        .devhire-meta-field select {
            width: 100%;
        }

        .devhire-meta-full {
            grid-column: 1 / -1;
        }

        @media (max-width: 782px) {
            .devhire-meta-grid {
                grid-template-columns: 1fr;
            }

            .devhire-meta-full {
                grid-column: auto;
            }
        }
    </style>

    <div class="devhire-meta-grid">

        <div class="devhire-meta-field">

            <label for="devhire_company">
                Company
            </label>

            <select
                id="devhire_company"
                name="devhire_company"
            >

                <option value="">
                    Select Company
                </option>

                <?php foreach ($companies as $company_post) : ?>

                    <option
                        value="<?php echo esc_attr($company_post->ID); ?>"
                        <?php selected(
                            $company,
                            $company_post->ID
                        ); ?>
                    >
                        <?php
                        echo esc_html(
                            $company_post->post_title
                        );
                        ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="devhire-meta-field">

            <label for="devhire_salary">
                Salary
            </label>

            <input
                id="devhire_salary"
                name="devhire_salary"
                type="text"
                placeholder="$80,000 - $120,000 / year"
                value="<?php echo esc_attr($salary); ?>"
            >

        </div>


        <div class="devhire-meta-field">

            <label for="devhire_experience">
                Experience Level
            </label>

            <select
                id="devhire_experience"
                name="devhire_experience"
            >

                <option value="">
                    Select Level
                </option>

                <?php
                $levels = [
                    'Entry Level',
                    'Junior',
                    'Mid Level',
                    'Senior',
                    'Lead',
                    'Principal',
                ];

                foreach ($levels as $level) :
                ?>

                    <option
                        value="<?php echo esc_attr($level); ?>"
                        <?php selected(
                            $experience,
                            $level
                        ); ?>
                    >
                        <?php echo esc_html($level); ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="devhire-meta-field">

            <label for="devhire_remote">
                Remote Option
            </label>

            <select
                id="devhire_remote"
                name="devhire_remote"
            >

                <option
                    value="no"
                    <?php selected($remote, 'no'); ?>
                >
                    No
                </option>

                <option
                    value="yes"
                    <?php selected($remote, 'yes'); ?>
                >
                    Yes
                </option>

                <option
                    value="hybrid"
                    <?php selected($remote, 'hybrid'); ?>
                >
                    Hybrid
                </option>

            </select>

        </div>


        <div class="devhire-meta-field">

            <label for="devhire_deadline">
                Application Deadline
            </label>

            <input
                id="devhire_deadline"
                name="devhire_deadline"
                type="date"
                value="<?php echo esc_attr($deadline); ?>"
            >

        </div>


        <div class="devhire-meta-field devhire-meta-full">

            <label for="devhire_apply_url">
                Application URL
            </label>

            <input
                id="devhire_apply_url"
                name="devhire_apply_url"
                type="url"
                placeholder="https://company.com/jobs/apply"
                value="<?php echo esc_attr($apply_url); ?>"
            >

        </div>

    </div>

    <?php
}


/**
 * Save Job Details.
 */
function devhire_save_job_details($post_id) {

    if (
        !isset($_POST['devhire_job_details_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(
                wp_unslash(
                    $_POST['devhire_job_details_nonce']
                )
            ),
            'devhire_save_job_details'
        )
    ) {
        return;
    }

    if (
        defined('DOING_AUTOSAVE') &&
        DOING_AUTOSAVE
    ) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (get_post_type($post_id) !== 'job') {
        return;
    }


    $fields = [
        'devhire_company' => [
            'key'      => '_devhire_company',
            'sanitize' => 'absint',
        ],

        'devhire_salary' => [
            'key'      => '_devhire_salary',
            'sanitize' => 'sanitize_text_field',
        ],

        'devhire_experience' => [
            'key'      => '_devhire_experience',
            'sanitize' => 'sanitize_text_field',
        ],

        'devhire_remote' => [
            'key'      => '_devhire_remote',
            'sanitize' => 'sanitize_text_field',
        ],

        'devhire_deadline' => [
            'key'      => '_devhire_deadline',
            'sanitize' => 'sanitize_text_field',
        ],

        'devhire_apply_url' => [
            'key'      => '_devhire_apply_url',
            'sanitize' => 'esc_url_raw',
        ],
    ];


    foreach ($fields as $field => $config) {

        if (!isset($_POST[$field])) {
            delete_post_meta(
                $post_id,
                $config['key']
            );

            continue;
        }

        $value = wp_unslash($_POST[$field]);

        $value = call_user_func(
            $config['sanitize'],
            $value
        );

        if ($value === '' || $value === 0) {

            delete_post_meta(
                $post_id,
                $config['key']
            );

        } else {

            update_post_meta(
                $post_id,
                $config['key'],
                $value
            );
        }
    }
}

add_action(
    'save_post_job',
    'devhire_save_job_details'
);
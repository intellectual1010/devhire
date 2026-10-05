<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add Company Details meta box.
 */
function devhire_add_company_meta_box() {
    add_meta_box(
        'devhire_company_details',
        'Company Details',
        'devhire_company_details_callback',
        'company',
        'normal',
        'high'
    );
}

add_action('add_meta_boxes', 'devhire_add_company_meta_box');


function devhire_company_details_callback($post) {

    wp_nonce_field(
        'devhire_save_company_details',
        'devhire_company_details_nonce'
    );

    $website = get_post_meta(
        $post->ID,
        '_devhire_company_website',
        true
    );

    $location = get_post_meta(
        $post->ID,
        '_devhire_company_location',
        true
    );

    $size = get_post_meta(
        $post->ID,
        '_devhire_company_size',
        true
    );

    $industry = get_post_meta(
        $post->ID,
        '_devhire_company_industry',
        true
    );
    ?>

    <div class="devhire-company-fields">

        <p>
            <label for="devhire_company_website">
                <strong>Website</strong>
            </label>
            <br>

            <input
                type="url"
                id="devhire_company_website"
                name="devhire_company_website"
                value="<?php echo esc_attr($website); ?>"
                placeholder="https://example.com"
                style="width:100%;"
            >
        </p>

        <p>
            <label for="devhire_company_location">
                <strong>Location</strong>
            </label>
            <br>

            <input
                type="text"
                id="devhire_company_location"
                name="devhire_company_location"
                value="<?php echo esc_attr($location); ?>"
                placeholder="San Francisco, CA"
                style="width:100%;"
            >
        </p>

        <p>
            <label for="devhire_company_industry">
                <strong>Industry</strong>
            </label>
            <br>

            <input
                type="text"
                id="devhire_company_industry"
                name="devhire_company_industry"
                value="<?php echo esc_attr($industry); ?>"
                placeholder="Software Development"
                style="width:100%;"
            >
        </p>

        <p>
            <label for="devhire_company_size">
                <strong>Company Size</strong>
            </label>
            <br>

            <select
                id="devhire_company_size"
                name="devhire_company_size"
                style="width:100%;"
            >

                <option value="">
                    Select Size
                </option>

                <?php
                $sizes = [
                    '1-10 employees',
                    '11-50 employees',
                    '51-200 employees',
                    '201-500 employees',
                    '501-1000 employees',
                    '1000+ employees',
                ];

                foreach ($sizes as $company_size) :
                ?>

                    <option
                        value="<?php echo esc_attr($company_size); ?>"
                        <?php selected($size, $company_size); ?>
                    >
                        <?php echo esc_html($company_size); ?>
                    </option>

                <?php endforeach; ?>

            </select>
        </p>

    </div>

    <?php
}


/**
 * Save Company Details.
 */
function devhire_save_company_details($post_id) {

    if (
        !isset($_POST['devhire_company_details_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(
                wp_unslash(
                    $_POST['devhire_company_details_nonce']
                )
            ),
            'devhire_save_company_details'
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

    if (get_post_type($post_id) !== 'company') {
        return;
    }


    $fields = [
        'devhire_company_website' => [
            'key' => '_devhire_company_website',
            'sanitize' => 'esc_url_raw',
        ],

        'devhire_company_location' => [
            'key' => '_devhire_company_location',
            'sanitize' => 'sanitize_text_field',
        ],

        'devhire_company_industry' => [
            'key' => '_devhire_company_industry',
            'sanitize' => 'sanitize_text_field',
        ],

        'devhire_company_size' => [
            'key' => '_devhire_company_size',
            'sanitize' => 'sanitize_text_field',
        ],
    ];


    foreach ($fields as $field => $config) {

        if (!isset($_POST[$field])) {
            continue;
        }

        $value = call_user_func(
            $config['sanitize'],
            wp_unslash($_POST[$field])
        );

        if ($value === '') {

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
    'save_post_company',
    'devhire_save_company_details'
);
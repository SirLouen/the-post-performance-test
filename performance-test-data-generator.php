<?php

function generate_performance_test_data() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    global $wpdb;
    
    // Total post count to generate (for large dataset)
    $post_count = 10000;
    // Batch size for creating posts
    $batch_size = 100;
    
    echo "<h2>Generating {$post_count} test posts...</h2>";
    
    // Track created post IDs
    $created_post_ids = array();
    
    for ($i = 0; $i < $post_count; $i += $batch_size) {
        $current_batch = min($batch_size, $post_count - $i);
        echo "Creating posts " . ($i + 1) . " to " . ($i + $current_batch) . "...<br>";
        
        for ($j = 0; $j < $current_batch; $j++) {
            // Create post with varying complexity
            $post_id = wp_insert_post(array(
                'post_title' => "Test Post " . ($i + $j + 1),
                'post_content' => generate_random_content(rand(1, 5)),
                'post_status' => 'publish',
                'post_author' => 1,
                'post_type' => rand(0, 10) > 8 ? 'page' : 'post', // Mix of post types
            ));
            
            if (!is_wp_error($post_id)) {
                $created_post_ids[] = $post_id;
                
                // Add varying amounts of post meta
                $meta_count = rand(5, 20);
                for ($m = 0; $m < $meta_count; $m++) {
                    update_post_meta($post_id, 'test_meta_key_' . $m, 'test_meta_value_' . $m . '_' . rand(1000, 9999));
                }
                
                // Add terms to some posts
                if (rand(0, 10) > 3) {
                    $term_count = rand(1, 5);
                    for ($t = 0; $t < $term_count; $t++) {
                        wp_set_object_terms($post_id, 'test-term-' . rand(1, 20), 'category', true);
                    }
                    
                    if (rand(0, 10) > 5) {
                        $tag_count = rand(1, 8);
                        for ($t = 0; $t < $tag_count; $t++) {
                            wp_set_object_terms($post_id, 'test-tag-' . rand(1, 30), 'post_tag', true);
                        }
                    }
                }
            }
        }
    }
    
    $test_data_dir = WP_CONTENT_DIR . '/performance-test-data';
    if (!file_exists($test_data_dir)) {
        mkdir($test_data_dir, 0755, true);
    }
    
    create_mixed_post_dataset($test_data_dir, $created_post_ids, 1000, 'small');
    create_mixed_post_dataset($test_data_dir, $created_post_ids, 5000, 'medium');
    create_mixed_post_dataset($test_data_dir, $created_post_ids, 10000, 'large');
    
    echo "<h2>Test data generation complete!</h2>";
    echo "<p>Test data files have been saved to: {$test_data_dir}</p>";
    echo "<p>Use these files in your performance testing script.</p>";
}

// Generate random post content
function generate_random_content($paragraphs) {
    $content = '';
    $lorem = "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.";
    
    for ($i = 0; $i < $paragraphs; $i++) {
        $content .= "<p>" . $lorem . "</p>\n";
    }
    
    return $content;
}

// Create a dataset with mixed post object types
function create_mixed_post_dataset($dir, $post_ids, $count, $size_label) {
    $dataset = array();
    $count = min($count, count($post_ids));
    
    // Shuffle post IDs to get a random selection
    shuffle($post_ids);
    $selected_ids = array_slice($post_ids, 0, $count);
    
    foreach ($selected_ids as $index => $post_id) {
        $type = $index % 3;
        
        switch ($type) {
            case 0:
                // Just the post ID (numeric)
                $dataset[] = $post_id;
                break;
                
            case 1:
                // stdClass object with ID
                $obj = new stdClass();
                $obj->ID = $post_id;
                $dataset[] = $obj;
                break;
                
            case 2:
                // Full WP_Post object
                $dataset[] = get_post($post_id);
                break;
        }
    }
    
    // Save dataset to file
    $filename = $dir . '/test-dataset-' . $size_label . '.php';
    $content = "<?php\n\$data = [];\n";

    // We cannot use var_export() because it will not work with WP_Post objects
    // So we have to build the dataset based on the type of object
    foreach ($dataset as $index => $item) {
        if ($item instanceof WP_Post) {
            // For WP_Post objects, just store the ID
            $content .= "\$data[] = " . $item->ID . ";\n";
        } elseif (is_object($item) && isset($item->ID)) {
            // For stdClass objects with ID
            $content .= "\$data[] = (object)['ID' => " . $item->ID . "];\n";
        } else {
            // For numeric IDs
            $content .= "\$data[] = " . $item . ";\n";
        }
    }

    $content .= "return \$data;";
    file_put_contents($filename, $content);
    
    echo "Created {$size_label} dataset with {$count} mixed post objects.<br>";
}

// Add admin menu item
function performance_test_data_menu() {
    add_management_page(
        'Generate Test Data',
        'Generate Test Data',
        'manage_options',
        'generate-test-data',
        'generate_test_data_page'
    );
}
add_action('admin_menu', 'performance_test_data_menu');

// Admin page callback
function generate_test_data_page() {
    echo '<div class="wrap">';
    echo '<h1>Generate Performance Test Data</h1>';
    
    if (isset($_POST['generate_data']) && check_admin_referer('generate_test_data_nonce')) {
        generate_performance_test_data();
    } else {
        echo '<form method="post">';
        wp_nonce_field('generate_test_data_nonce');
        echo '<p>This will generate test data for performance testing the_post() function.</p>';
        echo '<p>Warning: This will create a large number of posts in your database. Use only in a testing environment.</p>';
        echo '<p><input type="submit" name="generate_data" class="button button-primary" value="Generate Test Data"></p>';
        echo '</form>';
    }
    
    echo '</div>';
}
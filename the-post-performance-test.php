<?php
/*
Plugin Name: The Post Performance Test
Description: Tests performance of different the_post() implementations
Version: 1.0.0
Author: SirLouen <sir.louen@gmail.com>
*/

// Include the original and modified functions
require_once(dirname(__FILE__) . '/the-post-implementations.php');
require_once(dirname(__FILE__) . '/performance-test-data-generator.php');

// Add admin menu item
function the_post_performance_test_menu() {
    add_management_page(
        'The Post Performance Test',
        'The Post Performance Test',
        'manage_options',
        'the-post-performance-test',
        'the_post_performance_test_page'
    );
}
add_action('admin_menu', 'the_post_performance_test_menu');

// Admin page callback
function the_post_performance_test_page() {
    echo '<div class="wrap">';
    echo '<h1>The Post Performance Test</h1>';
    
    if (isset($_POST['run_test']) && check_admin_referer('the_post_test_nonce')) {
        run_the_post_performance_test();
    } else {
        echo '<form method="post">';
        wp_nonce_field('the_post_test_nonce');
        
        echo '<p>Select test dataset size:</p>';
        echo '<select name="dataset_size">';
        echo '<option value="small">Small (1000 posts)</option>';
        echo '<option value="medium">Medium (5000 posts)</option>';
        echo '<option value="large">Large (10000 posts)</option>';
        echo '</select>';
        
        echo '<p>Number of iterations:</p>';
        echo '<input type="number" name="iterations" value="100" min="1" max="1000">';
        
        echo '<p><input type="submit" name="run_test" class="button button-primary" value="Run Performance Test"></p>';
        echo '</form>';
    }
    
    echo '</div>';
}

// Run the performance test
function run_the_post_performance_test() {
    $dataset_size = isset($_POST['dataset_size']) ? $_POST['dataset_size'] : 'small';
    $iterations = isset($_POST['iterations']) ? intval($_POST['iterations']) : 100;
    
    // Load test data
    $data_file = WP_CONTENT_DIR . '/performance-test-data/test-dataset-' . $dataset_size . '.php';
    if (!file_exists($data_file)) {
        echo '<div class="error"><p>Test data file not found. Please generate test data first.</p></div>';
        return;
    }
    
    $test_data = include($data_file);
    
    echo '<h2>Running Performance Test</h2>';
    echo '<p>Dataset: ' . $dataset_size . ' (' . count($test_data) . ' posts)</p>';
    echo '<p>Iterations: ' . $iterations . '</p>';
    
    // Test original implementation
    $original_results = test_the_post_implementation('original', $test_data, $iterations);
    
    // Test modified implementation
    $modified_results = test_the_post_implementation('modified', $test_data, $iterations);
    
    // Display results
    echo '<h2>Results</h2>';
    
    echo '<table class="widefat">';
    echo '<thead><tr><th>Metric</th><th>Original Version</th><th>Modified Version</th><th>Difference</th></tr></thead>';
    echo '<tbody>';
    
    // Execution Time
    $time_diff = $modified_results['execution_time'] - $original_results['execution_time'];
    $time_diff_pct = ($original_results['execution_time'] > 0) ? 
        ($time_diff / $original_results['execution_time'] * 100) : 0;
    $time_diff_class = ($time_diff < 0) ? 'better' : 'worse';
    
    echo '<tr>';
    echo '<td>Execution Time</td>';
    echo '<td>' . number_format($original_results['execution_time'], 6) . ' seconds</td>';
    echo '<td>' . number_format($modified_results['execution_time'], 6) . ' seconds</td>';
    echo '<td class="' . $time_diff_class . '">' . 
        number_format($time_diff_pct, 2) . '% ' . 
        ($time_diff < 0 ? 'faster' : 'slower') . '</td>';
    echo '</tr>';
    
    // Memory Usage
    $memory_diff = $modified_results['memory_peak'] - $original_results['memory_peak'];
    $memory_diff_pct = ($original_results['memory_peak'] > 0) ? 
        ($memory_diff / $original_results['memory_peak'] * 100) : 0;
    $memory_diff_class = ($memory_diff < 0) ? 'better' : 'worse';
    
    echo '<tr>';
    echo '<td>Peak Memory Usage</td>';
    echo '<td>' . number_format($original_results['memory_peak'] / 1024 / 1024, 2) . ' MB</td>';
    echo '<td>' . number_format($modified_results['memory_peak'] / 1024 / 1024, 2) . ' MB</td>';
    echo '<td class="' . $memory_diff_class . '">' . 
        number_format($memory_diff_pct, 2) . '% ' . 
        ($memory_diff < 0 ? 'less' : 'more') . '</td>';
    echo '</tr>';
    
    // Query Count
    $query_diff = $modified_results['query_count'] - $original_results['query_count'];
    $query_diff_class = ($query_diff <= 0) ? 'better' : 'worse';
    
    echo '<tr>';
    echo '<td>Database Queries</td>';
    echo '<td>' . $original_results['query_count'] . '</td>';
    echo '<td>' . $modified_results['query_count'] . '</td>';
    echo '<td class="' . $query_diff_class . '">' . 
        ($query_diff == 0 ? 'No difference' : sprintf('%+d queries', $query_diff)) . '</td>';
    echo '</tr>';
    
    echo '</tbody></table>';
    
    echo '<style>
        .better { color: green; font-weight: bold; }
        .worse { color: red; font-weight: bold; }
    </style>';
    
    // Show detailed results
    echo '<h3>Detailed Results</h3>';
    echo '<pre>';
    echo "Original Implementation:\n";
    print_r($original_results);
    echo "\nModified Implementation:\n";
    print_r($modified_results);
    echo '</pre>';
}

// Test a specific implementation
function test_the_post_implementation($version, $test_data, $iterations) {
    global $wpdb;
    
    // Reset metrics
    $start_time = microtime(true);
    $memory_start = memory_get_usage();
    $memory_peak_start = memory_get_peak_usage();
    $query_count_start = $wpdb->num_queries;
    
    // Create a WP_Query instance for testing
    $query = new WP_Query();
    
    // Run the test iterations
    for ($i = 0; $i < $iterations; $i++) {
        // Set up the query with our test data
        $query->posts = $test_data;
        $query->post_count = count($test_data);
        $query->current_post = -1;
        $query->in_the_loop = false;
        
        // Run through all posts
        while ($query->current_post < $query->post_count - 1) {
            if ($version === 'original') {
                the_post_original($query);
            } else {
                the_post_modified($query);
            }
            
            // Clean up to prevent memory issues
            if (isset($GLOBALS['post'])) {
                unset($GLOBALS['post']);
            }
        }
    }
    
    // Calculate metrics
    $execution_time = microtime(true) - $start_time;
    $memory_used = memory_get_usage() - $memory_start;
    $memory_peak = memory_get_peak_usage() - $memory_peak_start;
    $query_count = $wpdb->num_queries - $query_count_start;
    
    return [
        'execution_time' => $execution_time,
        'memory_used' => $memory_used,
        'memory_peak' => $memory_peak,
        'query_count' => $query_count,
        'iterations' => $iterations,
        'post_count' => count($test_data)
    ];
}
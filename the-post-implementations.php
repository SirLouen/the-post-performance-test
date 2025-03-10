<?php

// Original implementation
function the_post_original($query) {
    global $post;

    if (!$query->in_the_loop) {
        // Only prime the post cache for queries limited to the ID field.
        $post_ids = array_filter($query->posts, 'is_numeric');
        // Exclude any falsey values, such as 0.
        $post_ids = array_filter($post_ids);
        if ($post_ids) {
            _prime_post_caches($post_ids, true, true);
        }
        $post_objects = array_map('get_post', $query->posts);
        update_post_author_caches($post_objects);
    }

    $query->in_the_loop = true;
    $query->before_loop = false;

    if (-1 == $query->current_post) { // Loop has just started.
        do_action_ref_array('loop_start', array(&$query));
    }

    $post = $query->next_post();
    $query->setup_postdata($post);
}

// Modified implementation 
// Patches https://patch-diff.githubusercontent.com/raw/WordPress/wordpress-develop/pull/8418.diff
// And https://patch-diff.githubusercontent.com/raw/WordPress/wordpress-develop/pull/8460.diff
function the_post_modified($query) {
    global $post;

    if (!$query->in_the_loop) {
        // Get post IDs to prime incomplete post objects.
        $post_ids = array_reduce(
            $query->posts,
            function ($carry, $post) {
                if (is_numeric($post) && $post > 0) {
                    // Query for post ID.
                    $carry[] = $post;
                }

                if (is_object($post) && isset($post->ID)) {
                    // Query for object, either WP_Post or stdClass.
                    $carry[] = $post->ID;
                }

                return $carry;
            },
            array()
        );
        if ($post_ids) {
            _prime_post_caches($post_ids, true, true);
        }
        $post_objects = array_map('get_post', $post_ids);
        update_post_author_caches($post_objects);
    }

    $query->in_the_loop = true;
    $query->before_loop = false;

    if (-1 === $query->current_post) { // Loop has just started.
        do_action_ref_array('loop_start', array(&$query));
    }

    $post = $query->next_post();

    // Ensure a full post object is available.
    if ($post instanceof stdClass) {
        // stdClass indicates that a partial post object was queried.
        $post = get_post($post->ID);
    } elseif (is_numeric($post)) {
        // Numeric indicates that only post IDs were queried.
        $post = get_post($post);
    }

    // Set up the global post object for the loop.
    $query->setup_postdata($post);
}

// Helper function to mimic WP_Query's next_post method
function next_post_helper($query) {
    $query->current_post++;
    
    if ($query->current_post < $query->post_count) {
        return $query->posts[$query->current_post];
    }
    
    return null;
}
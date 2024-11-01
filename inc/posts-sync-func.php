<?php
// Post sync functions
function scwp_sync_posts_func() {
    $target_url = sanitize_text_field($_POST['website_url']);
    $post_status = sanitize_text_field($_POST['post_status']);
    $num_posts = absint($_POST['num_posts']);
    $import_comments = isset($_POST['import_comments']) ? intval($_POST['import_comments']) : 0;
    $author_id = absint($_POST['author']);

    // Define the request arguments
    $request_args = array(
        'timeout' => 30,  // Set a reasonable timeout
    );

    // Make the API request using WordPress HTTP API
    $response = wp_safe_remote_get($target_url . '/wp-json/wp/v2/posts?per_page=' . $num_posts, $request_args);

    // Check for errors in the API request
    if (is_wp_error($response)) {
        echo '<div class="sync-posts-alert danger">' . esc_html($response->get_error_message()) . '</div>';
        wp_die();
    }

    // Get the response body
    $body = wp_remote_retrieve_body($response);

    $num_posts = absint($_POST['num_posts']);

    // Validate the "num_posts" value
    if ($num_posts < 1) {
        echo '<div class="sync-posts-alert warning">Number of posts should not be less than 1.</div>';
        wp_die();
    }

    // Parse the response
    $posts = json_decode($body);
    if (!empty($posts)) {
        foreach ($posts as $post) {
            // Check if post with same title already exists
            $existing_post = get_page_by_title($post->title->rendered, OBJECT, 'post');
            if ($existing_post) {
                echo '<li class="warning">Skipped post: <b>' . esc_html($post->title->rendered) . '</b></li>';
                continue;
            }

            // Import post data to the current site
            $imported_post_id = wp_insert_post(array(
                'post_title' => $post->title->rendered,
                'post_status' => $post_status,
                'post_author' => $author_id,
            ));

            // Import post content with replaced image URLs
            $imported_content = scwp_download_posts_content_images($post->content->rendered, $imported_post_id);
            $imported_post_data = array(
                'ID' => $imported_post_id,
                'post_content' => $imported_content,
            );
            wp_update_post($imported_post_data);

            // Import post categories
            if (!empty($post->categories)) {
                $imported_categories = array();
                foreach ($post->categories as $category_id) {
                    $category = get_category_by_slug($category_id);
                    if (!$category) {
                        $category_data = json_decode(file_get_contents($target_url . '/wp-json/wp/v2/categories/' . $category_id));
                        if ($category_data) {
                            // Check if the category already exists by name
                            $existing_category = get_category_by_slug($category_data->slug);
                            if (!$existing_category) {
                                $new_category = wp_insert_category(array(
                                    'cat_name' => $category_data->name,
                                    'category_nicename' => $category_data->slug,
                                    'category_parent' => $category_data->parent,
                                ));
                                if (!is_wp_error($new_category)) {
                                    $imported_categories[] = $new_category;
                                }
                            } else {
                                $imported_categories[] = $existing_category->term_id;
                            }
                        }
                    } else {
                        $imported_categories[] = $category->term_id;
                    }
                }
                wp_set_post_categories($imported_post_id, $imported_categories);
            }

            // Import post comments
            if ($import_comments) {
                $comments_url = $target_url . '/wp-json/wp/v2/comments?post=' . $post->id;
                $comments_response = file_get_contents($comments_url);
                $comments = json_decode($comments_response);

                if (!empty($comments)) {
                    foreach ($comments as $comment) {
                        $comment_data = array(
                            'comment_post_ID' => $imported_post_id,
                            'comment_author' => $comment->author_name,
                            'comment_author_email' => $comment->author_email,
                            'comment_author_url' => $comment->author_url,
                            'comment_content' => $comment->content->rendered,
                            'comment_date' => $comment->date,
                            'comment_approved' => $comment->status === 'approved' ? 1 : 0,
                        );

                        wp_insert_comment($comment_data);
                    }
                }
            }

            // Import post tags
            if (!empty($post->tags)) {
                $imported_tags = array();
                foreach ($post->tags as $tag_id) {
                    $tag = get_term_by('slug', $tag_id, 'post_tag');
                    if (!$tag) {
                        $tag_data = json_decode(file_get_contents($target_url . '/wp-json/wp/v2/tags/' . $tag_id));
                        if ($tag_data) {
                            // Check if the tag already exists by name
                            $existing_tag = get_term_by('slug', $tag_data->slug, 'post_tag');
                            if (!$existing_tag) {
                                $new_tag = wp_insert_term($tag_data->name, 'post_tag');
                                if (!is_wp_error($new_tag)) {
                                    $imported_tags[] = $new_tag['term_id'];
                                }
                            } else {
                                $imported_tags[] = $existing_tag->term_id;
                            }
                        }
                    } else {
                        $imported_tags[] = $tag->term_id;
                    }
                }
                wp_set_post_tags($imported_post_id, $imported_tags);
            }

            // Import post thumbnail
            if (!empty($post->featured_media)) {
                $media = json_decode(file_get_contents($target_url . '/wp-json/wp/v2/media/' . $post->featured_media));
                if ($media) {
                    $image_url = $media->source_url;
                    $image_name = basename($image_url);
                    $upload_dir = wp_upload_dir();
                    $image_data = file_get_contents($image_url);

                    if ($image_data !== false) {
                        $file = $upload_dir['path'] . '/' . $image_name;
                        file_put_contents($file, $image_data);

                        $wp_filetype = wp_check_filetype($image_name, null);
                        $attachment = array(
                            'guid' => $upload_dir['url'] . '/' . $image_name,
                            'post_mime_type' => $wp_filetype['type'],
                            'post_title' => sanitize_file_name($image_name),
                            'post_content' => '',
                            'post_status' => 'inherit',
                        );

                        $attach_id = wp_insert_attachment($attachment, $file, $imported_post_id);
                        $attach_data = wp_generate_attachment_metadata($attach_id, $file);
                        wp_update_attachment_metadata($attach_id, $attach_data);
                        set_post_thumbnail($imported_post_id, $attach_id);
                    }
                }
            }

            // Display imported post details
            if (!empty($post->title->rendered)) {
                echo '<li class="success">Imported post: <b>' . esc_html($post->title->rendered) . '</b></li>';
            } else {
                echo '<li class="warning">Imported post: <b>No post title available!</b></li>';
            }
        }
    } else {
        echo '<div class="sync-posts-alert info">No posts found.</div>';
    }

    wp_die();
}

// Helper function to download and replace content images
function scwp_download_posts_content_images($content, $post_id) {
    preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/', $content, $matches);

    if (!empty($matches[1])) {
        foreach ($matches[1] as $index => $image_url) {
            $image_name = basename($image_url);
            $upload_dir = wp_upload_dir();
            $image_data = file_get_contents($image_url);

            if ($image_data !== false) {
                $file = $upload_dir['path'] . '/' . $image_name;
                file_put_contents($file, $image_data);

                $wp_filetype = wp_check_filetype($image_name, null);
                $attachment = array(
                    'guid' => $upload_dir['url'] . '/' . $image_name,
                    'post_mime_type' => $wp_filetype['type'],
                    'post_title' => sanitize_file_name($image_name),
                    'post_content' => '',
                    'post_status' => 'inherit',
                );

                $attach_id = wp_insert_attachment($attachment, $file, $post_id);
                $attach_data = wp_generate_attachment_metadata($attach_id, $file);
                wp_update_attachment_metadata($attach_id, $attach_data);

                $new_image_url = wp_get_attachment_url($attach_id);
                $content = str_replace($matches[0][$index], '<img src="' . $new_image_url . '">', $content);
            }
        }
    }

    return $content;
}
?>
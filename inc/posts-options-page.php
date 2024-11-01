<?php
// Render the plugin options page
function scwp_sync_posts_options() { ?>
    <div class="wrap">
        <form id="sync-posts-form" method="post">
            <h1>Sync Posts</h1> <hr>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Website URL:</th>
                    <td><input type="text" name="website_url" value="<?php echo esc_url( sanitize_text_field( $_POST['website_url'] ) ); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Post Status:</th>
                    <td>
                        <select name="post_status">
                            <option value="pending">Pending</option>
                            <option value="draft">Draft</option>
                            <option value="publish">Publish</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Number of Posts:</th>
                    <td><input type="number" name="num_posts" value="<?php echo absint($_POST['num_posts']); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Post Comments:</th>
                    <td>
                        <select name="import_comments">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Author:</th>
                    <td>
                    <?php
                        $authors = get_users(array('fields' => array('ID', 'display_name')));
                        if (!empty($authors)) {
                            echo '<select name="author">';
                            foreach ($authors as $author) {
                                echo '<option value="' . esc_attr($author->ID) . '">' . esc_html($author->display_name) . '</option>';
                            }
                            echo '</select>';
                        }
                    ?>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="button" name="start_syncing" id="start-syncing" class="button button-primary" value="Start Syncing" />
            </p>
        
            <div id="sync-results"></div>
        </form>
    </div>

    <script>
        jQuery(document).ready(function($) {
            $('#start-syncing').click(function() {
                var formData = $('#sync-posts-form').serializeArray();
                formData.push({name: 'action', value: 'sync_posts'});

                $('#start-syncing').prop('disabled', true);
                $('#sync-results').html('<div class="sync-posts-alert info">Loading... Please do not close the page.</div>');

                $.ajax({
                    url: '<?php echo esc_url(admin_url("admin-ajax.php")); ?>',
                    type: 'POST',
                    dataType: 'html',
                    data: formData,
                    success: function(response) {
                        $('#sync-results').html(response);
                    },
                    error: function() {
                        $('#sync-results').html('<div class="sync-posts-alert warning">An error occurred, please try again.</div>');
                    },
                    complete: function() {
                        $('#start-syncing').prop('disabled', false);
                    }
                });
            });
        });
    </script>
    <?php
}
?>
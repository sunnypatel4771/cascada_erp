<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-semibold tw-text-lg tw-text-neutral-600 tw-leading-6 tw-pb-2">Quick Replies</h4>
                <div class="panel_s">
                    <div class="panel-body">
                        <form id="quick-reply-form">
                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                            <input type="hidden" name="id" id="quick-reply-id">
                            <div class="form-group">
                                <label for="name">Name</label>
                                <input type="text" class="form-control" name="name" id="quick-reply-name" required>
                            </div>
                            <div class="form-group">
                                <label for="message">Message</label>
                                <textarea class="form-control" name="message" id="quick-reply-message" required></textarea>
                            </div>
                            <button type="submit" id="quick-reply-submit" class="btn btn-primary">Save</button>
                        </form>
                        <hr>
                        <div class="table-responsive">
                            <table class="table dt-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Message</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="quick-reply-list">
                                    <?php foreach($quick_replies as $reply): ?>
                                        <tr data-id="<?= $reply->id ?>">
                                            <td><?= $reply->id ?></td>
                                            <td><?= $reply->name ?></td>
                                            <td><?= $reply->message ?></td>
                                            <td>
                                                <button class="btn btn-info btn-icon edit-btn" data-id="<?= $reply->id ?>" data-name="<?= $reply->name ?>" data-message="<?= $reply->message ?>"><i class="fa fa-pencil"></i></button>
                                                <button class="btn btn-danger btn-icon delete-btn" data-id="<?= $reply->id ?>"><i class="fa fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>

<script>
    $(document).ready(function() {
        $('#quick-reply-form').on('submit', function(e) {
            e.preventDefault();
            let id = $('#quick-reply-id').val();
            let url = id ? 'QuickReplies/update/' + id : 'QuickReplies/store';
            let method = 'POST'; // Use POST for both create and update for simplicity

            $.ajax({
                url: url,
                type: method,
                data: $(this).serialize(), // Serialize the form data, including the CSRF token
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        location.reload();
                    } else {
                        alert(response.message);
                    }
                }
            });
        });

        $('.edit-btn').on('click', function() {
            let id = $(this).data('id');
            let name = $(this).data('name');
            let message = $(this).data('message');
            $('#quick-reply-id').val(id);
            $('#quick-reply-name').val(name);
            $('#quick-reply-message').val(message);
            $('#quick-reply-submit').text('Update');
        });

        $('.delete-btn').on('click', function() {
            if (confirm('Are you sure you want to delete this quick reply?')) {
                let id = $(this).data('id');

                $.ajax({
                    url: 'QuickReplies/delete/' + id,
                    type: 'POST', // Use POST for delete for simplicity
                    data: {
                        [csrfData.csrf_token_name]: csrfData.csrf_hash // Include CSRF token in the delete request
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            location.reload();
                        } else {
                            alert('Error deleting the quick reply.');
                        }
                    }
                });
            }
        });
    });
</script>

<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="no-margin">Groups List</h4>
                <hr class="hr-panel-heading">
            </div>
            <div class="col-md-12 text-right">
                <a href="<?= admin_url('whatsapp/groups/form'); ?>" class="btn btn-info pull-right mbot15">Add New Group</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table id="groupsTable" class="table dt-table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Group Name</th>
                                        <th>Description</th>
                                        <th>Contacts Count</th> <!-- New column header -->
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($groups as $group): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($group['name']); ?></td>
                                            <td><?= htmlspecialchars($group['description']); ?></td>
                                            <td><?= htmlspecialchars($group['contacts_count']); ?></td> <!-- Display contacts count -->
                                            <td>
                                                <a href="<?= admin_url('whatsapp/groups/form/' . $group['id']); ?>" class="btn btn-xs btn-warning">Edit</a>
                                                <a href="<?= admin_url('whatsapp/groups/delete/' . $group['id']); ?>" class="btn btn-xs btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
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
        $('#groupsTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true,
            "responsive": true
        });
    });
</script>

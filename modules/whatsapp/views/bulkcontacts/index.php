<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <!-- Page Title and Actions -->
            <div class="col-md-12">
                <h4 class="no-margin">Bulk Contacts List</h4>
                <hr class="hr-panel-heading">
            </div>

            <!-- Create and Import Actions -->
            <div class="col-md-12 text-right mbot15">
                <a href="<?= admin_url('whatsapp/BulkContacts/create'); ?>" class="btn btn-info">Create New Contact</a>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#importModal">Import Contacts</button>
            </div>
        </div>

        <!-- Contacts Table Panel -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h5>Contacts</h5>
                        <div class="table-responsive">
                            <table id="contactsTable" class="table dt-table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Number</th>
                                        <th>Company</th>
                                        <th>Groups</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contacts as $contact): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($contact['name']); ?></td>
                                            <td><?= htmlspecialchars($contact['phonenumber']); ?></td>
                                            <td><?= htmlspecialchars($contact['company_name']); ?></td>
                                            <td>
                                                <?php if (!empty($contact['groups'])): ?>
                                                    <ul class="list-unstyled">
                                                        <?php foreach ($contact['groups'] as $group): ?>
                                                            <li><span class="badge badge-primary"><?= htmlspecialchars($group); ?></span></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else: ?>
                                                    <span class="text-muted">No Groups Assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="<?= admin_url('whatsapp/BulkContacts/edit/' . $contact['id']); ?>" class="btn btn-xs btn-warning" title="Edit">
                                                    <i class="fa fa-edit"></i>
                                                </a>
                                                <a href="<?= admin_url('whatsapp/BulkContacts/delete/' . $contact['id']); ?>" class="btn btn-xs btn-danger" title="Delete" onclick="return confirm('Are you sure?')">
                                                    <i class="fa fa-trash"></i>
                                                </a>
                                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $contact['phonenumber']); ?>" class="btn btn-xs btn-success" title="WhatsApp" target="_blank">
                                                    <i class="fa fa-whatsapp"></i>
                                                </a>
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

        <!-- Import Modal -->
        <div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <form action="<?= admin_url('whatsapp/BulkContacts/import'); ?>" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">
                        <div class="modal-header">
                            <h5 class="modal-title" id="importModalLabel">Import Contacts</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="file">Upload CSV File</label>
                                <input type="file" class="form-control" id="file" name="file" accept=".csv" required>
                            </div>
                            <div class="form-group">
                                <label for="groups">Assign to Groups</label>
                                <select name="groups[]" id="groups" class="form-control select2" multiple>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?= $group['id']; ?>"><?= htmlspecialchars($group['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <p>Ensure the CSV file has columns: <strong>Name, Number, Company, Address, City, State, Country, Description</strong>.</p>
                            <p><a href="<?= module_dir_url(WHATSAPP_MODULE, 'assets/samples/sample_contacts.csv') ?>" download>Download Sample CSV</a></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Import</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
$(document).ready(function() {

});
</script>

<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="container">
            <h2 class="mt-4"><?= isset($contact) ? 'Edit Contact' : 'Create New Contact' ?></h2>

            <form action="<?= isset($contact) ? admin_url('whatsapp/BulkContacts/update/' . $contact->id) : admin_url('whatsapp/BulkContacts/store'); ?>" method="post" class="mt-3">
                                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">

                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" value="<?= isset($contact) ? $contact->name : '' ?>" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="company_name">Company Name:</label>
                    <input type="text" id="company_name" name="company_name" value="<?= isset($contact) ? $contact->company_name : '' ?>" class="form-control">
                </div>

                <div class="form-group">
                    <label for="number">Phone Number:</label>
                    <input type="text" id="phonenumber" name="phonenumber" value="<?= isset($contact) ? $contact->phonenumber : '' ?>" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="address">Address:</label>
                    <textarea id="address" name="address" class="form-control"><?= isset($contact) ? $contact->address : '' ?></textarea>
                </div>

                <div class="form-group">
                    <label for="city">City:</label>
                    <input type="text" id="city" name="city" value="<?= isset($contact) ? $contact->city : '' ?>" class="form-control">
                </div>

                <div class="form-group">
                    <label for="state">State:</label>
                    <input type="text" id="state" name="state" value="<?= isset($contact) ? $contact->state : '' ?>" class="form-control">
                </div>

                <div class="form-group">
                    <label for="country">Country:</label>
                    <input type="text" id="country" name="country" value="<?= isset($contact) ? $contact->country : '' ?>" class="form-control">
                </div>

                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" class="form-control"><?= isset($contact) ? $contact->description : '' ?></textarea>
                </div>

                <div class="form-group">
                    <label for="groups">Groups:</label>
                    <select id="groups" name="groups[]" class="form-control select2" multiple>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?= $group['id']; ?>" <?= isset($contact) && in_array($group['id'], $contact_groups) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($group['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><?= isset($contact) ? 'Update Contact' : 'Create Contact' ?></button>
                
            </form>
        </div>
    </div>
</div>

<?php init_tail(); ?>

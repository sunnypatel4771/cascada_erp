<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="no-margin"><?= isset($group) ? 'Edit Group' : 'Create New Group'; ?></h4>
                <hr class="hr-panel-heading">
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <form action="<?= admin_url('whatsapp/groups/save' . (isset($group) ? '/' . $group->id : '')); ?>" method="post">
                            <!-- CSRF Protection -->
                            <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" 
                                   value="<?= $this->security->get_csrf_hash(); ?>">

                            <div class="form-group">
                                <label for="name">Group Name</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= isset($group) ? htmlspecialchars($group->name) : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4"><?= isset($group) ? htmlspecialchars($group->description) : ''; ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-info"><?= isset($group) ? 'Update Group' : 'Create Group'; ?></button>
                            <a href="<?= admin_url('groups'); ?>" class="btn btn-default">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

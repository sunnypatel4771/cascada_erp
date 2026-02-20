<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <?php echo _l('WhatsApp Verification Status'); ?>
                        </h4>
                        <hr class="hr-panel-heading" />

                        <?php 
                        $default_phone_number_id = whatsapp_default_phone_number()['phone_number_id'];
                        if (!empty($numbers) && !empty($numbers)) { ?>
                            <div class="table-responsive">
                                <table class="table table-bordered dt-table table-auto w-full">
                                    <thead>
                                        <tr class="bg-gray-200">
                                            <th><?php echo _l('Profile Picture'); ?></th>
                                            <th><?php echo _l('Verified Name'); ?></th>
                                            <th><?php echo _l('Code Verification Status'); ?></th>
                                            <th><?php echo _l('Display Phone Number'); ?></th>
                                            <th><?php echo _l('Quality Rating'); ?></th>
                                            <th><?php echo _l('Platform Type'); ?></th>
                                            <th><?php echo _l('Throughput Level'); ?></th>
                                            <th><?php echo _l('ID'); ?></th>
                                            <th><?php echo _l('Actions'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($numbers as $data) { ?>
                                            <tr class="hover:bg-gray-100 transition duration-300 ease-in-out animate-fadeIn">
                                                <td class="p-2">
                                                    <?php if (!empty($data['profile_picture_url'])) { ?>
                                                        <img src="<?php echo $data['profile_picture_url']; ?>" alt="Profile Picture" class="m-h-2 m-w-2 rounded-full" style="max-width:100px">
                                                    <?php } else { ?>
                                                        <span class="text-gray-500">No Picture</span>
                                                    <?php } ?>
                                                </td>
                                                <td class="p-2"><?php echo $data['verified_name']; ?></td>
                                                <td class="p-2"><?php echo $data['code_verification_status']; ?></td>
                                                <td class="p-2"><?php echo $data['phone_number']; ?></td>
                                                <td class="p-2"><?php echo $data['quality_rating']; ?></td>
                                                <td class="p-2"><?php echo $data['platform_type']; ?></td>
                                                <td class="p-2"><?php echo $data['throughput_level']; ?></td>
                                                <td class="p-2"><?php echo $data['id']; ?></td>
                                                <td class="p-2">
                                                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#updateProfileModal<?php echo $data['id']; ?>">
                                                        <?php echo _l('Update Profile'); ?>
                                                    </button>
                                                    <?php if ($data['phone_number_id'] != $default_phone_number_id) { ?>
                                                        <a href="<?php echo admin_url('whatsapp/set_default_phone_number?id=' . $data['id']); ?>" class="btn btn-info">
                                                            <i class="fa-solid fa-check tw-mr-1"></i>
                                                            <?php echo _l('mark_as_default'); ?>
                                                        </a>
                                                    <?php } ?>
                                                </td>
                                            </tr>

                                            <!-- Modal for updating profile -->
                                            <div class="modal fade" id="updateProfileModal<?php echo $data['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="updateProfileModalLabel<?php echo $data['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="updateProfileModalLabel<?php echo $data['id']; ?>"><?php echo _l('Update Profile for '); ?><?php echo $data['verified_name']; ?></h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <form action="<?php echo admin_url('whatsapp/update_profile'); ?>" method="post" enctype="multipart/form-data">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                                <input type="hidden" name="phone_number_id" value="<?php echo $data['phone_number_id']; ?>">
                                                                <div class="form-group">
                                                                    <label for="profile_picture"><?php echo _l('Profile Picture'); ?></label>
                                                                    <?php if (!empty($data['profile_picture_url'])) { ?>
                                                                        <div class="mb-2">
                                                                            <img src="<?php echo $data['profile_picture_url']; ?>" alt="Current Profile Picture" class="h-12 w-12 rounded-full">
                                                                        </div>
                                                                    <?php } ?>
                                                                </div>
                                                                <div class="form-group">
                                                                    <label for="about"><?php echo _l('About'); ?></label>
                                                                    <textarea name="about" class="form-control" rows="3"><?php echo $data['about']; ?></textarea>
                                                                </div>
                                                                <div class="form-group">
                                                                    <label for="address"><?php echo _l('Address'); ?></label>
                                                                    <textarea name="address" class="form-control" rows="2"><?php echo $data['address']; ?></textarea>
                                                                </div>
                                                                <div class="form-group">
                                                                    <label for="vertical"><?php echo _l('Vertical'); ?></label>
                                                                    <input type="text" name="vertical" class="form-control" value="<?php echo $data['vertical']; ?>">
                                                                </div>
                                                                <div class="form-group">
                                                                    <label for="email"><?php echo _l('Email'); ?></label>
                                                                    <input type="email" name="email" class="form-control" value="<?php echo $data['email']; ?>">
                                                                </div>
                                                                <div class="form-group">
                                                                    <label for="websites"><?php echo _l('Websites'); ?></label>
                                                                    <input type="text" name="websites" class="form-control" value="<?php echo $data['websites']; ?>">
                                                                </div>
                                                                <input type="hidden" name="messaging_product" value="whatsapp">
                                                                <div class="form-group">
                                                                    <label for="about"><?php echo _l('ai_prompt'); ?></label>
                                                                    <textarea name="ai_prompt" class="form-control" rows="3"><?php echo $data['ai_prompt']; ?></textarea>
                                                                </div>

                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo _l('Close'); ?></button>
                                                                <button type="submit" class="btn btn-primary"><?php echo _l('Save changes'); ?></button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <p><?php echo _l('No verification data available'); ?></p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
  window.fbAsyncInit = function() {
    FB.init({
      appId            : '515809023421072',
      autoLogAppEvents : true,
      xfbml            : true,
      version          : 'v21.0'
    });
  };
</script>
<script async defer crossorigin="anonymous"
  src="https://connect.facebook.net/en_US/sdk.js">
</script>

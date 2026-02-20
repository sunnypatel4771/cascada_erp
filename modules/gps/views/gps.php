<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <h4 class="no-margin"><?php echo html_escape($title); ?></h4>
            <hr class="hr-panel-heading" />

            <div class="mbot10">
              <a href="<?php echo html_escape($target_url); ?>" target="_blank" rel="noopener" class="btn btn-primary">
                <i class="fa fa-external-link"></i>
                <?php echo _l('gps_open_new_tab'); ?>
              </a>
              <span class="text-muted mleft10"><?php echo _l('gps_iframe_note'); ?></span>
            </div>

            <div class="gps-iframe-wrapper" style="border:1px solid #e4e5e7;border-radius:4px;overflow:hidden;">
              <iframe
                src="<?php echo html_escape($target_url); ?>"
                style="width:100%;height:75vh;border:0;"
                referrerpolicy="no-referrer"
                sandbox="allow-forms allow-same-origin allow-scripts allow-popups allow-popups-to-escape-sandbox"
              ></iframe>
            </div>

            <p class="text-muted mtop10" style="margin-bottom:0;">
              <strong><?php echo _l('gps_tip_title'); ?>:</strong>
              <?php echo _l('gps_tip_body'); ?>
            </p>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>

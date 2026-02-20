<!-- CSS for animation and preview styling -->
<style>
  .animated-section {
      transition: max-height 0.3s ease, opacity 0.3s ease;
      overflow: hidden;
  }
  .animated-section.closed {
      max-height: 0;
      opacity: 0;
  }
  .animated-section.open {
      max-height: 800px; /* Set to maximum expected height */
      opacity: 1;
  }
  .fade-in {
      animation: fadeIn 0.3s ease-in-out;
  }
  @keyframes fadeIn {
      0% { opacity: 0; }
      100% { opacity: 1; }
  }
  
  /* Preview container styling (adjust as needed) */
  .preview {
      border: 1px solid #ddd;
      padding: 15px;
      margin-top: 20px;
      background: #f9f9f9;
  }
  .preview h4 {
      margin-top: 0;
  }
  .header_data, .body_data, .footer_data {
      margin-bottom: 10px;
  }
</style>

<?php 
// Decode JSON data for header, body, and footer parameters if provided as strings.
$header_params = !empty($data['header_params']) ? json_decode($data['header_params'], true) : [];
$body_params   = !empty($data['body_params']) ? json_decode($data['body_params'], true) : [];
$footer_params = !empty($data['footer_params']) ? json_decode($data['footer_params'], true) : [];
?>

<?php if (!empty($template)) { ?>

    <!-- Template Details -->
    <div class="template-details">
        <h3><?= _l('template') . ': ' . $template['template_name'] ?></h3>
        <p><strong><?= _l('parameter_format') ?>:</strong> <?= $template['parameter_format'] ?></p>
        <?php if (!empty($template['sub_category'])): ?>
            <p><strong><?= _l('sub_category') ?>:</strong> <?= $template['sub_category'] ?></p>
        <?php endif; ?>
        <?php if (!empty($template['template_description'])): ?>
            <p><strong><?= _l('description') ?>:</strong> <?= $template['template_description'] ?></p>
        <?php endif; ?>
    </div>
    <hr>

    <!-- Header Section -->
    <?php if (!empty($template['header_data_format'])): ?>
        <?php if ($template['header_params_count'] > 0): ?>
            <h4 class="tw-mt-0 tw-font-semibold tw-text-neutral-700"><?= _l('header'); ?></h4>
            <?php if ('TEXT' === $template['header_data_format'] || '' === $template['header_data_format']): ?>
                <?php if ($template['parameter_format'] == 'NAMED'): ?>
                    <?php 
                    // Decode the header examples from the template.
                    $header_examples = json_decode($template['header_example'], true);
                    if (!empty($header_examples['header_text_named_params'])):
                        foreach ($header_examples['header_text_named_params'] as $example):
                            $param_name   = $example['param_name'];
                            $example_text = $example['example'];
                            // Use provided value if available; otherwise the example.
                            $header_value = isset($header_params[$param_name]['value']) ? $header_params[$param_name]['value'] : $example_text;
                    ?>
                        <?= render_input(
                            'header_params[' . $param_name . '][value]',
                            _l('variable') . ' ' . $param_name,
                            $header_value,
                            'text',
                            ['autocomplete' => 'off'],
                            [],
                            '',
                            'header_param_text header_input header[' . $param_name . '] mentionable'
                        ); ?>
                    <?php 
                        endforeach;
                    endif;
                    ?>
                <?php elseif ($template['parameter_format'] == 'POSITIONAL'): ?>
                    <?php for ($i = 1; $i <= $template['header_params_count']; ++$i): ?>
                        <?php 
                        $header_value = isset($header_params[$i]['value']) ? $header_params[$i]['value'] : '';
                        ?>
                        <?= render_input(
                            'header_params[' . $i . '][value]',
                            _l('variable') . ' ' . $i,
                            $header_value,
                            'text',
                            ['autocomplete' => 'off'],
                            [],
                            '',
                            'header_param_text header_input header[' . $i . '] mentionable'
                        ); ?>
                    <?php endfor; ?>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-danger"><?= _l('currently_type_not_supported', $template['header_data_format']); ?></div>
            <?php endif; ?>
            <hr>
        <?php endif; ?>
    <?php endif; ?>

  <?php 
    // Get allowed extensions from the helper.
    $allowed_extension = whatsapp_get_allowed_extension(); 
    // Lowercase the header data format for comparison.
    $dataFormat = strtolower($template['header_data_format']);
    
    // Only prepare file upload variables if the header format is not 'text'
    if ($dataFormat !== 'text') {
        $allowedExtension = $allowed_extension[$dataFormat];
        $filename = isset($data['filename'])
                      ? base_url(get_upload_path_by_type(($type === 'bot') ? 'bot' : 'campaign') . $data['filename'])
                      : '';
    }
?>
    <?php if ($dataFormat !== 'text'): ?>

    <h4 class="tw-mt-0 tw-font-semibold tw-text-neutral-700"><?= _l($dataFormat); ?></h4>
    <input type="hidden" id="maxFileSize" value="<?= $allowedExtension['size'] ?>">
    <div class="view_<?= $type ?>_file <?= (empty($data['filename'])) ? 'hide' : '' ?>">
        <?php if (!empty($data['filename'])): ?>
            <div class="row">
                <div class="col-md-9">
                    <input type="hidden" id="file_url" value="<?= $filename; ?>">
                    <?php if ($dataFormat === 'image'): ?>
                        <img src="<?= $filename; ?>" class="img img-responsive" style="max-width:70%; height:auto;">
                    <?php elseif ($dataFormat === 'document'): ?>
                        <a href="<?= $filename; ?>" class="btn btn-default"><?= _l('view_document') ?></a>
                    <?php elseif ($dataFormat === 'video'): ?>
                        <video src="<?= $filename; ?>" class="img img-responsive" controls style="max-width:70%; height:auto;"></video>
                    <?php endif; ?>
                </div>
                <div class="col-md-3 text-right">
                    <a href="<?= admin_url(WHATSAPP_MODULE . '/' . $type . 's/delete_' . $type . '_files/' . $data['data']['id']) ?>"><i class="fa fa-remove text-danger"></i></a>
                </div>
            </div>
        <?php endif; ?>
    </div>

        <!-- File input for non-text types -->
        <div class="<?= $type ?>_file <?= (!empty($data['filename'])) ? 'hide' : '' ?>">
            <label for="<?= $dataFormat ?>" class="control-label">
                <i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1" data-toggle="tooltip" data-title="<?= _l('maximum_file_size_should_be') . $allowedExtension['size'] . ' MB' ?>"></i>
                <?= _l('select_' . $dataFormat) ?>
                <small class="text-muted">( <?= _l('allowed_file_types') . $allowedExtension['extension'] ?> )</small>
            </label>
            <input type="file" name="filename" id="<?= $dataFormat ?>" accept="<?= $allowedExtension['extension'] ?>" class="form-control header_file mentionable">
        </div>
    <?php endif; ?>
    <hr>

    <!-- Body Section -->
    <?php if (!empty($template['body_params_count']) && $template['body_params_count'] > 0): ?>
        <h4 class="tw-mt-0 tw-font-semibold tw-text-neutral-700"><?= _l('body'); ?></h4>
        <?php if ($template['parameter_format'] == 'NAMED'): ?>
            <?php 
            $body_examples = json_decode($template['body_example'], true);
            if (!empty($body_examples['body_text_named_params'])):
                foreach ($body_examples['body_text_named_params'] as $example):
                    $param_name   = $example['param_name'];
                    $example_text = $example['example'];
                    $body_value   = isset($body_params[$param_name]['value']) ? $body_params[$param_name]['value'] : $example_text;
            ?>
                <?= render_input(
                    'body_params[' . $param_name . '][value]',
                    _l('variable') . ' ' . $param_name,
                    $body_value,
                    'text',
                    ['autocomplete' => 'off'],
                    [],
                    '',
                    'body_param_text body_input body[' . $param_name . '] mentionable'
                ); ?>
            <?php 
                endforeach;
            endif;
            ?>
        <?php elseif ($template['parameter_format'] == 'POSITIONAL'): ?>
            <?php for ($i = 1; $i <= $template['body_params_count']; ++$i): ?>
                <?php $body_value = isset($body_params[$i]['value']) ? $body_params[$i]['value'] : ''; ?>
                <?= render_input(
                    'body_params[' . $i . '][value]',
                    _l('variable') . ' ' . $i,
                    $body_value,
                    'text',
                    ['autocomplete' => 'off'],
                    [],
                    '',
                    'body_param_text body_input body[' . $i . '] mentionable'
                ); ?>
            <?php endfor; ?>
        <?php endif; ?>
        <hr>
    <?php endif; ?>

    <!-- Footer Section -->
    <?php if (!empty($template['footer_params_count']) && $template['footer_params_count'] > 0): ?>
        <h4 class="tw-mt-0 tw-font-semibold tw-text-neutral-700"><?= _l('footer'); ?></h4>
        <?php if ($template['parameter_format'] == 'NAMED'): ?>
            <?php 
            $footer_examples = json_decode($template['footer_example'], true);
            if (!empty($footer_examples['footer_text_named_params'])):
                foreach ($footer_examples['footer_text_named_params'] as $example):
                    $param_name   = $example['param_name'];
                    $example_text = $example['example'];
                    $footer_value = isset($footer_params[$param_name]['value']) ? $footer_params[$param_name]['value'] : $example_text;
            ?>
                <?= render_input(
                    'footer_params[' . $param_name . '][value]',
                    _l('variable') . ' ' . $param_name,
                    $footer_value,
                    'text',
                    ['autocomplete' => 'off'],
                    [],
                    '',
                    'footer_param_text footer_input footer[' . $param_name . '] mentionable'
                ); ?>
            <?php 
                endforeach;
            endif;
            ?>
        <?php elseif ($template['parameter_format'] == 'POSITIONAL'): ?>
            <?php for ($i = 1; $i <= $template['footer_params_count']; ++$i): ?>
                <?php $footer_value = isset($footer_params[$i]['value']) ? $footer_params[$i]['value'] : ''; ?>
                <?= render_input(
                    'footer_params[' . $i . '][value]',
                    _l('variable') . ' ' . $i,
                    $footer_value,
                    'text',
                    ['autocomplete' => 'off'],
                    [],
                    '',
                    'footer_param_text footer_input footer[' . $i . '] mentionable'
                ); ?>
            <?php endfor; ?>
        <?php endif; ?>
        <hr>
    <?php endif; ?>


<?php } ?>

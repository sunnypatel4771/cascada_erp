"use strict";

(function() {
  // Global variables for preview data and Tribute instance
  let whatsapp_tribute;
  let header_data = '';
  let body_data = '';
  let footer_data = '';

  /**
   * Reattach Tribute to all elements with the "mentionable" class.
   */
  function whatsapp_refreshTribute() {
    if (whatsapp_tribute) {
      whatsapp_tribute.detach(document.querySelectorAll(".mentionable"));
      whatsapp_tribute.attach(document.querySelectorAll(".mentionable"));
    }
  }

  /**
   * Updates the preview section using the response from the server.
   * @param {Object} response - Server response containing view, header, body, footer, button and file data.
   */
  function updatePreviewSection(response) {
    $('.variableDetails').removeClass('hide');
    $('#preview_message').show();

    if (response.error) {
      showError(response.error);
      return;
    }

    updateVariablesSection(response.view);
    updatePreviewData(response);
    updateButtonData(response.button_data);
    updateFilePreview(response.file_data);

    // Update global preview data variables
    header_data = response.header_data || '';
    body_data = response.body_data || '';
    footer_data = response.footer_data || '';

    triggerInputEvents();
    whatsapp_refreshTribute();
  }

  /**
   * Displays an error message.
   * @param {String} error - Error message.
   */
  function showError(error) {
    $('.variables').html(`<div class="alert alert-danger">${error}</div>`);
    $('.selectpicker').selectpicker('refresh');
  }

  /**
   * Updates the variables section with provided HTML view.
   * @param {String} view - HTML content to be rendered.
   */
  function updateVariablesSection(view) {
    const content = (/\S/.test(view))
      ? view
      : '<div class="alert alert-danger">Currently, the variable is not available for this template.</div>';
    $('.variables').html(content);
    $('.selectpicker').selectpicker('refresh');
  }

  /**
   * Updates the preview message with header, body, and footer data.
   * @param {Object} response - Response object containing preview data.
   */
  function updatePreviewData(response) {
    const previewData = `
      <strong class="header_data">${response.header_data || ''}</strong><br><br>
      <p class="body_data">${response.body_data || ''}</p><br>
      <span class="text-muted tw-text-xs footer_data">${response.footer_data || ''}</span>
    `;
    $('.previewmsg').html(previewData);
  }

  /**
   * Renders button preview.
   * @param {Object} button_data - Data object containing buttons.
   */
  function updateButtonData(button_data) {
    let buttonHtml = '';
    if (button_data && button_data.buttons) {
      button_data.buttons.forEach(val => {
        buttonHtml += `<button class="btn btn-default btn-lg btn-block wtc_button">${val.text}</button>`;
      });
    }
    $('.previewBtn').html(buttonHtml);
  }

  /**
   * Renders file preview based on file type.
   * @param {Object} file_data - Data object containing file type and URL.
   */
  function updateFilePreview(file_data) {
    let filePreview = '';
    if (file_data) {
      switch (file_data.type) {
        case 'IMAGE':
          filePreview = `<img src="${file_data.url}" class="img img-responsive" alt="Image Preview" style="max-width: 100%; height: auto;">`;
          break;
        case 'DOCUMENT':
          filePreview = `<a href="${file_data.url}" target="_blank" class="btn btn-default">View Document</a>`;
          break;
        case 'VIDEO':
          filePreview = `<video src="${file_data.url}" class="img img-responsive" controls style="max-width: 100%; height: auto;"></video>`;
          break;
        default:
          filePreview = '<div class="alert alert-danger">Unsupported file type for preview.</div>';
      }
    }
    $('.previewImage').html(filePreview);
  }

  /**
   * Triggers input and change events on header, body, and footer inputs.
   */
  function triggerInputEvents() {
    $('.header_input, .body_input, .footer_input').trigger('input').trigger('change');
  }

  /**
   * Initializes required data, Tribute, and selectpicker.
   */
  function whatsapp_loadData() {
    init_selectpicker(); // Assumes this function is defined globally
    loadMergeFields();
    attachTributeToMentionable();
  }

  /**
   * Initializes Tribute (mention) plugin with merge fields.
   */
  function loadMergeFields() {
    const rel_type = getRelType();
    let options = [];

    // Check if merge_fields is defined as a global variable.
    // Supports both array and object structures.
    if (typeof merge_fields === 'object') {
      if (Array.isArray(merge_fields)) {
        merge_fields.forEach(item => {
          if (item[rel_type] && Array.isArray(item[rel_type])) {
            options = options.concat(item[rel_type].map(field => ({ key: field.name, value: field.key })));
          }
          // Include fallback "other" fields if relation type isn't "other"
          if (rel_type !== 'other' && item['other'] && Array.isArray(item['other'])) {
            options = options.concat(item['other'].map(field => ({ key: field.name, value: field.key })));
          }
        });
      } else {
        // Assume merge_fields is an object with keys corresponding to relation types.
        options = (merge_fields[rel_type] || []).map(field => ({ key: field.name, value: field.key }));
        if (rel_type !== 'other') {
          options = options.concat((merge_fields['other'] || []).map(field => ({ key: field.name, value: field.key })));
        }
      }
    }

    // Initialize Tribute with the "@" trigger and a custom selectTemplate.
    whatsapp_tribute = new Tribute({
      values: options,
      trigger: '@',
      selectClass: "highlights",
      selectTemplate: function(item) {
        if (typeof item === 'undefined') return null;
        // Return the merge field's value upon selection.
        return item.original.value;
      }
    });
    
    // Attach Tribute to elements with the "mentionable" class.
    whatsapp_tribute.attach(document.querySelectorAll(".mentionable"));
  }

  /**
   * Reattach Tribute to mentionable elements.
   */
  function attachTributeToMentionable() {
    if (whatsapp_tribute) {
      whatsapp_tribute.detach(document.querySelectorAll(".mentionable"));
      whatsapp_tribute.attach(document.querySelectorAll(".mentionable"));
    }
  }

  /**
   * Returns the relation type based on the #rel_type input value.
   * @returns {String} Relation type.
   */
  function getRelType() {
    const relType = $('#rel_type').val();
    return relType === 'leads' ? 'leads' : relType === 'contacts' ? 'client' : 'other';
  }

  /**
   * Updates preview text by replacing placeholders with corresponding input values.
   * Supports both positional (e.g. {{1}}) and named (e.g. {{order_id}}) formats.
   * This single function replaces the duplicate definitions.
   * @param {jQuery} inputElement - The input element that triggered the event.
   */
  function updatePreviewFromInput(inputElement) {
    const inputType = getInputType(inputElement);
    if (!inputType) return;

    // Use preview containers as defined in your view.
    const typeMap = {
      header: { data: header_data || '', selector: '.header_data' },
      body: { data: body_data || '', selector: '.body_data' },
      footer: { data: footer_data || '', selector: '.footer_data' }
    };

    // Ensure we work with a string.
    const dataText = String(typeMap[inputType].data || '');

    // Map input name prefixes for each type.
    const prefixMap = {
      header: 'header_params',
      body: 'body_params',
      footer: 'footer_params'
    };
    const prefix = prefixMap[inputType];

    // Retrieve global example mappings (fallback to an empty object if not defined)
    let exampleMapping = {};
    if (inputType === 'header') {
      exampleMapping = window.headerExampleMapping || {};
    } else if (inputType === 'body') {
      exampleMapping = window.bodyExampleMapping || {};
    } else if (inputType === 'footer') {
      exampleMapping = window.footerExampleMapping || {};
    }

    // Replace placeholders in the preview text.
    const updatedData = dataText.replace(/{{([^}]+)}}/g, function (match, placeholder) {
      // Build selector for input that should replace this placeholder.
      const selector = `input.${inputType}_input[name^="${prefix}[${placeholder}]"]`;
      const value = $(selector).val();
      if (value !== undefined && value !== null && value !== '') {
        return value;
      } else if (exampleMapping[placeholder] !== undefined) {
        return exampleMapping[placeholder];
      }
      return match;
    });

    // Update the preview container.
    $(typeMap[inputType].selector).text(updatedData);
  }

  /**
   * Returns input type ('header', 'body', or 'footer') based on the element's class.
   * @param {jQuery} element - The input element.
   * @returns {String|null} Input type.
   */
  function getInputType(element) {
    if (element.hasClass('header_input')) return 'header';
    if (element.hasClass('body_input')) return 'body';
    if (element.hasClass('footer_input')) return 'footer';
    return null;
  }

  /**
   * Extracts context and typeId from the current URL.
   * @returns {Object} Object containing context and typeId.
   */
  function getContextFromUrl() {
    const currentUrl = window.location.href;
    const typeId = extractTypeId(currentUrl);

    if (currentUrl.includes('/whatsapp/campaigns/')) {
      return { context: 'campaign', typeId: typeId };
    } else if (currentUrl.includes('/whatsapp/bots/')) {
      return { context: 'bot', typeId: typeId };
    } else {
      return { context: 'unknown', typeId: typeId };
    }
  }

  /**
   * Extracts the typeId from a URL.
   * @param {String} url - The URL string.
   * @returns {String|null} The extracted typeId.
   */
  function extractTypeId(url) {
    const match = url.match(/\/(\d+)(\/)?$/);
    return match ? match[1] : null;
  }

  /**
   * Handles template change events: loads the template mapping via AJAX.
   */
  $(document).on('change', '#template_id', function () {
    const templateId = $(this).val();
    const { context, typeId } = getContextFromUrl();
    const requestUrl = `${admin_url}whatsapp/get_template_map`;

    $.ajax({
      url: requestUrl,
      type: 'POST',
      dataType: 'json',
      data: { 'template_id': templateId, 'type': context, 'type_id': typeId },
    })
    .done(function (response) {
      updatePreviewSection(response);
    })
    .fail(function (jqXHR, textStatus, errorThrown) {
      console.error('AJAX request failed:', textStatus, errorThrown);
    });
  });

  /**
   * Handle file change events for header and bot files.
   */
  $(document).on('change', '.header_file', function (event) {
    handleFileChange(event, '#header_file');
  });
  $(document).on('change', '#bot_file', function (event) {
    handleFileChange(event, '#bot_file');
  });

  /**
   * Handles file change: updates file preview and validates file size.
   * @param {Event} event - Change event.
   * @param {String} targetSelector - Selector for file preview target.
   */
  function handleFileChange(event, targetSelector) {
    const file = event.target.files[0];
    const maxAllowedSize = $('#maxFileSize').val() * 1024 * 1024;
    const fileSize = file ? file.size : 0;
    const previewUrl = file ? URL.createObjectURL(file) : '';
    const fileType = file ? file.type : '';

    if (fileSize > maxAllowedSize) {
      alert_float('danger', `Max file size upload ${$('#maxFileSize').val()} (MB)`);
      $(targetSelector).empty();
      $(event.target).val('');
      return;
    }

    if (previewUrl) {
      let previewElement = '';
      if (fileType.startsWith('image/')) {
        previewElement = `<img src="${previewUrl}" class="img img-responsive" alt="Image Preview" style="max-width: 100%; height: auto;">`;
      } else if (fileType.startsWith('video/')) {
        previewElement = `<video src="${previewUrl}" class="img img-responsive" controls style="max-width: 100%; height: auto;"></video>`;
      } else if (fileType.startsWith('application/')) {
        previewElement = `<a href="${previewUrl}" target="_blank" class="btn btn-default">${file.name}</a>`;
      }
      $(targetSelector).html(previewElement);
    }
  }

  // Initialize data load on document ready.
  $(function () {
    whatsapp_loadData();
  });

  // Expose some functions globally if needed (for Tribute reattachment)
  window.whatsapp_refreshTribute = whatsapp_refreshTribute;
  window.updatePreviewSection = updatePreviewSection;
})();

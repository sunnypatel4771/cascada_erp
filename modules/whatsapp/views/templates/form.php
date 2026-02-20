<?php init_head(); ?>
<?php defined('BASEPATH') || exit('No direct script access allowed');

// Determine if we're editing an existing template or creating a new one
$is_edit = isset($template) && !empty($template);

$form_action = $is_edit ? admin_url('templates/update_template/' . $template->template_id) : admin_url('templates/save_template');
$button_text = $is_edit ? _l('update_template') : _l('save_template');
$form_title = $is_edit ? _l('edit_template') : _l('create_template');
?>
<div id="wrapper">
    <div class="content">
        <div class="container mx-auto py-8">
            <div class="max-w-3xl mx-auto bg-white p-6 rounded-lg shadow-lg">
                <h4 class="text-lg font-semibold text-gray-700 mb-6"><?php echo $form_title; ?></h4>
                <div class="col-12" x-data="{ headerType:'', header_text_body:'', footer_text_body:'', text_body:'', enableHeaderVariableExample:false, newBodyTextInputFields:[], buttonModels:{}, customButtons:{ totalAllowedButtons:10, totalButtonsUsed:0, buttonUsesByTypes:{ URL_BUTTON:0, URL_BUTTON_LIMIT:2, COPY_CODE:0, COPY_CODE_LIMIT:1, VOICE_CALL:0, VOICE_CALL_LIMIT:1, PHONE_NUMBER:0, PHONE_NUMBER_LIMIT:1 }, totalUrlButtonUsed:0, data:{}, }, addWhatsAppButtonOption : function(buttonType) { let uniqueBtnId = _.uniqueId(); this.customButtons.data[uniqueBtnId] = { buttonType : buttonType, buttonIndex : uniqueBtnId }; this.customButtons.totalButtonsUsed++; if((buttonType == 'URL_BUTTON') || (buttonType == 'DYNAMIC_URL_BUTTON')) { this.customButtons.buttonUsesByTypes['URL_BUTTON']++; } else if((buttonType == 'COPY_CODE')) { this.customButtons.buttonUsesByTypes['COPY_CODE']++; } else if((buttonType == 'VOICE_CALL')) { this.customButtons.buttonUsesByTypes['VOICE_CALL']++; } else if((buttonType == 'PHONE_NUMBER')) { this.customButtons.buttonUsesByTypes['PHONE_NUMBER']++; } }, deleteWhatsAppButtonOption : function(buttonIndex) { let buttonType = this.customButtons.data[buttonIndex]['buttonType']; if((buttonType == 'URL_BUTTON') || (buttonType == 'DYNAMIC_URL_BUTTON')) { this.customButtons.buttonUsesByTypes['URL_BUTTON']--; } else if((buttonType == 'COPY_CODE')) { this.customButtons.buttonUsesByTypes['COPY_CODE']-- ; } else if((buttonType == 'VOICE_CALL')) { this.customButtons.buttonUsesByTypes['VOICE_CALL']-- ; } else if((buttonType == 'PHONE_NUMBER')) { this.customButtons.buttonUsesByTypes['PHONE_NUMBER']-- ; } delete this.customButtons.data[buttonIndex]; delete this.buttonModels[buttonIndex]; this.customButtons.totalButtonsUsed--; }}">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-7">
                                    <form action="https://whatsjet-demo.livelyworks.net/vendor-console/whatsapp/templates/create-process" class="lw-form lw-ajax-form" method="POST" data-show-processing="true" role="form" data-error-class="has-danger" id="lwNewTemplateCreationForm" novalidate="novalidate"> <input type="hidden" name="_token" value="1PCh9aiF4ANNuLJL4B54eyev5c9NsZSuqaImONWd" autocomplete="off">
                                        <div class="form-group mb-1 "> <label for="lwTemplateNameField">Template Name</label> <input class="lw-form-field form-control" type="text" id="lwTemplateNameField" data-form-group-class="" name="template_name" required="true"> </div> <span class="form-text text-muted mt-3 text-sm"><a target="_blank" href="https://developers.facebook.com/docs/whatsapp/message-templates/guidelines/">Template Formatting Help</a></span>
                                        <div class="form-group mb-1 "> <label for="lwTemplateLanguageField">Template Language Code</label> <input class="lw-form-field form-control" type="text" id="lwTemplateLanguageField" data-form-group-class="" name="language_code" required="true"> </div>
                                        <div class="alert alert-secondary my-3"> While Authentication and Flow templates are supported for sending however you need to create/edit those templates on Meta. <a class="lw-btn btn btn-sm btn-light float-right" target="_blank" href="https://business.facebook.com/wa/manage/message-templates/?waba_id=330784813442750"> Manage Templates on Meta <i class="fas fa-external-link-alt"></i></a> </div>
                                        <div class="form-group mb-1 "> <label for="lwSelectCategoryField-selectized">Category</label> <select data-lw-plugin="lwSelectize" class="lw-form-field form-control selectized" id="lwSelectCategoryField" type="selectize" data-form-group-class="" data-selected="" name="category" tabindex="-1" style="display: none;">
                                                <option value="MARKETING" selected="selected">MARKETING</option>
                                            </select>
                                            <div class="selectize-control lw-form-field form-control single">
                                                <div class="selectize-input items full has-options has-items">
                                                    <div class="item" data-value="MARKETING">MARKETING</div><input type="select-one" autocomplete="off" tabindex="" id="lwSelectCategoryField-selectized" style="width: 4px;">
                                                </div>
                                                <div class="selectize-dropdown single lw-form-field form-control" style="display: none;">
                                                    <div class="selectize-dropdown-content"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <fieldset> <input id="lwMediaFileName" type="hidden" value="" name="uploaded_media_file_name">
                                            <legend>Header <small>(Optional)</small></legend>
                                            <div class="form-group mb-1 "> <label for="lwMediaHeaderType-selectized">Header Type</label> <select data-lw-plugin="lwSelectize" class="lw-form-field form-control selectized" id="lwMediaHeaderType" x-model="headerType" type="selectize" data-form-group-class="" data-selected="" name="media_header_type" tabindex="-1" style="display: none;">
                                                    <option value="0" selected="selected">None</option>
                                                </select>
                                                <div class="selectize-control lw-form-field form-control single">
                                                    <div class="selectize-input items full has-options has-items">
                                                        <div class="item" data-value="0">None</div><input type="select-one" autocomplete="off" tabindex="" id="lwMediaHeaderType-selectized" style="width: 4px;">
                                                    </div>
                                                    <div class="selectize-dropdown single lw-form-field form-control" style="display: none;">
                                                        <div class="selectize-dropdown-content"></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="my-3">
                                                <div x-show="headerType == 'text'" class="form-group col-sm-12" style="display: none;">
                                                    <div class="form-group mb-1 "> <label for="lwHeaderTextBody">Header Text</label> <input class="lw-form-field form-control" type="text" id="lwHeaderTextBody" data-form-group-class="" x-model="header_text_body" name="header_text_body"> </div>
                                                    <div class="form-group text-right"> <button :disabled="enableHeaderVariableExample" id="lwAddSinglePlaceHolder" class="btn btn-dark btn-sm" type="button"> <i class="fa fa-plus"></i> Add Variable</button> </div> <template x-if="enableHeaderVariableExample">
                                                        <div class="form-group mb-1 "> <label for="lwHeaderTextBodyExample">Header Text Variable Example</label> <input class="lw-form-field form-control" type="text" id="lwHeaderTextBodyExample" data-form-group-class="" name="example_header_fields"> </div>
                                                    </template>
                                                </div>
                                                <div x-show="headerType == 'document'" class="form-group col-sm-12" style="display: none;">
                                                    <div class="filepond--root lw-file-uploader filepond--hopper" id="lwDocumentMediaFilepond" data-style-button-remove-item-position="left" data-style-button-process-item-position="right" data-style-load-indicator-position="right" data-style-progress-indicator-position="right" data-style-button-remove-item-align="false"><input class="filepond--browser" type="file" id="filepond--browser-k617l3b38" name="filepond" aria-controls="filepond--assistant-k617l3b38" aria-labelledby="filepond--drop-label-k617l3b38" accept="text/plain,application/pdf,application/vnd.ms-powerpoint,application/msword,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                                                        <div style="height: 100%;"></div>
                                                        <div class="filepond--drop-label" style="transform: translate3d(0px, 0px, 0px); opacity: 1;"><label for="filepond--browser-k617l3b38" id="filepond--drop-label-k617l3b38" aria-hidden="true">Select Document</label></div>
                                                        <div class="filepond--list-scroller">
                                                            <ul class="filepond--list" role="list"></ul>
                                                        </div>
                                                        <div class="filepond--panel filepond--panel-root" data-scalable="true">
                                                            <div class="filepond--panel-top filepond--panel-root"></div>
                                                            <div class="filepond--panel-center filepond--panel-root"></div>
                                                            <div class="filepond--panel-bottom filepond--panel-root"></div>
                                                        </div><span class="filepond--assistant" id="filepond--assistant-k617l3b38" role="status" aria-live="polite" aria-relevant="additions"></span>
                                                        <div class="filepond--drip"></div>
                                                        <fieldset class="filepond--data"></fieldset>
                                                    </div>
                                                </div>
                                                <div x-show="headerType == 'image'" class="form-group col-sm-12" style="display: none;">
                                                    <div class="filepond--root lw-file-uploader filepond--hopper" id="lwImageMediaFilepond" data-style-button-remove-item-position="left" data-style-button-process-item-position="right" data-style-load-indicator-position="right" data-style-progress-indicator-position="right" data-style-button-remove-item-align="false"><input class="filepond--browser" type="file" id="filepond--browser-iw3qd97f7" name="filepond" aria-controls="filepond--assistant-iw3qd97f7" aria-labelledby="filepond--drop-label-iw3qd97f7" accept="image/jpeg,image/png">
                                                        <div style="height: 100%;"></div>
                                                        <div class="filepond--drop-label" style="transform: translate3d(0px, 0px, 0px); opacity: 1;"><label for="filepond--browser-iw3qd97f7" id="filepond--drop-label-iw3qd97f7" aria-hidden="true">Select Image</label></div>
                                                        <div class="filepond--list-scroller">
                                                            <ul class="filepond--list" role="list"></ul>
                                                        </div>
                                                        <div class="filepond--panel filepond--panel-root" data-scalable="true">
                                                            <div class="filepond--panel-top filepond--panel-root"></div>
                                                            <div class="filepond--panel-center filepond--panel-root"></div>
                                                            <div class="filepond--panel-bottom filepond--panel-root"></div>
                                                        </div><span class="filepond--assistant" id="filepond--assistant-iw3qd97f7" role="status" aria-live="polite" aria-relevant="additions"></span>
                                                        <div class="filepond--drip"></div>
                                                        <fieldset class="filepond--data"></fieldset>
                                                    </div>
                                                </div>
                                                <div x-show="headerType == 'video'" class="form-group col-sm-12" style="display: none;">
                                                    <div class="filepond--root lw-file-uploader filepond--hopper" id="lwVideoMediaFilepond" data-style-button-remove-item-position="left" data-style-button-process-item-position="right" data-style-load-indicator-position="right" data-style-progress-indicator-position="right" data-style-button-remove-item-align="false"><input class="filepond--browser" type="file" id="filepond--browser-onhlsq5tx" name="filepond" aria-controls="filepond--assistant-onhlsq5tx" aria-labelledby="filepond--drop-label-onhlsq5tx" accept="video/mp4,video/3gp">
                                                        <div style="height: 100%;"></div>
                                                        <div class="filepond--drop-label" style="transform: translate3d(0px, 0px, 0px); opacity: 1;"><label for="filepond--browser-onhlsq5tx" id="filepond--drop-label-onhlsq5tx" aria-hidden="true">Select Video</label></div>
                                                        <div class="filepond--list-scroller">
                                                            <ul class="filepond--list" role="list"></ul>
                                                        </div>
                                                        <div class="filepond--panel filepond--panel-root" data-scalable="true">
                                                            <div class="filepond--panel-top filepond--panel-root"></div>
                                                            <div class="filepond--panel-center filepond--panel-root"></div>
                                                            <div class="filepond--panel-bottom filepond--panel-root"></div>
                                                        </div><span class="filepond--assistant" id="filepond--assistant-onhlsq5tx" role="status" aria-live="polite" aria-relevant="additions"></span>
                                                        <div class="filepond--drip"></div>
                                                        <fieldset class="filepond--data"></fieldset>
                                                    </div>
                                                </div>
                                            </div>
                                        </fieldset>
                                        <fieldset>
                                            <legend>Body</legend> <small>Enter the text for your message in the language you've selected.</small>
                                            <div class="form-group"> <label for="lwTemplateBody">Body Text</label> <textarea name="template_body" id="lwTemplateBody" class="form-control valid" x-model="text_body" rows="10" aria-invalid="false"></textarea> </div>
                                            <div class="form-group text-right"> <button id="lwBoldBtn" class="btn btn-light btn-sm" type="button"> <i class="fa fa-bold"></i></button> <button id="lwItalicBtn" class="btn btn-light btn-sm" type="button"> <i class="fa fa-italic"></i></button> <button id="lwStrikeThroughBtn" class="btn btn-light btn-sm" type="button"> <i class="fa fa-strikethrough"></i></button> <button id="lwCodeBtn" class="btn btn-light btn-sm" type="button"> <i class="fa fa-code"></i></button> <button id="lwAddPlaceHolder" class="btn btn-dark btn-sm" type="button"> <i class="fa fa-plus"></i> Add Variables</button> </div>
                                            <div> <template x-if="_.size(newBodyTextInputFields)">
                                                    <div>
                                                        <h4>Samples Text</h4> <template x-for="(item, index) in newBodyTextInputFields" :key="index">
                                                            <div class="form-group">
                                                                <div class="input-group">
                                                                    <div class="input-group-prepend"> <span class="input-group-text"> <span x-text="item.text_variable"></span> </span> </div> <input type="text" class="form-control" x-bind:name="'example_body_fields[' + index + ']'" required="required">
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </template>
                                                <div>
                                                    <h4>Samples Text</h4> <template x-for="(item, index) in newBodyTextInputFields" :key="index">
                                                        <div class="form-group">
                                                            <div class="input-group">
                                                                <div class="input-group-prepend"> <span class="input-group-text"> <span x-text="item.text_variable"></span> </span> </div> <input type="text" class="form-control" x-bind:name="'example_body_fields[' + index + ']'" required="required">
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <div class="form-group">
                                                        <div class="input-group">
                                                            <div class="input-group-prepend"> <span class="input-group-text"> <span x-text="item.text_variable">{{1}}</span> </span> </div> <input type="text" class="form-control" x-bind:name="'example_body_fields[' + index + ']'" required="required" name="example_body_fields[1]">
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <div class="input-group">
                                                            <div class="input-group-prepend"> <span class="input-group-text"> <span x-text="item.text_variable">{{2}}</span> </span> </div> <input type="text" class="form-control" x-bind:name="'example_body_fields[' + index + ']'" required="required" name="example_body_fields[2]">
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <div class="input-group">
                                                            <div class="input-group-prepend"> <span class="input-group-text"> <span x-text="item.text_variable">{{3}}</span> </span> </div> <input type="text" class="form-control" x-bind:name="'example_body_fields[' + index + ']'" required="required" name="example_body_fields[3]">
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <div class="input-group">
                                                            <div class="input-group-prepend"> <span class="input-group-text"> <span x-text="item.text_variable">{{4}}</span> </span> </div> <input type="text" class="form-control" x-bind:name="'example_body_fields[' + index + ']'" required="required" name="example_body_fields[4]">
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <div class="input-group">
                                                            <div class="input-group-prepend"> <span class="input-group-text"> <span x-text="item.text_variable">{{5}}</span> </span> </div> <input type="text" class="form-control" x-bind:name="'example_body_fields[' + index + ']'" required="required" name="example_body_fields[5]">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </fieldset>
                                        <div class="form-group mb-1 "> <label for="lwTemplateFooter">Footer (Optional)</label> <input class="lw-form-field form-control" type="text" id="lwTemplateFooter" data-form-group-class="" name="template_footer" x-model="footer_text_body"> <span class="form-text text-muted mt-3 text-sm">Add a short line of text to the bottom of your message template.</span> </div>
                                        <fieldset>
                                            <legend>Buttons <small>(Optional)</small></legend>
                                            <div class="mb-4 ">
                                                <h3 class="text-muted">Create buttons that let customers respond to your message or take action.</h3>
                                            </div>
                                            <div class="lw-buttons-container">
                                                <div> <template x-for="customButtonData in customButtons.data">
                                                        <div class="card shadow-none mb-2">
                                                            <h3 class="card-header"> <template x-if="customButtonData.buttonType == 'QUICK_REPLY'"> <span>Quick Reply Button</span> </template> <template x-if="customButtonData.buttonType == 'PHONE_NUMBER'"> <span>Phone Number Button</span> </template> <template x-if="customButtonData.buttonType == 'URL_BUTTON'"> <span>URL Button</span> </template> <template x-if="customButtonData.buttonType == 'DYNAMIC_URL_BUTTON'"> <span>Dynamic URL Button</span> </template> <template x-if="customButtonData.buttonType == 'VOICE_CALL'"> <span>WhatsApp Call Button</span> </template> <template x-if="customButtonData.buttonType == 'COPY_CODE'"> <span>Coupon Code Copy Button</span> </template> <button @click.prevent="deleteWhatsAppButtonOption(customButtonData.buttonIndex)" class="btn btn-link float-right p-1" type="button"><i class="fa fa-times text-danger"></i></button> </h3>
                                                            <div class="card-body"> <input type="hidden" x-bind:name="'message_buttons['+customButtonData.buttonIndex+'][type]'" x-bind:value="customButtonData.buttonType"> <template x-if="_.includes(['QUICK_REPLY','PHONE_NUMBER', 'URL_BUTTON', 'VOICE_CALL','DYNAMIC_URL_BUTTON'], customButtonData.buttonType)">
                                                                    <div class="form-group mb-1 mt-4"> <label for="d36103610972d0e85c9785b66cd96ba4e3ccab5c">Button Text</label>
                                                                        <div class="input-group ">
                                                                            <div class="input-group-prepend"> <span class="input-group-text"><i class="fa fa-font"></i></span> </div> <input class="lw-form-field form-control" type="text" id="d36103610972d0e85c9785b66cd96ba4e3ccab5c" x-bind:id="customButtonData.buttonIndex" data-form-group-class="mt-4" x-model="buttonModels[customButtonData.buttonIndex]" x-bind:name="'message_buttons['+customButtonData.buttonIndex+'][text]'">
                                                                        </div>
                                                                    </div>
                                                                </template> <template x-if="customButtonData.buttonType == 'PHONE_NUMBER'">
                                                                    <div class="form-group mb-1 "> <label for="936bb70c770c04f6261176f36b5887fc2bc94738">Phone Number</label>
                                                                        <div class="input-group ">
                                                                            <div class="input-group-prepend"> <span class="input-group-text"><i class="fa fa-phone-alt"></i></span> </div> <input class="lw-form-field form-control" type="number" id="936bb70c770c04f6261176f36b5887fc2bc94738" x-bind:id="customButtonData.buttonIndex" data-form-group-class="" x-bind:name="'message_buttons['+customButtonData.buttonIndex+'][phone_number]'">
                                                                        </div>
                                                                    </div>
                                                                </template> <template x-if="customButtonData.buttonType == 'URL_BUTTON'">
                                                                    <div class="form-group mb-1 mt-4"> <label for="c85b32dd94e9f6b57e81f3fc83421deb4e10984b">Website URL</label>
                                                                        <div class="input-group ">
                                                                            <div class="input-group-prepend"> <span class="input-group-text"><i class="fa fa-link"></i></span> </div> <input class="lw-form-field form-control" type="url" id="c85b32dd94e9f6b57e81f3fc83421deb4e10984b" x-bind:id="customButtonData.buttonIndex" data-form-group-class="mt-4" x-bind:name="'message_buttons['+customButtonData.buttonIndex+'][url]'">
                                                                        </div>
                                                                    </div>
                                                                </template> <template x-if="customButtonData.buttonType == 'DYNAMIC_URL_BUTTON'">
                                                                    <div class="form-group mb-1 mt-4"> <label for="c85b32dd94e9f6b57e81f3fc83421deb4e10984b">Website URL</label>
                                                                        <div class="input-group ">
                                                                            <div class="input-group-prepend"> <span class="input-group-text"><i class="fa fa-link"></i></span> </div> <input class="lw-form-field form-control" type="url" id="c85b32dd94e9f6b57e81f3fc83421deb4e10984b" x-bind:id="customButtonData.buttonIndex" data-form-group-class="mt-4" x-bind:name="'message_buttons['+customButtonData.buttonIndex+'][url]'">
                                                                            <div class="input-group-append"> <span class="input-group-text">{{1}}</span> </div>
                                                                        </div>
                                                                    </div>
                                                                </template> <template x-if="_.includes(['COPY_CODE', 'DYNAMIC_URL_BUTTON'],customButtonData.buttonType)">
                                                                    <div class="form-group mb-1 mt-4"> <label for="3c3676ad9b9291821e327b4504145b6ab452bd5d">Example</label> <input class="lw-form-field form-control" type="text" id="3c3676ad9b9291821e327b4504145b6ab452bd5d" x-bind:id="customButtonData.buttonIndex" data-form-group-class="mt-4" x-bind:name="'message_buttons['+customButtonData.buttonIndex+'][example]'"> </div>
                                                                </template> </div>
                                                        </div>
                                                    </template> </div>
                                                <div class="mt-4"> <button :disabled="customButtons.totalButtonsUsed >= customButtons.totalAllowedButtons" class="btn btn-dark btn-sm" type="button" @click.prevent="addWhatsAppButtonOption('QUICK_REPLY')"><i class="fa fa-reply"></i> Quick Reply Button</button> <button :disabled="(customButtons.totalButtonsUsed >= customButtons.totalAllowedButtons) || (customButtons.buttonUsesByTypes.PHONE_NUMBER >= customButtons.buttonUsesByTypes.PHONE_NUMBER_LIMIT)" class="btn btn-dark btn-sm" type="button" @click.prevent="addWhatsAppButtonOption('PHONE_NUMBER')"><i class="fa fa-phone-alt"></i> Phone Number Button</button> <button :disabled="(customButtons.totalButtonsUsed >= customButtons.totalAllowedButtons) || (customButtons.buttonUsesByTypes.COPY_CODE >= customButtons.buttonUsesByTypes.COPY_CODE_LIMIT)" class="btn btn-dark btn-sm" type="button" @click.prevent="addWhatsAppButtonOption('COPY_CODE')"><i class="fa fa-clipboard"></i> Copy Code Button</button> <button :disabled="(customButtons.totalButtonsUsed >= customButtons.totalAllowedButtons) || (customButtons.buttonUsesByTypes.URL_BUTTON >= customButtons.buttonUsesByTypes.URL_BUTTON_LIMIT)" class="btn btn-dark btn-sm" type="button" @click.prevent="addWhatsAppButtonOption('URL_BUTTON')"> <i class="fa fa-link"></i> URL Button</button> <button :disabled="(customButtons.totalButtonsUsed >= customButtons.totalAllowedButtons) || (customButtons.buttonUsesByTypes.URL_BUTTON >= customButtons.buttonUsesByTypes.URL_BUTTON_LIMIT)" class="btn btn-dark btn-sm" type="button" @click.prevent="addWhatsAppButtonOption('DYNAMIC_URL_BUTTON')"> <i class="fa fa-link"></i> Dynamic URL Button</button> <template x-if="customButtons.totalButtonsUsed >= customButtons.totalAllowedButtons">
                                                        <div class="alert alert-danger mt-4"> You have reached maximum buttons allowed by Meta for template </div>
                                                    </template> </div>
                                            </div>
                                        </fieldset>
                                        <div class="form-group"> <button type="submit" class="btn btn-primary">Submit</button> </div>
                                        <div class="lw-form-overlay"></div>
                                    </form>
                                </div>
                                <div class="col-md-1"></div>
                                <div class="col-md-4">
                                    <div class="lw-whatsapp-template-create-preview">
                                        <h3>Template Preview</h3>
                                        <div class="lw-whatsapp-preview-container"> <img class="lw-whatsapp-preview-bg" src="https://whatsjet-demo.livelyworks.net/imgs/wa-message-bg.png" alt="">
                                            <div class="lw-whatsapp-preview">
                                                <div class="card ">
                                                    <div x-show="headerType &amp;&amp; (headerType != 'text')" class="lw-whatsapp-header-placeholder" style="display: none;"> <i x-show="headerType == 'video'" class="fa fa-5x fa-play-circle text-white" style="display: none;"></i> <i x-show="headerType == 'image'" class="fa fa-5x fa-image text-white" style="display: none;"></i> <i x-show="headerType == 'location'" class="fa fa-5x fa-map-marker-alt text-white" style="display: none;"></i> <i x-show="headerType == 'document'" class="fa fa-5x fa-file-alt text-white" style="display: none;"></i> </div>
                                                    <div x-show="headerType == 'location'" class="lw-whatsapp-location-meta bg-secondary p-2" style="display: none;"> <small>{{location_name}}</small><br> <small>{{address}}</small> </div>
                                                    <div x-show="headerType == 'text'" class="lw-whatsapp-body mb--3" style="display: none;"> <strong x-text="header_text_body"></strong> </div>
                                                    <div class="lw-whatsapp-body lw-ws-pre-line" x-html="appFuncs.formatWhatsAppText(text_body)"> {{1}} {{2}} {{3}} {{4}} {{5}} </div>
                                                    <div class="lw-whatsapp-footer text-muted" x-text="footer_text_body"></div>
                                                    <div class="card-footer lw-whatsapp-buttons">
                                                        <div class="list-group list-group-flush lw-whatsapp-buttons"> <template x-for="(customButtonData, index) in customButtons.data" :key="index">
                                                                <div>
                                                                    <div class="list-group-item"> <template x-if="customButtonData.buttonType == 'QUICK_REPLY'"> <i class="fa fa-reply"></i> </template> <template x-if="customButtonData.buttonType == 'PHONE_NUMBER'"> <i class="fa fa-phone-alt"></i> </template> <template x-if="customButtonData.buttonType == 'URL_BUTTON'"> <i class="fas fa-external-link-square-alt"></i> </template> <template x-if="customButtonData.buttonType == 'DYNAMIC_URL_BUTTON'"> <i class="fas fa-external-link-square-alt"></i> </template> <template x-if="customButtonData.buttonType == 'VOICE_CALL'"> <i class="fab fa-whatsapp"></i><i class="fa fa-phone-alt"></i> </template> <template x-if="customButtonData.buttonType == 'COPY_CODE'"> <span><i class="fa fa-copy"></i> Copy Code</span> </template> <span x-text="buttonModels[customButtonData.buttonIndex]"></span> </div> <template x-if="index == 3">
                                                                        <div class="list-group-item"><i class="fa fa-menu"></i> See all options <br><small class="text-orange">More than 3 buttons will be shown in the list by clicking</small></div>
                                                                    </template>
                                                                </div>
                                                            </template> </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    function toggleHeaderFields() {
        var headerType = document.getElementById('header_type').value;
        document.getElementById('header_text_field').style.display = headerType === 'text' ? 'block' : 'none';
        document.getElementById('header_file_field').style.display = ['image', 'document', 'video'].includes(headerType) ? 'block' : 'none';
    }
</script>
<?php init_tail(); ?>
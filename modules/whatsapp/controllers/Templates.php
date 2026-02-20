<?php

defined('BASEPATH') || exit('No direct script access allowed');

/**
 * Templates Controller
 *
 * Handles operations related to WhatsApp templates.
 */
class Templates extends AdminController
{
    /**
     * Constructor
     *
     * Initializes the controller and checks module activation status.
     */
    public function __construct()
    {
        parent::__construct();

        // Check if the WhatsApp module is inactive
        if ($this->app_modules->is_inactive('whatsapp')) {
            access_denied();
        }

        $this->load->model('whatsapp_interaction_model'); // Load the WhatsApp interaction model
        $this->load->library('WhatsappLibrary'); // Load the WhatsappLibrary for template creation and editing
    }

    /**
     * Index method
     *
     * Loads the main view for WhatsApp templates management.
     */
    public function index()
    {
        // Check if user has permission to view WhatsApp templates
        if (!staff_can('view', 'whatsapp_template')) {
            access_denied();
        }

        $viewData['title'] = _l('templates'); // Set view title

        $this->load->view('templates/index', $viewData); // Load templates view
    }

    /**
     * Get Table Data method
     *
     * Retrieves data for the templates table via AJAX.
     *
     * @return bool Returns false if the request is not an AJAX request.
     */
    public function get_table_data()
    {
        if (!$this->input->is_ajax_request()) {
            return false;
        }

        $this->app->get_table_data(module_views_path(WHATSAPP_MODULE, 'tables/templates')); // Get table data
    }

    /**
     * Load Templates method
     *
     * Loads WhatsApp templates asynchronously.
     *
     * @return bool Returns false if the request is not an AJAX request or if the user lacks permission.
     */
    public function load_templates()
    {
        if (!$this->input->is_ajax_request() && !staff_can('load_template', 'whatsapp_template')) {
            return false;
        }

        $response = $this->whatsapp_interaction_model->load_templates(); // Call model method to load templates
        if (false == $response['success']) {
            // If loading templates fails, return error response
            echo json_encode([
                'success' => $response['success'],
                'type'    => $response['type'],
                'message' => $response['message'],
            ]);
            exit();
        }

        // If templates are loaded successfully, return success response
        echo json_encode([
            'success' => true,
            'type'    => 'success',
            'message' => _l('template_data_loaded'),
        ]);
    }

    /**
     * Create Template method
     */
public function save_template()
{
    // Check if it's a POST request
    if ($this->input->server('REQUEST_METHOD') !== 'POST') {
        show_error('Invalid request method', 405);
        return;
    }

    // Collect data from the POST request
    $templateData = [
        'template_name' => $this->input->post('template_name', true), // Template Name
        'category'      => $this->input->post('category', true), // Category
        'language'      => $this->input->post('language', true), // Language
        'header_type'   => $this->input->post('header_type', true), // Header Type
        'header_text'   => $this->input->post('header_text', true), // Header Text
        'body_text'     => $this->input->post('body_text', true), // Body Text
        'footer_text'   => $this->input->post('footer_text', true), // Footer Text
        'buttons'       => json_encode(explode(',', $this->input->post('buttons', true))), // Buttons (comma-separated to JSON array)
    ];

    // Handle file upload if header file is included
    if (!empty($_FILES['header_file']['name'])) {
        $config['upload_path']   = './uploads/whatsapp_templates/';
        $config['allowed_types'] = 'jpg|jpeg|png|gif|pdf|mp4|avi|mkv|doc|docx';
        $config['max_size']      = 2048; // 2 MB
        $config['encrypt_name']  = true;

        $this->load->library('upload', $config);

        if ($this->upload->do_upload('header_file')) {
            $uploadData = $this->upload->data();
            $templateData['header_file'] = $uploadData['file_name']; // Save the uploaded file name
        } else {
            echo json_encode([
                'success' => false,
                'message' => $this->upload->display_errors(),
            ]);
            return;
        }
    }

    // Insert or update the template
    if ($this->input->post('template_id')) {
        // Update existing template
        $this->db->where('template_id', $this->input->post('template_id'));
        $success = $this->db->update(db_prefix() . 'whatsapp_templates', $templateData);
    } else {
        // Insert new template
        $success = $this->db->insert(db_prefix() . 'whatsapp_templates', $templateData);
    }

    // Return response
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Template saved successfully!',
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save the template.',
        ]);
    }
}


/**
 * Template Form (Create & Edit)
 *
 * Displays the form for creating or editing a WhatsApp template.
 *
 * @param int|null $id The template ID for editing. Null for creating a new template.
 */
public function template_form($id = null)
{
    // Check if the user has permission to create or edit templates
    if (!staff_can('create', 'whatsapp_template') && !$id) {
        access_denied();
    }
    if (!staff_can('edit', 'whatsapp_template') && $id) {
        access_denied();
    }

    // Initialize data array
    $data = [
        'title' => $id ? _l('edit_template') : _l('create_template'),
        'categories' => [
            'marketing',
            'support',
            'alerts',
            'promotions',
            'notifications',
        ],
        'languages' => [
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'pt' => 'Portuguese',
            'hi' => 'Hindi',
        ],
    ];

    if ($id) {
        // Fetch template data for editing
        $data['template'] = $this->db->where('id', $id)
                                     ->get(db_prefix() . 'whatsapp_templates')
                                     ->row();

        if (!$data['template']) {
            show_404();
        }
    }

    // Load the form view
    $this->load->view('whatsapp/templates/form', $data);
}



    /**
     * Update Template method
     */
    public function update_template()
    {
        // Collect template ID and updated data from POST request
        $templateId = $this->input->post('template_id');
        $header = [
            "type"   => "HEADER",
            "format" => $this->input->post('new_header_data_format'),
        ];

        $body = [
            "type"       => "BODY",
            "parameters" => [
                [
                    "type" => "text",
                    "text" => $this->input->post('new_body_data'),
                ],
            ]
        ];

        // Optional footer and buttons
        $footer = $this->input->post('new_footer') ? [
            "type" => "FOOTER",
            "text" => $this->input->post('new_footer')
        ] : null;

        $buttons = $this->input->post('new_buttons') ? json_decode($this->input->post('new_buttons')) : null;
        
        // Retrieve access token and account ID dynamically
        $accessToken = get_option('whatsapp_access_token'); // Fetch stored access token
        $accountId   = get_option('whatsapp_business_account_id'); // Fetch stored WhatsApp Business Account ID

        if (!$accessToken || !$accountId) {
            echo json_encode(['success' => false, 'message' => 'Missing WhatsApp access token or account ID.']);
            return;
        }

        // Call the editTemplate method from WhatsappLibrary to update the template
        $responseData = $this->whatsapplibrary->editTemplate(
            $header,
            $body,
            $footer,
            $buttons,
            $templateId,
            $accessToken
        );

        // Handle the response
        if ($responseData['success']) {
            echo json_encode(['success' => true, 'message' => 'Template updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update template']);
        }
    }
}

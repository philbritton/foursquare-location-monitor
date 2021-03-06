<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Admin_controller extends CI_Controller {
	
	public $layout = 'default';
	
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		// Require login
		if (!$this->user->isLoggedIn())
			redirect('login');

		// Require Admin
		if (!$this->user->isAdmin())
			show_error('Access denied.', 401);

		// Helpers
		$this->load->library('form_validation');

		// Models
		$this->load->model('foursquare_check');
		$this->load->model('beta_key');
		$this->load->model('package');

	}

	/**
	 * Index
	 */
	public function index() {

		// See if upgrade is needed
		$this->load->config('migration');
		$query = $this->db->query('SELECT `version` FROM `migrations`');
		$migration = $query->row();
		$data['prompt_update'] = ((int) $migration->version < (int) $this->config->item('migration_version')) ? $migration->version : false;

		// Check to see if monitoring is running
		$query = $this->db->query('SELECT MAX(insert_ts) AS `last_insert`, NOW() AS `right_now` FROM foursquare_check_log_live;');
		$last_check = $query->row();
		$data['prompt_cron'] = ($last_check->last_insert && (strtotime($last_check->right_now) - strtotime($last_check->last_insert)) > 60*60) ? $last_check->last_insert : false;

		// Get list of users
		$data['active_accounts'] = $this->user->adminGetAllUsers(true, 10);
		$data['inactive_accounts'] = $this->user->adminGetAllUsers(false, 10);
		
		// Get most recent checks
		$data['last_checks'] = $this->foursquare_check->adminGetAllChecks(10);

		// Get beta keys
		$data['beta_keys'] = $this->beta_key->adminGetAllBetaKeys(25);

		$data['sidebar_content'] = $this->load->view('admin/_sidebar', $data, true);
		$data['page_title'] = 'Administrator Dashboard';
		$this->load->view('admin/dashboard', $data);
		
		return;
	}

	/**
	 * Users
	 */
	public function users() {

		if ($this->uri->segment(3) == 'inactive'):
			$data['page_title'] = 'Inactive Users';
			$data['accounts'] = $this->user->adminGetAllUsers(false);
		else:
			$data['page_title'] = 'Active Users';
			$data['accounts'] = $this->user->adminGetAllUsers(true);
		endif;

		$this->load->view('admin/users', $data);
	}
	
	/**
	 * User
	 */
	public function user() {
		$user_id = $this->uri->segment(3);
		
		// If invalid user ID, show error
		if (!is_numeric($user_id))
			show_error('Invalid user ID.');
		
		// Package list
		$packages = $this->package->getPackages();
		foreach ($packages as $row):
			$package_list[$row->id] = sprintf('%s (%s)', $row->name, number_format($row->check_limit));
		endforeach;
		$data['packages'] = $package_list;
		
		$data['user'] = $this->user->getUserById($user_id);
		$data['checks'] = $this->foursquare_check->getChecksByUserId($user_id);
		$data['page_title'] = sprintf('User: %s', $data['user']->username);
		$this->load->view('admin/user', $data);	
	}
	
	function user_change_package() {
		$user_id = $this->input->post('user_id');
		$package_id = $this->input->post('package_id');
		$this->user->adminchangeUserPackage($user_id, $package_id);
		$this->session->set_flashdata('Package updated!');
		redirect('admin/user/'.$user_id);
	}
	
	/**
	 * Deactivate User
	 */
	public function deactivate_user() {
		$user_id = $this->uri->segment(3);
		
		// Prevent deactivating current user
		$current_user = unserialize($this->session->userdata('user'));
		if ($current_user->id == $user_id)
			show_error('Deactivating your own account would be a mistake.', 403);
		
		$this->user->adminUpdateUserStatus($user_id, 0);
		$this->session->set_flashdata('message', 'User deactivated!');
		redirect('admin');
	}
	
	/**
	 * Activate User
	 */
	public function activate_user() {
		$user_id = $this->uri->segment(3);
		$this->user->adminUpdateUserStatus($user_id, 1);
		$this->session->set_flashdata('message', 'User activated!');
		redirect('admin');
	}

	/**
	 * Assume User
	 */
	public function assume_user() {
		$user_id = $this->uri->segment(3);
		$this->user->adminAssumeUser($user_id);
		$this->session->set_flashdata('message', 'You have been logged out of your account and in as this user.');
		redirect('/');
	}

	public function beta_keys() {
		$data['beta_keys'] = $this->beta_key->adminGetAllBetaKeys(25);
		$data['page_title'] = 'Beta Keys';
	
		$this->load->view('admin/beta_keys', $data);
	}

	/**
	 * Beta Key New
	 */
	public function beta_key_new() {

		$this->form_validation->set_error_delimiters('<div class="alert alert-error">', '<a class="close" href="#">&times;</a></div>');
		$this->form_validation->set_rules('name', 'Name', 'required');
		$this->form_validation->set_rules('email', 'Email', 'required|valid_email');
		
			// If submission valid, run 
			if ($this->form_validation->run() != FALSE):
				$key = $this->beta_key->adminCreateBetaKeyFromPost();
				$this->sendBetaKeyEmail($key);
				$this->session->set_flashdata('message', 'Beta key created! User has been sent their login information.');
				redirect('admin');
			endif;
		
		$data['name'] = $this->input->post('name');
		$data['email'] = $this->input->post('email');

		$data['page_title'] = 'Create Beta Key';
		$this->load->view('admin/form_beta_key', $data);
	}
	
	/**
	 * Beta Key Revoke
	 */
	public function beta_key_revoke() {
		$beta_key = $this->uri->segment(3);
		
		// Must have a valid beta key
		if (!$beta_key || strlen($beta_key) < 10)
			redirect('admin');
		
		$status = $this->beta_key->adminRevokeBetaKey($beta_key);
		
		if ($status):
			$this->session->set_flashdata('message', 'Revoked beta key.');
		else:
			$this->session->set_flashdata('message', 'Could not revoke beta key.');
		endif;
		
		redirect('/admin');
		
	}
	
	public function system_upgrade() {

		$this->load->library('migration');

		if ( ! $this->migration->current()):
			show_error($this->migration->error_string());
		endif;

		$this->session->set_flashdata('message', 'Database upgrade complete!');
		redirect('/admin/');
		
	}
	
	public function packages() {
		$data['packages'] = $this->package->getPackagesWithUserCount();
		$data['page_title'] = 'Manage Packages';
		
		$this->load->view('admin/packages', $data);
	}
	
	public function package() {
		
		$package_id = $this->uri->segment(3);
		
		// Handle update request
		if ($this->input->post('id') > 0):
			$this->package->updatePackageFromPost();
			$this->session->set_flashdata('message', 'Package updated!');
			redirect('admin/packages');

		// handle add request
		elseif ($this->input->post('name') != ''):
			$this->package->addPackageFromPost();
			$this->session->set_flashdata('message', 'Package added!');
			redirect('admin/packages');
		endif;
	
		// Display new package Form
		if ($package_id == 'new'):
					
			$data['package'] = new Package();
			$data['page_title'] = 'New Package';
		
		// Display packaged edit form
		else:
			
			$package = $this->package->getPackageById($package_id);
			if (!$package)
				show_404();
			
			$data['package'] = $package;
			$data['page_title'] = $package->name;
			
		endif;
		
		$this->load->view('admin/form_package', $data);
		
	}
	
	public function package_delete() {
		$package_id = $this->uri->segment(3);
		
		$package = $this->package->getPackageById($package_id);
		if (!$package)
			show_404();
		
		if ($this->input->post('migrate_to') > 0):
			$this->package->deletePAckage($package->id, $this->input->post('migrate_to'));
			$this->session->set_flashdata('message', 'Package deleted!');
			redirect('admin/packages');
		endif;
		
		$data['packages'] = $this->package->getPackages();		
		$data['package'] = $package;

		$data['page_title'] = 'Delete ' . $package->name;
		
		$this->load->view('admin/form_package_delete', $data);
		
		
	}
	
	
	/* Private Methods Below */
	/**
	 * Send Beta Key Email
	 *
	 * @param object $key 
	 */
	private function sendBetaKeyEmail($key) {
		
		if (!is_object($key))
			show_error('Beta key was not saved.', 500);
		
		$data['key'] = $key;
		$data['application_name'] = $this->config->item('application_name');
		
		// Create message
		$this->email->from($this->config->item('application_email'), $this->config->item('application_name'));
		$this->email->to($key->email);
		$this->email->subject('Beta invitation for ' . $this->config->item('application_name') . '!');
		$this->email->message($this->load->view('emails/new_beta_key_message', $data, true));

		return $this->email->send();
		
	}
	
}
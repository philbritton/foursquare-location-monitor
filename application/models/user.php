<?php

class User extends CI_Model {

	public $username;
	public $first_name;
	public $last_name;
	public $email;
	
	public function instance() {
		return $this;
	}

	public function login() {
		$this->db->select(array('id', 'username', 'display_name', 'first_name', 'last_name', 'email', 'level', 'package_id', 'insert_ts', 'status'));
		$this->db->where('username', $this->input->post('username'));
		$this->db->where('password', md5($this->input->post('password') . $this->config->item('encryption_key')));
		$this->db->where('status', 1);
		$query = $this->db->get('users');
		
		// If valid, set 'user' session variable. If not, clear it.
		if($query->num_rows == 1):
			$this->session->set_userdata('user', serialize(array_shift($query->result())));
			return true;
		else:
			$this->session->unset_userdata('user');
			return false;
		endif;
	}
	
	public function logout() {
		$this->session->unset_userdata('user');
		return true;
	}
	
	public function isLoggedIn() {
		$user = unserialize($this->session->userdata('user'));
		if (!$user)
			return false;
		else
			return true;
	}
	
	public function getUserById($id = 0) {
		$this->db->select(array('id', 'username', 'display_name', 'first_name', 'last_name', 'email', 'level', 'package_id', 'insert_ts', 'status'));
		$query = $this->db->get_where('users', array('id' => $id), 1);
		return $query->row();
	}
	
	public function getUserByUsername($username = null) {
		$this->db->select(array('id', 'username', 'display_name', 'first_name', 'last_name', 'email', 'level', 'package_id', 'insert_ts', 'status'));
		$query = $this->db->get_where('users', array('username' => $username), 1);
		return $query->row();
	}
	
	public function updateUserFromPost($id) {
		$update_user_data = array(
			'display_name' => $this->input->post('first_name') . ' ' . $this->input->post('last_name'),
			'first_name' => $this->input->post('first_name'),
			'last_name' => $this->input->post('last_name'),
			'email' => $this->input->post('email')
		);
		$this->db->where('id', $id);
		$update = $this->db->update('users', $update_user_data);
		
		// Update session information
		if ($update):
			$user = $this->getUserById($id);
			$this->session->unset_userdata('user');
			$this->session->set_userdata('user', serialize($user));
		endif;
		
		return $update;
	}
	
	public function updatePasswordFromPost($id) {
		$update_user_data = array(
			'password' => md5($this->input->post('password') . $this->config->item('encryption_key'))
		);
		$this->db->where('id', $id);
		$update = $this->db->update('users', $update_user_data);
		
		return $update;
	}
	
	
	public function createNewUserFromPost() {
		$insert_user_data = array(
			'username' => $this->input->post('username'),
			'password' => md5($this->input->post('password') . $this->config->item('encryption_key')),
			'display_name' => $this->input->post('first_name') . ' ' . $this->input->post('last_name'),
			'first_name' => $this->input->post('first_name'),
			'last_name' => $this->input->post('last_name'),
			'email' => $this->input->post('email'),
			'package_id' => 1,
			'level' => 'user'
		);
		
		$insert = $this->db->insert('users', $insert_user_data);
		
		// Authenticate the user
		if ($insert):
			$user = $this->getUserById($this->db->insert_id());
			$this->session->unset_userdata('user');
			$this->session->set_userdata('user', serialize($user));
		else:
			show_eror('User registration failed.', 500);
		endif;
		
		return $user;
	}
	
	public function packageList() {
		$this->db->order_by('check_limit', 'ASC');
		$query = $this->db->get('packages');
		
		return $query->result();
		
	}
	
	public function userExists($username = null) {
		$this->db->where('username', $username);
		$query = $this->db->get('users');
		$result = $query->row();
		
		if (isset($result->username) && $result->username == $username):
			return true;
		endif;
		
		return false;
		
	}

	public function getPasswordResetKey($username = '') {
		
	}

	public function usePasswordResetKey($key = '') {

	}

	/* ***** Site Administrator Methods ***** */
	
	/**
	 * Admin Check
	 */
	public function isAdmin() {
		$user = unserialize($this->session->userdata('user'));
		if ($user->level != 'admin')
			return false;
		else
			return true;
	}
	
	/**
	 * Admin Get All USers
	 *
	 * @param string $status 
	 * @param string $limit 
	 * @param string $offset 
	 */
	public function adminGetAllUsers($status = true, $limit = 50, $offset = 0) {
		if (!$this->isAdmin())
			show_error('Access denied.');
		
		$this->db->select(array('users.id', 'username', 'display_name', 'first_name', 'last_name', 'email', 'level', 'package_id AS package', 'count(foursquare_checks.id) AS check_count', 'users.insert_ts'));
		$this->db->join('foursquare_checks', 'foursquare_checks.user_id = users.id', 'left');
		$this->db->where('status', ($status == true) ? 1 : 0);
		$this->db->group_by('users.id');
		$this->db->order_by('insert_ts', 'DESC');
		$this->db->limit($limit, $offset);
		$query = $this->db->get('users');
		
		return $query->result();
	}

	public function adminUpdateUserStatus($user_id, $status = 0) {
		if (!$this->isAdmin())
			show_error('Access denied.');
		
		$this->db->where('id', $user_id);
		$status_data = array(
			'status' => ($status == 1) ? 1 : 0
		);
		$status = $this->db->update('users', $status_data);
		
		return $status;
	}
	
	public function adminchangeUserPackage($user_id, $package_id) {
		$user = $this->getUserById($user_id);
		if (!$user)
			show_error('Could not locate user record', 404);

		// Process update
		$user_update_data = array(
			'package_id' => $package_id
		);
		$this->db->where('id', $user->id);
		$this->db->update('users', $user_update_data);
	}
	
	public function adminAssumeUser($user_id) {
		if (!$this->isAdmin())
			show_error('Access denied.');
		
		// Load user record
		$user = $this->getUserById($user_id);
		if (!$user)
			show_error('Could not locate user record', 404);
		$this->session->set_userdata('admin_user_tmp', $this->session->userdata('user'));
		$this->session->unset_userdata('user');
		$this->session->set_userdata('user', serialize($user));
		
		return $user;
	}
	
	public function adminResumeAdmin() {
		
		// Pull Admin data out of temporary session store
		$user = unserialize($this->session->userdata('admin_user_tmp'));
		if (!$user)
			show_error('Could not resume admin session.', 500);

		$this->session->unset_userdata('admin_user_tmp');
		$this->session->unset_userdata('user');
		$this->session->set_userdata('user', serialize($user));
	}


}
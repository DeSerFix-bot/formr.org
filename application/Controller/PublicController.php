<?php

class PublicController extends Controller {
	public function __construct(Site &$site) {
		parent::__construct($site);
	}

	public function indexAction() {
		$this->renderView('public/home');
	}

	public function documentationAction() {
		$this->renderView('public/documentation');
	}

	public function studiesAction() {
		$this->renderView('public/studies', array('runs' => $this->user->getAvailableRuns()));
	}

	public function teamAction() {
		$this->renderView('public/team');
	}

	public function editUserAction() {
		/**
		* @todo: 
		* - allow changing email address
		* - email address verification
		* - my access code has been compromised, reset? possible problems with external data, maybe they should get their own tokens...
		*/

		if(!$this->user->loggedIn()) {
			alert('You need to be logged in to go here.', 'alert-info');
			redirect_to("index");
		}

		if(!empty($_POST)) {
			$redirect = false;
			if($this->request->str('new_password')) {
				if($this->user->changePassword($this->request->str('password'), $this->request->str('new_password'))) {
					alert('<strong>Success!</strong> Your password was changed!','alert-success');
					$redirect = true;
				} else {
					alert(implode($this->user->errors), 'alert-danger');
				}
			}

			if($this->request->str('new_email')) {
				if($this->user->changeEmail($this->request->str('password'), $this->request->str('new_email'))) {
					alert('<strong>Success!</strong> Your email address was changed!', 'alert-success');
					$redirect = true;
				} else {
					alert(implode($this->user->errors),'alert-danger');
				}
			}

			if($redirect) {
				redirect_to('index');
			}
		}
		$this->renderView('public/edit_user');
	}

	public function loginAction() {
		if($this->user->loggedIn()) {
			redirect_to("index");
		}

		if($this->request->str('email') && $this->request->str('password')) {
			if($this->user->login($this->request->str('email'), $this->request->str('password'))) {
				alert('<strong>Success!</strong> You were logged in!', 'alert-success');
				Session::set('user', serialize($this->user));
				$redirect = $this->user->isAdmin() ? redirect_to('admin') : redirect_to();
			} else {
				alert(implode($this->user->errors), 'alert-danger');
			}
		}
		$this->renderView('public/login');
	}

	public function logoutAction() {
		$user = $this->user;
		if($user->loggedIn()) {
			$user->logout();
			$user = new User($this->fdb, null, null);
			alert('<strong>Logged out:</strong> You have been logged out.','alert-info');
		}
		redirect_to("index");
	}

	public function registerAction() {
		$user = $this->user;
		$site = $this->site;

		//fixme: cookie problems lead to fatal error with missing user code
		if($user->loggedIn()) {
			alert('You were already logged in. Please logout before you can register.', 'alert-info');
			redirect_to("index");
		}

		if($site->request->str('email')) {
			if($user->register($site->request->str('email'), $site->request->str('password'), $site->request->str('referrer_code'))) {
				alert('<strong>Success!</strong> You were registered and logged in!','alert-success');
				redirect_to('index');
			} else {
				alert(implode($user->errors),'alert-danger');
			}
		}
		$this->renderView('public/register');
	}

	public function verifyEmailAction() {
		$user = $this->user;

		if((!isset($_GET['verification_token']) OR !isset($_GET['email']) ) AND !isset($_POST['email'])):
			alert("You need to follow the link you received in your verification mail.");
			redirect_to("public/login");
		else:
			$user->verify_email($_GET['email'], $_GET['verification_token']);
			redirect_to("public/login");
		endif;
	}

	public function forgotPasswordAction() {
		if($this->user->loggedIn()) {
			redirect_to("index");
		}

		if($this->request->str('email')) {
			$this->user->forgot_password($this->request->str('email'));
		}
		$this->renderView('public/forgot_password');
	}

	public function resetPasswordAction() {
		$user = $this->user;
		if($user->loggedIn()) {
			redirect_to("index");
		}

		if((!isset($_GET['reset_token']) OR !isset($_GET['email']) ) AND !isset($_POST['email'])):
			alert("You need to follow the link you received in your password reset mail");
			redirect_to("public/forgot_password");
		endif;

		if(!empty($_POST) AND isset($_POST['email'])  AND isset($_POST['new_password'])  AND isset($_POST['reset_token'])) {
			$user->reset_password($_POST['email'], $_POST['reset_token'], $_POST['new_password']);
		}

		$this->renderView('public/reset_password', array(
			'reset_data_email' => isset($_GET['email']) ? $_GET['email'] : '',
			'reset_data_token' => isset($_GET['reset_token']) ? $_GET['reset_token'] : '',
		));
	}

	public function notFoundAction() {
		$this->renderView('public/not_found');
	}

	public function runAction($run_name = '') {
		// hack for run name
		$_GET['run_name'] = $run_name;
		$this->site->request->run_name = $run_name;

		$this->user = $this->site->loginUser($this->user);
		$run = new Run($this->fdb, $this->request->str('run_name'));
		$run_vars = $run->exec($this->user);
		if ($run_vars) {
			Template::load('public/run', $run_vars);
		}
	}

	public function settingsAction($run_name = '') {
		$run = new Run($this->fdb, $run_name);
		if (!$run->valid) {
			not_found();
		}
		// People who have no session in the run need not set anything
		$session = new RunSession($this->fdb, $run->id, 'cron', $this->user->user_code);
		if (!$session->id) {
			alert('A session for you could not be found in the study', 'alert-danger');
			redirect_to('index');
		}

		$settings = array('no_email' => 1);
		if (Request::isHTTPPostRequest()) {
			$update = array();
			$settings = array(
				'no_email' => $this->request->getParam('no_email'),
				'delete_cookie' => (int)$this->request->getParam('delete_cookie'),
			);

			if ($settings['no_email'] == 1) {
				$update['no_email'] = null;
			} elseif ($settings['no_email'] == 0) {
				$update['no_email'] = 0;
			} elseif ($ts = strtotime($settings['no_email'])) {
				$update['no_email'] = $ts;
			}

			if ($update) {
				$this->fdb->update('survey_run_sessions', $update, array('id' => $session->id));
			}
			$this->fdb->insert_update('survey_run_settings', array(
				'run_session_id' => $session->id,
				'settings' => json_encode($settings),
			));
			if ($settings['delete_cookie'])  {
				alert('Your session was ended as requested! You need to login again', 'alert-warning');
				Session::destroy();
			}
			alert('Settings saved successfully for survey "'.$run->name.'"', 'alert-success');
		}

		$row = $this->fdb->findRow('survey_run_settings', array('run_session_id' => $session->id));
		if ($row) {
			$settings = (array)json_decode($row['settings']);
		}
		Template::load('public/settings', array(
			'run' => $run,
			'settings' => $settings,
		));
	}
}


<?php
require_once '../define_root.php';
require_once INCLUDE_ROOT.'admin/admin_header.php';

if( !empty($_POST) ) {
	$study->editSubstitutions($_POST);
	redirect_to(WEBROOT . "admin/{$study->name}/edit_substitutions");
}

$has_access = false;
if($user-> AND $currentUser->ownsStudy($study->id)):
	$has_access = true;
elseif($study->registration_required AND userIsLoggedIn()):
	$has_access = true;
elseif($study->settings['closed_user_pool']):
	$has_access = false;
endif;


if($has_access):
	$session = new Session(null,$study);
	$session->create();
	
	$_SESSION['session'] = $session->session;
	
	$goto = "{$study->name}/survey/";
	if(isset($run))
		$goto .= "&run_id=".$run->id;
	redirect_to($goto);
else:
	alert("<strong>Sorry.</strong> You don't have access");
	redirect_to("index.php");	
endif;
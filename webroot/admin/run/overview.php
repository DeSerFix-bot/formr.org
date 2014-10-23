<?php
require_once '../../../define_root.php';
require_once INCLUDE_ROOT.'View/admin_header.php';

$users = $run->getNumberOfSessionsInRun();
require_once INCLUDE_ROOT.'View/header.php';
require_once INCLUDE_ROOT.'View/acp_nav.php';
$overview_script = $run->getOverviewScript();
$user_overview = $run->getUserCounts();

session_over($site, $user);
?>
<div class="row">
	<div class="col-lg-12">

		<h1><i class="fa fa-eye"></i> Run overview <small><?=$overview_script->title?></small></h2>
		<h2><?=$user_overview['users_finished']?>  finished users, <?=$user_overview['users_active']?> active users, <?=$user_overview['users_waiting']?> <abbr title="inactive for at least a week">waiting</abbr> users</h2>
		<?php
		$report =  $overview_script->parseBodySpecial();
		echo $report;
		?>
		
	</div>
</div>

<?php
require_once INCLUDE_ROOT.'View/footer.php';

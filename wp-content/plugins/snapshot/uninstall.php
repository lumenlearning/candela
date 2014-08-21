<?php
if (WP_UNINSTALL_PLUGIN === "snapshot/snapshot.php") {

	if (!isset($wpmudev_snapshot))
	{
		include dirname(__FILE__) . "/snapshot.php";
		$wpmudev_snapshot = new WPMUDEVSnapshot();
	}
	$wpmudev_snapshot->uninstall_snapshot();
}
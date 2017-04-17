<?php
namespace ExternalModules;

require_once '../../classes/ExternalModules.php';

header('Content-type: application/json');
echo json_encode(array(
	'status' => 'success',
	'settings' => ExternalModules::getProjectSettingsAsArray($_POST['moduleDirectoryPrefix'], @$_POST['pid'])
));
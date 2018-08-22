<?php
namespace ExternalModules;
require_once dirname(dirname(dirname(__FILE__))) . '/classes/ExternalModules.php';

// Only administrators with specific projects or SuperUser can enable/disable modules

// Check that they have design rights
$userRights = \REDCap::getUserRights(USERID);
$design = $userRights[USERID]['design'];
$isUserDesignActivatable = (ExternalModules::getSystemSetting($_POST['prefix'], ExternalModules::KEY_USER_DESIGN_ACTIVATABLE) == true);

$canEnable = ( $design == 1 && $isUserDesignActivatable ) || SUPER_USER;

// Only superuser or special users
if (!$canEnable) exit;


$return_data['message'] = "success";

if (isset($_GET['pid'])) {
    ExternalModules::enableForProject($_POST['prefix'], $_POST['version'], $_GET['pid']);
}
else {
    $config = ExternalModules::getConfig($_POST['prefix'], $_POST['version']);
    $return_data['error_message'] = "";
    if(empty($config['description'])){
        $return_data['error_message'] .= "The module named  ".$config['name']." is missing a description. Fill in the config.json to ENABLE it.<br/>";
    }

    if(empty($config['authors'])){
        $return_data['error_message'] .= "The module named  ".$config['name']." is missing its authors. Fill in the config.json to ENABLE it.<br/>";
    }else{
        $missingEmail = true;
        foreach ($config['authors'] as $author){
            if(!empty( $author['email'])){
                $missingEmail = false;
                break;
            }
        }

        if($missingEmail){
            $return_data['error_message'] .= "The module named  ".$config['name']." needs at least one email inside the authors portion of the configuration.  Please fill an email for at least one author in the config.json.<br/>";
        }

        foreach ($config['authors'] as $author) {
            if (empty($author['institution'])) {
                $return_data['error_message'] .= "The module named  " . $config['name'] . " is missing an institution for at least one of it's authors the config.json file.<br/>";
                break;
            }
        }
    }

    if(empty($return_data['error_message'])) {
		$exception = ExternalModules::enableAndCatchExceptions($_POST['prefix'], $_POST['version']);
		if($exception){
			$return_data['error_message'] = 'Exception while enabling module: ' . $exception->getMessage();
			$return_data['stack_trace'] = $exception->getTraceAsString();
		}
    }
}

// Log this event
$logText = "Enable external module \"{$_POST['prefix']}_{$_POST['version']}\" for " . (!empty($_GET['pid']) ? "project" : "system");
\REDCap::logEvent($logText, "", "", null, null, $_GET['pid']);

echo json_encode($return_data);

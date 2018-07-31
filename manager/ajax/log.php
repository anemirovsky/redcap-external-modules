<?php
namespace ExternalModules;

use Exception;

$data = json_decode(file_get_contents('php://input'), true);

$noAuth = isset($data['noAuth']);
if($noAuth){
	define('NOAUTH', true);
}

require_once dirname(dirname(dirname(__FILE__))) . '/classes/ExternalModules.php';

$module = ExternalModules::getModuleInstance($_GET['prefix']);

$recordId = @$data['recordId'];
if($noAuth && !empty($recordId) && !ExternalModules::isTemporaryRecordId($recordId)){
	throw new Exception("Record ids (that aren't temporary) are not allowed on NOAUTH requests because they can easily be spoofed.");
}

$surveyHash = @$data['surveyHash'];
$responseHash = @$data['responseHash'];
if(!empty($responseHash)){
	// We're on a survey submission that already has a record id.
	// We shouldn't pass the record id directly because it would be easy to spoof.
	// Instead, we determine the record id from the response hash.

	require_once APP_PATH_DOCROOT . "/Surveys/survey_functions.php";

	// This line exists simply to prevent PHPStorm from complaining.
	$participant_id = null;

	// This method is called to set the $participant_id global;
	\Survey::setSurveyVals($surveyHash);

	$responseId = \decryptResponseHash($responseHash, $participant_id);

	$result = $module->query("select record from redcap_surveys_response where response_id = $responseId");
	$row = db_fetch_assoc($result);
	$recordId = $row['record'];
}


if(!empty($recordId)){
	$module->setRecordId($recordId);
}

$module->log($data['message'], $data['parameters']);

echo 'success';

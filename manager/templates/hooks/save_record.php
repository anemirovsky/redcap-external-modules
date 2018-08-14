<?php

use ExternalModules\ExternalModules;

$recordId = db_escape($arguments[1]);

$temporaryRecordId = db_escape(@$_POST[ExternalModules::EXTERNAL_MODULES_TEMPORARY_RECORD_ID]);
if(ExternalModules::isTemporaryRecordId($temporaryRecordId)){
	$sql = "update redcap_external_modules_log set record = '$recordId' where record = '$temporaryRecordId'";
	ExternalModules::query($sql);
}
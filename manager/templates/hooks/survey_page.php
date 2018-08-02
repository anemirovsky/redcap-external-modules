<?php
use ExternalModules\ExternalModules;

$recordId = db_escape($arguments[1]);

ExternalModules::sharedSurveyAndDataEntryActions($recordId);
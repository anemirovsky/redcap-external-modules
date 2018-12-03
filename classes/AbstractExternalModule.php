<?php
namespace ExternalModules;

AbstractExternalModule::init();

if (class_exists('ExternalModules\AbstractExternalModule')) {
	return;
}

use PHPSQLParser\PHPSQLParser;
use PHPSQLParser\PHPSQLCreator;

use Exception;
use UIState;

class AbstractExternalModule
{
	const UI_STATE_OBJECT_PREFIX = 'external-modules.';
	const EXTERNAL_MODULE_ID_STANDARD_WHERE_CLAUSE_PREFIX = "redcap_external_modules_log.external_module_id = (SELECT external_module_id FROM redcap_external_modules WHERE directory_prefix";

	// check references to this to make sure moving vars was safe
	// rename the following var?
	public static $RESERVED_LOG_PARAMETER_NAMES = ['log_id', 'external_module_id', 'ui_id'];
	private static $RESERVED_LOG_PARAMETER_NAMES_FLIPPED;
	public static $OVERRIDABLE_LOG_PARAMETERS_ON_MAIN_TABLE = ['timestamp', 'username', 'ip', 'project_id', 'record', 'message'];
	private static $LOG_PARAMETERS_ON_MAIN_TABLE;

	public $PREFIX;
	public $VERSION;

	private $recordId;
	private $userBasedSettingPermissions = true;

	# constructor
	function __construct()
	{
		// This if statement is only necessary for the BaseTestExternalModule.
		if(!isset($this->PREFIX)){
			list($prefix, $version) = ExternalModules::getParseModuleDirectoryPrefixAndVersion($this->getModuleDirectoryName());

			$this->PREFIX = $prefix;
			$this->VERSION = $version;
		}

		// Disallow illegal configuration options at module instantiation (and enable) time.
		self::checkSettings();
	}

	# checks the config.json settings for validity of syntax
	protected function checkSettings()
	{
		$config = $this->getConfig();
		$systemSettings = $config['system-settings'];
		$projectSettings = $config['project-settings'];

		$settingKeys = [];
		$checkSettings = function($settings) use (&$settingKeys, &$checkSettings){
			foreach($settings as $details) {
				$key = $details['key'];
				self::checkSettingKey($key);

				if (isset($settingKeys[$key])) {
					throw new Exception("The \"" . $this->PREFIX . "\" module defines the \"$key\" setting multiple times!");
				} else {
					$settingKeys[$key] = true;
				}

				if($details['type'] === 'sub_settings'){
					$checkSettings($details['sub_settings']);
				}
			}
		};

		$checkSettings($systemSettings);
		$checkSettings($projectSettings);
	}

	# checks a config.json setting key $key for validity
	# throws an exception if invalid
	private function checkSettingKey($key)
	{
		if(!self::isSettingKeyValid($key)){
			throw new Exception("The " . $this->PREFIX . " module has a setting named \"$key\" that contains invalid characters.  Only lowercase characters, numbers, and dashes are allowed.");
		}
	}

	# validity check for a setting key $key
	# returns boolean
	protected function isSettingKeyValid($key)
	{
		// Only allow lowercase characters, numbers, dashes, and underscores to ensure consistency between modules (and so we don't have to worry about escaping).
		return !preg_match("/[^a-z0-9-_]/", $key);
	}

	function selectData($some, $params=array())
	{
		self::checkPermissions(__FUNCTION__);

		return 'this could be some data from the database';
	}

	function updateData($some, $params=array())
	{
		self::checkPermissions(__FUNCTION__);

		throw new Exception('Not yet implemented!');
	}

	function deleteData($some, $params=array())
	{
		self::checkPermissions(__FUNCTION__);

		throw new Exception('Not yet implemented!');
	}

	function updateUserPermissions($some, $params=array())
	{
		self::checkPermissions(__FUNCTION__);

		throw new Exception('Not yet implemented!');
	}

	# check whether the current External Module has permission to call the requested method $methodName
	private function checkPermissions($methodName)
	{
		# Convert from camel to snake case.
		# Taken from the second solution here: http://stackoverflow.com/questions/1993721/how-to-convert-camelcase-to-camel-case
		$permissionName = ltrim(strtolower(preg_replace('/[A-Z]/', '_$0', $methodName)), '_');

		if (!$this->hasPermission($permissionName)) {
			throw new Exception("This module must request the \"$permissionName\" permission in order to call the $methodName() method.");
		}
	}

	# checks whether the current External Module has permission for $permissionName
	function hasPermission($permissionName)
	{
		return ExternalModules::hasPermission($this->PREFIX, $this->VERSION, $permissionName);
	}

	# get the config for the current External Module
	# consists of config.json and filled-in values
	function getConfig()
	{
		return ExternalModules::getConfig($this->PREFIX, $this->VERSION);
	}

	# get the directory name of the current external module
	function getModuleDirectoryName()
	{
		$reflector = new \ReflectionClass(get_class($this));
		return basename(dirname($reflector->getFileName()));
	}

	protected function getSettingKeyPrefix(){
		return '';
	}

	private function prefixSettingKey($key){
		return $this->getSettingKeyPrefix() . $key;
	}

	# a SYSTEM setting is a value to be used on all projects. It can be overridden by a particular project
	# a PROJECT setting is a value set by each project. It may be a value that overrides a system setting
	#      or it may be a value set for that project alone with no suggested System-level value.
	#      the project_id corresponds to the value in REDCap
	#      if a project_id (pid) is null, then it becomes a system value

	# Set the setting specified by the key to the specified value
	# systemwide (shared by all projects).
	function setSystemSetting($key, $value)
	{
		$key = $this->prefixSettingKey($key);
		ExternalModules::setSystemSetting($this->PREFIX, $key, $value);
	}

	# Get the value stored systemwide for the specified key.
	function getSystemSetting($key)
	{
		$key = $this->prefixSettingKey($key);
		return ExternalModules::getSystemSetting($this->PREFIX, $key);
	}

	/**
	 * Gets all system settings as an array. Does not include project settings. Each setting
	 * is formatted as: [ 'yourkey' => ['system_value' => 'foo', 'value' => 'bar'] ]
	 *
	 * @return array
	 */
	function getSystemSettings()
	{
	    return ExternalModules::getSystemSettingsAsArray($this->PREFIX);
	}

	# Remove the value stored systemwide for the specified key.
	function removeSystemSetting($key)
	{
		$key = $this->prefixSettingKey($key);
		ExternalModules::removeSystemSetting($this->PREFIX, $key);
	}

	# Set the setting specified by the key to the specified value for
	# this project (override the system setting).  In most cases
	# the project id can be detected automatically, but it can
	# optionaly be specified as the third parameter instead.
	function setProjectSetting($key, $value, $pid = null)
	{
		$pid = self::requireProjectId($pid);
		$key = $this->prefixSettingKey($key);
		ExternalModules::setProjectSetting($this->PREFIX, $pid, $key, $value);
	}

	# Returns the value stored for the specified key for the current
	# project if it exists.  If this setting key is not set (overriden)
	# for the current project, the system value for this key is
	# returned.  In most cases the project id can be detected
	# automatically, but it can optionally be specified as the third
	# parameter instead.
	function getProjectSetting($key, $pid = null)
	{
		$pid = self::requireProjectId($pid);
		$key = $this->prefixSettingKey($key);
		return ExternalModules::getProjectSetting($this->PREFIX, $pid, $key);
	}

	/**
	 * Gets all project and system settings as an array.  Useful for cases when you may
	 * be creating a custom config page for the external module in a project. Each setting
	 * is formatted as: [ 'yourkey' => ['system_value' => 'foo', 'value' => 'bar'] ]
	 *
	 * @param int|null $pid
	 * @return array containing status and settings
	 */
	function getProjectSettings($pid = null)
	{
		$pid = self::requireProjectId($pid);
		return ExternalModules::getProjectSettingsAsArray($this->PREFIX, $pid);
	}

	/**
	 * Saves all project settings (to be used with getProjectSettings).  Useful
	 * for cases when you may create a custom config page or need to overwrite all
	 * project settings for an external module.
	 * @param array $settings Array of all project-specific settings
	 * @param int|null $pid
	 */
	function setProjectSettings($settings, $pid = null)
	{
		$pid = self::requireProjectId($pid);
		ExternalModules::saveSettings($this->PREFIX, $pid, json_encode($settings));
	}

	# Remove the value stored for this project and the specified key.
	# In most cases the project id can be detected automatically, but
	# it can optionaly be specified as the third parameter instead.
	function removeProjectSetting($key, $pid = null)
	{
		$pid = self::requireProjectId($pid);
		$key = $this->prefixSettingKey($key);
		ExternalModules::removeProjectSetting($this->PREFIX, $pid, $key);
	}

	function getSubSettings($key)
	{
		$keys = [];
		$config = $this->getSettingConfig($key);
		foreach($config['sub_settings'] as $subSetting){
			$keys[] = $this->prefixSettingKey($subSetting['key']);
		}

		$rawSettings = ExternalModules::getProjectSettingsAsArray($this->PREFIX, self::requireProjectId());

		$subSettings = [];
		foreach($keys as $key){
			$values = $rawSettings[$key]['value'];
			for($i=0; $i<count($values); $i++){
				$value = $values[$i];
				$subSettings[$i][$key] = $value;
			}
		}

		return $subSettings;
	}

	function getSettingConfig($key)
	{
		$config = $this->getConfig();
		foreach(['project-settings', 'system-settings'] as $type) {
			foreach ($config[$type] as $setting) {
				if ($key == $setting['key']) {
					return $setting;
				}
			}
		}

		return null;
	}

	function getUrl($path, $noAuth = false, $useApiEndpoint = false)
	{
		$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

		// Include 'md' files as well to render README.md documentation.
		$isPhpPath = in_array($extension, ['php', 'md']) || (preg_match("/\.php\?/", $path));
		if ($isPhpPath || $useApiEndpoint) {
			// GET parameters after php file -OR- php extension
			$url = ExternalModules::getUrl($this->PREFIX, $path, $useApiEndpoint);
			if ($isPhpPath) {
				$pid = self::detectProjectId();
				if(!empty($pid) && !preg_match("/[\&\?]pid=/", $url)){
					$url .= '&pid='.$pid;
				}
				if($noAuth && !preg_match("/NOAUTH/", $url)) {
					$url .= '&NOAUTH';
				}
			}
		} else {
			// This must be a resource, like an image or css/js file.
			// Go ahead and return the version specific url.
			$pathPrefix = ExternalModules::getModuleDirectoryPath($this->PREFIX, $this->VERSION);
			$url =  ExternalModules::getModuleDirectoryUrl($this->PREFIX, $this->VERSION) . $path . '?' . filemtime($pathPrefix . '/' . $path);
		}
		return $url;
	}

	public function getModulePath()
	{
		return ExternalModules::getModuleDirectoryPath($this->PREFIX, $this->VERSION) . DS;
	}

	public function getModuleName()
	{
		return $this->getConfig()['name'];
	}

	public function resetSurveyAndGetCodes($projectId,$recordId,$surveyFormName = "", $eventId = "") {
		list($surveyId,$surveyFormName) = $this->getSurveyId($projectId,$surveyFormName);

		## Validate surveyId and surveyFormName were found
		if($surveyId == "" || $surveyFormName == "") return false;

		## Find valid event ID for form if it wasn't passed in
		if($eventId == "") {
			$eventId = $this->getValidFormEventId($surveyFormName,$projectId);

			if(!$eventId) return false;
		}

		## Search for a participant and response id for the given survey and record
		list($participantId,$responseId) = $this->getParticipantAndResponseId($surveyId,$recordId,$eventId);

		## Create participant and return code if doesn't exist yet
		if($participantId == "" || $responseId == "") {
			$hash = self::generateUniqueRandomSurveyHash();

			## Insert a participant row for this survey
			$sql = "INSERT INTO redcap_surveys_participants (survey_id, event_id, participant_email, participant_identifier, hash)
					VALUES ($surveyId,".prep($eventId).", '', null, '$hash')";

			if(!db_query($sql)) echo "Error: ".db_error()." <br />$sql<br />";
			$participantId = db_insert_id();

			## Insert a response row for this survey and record
			$returnCode = generateRandomHash();
			$firstSubmitDate = "'".date('Y-m-d h:m:s')."'";

			$sql = "INSERT INTO redcap_surveys_response (participant_id, record, first_submit_time, return_code)
					VALUES ($participantId, ".prep($recordId).", $firstSubmitDate,'$returnCode')";

			if(!db_query($sql)) echo "Error: ".db_error()." <br />$sql<br />";
			$responseId = db_insert_id();
		}
		## Reset response status if it already exists
		else {
			$sql = "SELECT p.participant_id, p.hash, r.return_code, r.response_id, COALESCE(p.participant_email,'NULL') as participant_email
					FROM redcap_surveys_participants p, redcap_surveys_response r
					WHERE p.survey_id = '$surveyId'
						AND p.participant_id = r.participant_id
						AND r.record = '".prep($recordId)."'
						AND p.event_id = '".prep($eventId)."'";

			$q = db_query($sql);
			$rows = [];
			while($row = db_fetch_assoc($q)) {
				$rows[] = $row;
			}

			## If more than one exists, delete any that are responses to public survey links
			if(db_num_rows($q) > 1) {
				foreach($rows as $thisRow) {
					if($thisRow["participant_email"] == "NULL" && $thisRow["response_id"] != "") {
						$sql = "DELETE FROM redcap_surveys_response
								WHERE response_id = ".$thisRow["response_id"];
						if(!db_query($sql)) echo "Error: ".db_error()." <br />$sql<br />";
					}
					else {
						$row = $thisRow;
					}
				}
			}
			else {
				$row = $rows[0];
			}
			$returnCode = $row['return_code'];
			$hash = $row['hash'];
			$participantId = "";

			if($returnCode == "") {
				$returnCode = generateRandomHash();
			}

			## If this is only as a public survey link, generate new participant row
			if($row["participant_email"] == "NULL") {
				$hash = self::generateUniqueRandomSurveyHash();

				## Insert a participant row for this survey
				$sql = "INSERT INTO redcap_surveys_participants (survey_id, event_id, participant_email, participant_identifier, hash)
						VALUES ($surveyId,".prep($eventId).", '', null, '$hash')";

				if(!db_query($sql)) echo "Error: ".db_error()." <br />$sql<br />";
				$participantId = db_insert_id();
			}

			// Set the response as incomplete in the response table, update participantId if on public survey link
			$sql = "UPDATE redcap_surveys_participants p, redcap_surveys_response r
					SET r.completion_time = null,
						r.first_submit_time = '".date('Y-m-d h:m:s')."',
						r.return_code = '".prep($returnCode)."'".
						($participantId == "" ? "" : ", r.participant_id = '$participantId'")."
					WHERE p.survey_id = $surveyId
						AND p.event_id = ".prep($eventId)."
						AND r.participant_id = p.participant_id
						AND r.record = '".prep($recordId)."'";
			db_query($sql);
		}

		// Set the response as incomplete in the data table
		$sql = "UPDATE redcap_data
				SET value = '0'
				WHERE project_id = ".prep($projectId)."
					AND record = '".prep($recordId)."'
					AND event_id = ".prep($eventId)."
					AND field_name = '{$surveyFormName}_complete'";

		$q = db_query($sql);
		// Log the event (if value changed)
		if ($q && db_affected_rows() > 0) {
			if(function_exists("log_event")) {
				\log_event($sql,"redcap_data","UPDATE",$recordId,"{$surveyFormName}_complete = '0'","Update record");
			}
			else {
				\Logging::logEvent($sql,"redcap_data","UPDATE",$recordId,"{$surveyFormName}_complete = '0'","Update record");
			}
		}

		@db_query("COMMIT");

		return array("hash" => $hash, "return_code" => $returnCode);
	}

	public function generateUniqueRandomSurveyHash() {
		## Generate a random hash and verify it's unique
		do {
			$hash = generateRandomHash(10);

			$sql = "SELECT p.hash
						FROM redcap_surveys_participants p
						WHERE p.hash = '$hash'";

			$result = db_query($sql);

			$hashExists = (db_num_rows($result) > 0);
		} while($hashExists);

		return $hash;
	}

	public function getProjectAndRecordFromHashes($surveyHash, $returnCode) {
		$sql = "SELECT s.project_id as projectId, r.record as recordId, s.form_name as surveyForm, p.event_id as eventId
				FROM redcap_surveys_participants p, redcap_surveys_response r, redcap_surveys s
				WHERE p.hash = '".prep($surveyHash)."'
					AND p.survey_id = s.survey_id
					AND p.participant_id = r.participant_id
					AND r.return_code = '".prep($returnCode)."'";

		$q = db_query($sql);

		$row = db_fetch_assoc($q);

		if($row) {
			return $row;
		}

		return false;
	}

	public function createPassthruForm($projectId,$recordId,$surveyFormName = "", $eventId = "") {
		$codeDetails = $this->resetSurveyAndGetCodes($projectId,$recordId,$surveyFormName,$eventId);

		$hash = $codeDetails["hash"];
		$returnCode = $codeDetails["return_code"];

		$surveyLink = APP_PATH_SURVEY_FULL . "?s=$hash";

		## Build invisible self-submitting HTML form to get the user to the survey
		echo "<html><body>
				<form name='passthruform' action='$surveyLink' method='post' enctype='multipart/form-data'>
				".($returnCode == "NULL" ? "" : "<input type='hidden' value='".$returnCode."' name='__code'/>")."
				<input type='hidden' value='1' name='__prefill' />
				</form>
				<script type='text/javascript'>
					document.passthruform.submit();
				</script>
				</body>
				</html>";
		return false;
	}

	public function getValidFormEventId($formName,$projectId) {
		if(!is_numeric($projectId) || $projectId == "") return false;

		$projectDetails = $this->getProjectDetails($projectId);

		if($projectDetails["repeatforms"] == 0) {
			$sql = "SELECT e.event_id
					FROM redcap_events_metadata e, redcap_events_arms a
					WHERE a.project_id = $projectId
						AND a.arm_id = e.arm_id
					ORDER BY e.event_id ASC
					LIMIT 1";

			$q = db_query($sql);

			if($row = db_fetch_assoc($q)) {
				return $row['event_id'];
			}
		}
		else {
			$sql = "SELECT f.event_id
					FROM redcap_events_forms f, redcap_events_metadata m, redcap_events_arms a
					WHERE a.project_id = $projectId
						AND a.arm_id = m.arm_id
						AND m.event_id = f.event_id
						AND f.form_name = '".prep($formName)."'
					ORDER BY f.event_id ASC
					LIMIT 1";

			$q = db_query($sql);

			if($row = db_fetch_assoc($q)) {
				return $row['event_id'];
			}
		}

		return false;
	}

	public function getSurveyId($projectId,$surveyFormName = "") {
		// Get survey_id, form status field, and save and return setting
		$sql = "SELECT s.survey_id, s.form_name, s.save_and_return
		 		FROM redcap_projects p, redcap_surveys s, redcap_metadata m
					WHERE p.project_id = ".prep($projectId)."
						AND p.project_id = s.project_id
						AND m.project_id = p.project_id
						AND s.form_name = m.form_name
						".($surveyFormName != "" ? (is_numeric($surveyFormName) ? "AND s.survey_id = '$surveyFormName'" : "AND s.form_name = '".prep($surveyFormName)."'") : "")
				."ORDER BY s.survey_id ASC
				 LIMIT 1";

		$q = db_query($sql);
		$surveyId = db_result($q, 0, 'survey_id');
		$surveyFormName = db_result($q, 0, 'form_name');

		return [$surveyId,$surveyFormName];
	}

	public function getParticipantAndResponseId($surveyId,$recordId,$eventId = "") {
		$sql = "SELECT p.participant_id, r.response_id
				FROM redcap_surveys_participants p, redcap_surveys_response r
				WHERE p.survey_id = '$surveyId'
					AND p.participant_id = r.participant_id
					AND r.record = '".$recordId."'".
				($eventId != "" ? " AND p.event_id = '".prep($eventId)."'" : "");

		$q = db_query($sql);
		$participantId = db_result($q, 0, 'participant_id');
		$responseId = db_result($q, 0, 'response_id');

		return [$participantId,$responseId];
	}

	public function getProjectDetails($projectId) {
		$sql = "SELECT *
				FROM redcap_projects
				WHERE project_id = '".prep($projectId)."'";

		$q = db_query($sql);

		return db_fetch_assoc($q);
	}

	public function getMetadata($projectId,$forms = NULL) {
		$metadata = \REDCap::getDataDictionary($projectId,"array",TRUE,NULL,$forms);

		return $metadata;
	}

	public function getData($projectId,$recordId,$eventId="",$format="array") {
		$data = \REDCap::getData($projectId,$format,$recordId);

		if($eventId != "") {
			return $data[$recordId][$eventId];
		}
		return $data;
	}

	public function saveData($projectId,$recordId,$eventId,$data) {
		return \REDCap::saveData($projectId,"array",[$recordId => [$eventId =>$data]]);
	}

	/**
	 * @param $projectId
	 * @param $recordId
	 * @param $eventId
	 * @param $formName
	 * @param $data array This must be in [instance => [field => value]] format
	 * @return array
	 */
	public function saveInstanceData($projectId,$recordId,$eventId,$formName,$data) {
		return \REDCap::saveData($projectId,"array",[$recordId => [$eventId => [$formName => $data]]]);
	}

	# function to enforce that a pid is required for a particular function
	private function requireProjectId($pid = null)
	{
		return $this->requireParameter('pid', $pid);
	}

	private function requireEventId($eventId = null)
	{
		return $this->requireParameter('event_id', $eventId);
	}

	private function requireInstanceId($instanceId = null)
	{
		return $this->requireParameter('instance', $instanceId);
	}

	private function requireParameter($parameterName, $value)
	{
		$value = self::detectParameter($parameterName, $value);

		if(!isset($value)){
			throw new Exception("You must supply the following either as a GET parameter or as the last argument to this method: $parameterName");
		}

		return $value;
	}

	private function detectParameter($parameterName, $value = null)
	{
		if($value == null){
			$value = @$_GET[$parameterName];

			if(!empty($value)){
				// Use intval() to prevent SQL injection.
				$value = intval($value);
			}
		}

		return $value;
	}

	# if $pid is empty/null, can get the pid from $_GET if it exists
	private function detectProjectId($projectId=null)
	{
		return $this->detectParameter('pid', $projectId);
	}

	private function detectEventId($eventId=null)
	{
		return $this->detectParameter('event_id', $eventId);
	}

	private function detectInstanceId($instanceId=null)
	{
		return $this->detectParameter('instance', $instanceId);
	}

	# pushes the execution of the module to the end of the queue
	# helpful to wait for data to be processed by other modules
	# execution of the module will be restarted from the beginning
	# For example:
	# 	if ($data['field'] === "") {
	#		delayModuleExecution();
	#		return;       // the module will be restarted from the beginning
	#	}
	public function delayModuleExecution() {
		return ExternalModules::delayModuleExecution();
	}

    public function sendAdminEmail($subject, $message){
        ExternalModules::sendAdminEmail($subject, $message, $this->PREFIX);
    }

    /**
     * Function that returns the label name from checkboxes, radio buttons, etc instead of the value
     * @param $params, associative array
     * @param null $value, (to support the old version)
     * @param null $pid, (to support the old version)
     * @return mixed|string, label
     */
    public function getChoiceLabel ($params, $value=null, $pid=null)
    {

        if(!is_array($params)) {
            $params = array('field_name'=>$params, 'value'=>$value, 'project_id'=>$pid);
        }

        //In case it's for a different project
        if ($params['project_id'] != "")
        {
            $pid = $params['project_id'];
        }else{
            $pid = self::detectProjectId();
        }

        $data = \REDCap::getData($pid, "array", $params['record_id']);
        $fieldName = str_replace('[', '', $params['field_name']);
        $fieldName = str_replace(']', '', $fieldName);

        $dateFormats = [
            "date_dmy" => "d-m-Y",
            "date_mdy" => "m-d-Y",
            "date_ymd" => "Y-m-d",
            "datetime_dmy" => "d-m-Y h:i",
            "datetime_mdy" => "m-d-Y h:i",
            "datetime_ymd" => "Y-m-d h:i",
            "datetime_seconds_dmy" => "d-m-Y h:i:s",
            "datetime_seconds_mdy" => "m-d-Y h:i:s",
            "datetime_seconds_ymd" => "Y-m-d  h:i:s"
        ];

        if (array_key_exists('repeat_instances', $data[$params['record_id']])) {
            if ($data[$params['record_id']]['repeat_instances'][$params['event_id']][$params['survey_form']][$params['instance']][$fieldName] != "") {
                //Repeat instruments
                $data_event = $data[$params['record_id']]['repeat_instances'][$params['event_id']][$params['survey_form']][$params['instance']];
            } else if ($data[$params['record_id']]['repeat_instances'][$params['event_id']][''][$params['instance']][$fieldName] != "") {
                //Repeat events
                $data_event = $data[$params['record_id']]['repeat_instances'][$params['event_id']][''][$params['instance']];
            } else {
                $data_event = $data[$params['record_id']][$params['event_id']];
            }
        } else {
            $data_event = $data[$params['record_id']][$params['event_id']];
        }

        $metadata = \REDCap::getDataDictionary($pid, 'array', false, $fieldName);

        //event arm is defined
        if (empty($metadata)) {
            preg_match_all("/\[[^\]]*\]/", $fieldName, $matches);
            $event_name = str_replace('[', '', $matches[0][0]);
            $event_name = str_replace(']', '', $event_name);

            $fieldName = str_replace('[', '', $matches[0][1]);
            $fieldName = str_replace(']', '', $fieldName);
            $metadata = \REDCap::getDataDictionary($pid, 'array', false, $fieldName);
        }
        $label = "";
        if ($metadata[$fieldName]['field_type'] == 'checkbox' || $metadata[$fieldName]['field_type'] == 'dropdown' || $metadata[$fieldName]['field_type'] == 'radio') {
            $project = new \Project($pid);
            $other_event_id = $project->getEventIdUsingUniqueEventName($event_name);
            $choices = preg_split("/\s*\|\s*/", $metadata[$fieldName]['select_choices_or_calculations']);
            foreach ($choices as $choice) {
                $option_value = preg_split("/,/", $choice)[0];
                if ($params['value'] != "") {
                    if (is_array($data_event[$fieldName])) {
                        foreach ($data_event[$fieldName] as $choiceValue => $multipleChoice) {
                            if ($multipleChoice === "1" && $choiceValue == $option_value) {
                                $label .= trim(preg_split("/^(.+?),/", $choice)[1]) . ", ";
                            }
                        }
                    } else if ($params['value'] === $option_value) {
                        $label = trim(preg_split("/^(.+?),/", $choice)[1]);
                    }
                } else if ($params['value'] === $option_value) {
                    $label = trim(preg_split("/^(.+?),/", $choice)[1]);
                    break;
                } else if ($params['value'] == "" && $metadata[$fieldName]['field_type'] == 'checkbox') {
                    //Checkboxes for event_arms
                    if ($other_event_id == "") {
                        $other_event_id = $params['event_id'];
                    }
                    if ($data[$params['record_id']][$other_event_id][$fieldName][$option_value] == "1") {
                        $label .= trim(preg_split("/^(.+?),/", $choice)[1]) . ", ";
                    }
                }
            }
            //we delete the last comma and space
            $label = rtrim($label, ", ");
        } else if ($metadata[$fieldName]['field_type'] == 'truefalse') {
            if ($params['value'] == '1') {
                $label = "True";
            } else  if ($params['value'] == '0'){
                $label = "False";
            }
        } else if ($metadata[$fieldName]['field_type'] == 'yesno') {
            if ($params['value'] == '1') {
                $label = "Yes";
            } else  if ($params['value'] == '0'){
                $label = "No";
            }
        } else if ($metadata[$fieldName]['field_type'] == 'sql') {
            if (!empty($params['value'])) {
                $q = db_query($metadata[$fieldName]['select_choices_or_calculations']);

                if ($error = db_error()) {
                    die($metadata[$fieldName]['select_choices_or_calculations'] . ': ' . $error);
                }

                while ($row = db_fetch_assoc($q)) {
                    if ($row['record'] == $params['value']) {
                        $label = $row['value'];
                        break;
                    }
                }
            }
        } else if (in_array($metadata[$fieldName]['text_validation_type_or_show_slider_number'], array_keys($dateFormats)) && $params['value'] != "") {
            $label = date($dateFormats[$metadata[$fieldName]['text_validation_type_or_show_slider_number']], strtotime($params['value']));
        }
        return $label;
    }

	public function getChoiceLabels($fieldName, $pid = null){
		// Caching could be easily added to this method to improve performance on repeat calls.

		$pid = $this->requireProjectId($pid);

		$dictionary = \REDCap::getDataDictionary($pid, 'array', false, [$fieldName]);
		$choices = explode('|', $dictionary[$fieldName]['select_choices_or_calculations']);
		$choicesById = [];
		foreach($choices as $choice){
			$parts = explode(', ', $choice);
			$id = trim($parts[0]);
			$label = trim($parts[1]);
			$choicesById[$id] = $label;
		}

		return $choicesById;
	}

	public function getFieldLabel($fieldName){
		$pid = self::requireProjectId();
		$dictionary = \REDCap::getDataDictionary($pid, 'array', false, [$fieldName]);
		return $dictionary[$fieldName]['field_label'];
	}

	public function query($sql){
		return ExternalModules::query($sql);
	}

	public function createDAG($dagName){
		$pid = db_escape(self::requireProjectId());
		$dagName = db_escape($dagName);

		$this->query("insert into redcap_data_access_groups (project_id, group_name) values ($pid, '$dagName')");

		return db_insert_id();
	}

    public function deleteDAG($dagId){
        $pid = db_escape(self::requireProjectId());
        $dagId = db_escape($dagId);

        $this->deleteAllDAGRecords($dagId);
        $this->deleteAllDAGUsers($dagId);
        $this->query("DELETE FROM redcap_data_access_groups where project_id = $pid and group_id = $dagId");
    }

    private function deleteAllDAGRecords($dagId){
        $pid = db_escape(self::requireProjectId());
        $dagId = db_escape($dagId);

        $records = $this->query("SELECT record FROM redcap_data where project_id = $pid and field_name = '__GROUPID__' and value = $dagId");
        while ($row = db_fetch_assoc($records)){
            $record = db_escape($row['record']);
            $this->query("DELETE FROM redcap_data where project_id = $pid and record = '".$record."'");
        }
        $this->query("DELETE FROM redcap_data where project_id = $pid and field_name = '__GROUPID__' and value = $dagId");
    }

    private function deleteAllDAGUsers($dagId){
        $pid = db_escape(self::requireProjectId());
        $dagId = db_escape($dagId);

        $this->query("DELETE FROM redcap_user_rights where project_id = $pid and group_id = $dagId");
    }

	public function renameDAG($dagId, $dagName){
		$pid = db_escape(self::requireProjectId());
		$dagId = db_escape($dagId);
		$dagName = db_escape($dagName);

		$this->query("update redcap_data_access_groups set group_name = '$dagName' where project_id = $pid and group_id = $dagId");
	}

	public function setDAG($record, $dagId){
		// $this->setData() is used instead of REDCap::saveData(), since REDCap::saveData() has some (perhaps erroneous) limitations for super users around setting DAGs on records that are already in DAGs  .
		// It also doesn't seem to be aware of DAGs that were just added in the same hook call (likely because DAGs are cached before the hook is called).
		// Specifying a "redcap_data_access_group" parameter for REDCap::saveData() doesn't work either, since that parameter only accepts the auto generated names (not ids or full names).

		$this->setData($record, '__GROUPID__', $dagId);
		
		// Update the record list cache table too
		if (method_exists('Records', 'updateRecordDagInRecordListCache')) {
			\Records::updateRecordDagInRecordListCache(self::requireProjectId(), $record, $dagId);
		}
	}

	public function setData($record, $fieldName, $values){
		$instanceId = db_escape(self::requireInstanceId());
		if($instanceId != 1){
			throw new Exception("Multiple instances are not currently supported!");
		}

		$pid = db_escape(self::requireProjectId());
		$eventId = db_escape(self::requireEventId());
		$record = db_escape($record);
		$fieldName = db_escape($fieldName);

		if(!is_array($values)){
			$values = [$values];
		}

		$beginTransactionVersion = '5.5';
		if($this->isPHPGreaterThan($beginTransactionVersion)){
			$this->query("SET AUTOCOMMIT=0");
			$this->query("BEGIN");
		}

		$this->query("DELETE FROM redcap_data where project_id = $pid and event_id = $eventId and record = '$record' and field_name = '$fieldName'");

		foreach($values as $value){
			$value = db_escape($value);
			$this->query("INSERT INTO redcap_data (project_id, event_id, record, field_name, value) VALUES ($pid, $eventId, '$record', '$fieldName', '$value')");
		}

		if($this->isPHPGreaterThan($beginTransactionVersion)) {
			$this->query("COMMIT");
			$this->query("SET AUTOCOMMIT=1");
		}
	}

	private function isPHPGreaterThan($requiredVersion){
		return version_compare(PHP_VERSION, $requiredVersion, '>=');
	}

	public function areSettingPermissionsUserBased(){
		return $this->userBasedSettingPermissions;
	}

	public function disableUserBasedSettingPermissions(){
		$this->userBasedSettingPermissions = false;
	}

	public function addAutoNumberedRecord($pid = null){
		$pid = $this->requireProjectId($pid);
		$eventId = $this->getFirstEventId($pid);
		$fieldName = \Records::getTablePK($pid);
		$recordId = $this->getNextAutoNumberedRecordId($pid);

		$this->query("insert into redcap_data (project_id, event_id, record, field_name, value) values ($pid, $eventId, $recordId, '$fieldName', $recordId)");
		$result = $this->query("select count(1) as count from redcap_data where project_id = $pid and event_id = $eventId and record = $recordId and field_name = '$fieldName' and value = $recordId");
		$count = $result->fetch_assoc()['count'];
		if($count > 1){
			$this->query("delete from redcap_data where project_id = $pid and event_id = $eventId and record = $recordId and field_name = '$fieldName' limit 1");
			return $this->addAutoNumberedRecord($pid);
		}
		else if($count == 0){
			throw new Exception("An error occurred while adding an auto numbered record for project $pid.");
		}

		$this->updateRecordCount($pid);
		
		// Add record to the record list cache table
		if (method_exists('Records', 'addRecordToRecordListCache')) {
			$arm = db_result(db_query("select arm_num from redcap_events_arms a, redcap_events_metadata e where a.arm_id = e.arm_id and e.event_id = $eventId"), 0);
			\Records::addRecordToRecordListCache($pid, $recordId, $arm);
		}

		return $recordId;
	}

	private function updateRecordCount($pid){
		$results = $this->query("select count(1) as count from (select 1 from redcap_data where project_id = $pid group by record) a");
		$count = $results->fetch_assoc()['count'];
		$this->query("update redcap_record_counts set record_count = $count where project_id = $pid");
	}

	private function getNextAutoNumberedRecordId($pid){
		$results = $this->query("
			select record from redcap_data 
			where project_id = $pid
			group by record
			order by cast(record as unsigned integer) desc limit 1
		");

		$row = $results->fetch_assoc();
		if(empty($row)){
			return 1;
		}
		else{
			return $row['record']+1;
		}
	}

	public function getFirstEventId($pid = null){
		$pid = $this->requireProjectId($pid);
		$results = $this->query("
			select event_id
			from redcap_events_arms a
			join redcap_events_metadata m
				on a.arm_id = m.arm_id
			where a.project_id = $pid
			order by event_id
		");

		$row = db_fetch_assoc($results);
		return $row['event_id'];
	}

	public function saveFile($path, $pid = null){
		$pid = $this->requireProjectId($pid);

		$file = [];
		$file['name'] = basename($path);
		$file['tmp_name'] = $path;
		$file['size'] = filesize($path);

		return \Files::uploadFile($file, $pid);
	}

	public function validateSettings($settings){
		return null;
	}

	/**
	 * Return a value from the UI state config. Return null if key doesn't exist.
	 * @param int/string $key key
	 * @return mixed - value if exists, else return null
	 */
	public function getUserSetting($key)
	{
		return UIState::getUIStateValue($this->detectProjectId(), self::UI_STATE_OBJECT_PREFIX . $this->PREFIX, $key);
	}
	
	/**
	 * Save a value in the UI state config
	 * @param int/string $key key
	 * @param mixed $value value for key
	 */
	public function setUserSetting($key, $value)
	{
		UIState::saveUIStateValue($this->detectProjectId(), self::UI_STATE_OBJECT_PREFIX . $this->PREFIX, $key, $value);
	}
	
	/**
	 * Remove key-value from the UI state config
	 * @param int/string $key key
	 */
	public function removeUserSetting($key)
	{
		UIState::removeUIStateValue($this->detectProjectId(), self::UI_STATE_OBJECT_PREFIX . $this->PREFIX, $key);
	}

	public function exitAfterHook(){
		ExternalModules::exitAfterHook();
	}

	public function redcap_module_link_check_display($project_id, $link)
	{
		if (SUPER_USER) {
			return $link;
		}

		if (!empty($project_id) && \REDCap::getUserRights(USERID)[USERID]['design']) {
			return $link;
		}

		return null;
    }

    public function redcap_module_configure_button_display(){
        return true;
    }

    public function getPublicSurveyUrl(){
        $instrumentNames = \REDCap::getInstrumentNames();
        $formName = db_real_escape_string(key($instrumentNames));

        $sql ="
			select h.hash from redcap_surveys s join redcap_surveys_participants h on s.survey_id = h.survey_id
			where form_name = '$formName' and participant_email is null
		";
        $result = db_query($sql);
        $row = db_fetch_assoc($result);
        $hash = @$row['hash'];

        return APP_PATH_SURVEY_FULL . "?s=$hash";
    }

	public function isSurveyPage()
	{
		return ExternalModules::isSurveyPage();
	}

	public function initializeJavascriptModuleObject()
	{
		$jsObjectParts = explode('\\', get_class($this));

		// Remove the class name, since it's always the same as it's parent namespace.
		array_pop($jsObjectParts);

		// Prepend "ExternalModules" to contain all module namespaces.
		array_unshift($jsObjectParts, 'ExternalModules');

		$jsObject = implode('.', $jsObjectParts);

		$pid = $this->getProjectId();
		$logUrl = APP_URL_EXTMOD . "manager/ajax/log.php?prefix=" . $this->PREFIX . "&pid=$pid";
		$noAuth = defined('NOAUTH');

		$recordId = $this->getRecordIdOrTemporaryRecordId();
		if($noAuth && !ExternalModules::isTemporaryRecordId($recordId)){
			// Don't sent the actual record id, since it shouldn't be trusted on non-authenticated requests anyway.
			$recordId = null;
		}

		?>
		<script>
			(function(){
				// Create the module object, and any missing parent objects.
				var parent = window
				;<?=json_encode($jsObjectParts)?>.forEach(function(part){
					if(parent[part] === undefined){
						parent[part] = {}
					}

					parent = parent[part]
				})

				<?=$jsObject?>.log = function(message, parameters){
					if(parameters === undefined){
						parameters = {}
					}

					<?php
					if(!empty($recordId)){
						?>
						if(parameters.record === undefined){
							parameters.record = <?=json_encode($recordId)?>
						}
						<?php
					}
					?>

					$.ajax({
						'type': 'POST',
						'url': "<?=$logUrl?>",
						'data': JSON.stringify({
							message: message
							,parameters: parameters
							,noAuth: <?=json_encode($noAuth)?>
							<?php if($this->isSurveyPage()) { ?>
								,surveyHash: <?=json_encode($_GET['s'])?>
								,responseHash: $('#form input[name=__response_hash__]').val()
							<?php } ?>
						}),
						'success': function(data){
							if(data !== 'success'){
								console.error("An error occurred while calling the log API:", data)
							}
						}
					})
				}
			})()
		</script>
		<?php
	}

	public function __call($name, $arguments){
		if($name === 'log'){
			return call_user_func_array([$this, 'log_internal'], $arguments);
		}

		throw new Exception("The following method does not exist: $name()");
	}

	private function log_internal($message, $parameters = [])
	{
		if (empty($message)) {
			throw new Exception("A message is required for log entries.");
		}

		foreach ($parameters as $name => $value) {
			if (isset(self::$RESERVED_LOG_PARAMETER_NAMES_FLIPPED[$name])) {
				throw new Exception("The '$name' parameter name is set automatically and cannot be overridden.");
			}
			else if($value === null){
				// There's no point in storing null values in the database.
				// If a parameter is missing, queries will return null for it anyway.
				unset($parameters[$name]);
			}
			else if(strpos($name, "'") !== false){
				throw new Exception("Single quotes are not allowed in parameter names.");
			}

			$type = gettype($value);
			if(!in_array($type, ['boolean', 'integer', 'double', 'string', 'NULL'])){
				throw new Exception("The type '$type' for the '$name' parameter is not supported.");
			}
		}

		$timestamp = @$parameters['timestamp'];
		if(empty($timestamp)){
			$timestamp = 'now()';
		}
		else{
			$timestamp = "'" . db_real_escape_string($timestamp) . "'";
		}

		$projectId = @$parameters['project_id'];
		if (empty($projectId)) {
			$projectId = $this->getProjectId();

			if (empty($projectId)) {
				$projectId = 'null';
			}
		}

		$username = @$parameters['username'];
		if(empty($username)){
			$username = ExternalModules::getUsername();;
		}

		if(isset($parameters['record'])){
			$recordId = $parameters['record'];

			// Unset it so it doesn't get added to the parameters table.
			unset($parameters['record']);
		}
		else{
			$recordId = $this->getRecordIdOrTemporaryRecordId();
		}

		if (empty($recordId)) {
			$recordId = 'null';
		}
		else{
			$recordId = "'" . db_real_escape_string($recordId) . "'";
		}

		$logValues = [];
		$logValues['timestamp'] = $timestamp;
		$logValues['ui_id'] = "(select ui_id from redcap_user_information where username = '" . db_real_escape_string($username) . "')";
		$logValues['ip'] = $this->getIPSQL(@$parameters['ip']);
		$logValues['external_module_id'] = "(select external_module_id from redcap_external_modules where directory_prefix = '{$this->PREFIX}')";
		$logValues['project_id'] = db_real_escape_string($projectId);
		$logValues['record'] = $recordId;
		$logValues['message'] = "'" . db_real_escape_string($message) . "'";

		$this->query("
			insert into redcap_external_modules_log
				(
					" . implode(",\n", array_keys($logValues)) . "				
				)
			values
				(
					" . implode(",\n", $logValues) . "
				)
		");

		$logId = db_insert_id();
		if (!empty($parameters)) {
			$this->insertLogParameters($logId, $parameters);
		}

		return $logId;
	}

	private function getIPSQL($ip)
	{
		$username = ExternalModules::getUsername();
		
		if(
			empty($ip)
			&& !empty($username) // Only log the ip if a user is currently logged in
			&& !$this->isSurveyPage() // Don't log IPs for surveys
		){
			// The IP could contain multiple comma separated addresses (if proxies are used).
			// To accommodated at least three IPv4 addresses, the DB field is 100 chars long like the redcap_log_event table.
			$ip = \System::clientIpAddress();
		}

		if (empty($ip)) {
			$ip = 'null';
		}
		else{
			$ip = "'" . db_real_escape_string($ip) . "'";
		}

		return $ip;
	}

	private function insertLogParameters($logId, $parameters)
	{
		$valuesSql = '';
		foreach ($parameters as $name => $value) {
			if (!empty($valuesSql)) {
				$valuesSql .= ',';
			}

			$name = db_real_escape_string($name);
			$value = db_real_escape_string($value);

			$valuesSql .= "($logId, '$name', '$value')";
		}

		$this->query("insert into redcap_external_modules_log_parameters (log_id, name, value) VALUES $valuesSql");
	}

	public function logAjax($data)
	{
		$parameters = @$data['parameters'];
		if(!$parameters){
			$parameters = [];
		}

		foreach($parameters as $name=>$value){
			if($name === 'record' && ExternalModules::isTemporaryRecordId($value)){
				// Allow the temporary record id to get passed through as a parameter.
				continue;
			}

			if(in_array($name, self::$OVERRIDABLE_LOG_PARAMETERS_ON_MAIN_TABLE)){
				throw new Exception("For security reasons, the '$name' parameter cannot be overridden via AJAX log requests.  It can be overridden only be overridden by PHP log requests.  You can add your own PHP page to this module to perform the logging, and call it via AJAX.");
			}
		}

		$surveyHash = @$data['surveyHash'];
		$responseHash = @$data['responseHash'];
		if(!empty($responseHash)){
			// We're on a survey submission that already has a record id.
			// We shouldn't pass the record id directly because it would be easy to spoof.
			// Instead, we determine the record id from the response hash.

			require_once APP_PATH_DOCROOT . "/Surveys/survey_functions.php";

			// This method is called to set the $participant_id global;
			global $participant_id;
			\Survey::setSurveyVals($surveyHash);

			$responseId = \decryptResponseHash($responseHash, $participant_id);

			$result = $this->query("select record from redcap_surveys_response where response_id = $responseId");
			$row = db_fetch_assoc($result);
			$recordId = $row['record'];
		}

		if(!empty($recordId)){
			$this->setRecordId($recordId);
		}

		return $this->log($data['message'], $parameters);
	}

	public function queryLogs($sql)
	{
		return $this->query($this->getQueryLogsSql($sql));
	}

	public function removeLogs($sql)
	{
		if(empty($sql)){
			throw new Exception('You must specify a where clause.');
		}

		$select = "select 1";
		$sql = $this->getQueryLogsSql("$select where $sql");
		$sql = substr_replace($sql, 'delete redcap_external_modules_log', 0, strlen($select));

		if(strpos($sql, AbstractExternalModule::EXTERNAL_MODULE_ID_STANDARD_WHERE_CLAUSE_PREFIX) === false) {
			// An external_module_id must have been specified in the where clause, preventing the standard clause from being included.
			// This check also make sure that a bug in the framework doesn't remove logs for all modules (especially important when developing changes to log methods).
			throw new Exception("Specifying an 'external_module_id' in the where clause for removeLogs() is not allowed to prevent modules from accidentally removing logs for other modules.");
		}

		return $this->query($sql);
	}

	public function getQueryLogsSql($sql)
	{
		$parser = new PHPSQLParser();
		$parsed = $parser->parse($sql);

		if($parsed['SELECT'] === null){
			throw new Exception("Queries must start with a 'select' statement.");
		}

		$selectFields = [];
		$whereFields = [];
		$orderByFields = [];
		$this->processPseudoQuery($parsed['SELECT'], $selectFields, true);
		$this->processPseudoQuery($parsed['WHERE'], $whereFields, false);
		$this->processPseudoQuery($parsed['ORDER'], $orderByFields, false);
		$fields = array_merge($selectFields, $whereFields, $orderByFields);

		$standardWhereClauses = [];

		if(!in_array('external_module_id', $whereFields)){
			$standardWhereClauses[] = AbstractExternalModule::EXTERNAL_MODULE_ID_STANDARD_WHERE_CLAUSE_PREFIX . " = '{$this->PREFIX}')";
		}

		if(!in_array('project_id', $whereFields)){
			$projectId = $this->getProjectId();
			if (!empty($projectId)) {
				$standardWhereClauses[] = "redcap_external_modules_log.project_id = $projectId";
			}
		}

		if(!empty($standardWhereClauses)){
			$standardWhereClausesSql = 'where ' . implode(' and ', $standardWhereClauses);

			if($parsed['WHERE'] === null){
				// Set it to an empty array, since array_merge() won't work on null.
				$parsed['WHERE'] = [];
			}
			else{
				$standardWhereClausesSql .= ' and ';
			}

			$parsedStandardWhereClauses = $parser->parse($standardWhereClausesSql);
			$parsed['WHERE'] = array_merge($parsedStandardWhereClauses['WHERE'], $parsed['WHERE']);
		}

		$creator = new PHPSQLCreator();
		$select = $creator->create(['SELECT' => $parsed['SELECT']]);
		$where = substr($creator->create($parsed), strlen($select));

		$fields = array_unique($fields);
		$joinUsername = false;
		$parameterFields = [];
		foreach ($fields as $field) {
			if ($field == 'username') {
				$joinUsername = true;
			} else if (isset(self::$LOG_PARAMETERS_ON_MAIN_TABLE[$field])) {
				// do nothing
			} else {
				$parameterFields[] = $field;
			}
		}

		$from = ' from redcap_external_modules_log';
		foreach ($parameterFields as $field) {
			// Needed for field names with spaces.
			$fieldString = str_replace("`", "", $field);

			$from .= "
						left join redcap_external_modules_log_parameters $field on $field.name = '$fieldString'
						and $field.log_id = redcap_external_modules_log.log_id
					";
		}

		if ($joinUsername) {
			$from .= "
						left join redcap_user_information on redcap_user_information.ui_id = redcap_external_modules_log.ui_id
					";
		}

		$sql = implode(' ', [$select, $from, $where]);

		return $sql;
	}

	private function processPseudoQuery(&$parsed, &$fields, $addAs)
	{
		for ($i = 0; $i < count($parsed); $i++) {
			$item =& $parsed[$i];
			$subtree =& $item['sub_tree'];

			if (is_array($subtree)) {
				$this->processPseudoQuery($subtree, $fields, $addAs);
			} else if (
				$item['expr_type'] == 'colref'
				&& $item['base_expr'] !== '*' // This allows for "count(*)" queries
			){
				$field = $item['base_expr'];
				$fields[] = $field;

				if ($field === 'username') {
					$newField = 'redcap_user_information.username';
				} else if(isset(self::$LOG_PARAMETERS_ON_MAIN_TABLE[$field])) {
					$newField = "redcap_external_modules_log.$field";
				} else {
					$newField = "$field.value";

					if ($addAs && $item['alias'] == false) {
						$newField .= " as $field";
					}
				}

				$item['base_expr'] = $newField;
			}
		}
	}

	public function getProjectId()
	{
		$pid = @$_GET['pid'];

		// Require only digits to prevent sql injection.
		if (ctype_digit($pid)) {
			return $pid;
		} else {
			return null;
		}
	}

	public function setRecordId($recordId)
	{
		$this->recordId = $recordId;
	}

	public function getRecordId()
	{
		return $this->recordId;
	}

	public function getRecordIdOrTemporaryRecordId()
	{
		$recordId = $this->getRecordId();
		if(empty($recordId)){
			// Use the temporary record id if it exists.
			$recordId = ExternalModules::getTemporaryRecordId();
		}

		return $recordId;
	}

	public static function init()
	{
		self::$RESERVED_LOG_PARAMETER_NAMES_FLIPPED = array_flip(self::$RESERVED_LOG_PARAMETER_NAMES);
		self::$LOG_PARAMETERS_ON_MAIN_TABLE = array_flip(array_merge(self::$RESERVED_LOG_PARAMETER_NAMES, self::$OVERRIDABLE_LOG_PARAMETERS_ON_MAIN_TABLE));
	}
}

<?php
namespace ExternalModules;
require_once dirname(dirname(dirname(__FILE__))) . '/classes/ExternalModules.php';

$module = ExternalModules::getModuleInstance($_GET['prefix']);

$data = json_decode(file_get_contents('php://input'), true);

$module->log($data['message'], $data['parameters']);

echo 'success';
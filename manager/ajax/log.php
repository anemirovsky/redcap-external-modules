<?php
namespace ExternalModules;

$data = json_decode(file_get_contents('php://input'), true);

if($data['noAuth']){
	define('NOAUTH', true);
}

require_once dirname(dirname(dirname(__FILE__))) . '/classes/ExternalModules.php';

$module = ExternalModules::getModuleInstance($_GET['prefix']);
$module->logAjax($data);

echo 'success';

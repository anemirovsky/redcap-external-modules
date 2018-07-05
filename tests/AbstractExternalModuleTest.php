<?php
namespace ExternalModules;
require_once 'BaseTest.php';

use \Exception;

class AbstractExternalModuleTest extends BaseTest
{
	/**
	 * @doesNotPerformAssertions
	 */
	function testCheckSettings_emptyConfig()
	{
		self::assertConfigValid([]);
	}

    function testCheckSettings_duplicateKeys()
    {
		self::assertConfigInvalid([
			'system-settings' => [
				['key' => 'some-key']
			],
			'project-settings' => [
				['key' => 'some-key']
			],
		], 'both the system and project level');

		self::assertConfigInvalid([
			'system-settings' => [
				['key' => 'some-key'],
				['key' => 'some-key'],
			],
		], 'system setting multiple times!');

		self::assertConfigInvalid([
			'project-settings' => [
				['key' => 'some-key'],
				['key' => 'some-key'],
			],
		], 'project setting multiple times!');
    }

	/**
	 * @doesNotPerformAssertions
	 */
	function testCheckSettingKey_valid()
	{
		self::assertConfigValid([
			'system-settings' => [
				['key' => 'key1']
			],
			'project-settings' => [
				['key' => 'key-two']
			],
		]);
	}

	function testCheckSettingKey_invalidChars()
	{
		$expected = 'contains invalid characters';

		self::assertConfigInvalid([
			'system-settings' => [
				['key' => 'A']
			]
		], $expected);

		self::assertConfigInvalid([
			'project-settings' => [
				['key' => '!']
			]
		], $expected);
	}

	function testIsSettingKeyValid()
	{
		$m = self::getInstance();

		$this->assertTrue($m->isSettingKeyValid('a'));
		$this->assertTrue($m->isSettingKeyValid('2'));
		$this->assertTrue($m->isSettingKeyValid('-'));
		$this->assertTrue($m->isSettingKeyValid('_'));

		$this->assertFalse($m->isSettingKeyValid('A'));
		$this->assertFalse($m->isSettingKeyValid('!'));
		$this->assertFalse($m->isSettingKeyValid('"'));
		$this->assertFalse($m->isSettingKeyValid('\''));
		$this->assertFalse($m->isSettingKeyValid(' '));
	}

	function assertConfigValid($config)
	{
		$this->setConfig($config);

		// Attempt to make a new instance of the module (which throws an exception on any config issues).
		new BaseTestExternalModule();
	}

	function assertConfigInvalid($config, $exceptionExcerpt)
	{
		$this->assertThrowsException(function() use ($config){
			self::assertConfigValid($config);
		}, $exceptionExcerpt);
	}

	function testSettingKeyPrefixes()
	{
		$normalValue = 1;
		$prefixedValue = 2;

		$this->setSystemSetting($normalValue);
		$this->setProjectSetting($normalValue);

		$m = $this->getInstance();
		$m->setSettingKeyPrefix('test-setting-prefix-');
		$this->assertNull($this->getSystemSetting());
		$this->assertNull($this->getProjectSetting());

		$this->setSystemSetting($prefixedValue);
		$this->setProjectSetting($prefixedValue);
		$this->assertSame($prefixedValue, $this->getSystemSetting());
		$this->assertSame($prefixedValue, $this->getProjectSetting());

		$this->removeSystemSetting();
		$this->removeProjectSetting();
		$this->assertNull($this->getSystemSetting());
		$this->assertNull($this->getProjectSetting());

		$m->setSettingKeyPrefix(null);
		$this->assertSame($normalValue, $this->getSystemSetting());
		$this->assertSame($normalValue, $this->getProjectSetting());

		// Prefixes with sub-settings are tested in testSubSettings().
	}

	function testSystemSettings()
	{
		$value = rand();
		$this->setSystemSetting($value);
		$this->assertSame($value, $this->getSystemSetting());

		$this->removeSystemSetting();
		$this->assertNull($this->getSystemSetting());
	}

	function testProjectSettings()
	{
		$projectValue = rand();
		$systemValue = rand();

		$this->setProjectSetting($projectValue);
		$this->assertSame($projectValue, $this->getProjectSetting());

		$this->removeProjectSetting();
		$this->assertNull($this->getProjectSetting());

		$this->setSystemSetting($systemValue);
		$this->assertSame($systemValue, $this->getProjectSetting());

		$this->setProjectSetting($projectValue);
		$this->assertSame($projectValue, $this->getProjectSetting());
	}

	function testSubSettings()
	{
		$_GET['pid'] = TEST_SETTING_PID;

		$groupKey = 'group-key';
		$settingKey = 'setting-key';
		$settingValues = [1, 2];

		$this->setConfig([
			'project-settings' => [
				[
					'key' => $groupKey,
					'type' => 'sub_settings',
					'sub_settings' => [
						[
							'key' => $settingKey
						]
					]
				]
			]
		]);

		$m = $this->getInstance();
		$m->setProjectSetting($settingKey, $settingValues);

		// Make sure prefixing makes the values we just set inaccessible.
		$m->setSettingKeyPrefix('some-prefix');
		$instances = $m->getSubSettings($groupKey);
		$this->assertEmpty($instances);
		$m->setSettingKeyPrefix(null);

		$instances = $m->getSubSettings($groupKey);
		$this->assertSame(count($settingValues), count($instances));
		for($i=0; $i<count($instances); $i++){
			$this->assertSame($settingValues[$i], $instances[$i][$settingKey]);
		}

		$m->removeProjectSetting($settingKey);
	}

	private function assertReturnedSettingType($value, $expectedType)
	{
		// We call set twice to make sure change detection is working properly, and we don't get an exception from trying to set the same value twice.
		$this->setProjectSetting($value);
		$this->setProjectSetting($value);

		$savedValue = $this->getProjectSetting();

		// We check the type separately from assertEquals() instead of using assertSame() because that wouldn't work for objects like stdClass.
		$savedType = gettype($savedValue);
		$this->assertEquals($expectedType, $savedType);
		$this->assertEquals($value, $savedValue);
	}

	function testSettingTypeConsistency()
	{
		$this->assertReturnedSettingType(true, 'boolean');
		$this->assertReturnedSettingType(false, 'boolean');
		$this->assertReturnedSettingType(1, 'integer');
		$this->assertReturnedSettingType(1.1, 'double');
		$this->assertReturnedSettingType("1", 'string');
		$this->assertReturnedSettingType([1], 'array');
		$this->assertReturnedSettingType([1,2,3], 'array');
		$this->assertReturnedSettingType(['a' => 'b'], 'array');
		$this->assertReturnedSettingType(null, 'NULL');

		$object = new \stdClass();
		$object->someProperty = true;
		$this->assertReturnedSettingType($object, 'object');
	}

	function testSettingTypeChanges()
	{
		$this->assertReturnedSettingType('1', 'string');
		$this->assertReturnedSettingType(1, 'integer');
	}

	function testArrayKeyPreservation()
	{
		$array = [1 => 2];
		$this->setProjectSetting($array);
		$this->assertSame($array, $this->getProjectSetting());
	}

	function testArrayNullValues()
	{
		$array = [0 => null];
		$this->setProjectSetting($array);
		$this->assertSame($array, $this->getProjectSetting());
	}

	function testSettingSizeLimit()
	{
		$data = str_repeat('a', ExternalModules::SETTING_SIZE_LIMIT);
		$this->setProjectSetting($data);
		$this->assertSame($data, $this->getProjectSetting());

		$this->assertThrowsException(function() use ($data){
			$data .= 'a';
			$this->setProjectSetting($data);
		}, 'value is larger than');
	}

	function testSettingKeySizeLimit()
	{
		$m = $this->getInstance();

		$key = str_repeat('a', ExternalModules::SETTING_KEY_SIZE_LIMIT);
		$value = rand();
		$m->setSystemSetting($key, $value);
		$this->assertSame($value, $m->getSystemSetting($key));
		$m->removeSystemSetting($key);

		$this->assertThrowsException(function() use ($m, $key){
			$key .= 'a';
			$m->setSystemSetting($key, '');
		}, 'key is longer than');
	}

	function testRequireAndDetectParameters()
	{
		$testRequire = function($param, $requireFunctionName){
			$this->assertThrowsException(function() use ($requireFunctionName){
				$this->callPrivateMethod($requireFunctionName);
			}, 'You must supply');

			$value = rand();
			$this->assertSame($value, $this->callPrivateMethod($requireFunctionName, $value));

			$_GET[$param] = $value;
			$this->assertSame($value, $this->callPrivateMethod($requireFunctionName, null));
			unset($_GET[$param]);
		};

		$testDetect = function($param, $detectFunctionName){
			$m = $this->getInstance();

			$this->assertSame(null, $m->$detectFunctionName(null));

			$value = rand();
			$this->assertSame($value, $m->$detectFunctionName($value));

			$_GET[$param] = $value;
			$this->assertSame($value, $m->$detectFunctionName(null));
			unset($_GET[$param]);
		};

		$testParameter = function($param, $functionNameSuffix) use ($testRequire, $testDetect){
			$testRequire($param, 'require' . $functionNameSuffix);
			$testDetect($param, 'detect' . $functionNameSuffix);
		};

		$testParameter('pid', 'ProjectId');
		$testParameter('event_id', 'EventId');
		$testParameter('instance', 'InstanceId');
	}

	function testDetectParamter_sqlInjection(){
		$_GET['pid'] = 'delete * from an_important_table';
		$this->assertEquals(0, $this->callPrivateMethod('detectParameter', 'pid'));
	}

	protected function getReflectionClass()
	{
		return $this->getInstance();
	}

	function testHasPermission()
	{
		$m = $this->getInstance();

		$testPermission = 'some_test_permission';
		$config = ['permissions' => []];

		$this->setConfig($config);
		$this->assertFalse($m->hasPermission($testPermission));

		$config['permissions'][] = $testPermission;
		$this->setConfig($config);
		$this->assertTrue($m->hasPermission($testPermission));
	}

	function testGetUrl()
	{
		$m = $this->getInstance();

		$base = APP_PATH_WEBROOT_FULL . 'external_modules/?prefix=' . $m->PREFIX . '&page=';
		$apiBase = APP_PATH_WEBROOT_FULL . 'api/?type=module&prefix=' . $m->PREFIX . '&page=';
		$moduleBase = ExternalModules::getModuleDirectoryUrl($m->PREFIX, $m->VERSION);

		$this->assertSame($base . 'test', $m->getUrl('test.php'));
		$this->assertSame($base . 'test&NOAUTH', $m->getUrl('test.php', true));
		$this->assertSame($apiBase . 'test', $m->getUrl('test.php', false, true));

		$pid = 123;
		$_GET['pid'] = $pid;
		$this->assertSame($base . 'test&pid=' . $pid, $m->getUrl('test.php'));

		$this->assertSame($moduleBase . 'images/foo.png?', $m->getUrl('images/foo.png'));
		$this->assertSame($apiBase . 'images%2Ffoo.png', $m->getUrl('images/foo.png', false, true));
	}

	function testIsPHPGreaterThan()
	{
		$isPHPGreaterThan = function($requiredVersion){
			return $this->callPrivateMethod('isPHPGreaterThan', $requiredVersion);
		};

		$versionParts = explode('.', PHP_VERSION);
		$lastNumber = $versionParts[2];

		$versionParts[2] = $lastNumber-1;
		$lowerVersion = implode('.', $versionParts);

		$versionParts[2] = $lastNumber+1;
		$higherVersion = implode('.', $versionParts);

		$this->assertTrue($isPHPGreaterThan(PHP_VERSION));
		$this->assertFalse($isPHPGreaterThan($higherVersion));
		$this->assertTrue($isPHPGreaterThan($lowerVersion));
	}
}

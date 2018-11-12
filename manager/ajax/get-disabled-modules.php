<?php
namespace ExternalModules;
require_once dirname(dirname(dirname(__FILE__))) . '/classes/ExternalModules.php';

?>

<table id='external-modules-disabled-table' class="table table-no-top-row-border">
	<?php

	if (!isset($_GET['pid'])) {
		$enabledModules = ExternalModules::getEnabledModules();
		$disabledModuleConfigs = ExternalModules::getDisabledModuleConfigs($enabledModules);
		foreach ($disabledModuleConfigs as $moduleDirectoryPrefix => $versions) {
			if (ExternalModules::isBundledModule($moduleDirectoryPrefix)) {
				unset($disabledModuleConfigs[$moduleDirectoryPrefix]);
			}
		}

		if (empty($disabledModuleConfigs)) {
			echo 'None';
		} else {
			foreach ($disabledModuleConfigs as $moduleDirectoryPrefix => $versions) {
				$config = reset($versions);
				
				// Determine if module is an example module
				$isExampleModule = ExternalModules::isExampleModule($moduleDirectoryPrefix, array_keys($versions));
	
				if(isset($enabledModules[$moduleDirectoryPrefix])){
					$enableButtonText = 'Change Version';
					$enableButtonIcon = 'fas fa-sync-alt';
					$deleteButtonDisabled = 'disabled'; // Modules cannot be deleted if they are currently enabled
				}
				else{
					$enableButtonText = 'Enable';
					$enableButtonIcon = 'fas fa-plus-circle';
					$deleteButtonDisabled = $isExampleModule ? 'disabled' : ''; // Modules cannot be deleted if they are example modules
				}

				if(empty($config)){
					$name = "None (config.json is missing for $moduleDirectoryPrefix)";
				}
				else{
					$name = $config['name'];
				}

				?>
				<tr data-module='<?= $moduleDirectoryPrefix ?>'>
					<td>
						<?= $name ?>
						<div class="cc_info">
						<?php if (isset($enabledModules[$moduleDirectoryPrefix])) { ?>
						(Current version: <?= $enabledModules[$moduleDirectoryPrefix] ?>)
						<?php } else { ?>
						(Not enabled)
						<?php } ?>
						</div>
					</td>
					<td>
						<select name="version">
							<?php
							foreach($versions as $version=>$config){
								echo "<option>$version</option>";
							}
							?>
						</select>
					</td>
					<td class="external-modules-action-buttons">
						<button class='btn btn-success btn-xs enable-button'><span class="<?=$enableButtonIcon?>" aria-hidden="true"></span> <?=$enableButtonText?></button> &nbsp;
						<button class='btn btn-defaultrc btn-xs disable-button' <?=$deleteButtonDisabled?>><span class="far fa-trash-alt" aria-hidden="true"></span> Delete module</button>
					</td>
				</tr>
				<?php
			}
		}
	} else {
		// Only get modules that have been made discoverable (but if a super user, display all)
		if (SUPER_USER) {
			$enabledModules = ExternalModules::getEnabledModules();
		} else {
			$enabledModules = ExternalModules::getDiscoverableModules();
		}
		// Sort modules by title
		$moduleTitles = array();
		foreach ($enabledModules as $prefix => $version) {
			$config = ExternalModules::getConfig($prefix, $version, $_GET['pid']);			
			$moduleTitles[$prefix] = trim($config['name']);
		}
		array_multisort($moduleTitles, SORT_REGULAR, $enabledModules);
		// Loop through each module to render
		foreach ($enabledModules as $prefix => $version) {
			$config = ExternalModules::getConfig($prefix, $version, $_GET['pid']);
			$enabled = ExternalModules::getProjectSetting($prefix, $_GET['pid'], ExternalModules::KEY_ENABLED);
			$system_enabled = ExternalModules::getSystemSetting($prefix, ExternalModules::KEY_ENABLED);
			$isDiscoverable = (ExternalModules::getSystemSetting($prefix, ExternalModules::KEY_DISCOVERABLE) == true);

			$name = $config['name'];
			if(empty($name)){
				continue;
			}

			if (!$enabled) {
			?>
				<tr data-module='<?= $prefix ?>' data-version='<?= $version ?>'>
					<td><div class='external-modules-title'>
                            <?= $name ?> <?= $version ?>
                            <?php if ($system_enabled) print "<span class='label label-warning badge badge-warning' title='This module is normally enabled globally for all projects'>Global Module</span>" ?>
                            <input type='hidden' name='version' value='<?= $version ?>'>
							<?php if ($isDiscoverable && SUPER_USER) { ?><span class="label label-info badge badge-info">Discoverable</span><?php } ?>
                        </div>
                        <div class='external-modules-description'>
                            <?php echo $config['description'] ? $config['description'] : '';?>
                        </div>
                        <div class='external-modules-byline'>
					<?php
						if (SUPER_USER && !isset($_GET['pid'])) {
							if ($config['authors']) {
								$names = array();
								foreach ($config['authors'] as $author) {
									$name = $author['name'];
									$institution = empty($author['institution']) ? "" : " <span class='author-institution'>({$author['institution']})</span>";
									if ($name) {
										if ($author['email']) {
											$names[] = "<a href='mailto:".$author['email']."'>".$name."</a> $institution";
										} else {
											$names[] = $name .  $institution;
										}
									}
								}
								if (count($names) > 0) {
									echo "by ".implode($names, ", ");
								}
							}
						}
					?>
					</div></td>
					<td class="external-modules-action-buttons">
						<?php if (SUPER_USER) { ?><button class='enable-button'>Enable</button><?php } ?>
					</td>
				</tr>
			<?php
			}
		}
	}

	?>
</table>

<script type="text/javascript">
	<?php
	if (isset($_GET['pid'])) {
		echo "var pid = ".json_encode($_GET['pid']).";";
	} else {
		echo "var pid = null;";
		if (isset($disabledModuleConfigs)) {
			echo "var disabledModules = ".json_encode($disabledModuleConfigs).";";
		}
	}
	?>
</script>
<?php ExternalModules::addResource(ExternalModules::getManagerJSDirectory().'get-disabled-modules.js'); ?>

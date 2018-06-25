<?php
namespace ExternalModules;
set_include_path('.' . PATH_SEPARATOR . get_include_path());
require_once dirname(__FILE__) . '/../../../classes/ExternalModules.php';
$extModLinks = ExternalModules::getLinks();
if (!empty($extModLinks)) {
?>
<script type="text/javascript">
	$(function () {
		var items = '';
		<?php
		foreach($extModLinks as $name=>$link){
			?>
			items += '<div class="cc_menu_item"><img src="<?php
				if (file_exists(ExternalModules::$BASE_PATH . 'images' . DS . $link['icon'] . '.png')) {
					echo ExternalModules::$BASE_URL . 'images/' . $link['icon'] . ".png";
				} else {
					echo APP_PATH_WEBROOT . 'Resources/images/' . $link['icon'] . ".png"; 
				}
				?>">';
			items += '&nbsp; ';
			items += '<a href="<?= $link['url'] ?>" target="<?= $link["target"]?>"><?= $name ?></a>';
			items += '</div>';

			<?php
		}
		?>
		if (items != '') {
			var menu = $('#control_center_menu');
			menu.append('<div class="cc_menu_divider"></div>');
			menu.append('<div class="cc_menu_section">');
			menu.append('<div class="cc_menu_header">External Modules</div>');
			menu.append(items);
			menu.append('</div>');
		}
	})
</script>
<?php
}
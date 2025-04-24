<?php
namespace PRC\Platform\Block_Area_Modules;

use DEFAULT_TECHNICAL_CONTACT;

class Plugin_Deactivator {

	public static function deactivate() {
		flush_rewrite_rules();

		wp_mail(
			DEFAULT_TECHNICAL_CONTACT,
			'PRC Block Area Modules Deactivated',
			'The PRC Block Area Modules plugin has been deactivated on ' . get_site_url()
		);
	}
}

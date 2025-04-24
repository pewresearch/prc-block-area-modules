<?php
namespace PRC\Platform\Block_Area_Modules;

use DEFAULT_TECHNICAL_CONTACT;

class Plugin_Activator {

	public static function activate() {
		flush_rewrite_rules();

		wp_mail(
			DEFAULT_TECHNICAL_CONTACT,
			'PRC Block Area Modules Activated',
			'The PRC Block Area Modules plugin has been activated on ' . get_site_url()
		);
	}
}

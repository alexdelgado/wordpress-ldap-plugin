<div class="wrap wordpress-ldap-options">
	<div id="icon-options-general" class="icon32"></div>
	<h2>Wordpress LDAP Plugin</h2>
	<?php settings_errors(); ?>
	<?php $active_tab = (isset($_GET['tab']) ? $_GET['tab'] : 'options'); ?>
	<h2 class="nav-tab-wrapper">
		<a href="?ldap_plugin_options&tab=options" class="nav-tab  <?php echo($active_tab == 'options' ? 'nav-tab-active' : ''); ?>">LDAP Options</a>
	</h2>
	<form method="post" action="options.php">
		<?php settings_fields('ldap_plugin_'. $active_tab); ?>
		<?php do_settings_sections('ldap_plugin_'. $active_tab); ?>
		<?php submit_button(); ?>
	</form>
</div>
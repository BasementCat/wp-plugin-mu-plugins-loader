<?php

namespace HM\MU_Plugins_Loader;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Plugin\PluginInterface;

class Plugin implements PluginInterface, EventSubscriberInterface {

	/**
	 * The composer class.
	 *
	 * @var Composer
	 */
	protected $composer;

	/**
	 * Called when the plugin is activated.
	 *
	 * @param Composer $composer The composer class.
	 */
	public function activate( Composer $composer, IOInterface $io ) {
		$this->composer = $composer;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array
	 */
	public static function getSubscribedEvents() {
		return array(
			'post-autoload-dump' => 'installLoader',
		);
	}

	/**
	 * Install the loader file.
	 *
	 * @return void
	 */
	public function installLoader() {
		$extra = $this->composer->getPackage()->getExtra();
		$hm_mu_plugins = $extra['mu-plugins'] ?? [];
		$hm_mu_plugins_path = $extra['mu-plugins-path'] ?? 'wp-content/mu-plugins';

		// Check for a custom install path for type:wordpress-muplugin.
		if ( ! empty( $extra['installer-paths'] ) ) {
			foreach ( $extra['installer-paths'] as $path => $types ) {
				if ( in_array( 'type:wordpress-muplugin', $types, true ) ) {
					$hm_mu_plugins_path = str_replace( '{$name}', '', $path );
					$hm_mu_plugins_path = trim( $hm_mu_plugins_path, '/' );
					break;
				}
			}
		}

		$dest_dir = sprintf(
			'%s/%s',
			dirname( $this->composer->getConfig()->get( 'vendor-dir' ) ),
			trim( $hm_mu_plugins_path, '/' )
		);
		$dest_file = $dest_dir . '/loader.php';

		// Discover installed mu-plugins if none are explicitly defined
		// "Explicitly defined" includes the presence of the "mu-plugins" key in "extra"
		if ( ! array_key_exists( 'mu-plugins', $extra ) ) {
			foreach ( scandir( $dest_dir ) as $mu_plugin_dir ) {
				$full_mu_plugin_dir = sprintf( '%s/%s', $dest_dir, $mu_plugin_dir );
				if ( is_dir( $full_mu_plugin_dir ) && ! str_starts_with( $mu_plugin_dir, '.' ) ) {
					foreach ( scandir( $full_mu_plugin_dir ) as $mu_plugin_file ) {
						$full_mu_plugin_file = sprintf( '%s/%s', $full_mu_plugin_dir, $mu_plugin_file );
						if ( is_file( $full_mu_plugin_file ) && str_ends_with( $full_mu_plugin_file, '.php' ) ) {
							$is_plugin_file = false;
							foreach ( file( $full_mu_plugin_file ) as $line) {
								if ( preg_match( '#^\s*\*\s*Plugin Name:#', $line ) ) {
									$is_plugin_file = true;
									break;
								}
							}
							if ( $is_plugin_file ) {
								$hm_mu_plugins[] = sprintf( '%s/%s', $mu_plugin_dir, $mu_plugin_file );
								break;
							}
						}
					}
				}
			}
		}

		$loader = file_get_contents( __DIR__ . '/loader.php' );

		// Replace the list of plugins if any present.
		if ( ! empty( $hm_mu_plugins ) ) {
			$loader = str_replace( '$hm_mu_plugins = []', '$hm_mu_plugins = ' . var_export( $hm_mu_plugins, true ), $loader );
		}

		file_put_contents( $dest_file, $loader );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Composer $composer Composer object.
	 * @param IOInterface $io Composer disk interface.
	 * @return void
	 */
	public function deactivate( Composer $composer, IOInterface $io ) {
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Composer $composer Composer object.
	 * @param IOInterface $io Composer disk interface.
	 * @return void
	 */
	public function uninstall( Composer $composer, IOInterface $io ) {
	}

}

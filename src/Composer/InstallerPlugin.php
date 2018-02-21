<?php
/**
 * User: Paul Coudeville <paul@metabolism.fr>
 */

namespace Metabolism\PackageActions\Composer;

use Composer\Composer,
	Composer\EventDispatcher\EventSubscriberInterface,
	Composer\Installer\PackageEvents,
	Composer\IO\IOInterface,
	Composer\Plugin\PluginInterface,
	Composer\Installer\PackageEvent,
	Composer\Script\ScriptEvents;

use Metabolism\PackgeActions\Composer\Manager\FileManager;

/**
 * Class Plugin
 * Allows the root package to have extra-functionnalities natively.
 *
 * @package ComposerPlugin\Plugin
 */
class InstallerPlugin implements PluginInterface, EventSubscriberInterface
{
    /** @var Composer $composer */
    protected $composer;

    /** @var IOInterface $io */
    protected $io;


    /**
     * Register Composer Event listeners
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::PRE_INSTALL_CMD       => ['onPostInstallCmd'],
            PackageEvents::POST_PACKAGE_INSTALL => ['onPostPackageInstall'],
            PackageEvents::POST_PACKAGE_UPDATE  => ['onPostPackageUpdate']
        ];
    }


    /**
     * Plugin activation function
     *
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }


    /**
     * Perform extra file actions according to Composer extra section in package.
     *
     * @param PackageEvent $event
     */
    public function onPostPackageInstall(PackageEvent $event)
    {

	    /** @var Package $installedPackage */
	    $installedPackage = $event->getOperation()->getPackage();
	    $this->operate($event, 'install', $installedPackage);
    }


    /**
     * Perform extra file actions according to Composer extra section in package.
     *
     * @param PackageEvent $event
     */
    public function onPostPackageUpdate(PackageEvent $event)
    {
	    /** @var Package $updatedPackage */
	    $updatedPackage = $event->getOperation()->getTargetPackage();
	    $this->operate($event, 'update', $updatedPackage);
    }


	/**
	 * Perform extra file actions according to Composer extra section in package.
	 *
	 * @param PackageEvent $event
	 * @param $type
	 * @param $package
	 */
    private function operate($event, $type, $package){

	    /** @var Package $root_pkg */
	    $extras = array_replace_recursive( $package->getExtra(), $event->getComposer()->getPackage()->getExtra());

	    // retro compat
	    if( $type == "install" )
	    	$has_actions = isset( $extras["file-management"] ) || isset( $extras["post-package-".$type] );
	    else
		    $has_actions = isset( $extras["post-package-".$type] );

	    if ( $has_actions )
	    {
		    /** @var FileManager $fm */
		    $fm = new FileManager( $this->io );

		    // retro compat
		    if( $type == "install" )
			    $actions = isset( $extras["file-management"] ) ? $extras["file-management"] : $extras["post-package-install"];
		    else
			    $actions = $extras["post-package-".$type];

		    foreach ( $actions as $action => $pkg_names )
		    {
			    if ( array_key_exists( $package->getName(), $pkg_names ) )
			    {
				    if ( method_exists( $fm, $action ) )
				    {
					    try
					    {
						    $fm->$action( $pkg_names[$package->getName()], $package, $event->getIO() );
					    }
					    catch ( \Exception $e )
					    {
						    $this->io->write( "<error>Error: " . $action . " action on " . $package->getName() . " : \n" . $e->getMessage() . "</error>" );
					    }
				    }
				    else
				    {
					    $this->io->writeError( "<warning> Skipping extra folder action : " . $action . ", method does not exist.</warning>" );
				    }
			    }
		    }
	    }
    }
}

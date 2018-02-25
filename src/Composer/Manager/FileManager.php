<?php

namespace Metabolism\PackageActions\Composer\Manager;

use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Script\Event;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Class FileManager
 *
 * File Manager
 */
class FileManager
{
    private $io;


    /**
     * FileManager constructor.
     *
     * @param IOInterface $io
     */
    public function __construct(IOInterface $io)
    {
        $this->io = $io;
    }


    /**
     * File Copy
     *
     * @param $files
     * @param Package $package
     * @param $io
     * @internal param Event $event
     */
    public function copy($files, $package, $io)
    {
	    $fileSystem = new FileSystem();
        $packageDir = 'vendor' . DIRECTORY_SEPARATOR . $package->getName();

        foreach ( $files as $source => $destination )
        {
            if ( $fileSystem->isAbsolutePath( $source ) )
            {
                throw new \InvalidArgumentException( "Invalid target path '$source' for package'{$package->getName()}'." . ' It must be relative.' );
            }

            if ( $fileSystem->isAbsolutePath( $destination ) )
            {
                throw new \InvalidArgumentException( "Invalid link path '$destination' for package'{$package->getName()}'." . ' It must be relative.' );
            }

            $source = $packageDir . DIRECTORY_SEPARATOR . $source;
            $destination   = getcwd() . DIRECTORY_SEPARATOR . $destination;

            if ( !$fileSystem->exists($destination) )
            {
	            if ( is_dir( $source ) )
	            {
		            try
		            {
			            $fileSystem->mkdir($destination);

			            $directoryIterator = new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS);
			            $iterator = new \RecursiveIteratorIterator($directoryIterator, \RecursiveIteratorIterator::SELF_FIRST);

			            foreach ($iterator as $item)
			            {
				            if ($item->isDir())
				            {
					            $fileSystem->mkdir($destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
				            }
				            else
				            {
					            $fileSystem->copy($item, $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
				            }
			            }
		            }
		            catch ( IOException $e )
		            {
			            throw new \InvalidArgumentException( sprintf( '<error>Could not copy %s</error>', $source . " \n" . $e->getMessage() ) );
		            }
	            }
	            else
	            {
		            try
		            {
			            $fileSystem->copy( $source, $destination );

			            $io->write( sprintf( '  - Copying <comment>%s</comment> to <comment>%s</comment>.', str_replace( getcwd(), '', $source ), str_replace( getcwd(), '', $destination ) ) );
		            }
		            catch ( IOException $e )
		            {
			            throw new \InvalidArgumentException( sprintf( '<error>Could not copy %s</error>', $source . " \n" . $e->getMessage() ) );
		            }
	            }
            }
        }
    }


    /**
     * @param Event $event
     * @param       $id
     * @return array
     */
    protected function get(Event $event, $id)
    {
        $options = $event->getComposer()->getPackage()->getExtra();

        $symlinks = [];

        if ( isset( $options[$id] ) && is_array( $options[$id] ) )
        {
            $symlinks = $options[$id];
        }

        return $symlinks;
    }


    /**
     * Folder removal
     *
     * @param $files
     * @param $package
     * @param $io
     * @internal param Event $event
     */
    public function remove($files, $package, $io)
    {
        $fs = new FileSystem();

        foreach ( $files as $file )
        {
            if ( $fs->isAbsolutePath( $file ) )
            {
                throw new \InvalidArgumentException( "Invalid target path '$file' for package'{$package->getName()}'." . ' It must be relative.' );
            }

            $file = getcwd() . DIRECTORY_SEPARATOR . $file;

            try
            {
                if ( $fs->exists( $file ) )
                {
                    $fs->remove( $file );
                    $io->write( sprintf( '  - Removing directory <comment>%s</comment>.', str_replace( getcwd(), '', $file ) ) );
                }
                elseif ( $fs->exists( $file ) )
                {
                    $fs->remove( $file );
                    $io->write( sprintf( '  - Removing file <comment>%s</comment>.', str_replace( getcwd(), '', $file ) ) );
                }


            } catch ( IOException $e )
            {
                throw new \InvalidArgumentException( sprintf( '<error>Could not remove %s</error>', $file ) );
            }
        }
    }


    /**
     * Folder Creation
     *
     * @param array   $files
     * @param Package $package
     */
    public function create($files, $package, $io)
    {
        $fs = new Filesystem();

        foreach ( $files as $file => $permissions )
        {
            if ( $fs->isAbsolutePath( $file ) )
            {
                throw new \InvalidArgumentException( "Invalid target path '$file' It must be relative." );
            }

            $file = getcwd() . DIRECTORY_SEPARATOR . $file;

            try
            {
                if ( !$fs->exists( $file ) )
                {
                    $io->write( sprintf( '  - Creating directory <comment>%s</comment>.', str_replace( getcwd(), '', $file ) ) );

                    $oldmask = umask( 0 );
                    $fs->mkdir( $file, octdec( $permissions ) );
                    umask( $oldmask );
                }
            }
            catch ( IOException $e )
            {
                throw new \InvalidArgumentException( sprintf( '<error>Could not create %s</error>', $e ) );
            }
        }
    }


    /**
     * @param array       $files
     * @param Package     $package
     * @param IOInterface $io
     */
    public function symlink($files, $package, $io)
    {
        $fs         = new Filesystem();
        $packageDir = DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $package->getName();

        foreach ( $files as $target => $links )
        {
            if ( $fs->isAbsolutePath( $target ) )
            {
                throw new \InvalidArgumentException( "Invalid symlink target path '$target' for package '{$package->getName()}'." . ' It must be relative.' );
            }

            $links = (array)$links;

            foreach ($links as $link)
            {
                if ( $fs->isAbsolutePath( $link ) )
                {
                    throw new \InvalidArgumentException( "Invalid symlink link path '$link' for package '{$package->getName()}'." . ' It must be relative.' );
                }

                $targetPath = getcwd() . $packageDir . DIRECTORY_SEPARATOR . $target;
                $linkPath   = getcwd() . DIRECTORY_SEPARATOR . $link;

                if ( !$fs->exists( $targetPath ) )
                {
                    throw new \RuntimeException( "The target path '$targetPath' for package'{$package->getName()}' does not exist." );
                }

                if ( $fs->exists( $linkPath ) )
                    $fs->remove( $linkPath );

                $io->write( sprintf( "  - Symlinking <comment>%s</comment> to <comment>%s</comment>", str_replace( getcwd(), '', $targetPath ), str_replace( getcwd(), '', $linkPath ) ) );

                $fs->symlink( $targetPath, $linkPath, true );
            }
        }
    }

}

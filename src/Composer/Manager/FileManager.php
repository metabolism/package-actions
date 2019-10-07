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
		            }
		            catch ( IOException $e )
		            {
			            throw new \InvalidArgumentException( sprintf( '<error>Could not copy %s</error>', $source . " \n" . $e->getMessage() ) );
		            }
	            }

	            $io->write( sprintf( '  - Copying <comment>%s</comment> to <comment>%s</comment>.', str_replace( getcwd(), '', $source ), str_replace( getcwd(), '', $destination ) ) );
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

        foreach ( $files as $origin => $targets )
        {
            if ( $fs->isAbsolutePath( $origin ) )
            {
                throw new \InvalidArgumentException( "Invalid symlink origin path '$origin' for package '{$package->getName()}'." . ' It must be relative.' );
            }

            $targets = (array)$targets;

            foreach ($targets as $target)
            {
                if ( $fs->isAbsolutePath( $target ) )
                {
                    throw new \InvalidArgumentException( "Invalid symlink target path '$target' for package '{$package->getName()}'." . ' It must be relative.' );
                }

                $originPath = getcwd() . $packageDir . DIRECTORY_SEPARATOR . $origin;
                $targetPath = getcwd() . DIRECTORY_SEPARATOR . $target;

	            $relativeOriginPath = $this->getRelativePath($targetPath, $originPath);

                if ( !$fs->exists( $originPath ) )
                {
                    throw new \RuntimeException( "The origin path '$originPath' for package'{$package->getName()}' does not exist." );
                }

                if ( $fs->exists( $targetPath ) )
                    $fs->remove( $targetPath );

                $io->write( sprintf( "  - Symlinking <comment>%s</comment> to <comment>%s</comment>", str_replace( getcwd(), '', $originPath ), str_replace( getcwd(), '', $targetPath ) ) );

                $fs->symlink( $relativeOriginPath, $targetPath, true );
            }
        }
    }


	/**
	 * @param string      $from
	 * @param Package     $to
	 */
	function getRelativePath($from, $to)
	{
		// some compatibility fixes for Windows paths
		$from = is_dir($from) ? rtrim($from, '\/') . '/' : $from;
		$to   = is_dir($to)   ? rtrim($to, '\/') . '/'   : $to;
		$from = str_replace('\\', '/', $from);
		$to   = str_replace('\\', '/', $to);

		$from     = explode('/', $from);
		$to       = explode('/', $to);

		$relPath  = $to;

		foreach($from as $depth => $dir) {
			// find first non-matching dir
			if($dir === $to[$depth]) {
				// ignore this directory
				array_shift($relPath);
			} else {
				// get number of remaining dirs to $from
				$remaining = count($from) - $depth;
				if($remaining > 1) {
					// add traversals up to first matching dir
					$padLength = (count($relPath) + $remaining - 1) * -1;
					$relPath = array_pad($relPath, $padLength, '..');
					break;
				} else {
					$relPath[0] = '.'.DIRECTORY_SEPARATOR . $relPath[0];
				}
			}
		}
		
		return implode(DIRECTORY_SEPARATOR, $relPath);
	}
}

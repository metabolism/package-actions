# Package actions
 
add copy/create/remove/rename/symlink per package actions 

available for both `post-package-install` and `post-package-update`

## Installation

    composer require metabolism/package-actions

## Example

    "extra": {
        "post-package-install": {
            "create": {
                "vendor/package-name": {
                    "public/uploads": 777,
                    "public/download": 655
            },
            "copy": {
                "vendor/package-name": {
                    "folder/file.php": "public/myfile.php",
                }
            },
            "symlink": {
                "vendor/package-name": {
                    "folder/file.php": "public/myfile.php",
                }
            }
            "rename": {
                "vendor/package-name": {
                    "public/myfile.php": "public/file.php",
                }
            }
            "remove": {
                "vendor/package-name": ["public/myfile.php"]
                }
            }
        }
    }

### Create

     destination : permissions
  
  `destination` is relative to the composer.json file
  
  `permissions` use umask, only 3 last digits

### Copy / Symlink

     source : destination
  
  `source` is relative to the package folder

  `destination` is relative to the composer.json file

### Rename

     source : destination
  
  `source` and `destination` are relative to the composer.json file

### Remove

     [destination]
  
  `destination` is relative to the composer.json file
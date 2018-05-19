# Package actions
 
add copy/create/remove/symlink per package actions 

available for both `post-package-install` and `post-package-update`

## Installation

    composer require metabolism/package-actions

## Example

    "extra": {
        "post-package-install": {
            "create": {
                "vendor/package-name": {
                    "web/uploads": 777,
                    "web/download": 655
            },
            "copy": {
                "vendor/package-name": {
                    "folder/file.php": "web/myfile.php",
                }
            },
            "symlink": {
                "vendor/package-name": {
                    "folder/file.php": "web/myfile.php",
                }
            }
            "remove": {
                "vendor/package-name": ["web/myfile.php"]
                }
            }
        }
    }

### Create

     destination : permissions
  
  destination is relative to the composer.json file
  
  permissions use umask, only 3 last digits

### Copy / Symlink

     source : destination
  
  source is relative to the package folder
  
  destination is relative to the composer.json file

### Remove

     [destination]
  
  destination is relative to the composer.json file
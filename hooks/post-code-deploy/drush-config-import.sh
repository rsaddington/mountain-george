#!/bin/sh
#
# Cloud Hook: drush-config-import
#
# Run drush config-import in the target environment. This script works as
# any Cloud hook.


# Map the script inputs to convenient names.
site=$1
target_env=$2
drush_alias=$site'.'$target_env

# Execute a standard drush command.
drush @$drush_alias config-import -y

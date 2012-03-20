#!/usr/bin/php
<?php

###########################################################
# Config section
# Please replace XXX with your credentials
# No slashes after paths please
###########################################################

// Paths
define('SCRIPT_DIR_PATH', 'path/to/xcodebuildprovisioning');

// Xcode stuff
// define('TARGET_SDK', 'iphoneos');

// If left commented script will prompt you for input
// Useful when using with CI
// define('DEVELOPER_IDENTITY', '');
// define('PROV_PROFILE_PATH', '');
// define('APPLICATION_IDENTIFIER', '');

###########################################################
# Launch
###########################################################

require_once(SCRIPT_DIR_PATH . '/launch.php');
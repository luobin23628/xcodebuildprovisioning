<?php

###########################################################
# xcodebuild run with specifying provisioning profile
# Created by Misha Karpenko on 15/11/11
# https://github.com/mishakarpenko/testflightappdeploy
# http://mishakarpenko.com
###########################################################

ini_set('display_errors', '0');

###########################################################
# Helper functions
###########################################################

$logPath = './' . date('Y.m.d-H.i.s') . '.log';

/**
 * @param string $message
 */
function log_message($message)
{
	$message = PHP_EOL . $message . PHP_EOL;
	echo $message;

	global $logPath;
	$handle = fopen($logPath, 'a');
	if ($handle) fwrite($handle, $message);
	fclose($handle);
}

/**
 * @param string $message
 */
function die_with_error($message)
{
	log_message('Error: ' . $message);
	die();
}

function exec_command($command, $returnAsString, $dieIfEmpty = TRUE, $echoCommand = FALSE)
{
	if ($echoCommand) log_message($command);

	$output = array();
	exec($command, $output);

	if ($dieIfEmpty && empty($output)) {
		die_with_error('"' . $command . '" output is empty');
	}

	if ($returnAsString) {
		$output = implode('', $output);
	}
	return $output;
}

###########################################################
# Dirs
###########################################################

// Your Xcode project directory
$projectDirPath = getcwd();

// Get project name from *.xcodeproj file
$projectName;
$handle = opendir($projectDirPath);
if ( ! $handle) die_with_error('Couldn\'t read project directory');
while (($fileName = readdir($handle)) !== false) {
	$matches = array();
	preg_match('/(?P<projectName>.*)\.xcodeproj/', $fileName, $matches);
	if (isset($matches['projectName'])) {
		$projectName = $matches['projectName'];
	}
}
closedir($handle);
if ( ! isset($projectName)) die_with_error('Couldn\'t find *.xcodeproj file');

// Get full script dir path
$scriptFullPath = exec_command('cd ' . SCRIPT_DIR_PATH . ' && pwd', TRUE);

// Build dirs
$buildDirPath = $scriptFullPath . '/builds';
$projectBuildDirPath = $buildDirPath . '/' . $projectName;
$tempBuildDirPath = $projectDirPath . '/build';

// IPA
$ipaPath = $projectBuildDirPath . '/' . $projectName . '.ipa';

// Logs
$logDirPath = $projectBuildDirPath . '/logs';
$logPath = $logDirPath . '/' . date('Y.m.d-H.i.s') . '.log';

if ( ! file_exists($buildDirPath)) mkdir($buildDirPath);
if ( ! file_exists($projectBuildDirPath)) mkdir($projectBuildDirPath);
if ( ! file_exists($logDirPath)) mkdir($logDirPath);

###########################################################
# Start
###########################################################

log_message("\033[32mxcodebuildprovisioning\033[37m\n$projectName");

###########################################################
# Identity
#
# Script will run
#
# security find-identity -v -p codesigning
#
# to list all of the codesigning identities found on
# your machine.
###########################################################

$developerIdentity;

if (defined('DEVELOPER_IDENTITY')) {
	$developerIdentity = DEVELOPER_IDENTITY;
} else {
	// Get developer identities
	$developerIdentities = array();

	// Get codesigning identities from keychain
	$output = exec_command('security find-identity -v -p codesigning');
		
	foreach ($output as $item) {
		$matches = array();
		preg_match('/"(?P<identity>.*)"/', $item, $matches);
		if (isset($matches['identity'])) {
			$developerIdentities[] = $matches['identity'];
		}
	}
	if (empty($developerIdentities)) die_with_error('Couldn\'t find any developer identity');

	// Choose identity
	log_message("Choose your identity:");
	for ($i = 0; $i < count($developerIdentities); $i++) {
		echo ($i + 1) . ') ' . $developerIdentities[$i] . PHP_EOL;
	}

	$developerIdentityChoice = -1;
	while ($developerIdentityChoice <= 0 ||
		   $developerIdentityChoice > count($developerIdentities)) {
		echo 'Please enter a number (from 1 to ' . count($developerIdentities) . '): ';
		$developerIdentityChoice = trim(fgets(STDIN));
	}

	$developerIdentity = $developerIdentities[intval($developerIdentityChoice) - 1];
}

log_message('Using identity "' . $developerIdentity . '"');

###########################################################
# Provisioning profile
#
# Script will look in 
# ~/Library/MobileDevice/Provisioning\ Profiles/ dir
# and try to find PEM of your identity certificate in
# every file.
#
# If not found - you will be prompted to choose one by team
# identifier.
###########################################################

$provProfilePath;

if (defined('PROV_PROFILE_PATH')) {
	$provProfilePath = PROV_PROFILE_PATH;	
} else {
	// Get pem
	$output = exec_command('security find-certificate -c "' . $developerIdentity . '" -p');

	// Remove BEGIN and END OF CERTIFICATE
	unset($output[count($output) - 1]);
	unset($output[0]);
	$pem = implode('', $output);

	$provProfilesDirPath = exec_command('cd ~/Library/MobileDevice/Provisioning\ Profiles/ && pwd', TRUE);

	// Search for appropriate provisioning profile
	$provProfiles = array();
	
	$handle = opendir($provProfilesDirPath);
	if ( ! $handle) die_with_error('Couldn\'t read provisioning profiles directory');
	
	while (($fileName = readdir($handle)) !== false) {
		if (pathinfo($fileName, PATHINFO_EXTENSION) != 'mobileprovision') continue;

		// Prepare contents
		$contents = file_get_contents($provProfilesDirPath . '/' . $fileName);
		$contents = str_replace("\t", '', $contents);
		$contents = str_replace("\n", '', $contents);

		// Collect team identifier
		$teamIdentifierPos = strpos($contents, 'TeamIdentifier');
		$teamIdentifierPos1 = strpos($contents, '<string>', $teamIdentifierPos) + strlen('<string>');
		$teamIdentifierPos2 = strpos($contents, '</string>', $teamIdentifierPos1);
		$teamIdentifier = substr($contents, $teamIdentifierPos1, $teamIdentifierPos2 - $teamIdentifierPos1);
		$provProfiles[$teamIdentifier] = $provProfilesDirPath . '/' . $fileName;

		$found = strpos($contents, $pem);
		if ($found !== false) {
			$provProfilePath = $provProfilesDirPath . '/' . $fileName;
		}
	}
	closedir($handle);

	if (empty($provProfiles)) die_with_error('No provisioning profiles found');

	if (empty($provProfilePath)) log_message('Couldn\'t automatically find provisioning profile');
		
	if (empty($provProfilePath) && defined('TEAM_IDENTIFIER')) {
		if (isset($provProfiles[TEAM_IDENTIFIER])) {
			$provProfilePath = $provProfiles[TEAM_IDENTIFIER];
		} else {
			log_message('Also couldn\'t find provisioning profile with specified TEAM_IDENTIFIER');
		}
	}

	if (empty($provProfilePath)) {
		// Choose team id
		log_message('Please select appropriate team identifier');
		$i = 0;
		foreach ($provProfiles as $teamIdentifier => $path) {
			echo ++$i . ') ' . $teamIdentifier . PHP_EOL;
		}

		$provProfilesChoice = -1;
		while ($provProfilesChoice <= 0 ||
			   $provProfilesChoice > count($provProfiles)) {
			echo 'Please enter a number (from 1 to ' . count($provProfiles) . '): ';
			$provProfilesChoice = trim(fgets(STDIN));
		}
		$provProfilesChoice = intval($provProfilesChoice) - 1;

		$i = 0;
		foreach ($provProfiles as $teamIdentifier => $path) {
			if ($i == $provProfilesChoice) {
				$provProfilePath = $path;
				break;				
			}
			$i++;
		}
	}
}

if (empty($provProfilePath)) die_with_error('Couldn\'t find provisioning profile');

log_message('Using provisioning profile:' . PHP_EOL . $provProfilePath);

###########################################################
# Build project
###########################################################

$targetSDK;
if (defined('TARGET_SDK')) {
	$targetSDK = TARGET_SDK;
} else {
	$targetSDK = 'iphoneos';
}

log_message("\033[32mBuilding project...\033[37m");
exec_command('xcodebuild -target "' . $projectName . '" -sdk "' . $targetSDK . '" -configuration Release CONFIGURATION_BUILD_DIR="' . $projectBuildDirPath . '" >> "' . $logPath . '"', FALSE, FALSE, TRUE);

// Make app filename
$appPath;
$handle = opendir($projectBuildDirPath);
if ( ! $handle) die_with_error('Couldn\'t read project build dir');
while (($fileName = readdir($handle)) !== false) {
	if (pathinfo($fileName, PATHINFO_EXTENSION) != 'app') continue;
	$appPath = $projectBuildDirPath . '/' . $fileName;
}
closedir($handle);
if ( ! file_exists($appPath)) die_with_error('Didn\'t create APP file');

// Clean temp files
exec_command('rm -rf ' . $tempBuildDirPath, FALSE, FALSE);

###########################################################
# Sign app
###########################################################

log_message("\033[32mPackaging and signing...\033[37m");
exec_command('/usr/bin/xcrun -sdk "' . $targetSDK . '" PackageApplication -v "' . $appPath . '" -o "' . $ipaPath . '" --sign "' . $developerIdentity . '" --embed "' . $provProfilePath . '" >> "' . $logPath . '"', FALSE, FALSE, TRUE);
if ( ! file_exists($ipaPath)) die_with_error('Didn\'t create IPA file');

log_message("\033[32mDone.\033[37m");
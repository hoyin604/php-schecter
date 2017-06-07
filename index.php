<?php
// this file is the entry point of this project
// with the purpose of setting up the config

$projectRoot_fsRef = __DIR__;
// the root folder of all kind

$app_tag = include $projectRoot_fsRef.'/tag.return.php';
// gover the deployment location
// $app_tag:local|dev|live or local_mynotebook / live_24 etc.
// live  | live_*  : the final production location
// dev   | dev_*   : the general testing / development location
// local | local_* : the local testing / development location
// only this file should be different in each deployment location
// config setting which are different in different location should base on this value

require_once $projectRoot_fsRef.'/lib/_psch.inc.php';
if (defined('_framework')) { // '_framework' will be defined when successfully included framework file

  // param setting
  // params are varibles pass from the outside world by:
  // - queryString
  // - form post
  // - special meaning's url fragment
  $param = array(
    'appName' => array(
  		'__default__' => 'defaultApp',
      'accept_preg' => '/^(defaultApp){0,255}$/', // possible appName
    ),
    'finalType' => array(
  		'__default__' => 'html',
      'accept_preg' => '/^(html|json){0,255}$/', // possible final output type
    ),
    'controller_loc_is' => array(
      '__default__' => 0,
    ),
  );

  // general configs here (same for all location)
  ini_set("session.cookie_httponly",1);
  ini_set("session.use_only_cookies",1);

  // configs for specific deploy location e.g. database related value
	if ($app_tag == 'live') {
		ini_set("display_errors",0);
		ini_set('display_startup_errors', 0);
	} else {
		ini_set("display_errors",1);
		ini_set('display_startup_errors', 1);
	}
  init_m($param);
  if (isset($m)) {
    include psch__getController('app');
		// $m->seta('root_webRef', '');
		// switch ($app_tag) {
		// 	case 'local':
		// 		$m->seta('root_webRef', "/atlantis_dev");
		// 		break;
		// 	case 'dev':
		// 		$m->seta('root_webRef', "/projects/atlantis/dev");
		// 		break;
		// 	case 'live':
		// 		$m->seta('root_webRef', "");
		// 		break;
		// }
	  // include psch__getController('type');
  }
}

?>

<?php if (!isset($projectRoot_fsRef)) exit;

define("_framework", "psch__");

function init_m($param = array()) {
	$r = false;
	global $app_tag, $m, $projectRoot_fsRef, $appRoot_fsRef, $typeRoot_fsRef, $locRoot_fsRef;
	include psch__safeLocalPHPFile('projectRoot', 'lib/NodeTree.class');
	if (class_exists('NodeTree')) {
		$m = new NodeTree(array(
			'param' => array(
				'appName' => array(
					'__default__' => 'defaultApp',
					'type' => 'key',
					'accept_preg' => '/^(defaultApp){0,255}$/',
					'searchRequest_is' => true,
					'searchUrl_is' => true,
				),
				'finalType' => array(
					'__default__' => 'html',
					'type' => 'key',
					'accept_preg' => '/^(html){0,255}$/',
					'searchRequest_is' => true,
					'searchUrl_is' => true,
				),
			)
		));
		$m->mergeArrayR('param', $param);
		// handle request_webRef
		$request_webRef = NodeTree::getaStringIfNotEmpty($_REQUEST, 'request_webRef', '');
		$request_webRef = NodeTree::toSafePath($request_webRef);
		$m->seta('request/url', $request_webRef);
		if ($request_webRef !== '') {
			$requestNode_keyList = explode('/', $request_webRef);
		} else {
			$requestNode_keyList = array();
		}
		$m->seta('request/remainNode_keyList', $requestNode_keyList);

		psch__processParam();

		// get the appName finally and setup app controller path
		$appName = $m->geta('param/appName', 'key');
		$appRoot_fsRef = $projectRoot_fsRef;
		$controller = '__notFound__';
		$test_path1 = $projectRoot_fsRef.'/'.$appName.'/__default__.php';
		$test_path2 = $projectRoot_fsRef.'/'.$appName.'.php';
		if (is_file($test_path1)) {
			$appRoot_fsRef = $projectRoot_fsRef.'/'.$appName;
			$controller = '__default__';
		} elseif (is_file($test_path2)) {
			$controller = $appName;
		}
		$m->seta('controller/app', $controller);

		// setup type controller
		$finalType = $m->geta('param/finalType', 'key');
		$typeRoot_fsRef = $appRoot_fsRef;
		$controller = '__notFound__';
		$test_path1 = $appRoot_fsRef.'/type/'.$finalType.'/__default__.php';
		$test_path2 = $appRoot_fsRef.'/type/'.$finalType.'.php';
		if (is_file($test_path1)) {
			$typeRoot_fsRef = $appRoot_fsRef.'/type/'.$finalType;
			$controller = '__default__';
		} elseif (is_file($test_path2)) {
			$typeRoot_fsRef = $appRoot_fsRef.'/type';
			$controller = $finalType;
		}
		$m->seta('controller/type', $controller);

		// setup loc controller
		$remainNode_keyList = $m->geta('request/remainNode_keyList', 'array', array());
		$locRoot_fsRef = $appRoot_fsRef;
		$controller = 'index';
		if (is_file($appRoot_fsRef.'/loc/index.php')) {
			$locRoot_fsRef = $appRoot_fsRef.'/loc';
		}
		$c = '';
		$lastDir_is = false;
		foreach ($remainNode_keyList as $i => $pathNode_name) {
			$t = $c.(($i>0)?'/':'').$pathNode_name;
			$file_is = is_file($locRoot_fsRef.'/'.$t.'.php');
			$dir_is  = is_file($locRoot_fsRef.'/'.$t.'/index.php');
			if ($file_is) {
				$c = $t;
				break;
			} elseif ($dir_is) {
				$c = $t;
				$lastDir_is = $dir_is;
			} else {
				break;
			}
		}
		if ($c !== '') {
			$controller = $c;
			if ($dir_is || ($lastDir_is && !$file_is && !$dir_is)) $controller .= '/index';
		}
		$m->seta('controller/loc', $controller);
		if ($c !== '') {
			if (!$file_is && !$dir_is) {
				// the last one is incorrect node
				$m->seta('request/remainNode_keyList', array_slice($remainNode_keyList, $i, count($remainNode_keyList) - $i));
			} elseif ($file_is && $i < count($remainNode_keyList)-1) {
				$m->seta('request/remainNode_keyList', array_slice($remainNode_keyList, $i+1, count($remainNode_keyList) - $i - 1));
			} else {
				$m->seta('request/remainNode_keyList', array());
			}
		}

		$r = true;
	}
	return $r;
}

// common function

function psch__getController($type = '') {
	global $app_tag, $m, $projectRoot_fsRef, $appRoot_fsRef, $typeRoot_fsRef, $locRoot_fsRef;

	$r = $projectRoot_fsRef.'/__notFound__.php';
	$root_fsRef = $projectRoot_fsRef;
	if ($type === 'app' && psch_stringIfNotEmpty_is($appRoot_fsRef)) {
		$root_fsRef = $appRoot_fsRef;
	} elseif ($type === 'type' && psch_stringIfNotEmpty_is($typeRoot_fsRef)) {
		$root_fsRef = $typeRoot_fsRef;
	} elseif ($type === 'loc' && psch_stringIfNotEmpty_is($locRoot_fsRef)) {
		$root_fsRef = $locRoot_fsRef;
	}

	$test_path = $root_fsRef.'/'.$m->geta('controller/'.$type, 'path').'.php';
	if (is_file($test_path)) {
		$r = $test_path;
	}
	return $r;
}

function psch__processParam($param_name = '') {
	global $m;
	if ($param_name === '') {
		$param_list = array_keys($m->geta('param', 'assoArray', array()));
	} else {
		$param_list = array($param_name);
	}
	foreach ($param_list as $i => $param_name) {
		$accept_preg      = $m->geta('param/'.$param_name.'/accept_preg', 'string', '');
		$searchRequest_is = $m->geta('param/'.$param_name.'/searchRequest_is', 'boolean', true);
		$searchUrl_is     = $m->geta('param/'.$param_name.'/searchUrl_is', 'boolean', true);
		$type             = $m->geta('param/'.$param_name.'/type', 'key', 'string'); // string|integer
		$val = '__default__';
		if ($accept_preg != '') {
			// get param in url first
			if ($searchUrl_is) {
				$remainNode_keyList = $m->geta('request/remainNode_keyList', 'array');
				if (count($remainNode_keyList) >= 1) {
					foreach ($remainNode_keyList as $i => $v) {
						if (preg_match($accept_preg, $v)) {
							$val = $v;
							// remove the item in request/node_keyList
							array_splice($remainNode_keyList, $i, 1);
							$m->seta('request/remainNode_keyList', $remainNode_keyList);
							break;
						}
					}
				}
			}
			// get param in $_REQUEST
			if ($searchRequest_is) {
				$v = NodeTree::getaStringIfNotEmpty($_REQUEST, $param_name);
				if (preg_match($accept_preg, $v)) {
					$val = $v;
				}
			}
			if ($val != '__default__') {
				if ($type == 'integer') {
					$val = intval($val, 10);
				}
				$m->setaIfValNotDefault('param/'.$param_name.'/__default__', $val);
			}
		}
	}
}


function psch__safeLocalPHPFile($fsBase_type, $path) {
	// $fsBase_type : projectRoot|appRoot|typeRoot|locRoot
	// filename in $path have restriction which only allow a-z, 0-9, dot, hyphen and underscore, .php omitted

	global $projectRoot_fsRef;

	$r = $projectRoot_fsRef.'/__notFound__.php';

	$c3 = preg_match('/^(projectRoot|appRoot|typeRoot|locRoot){1,255}$/', $fsBase_type);
	$path = preg_replace('/\/{2,}/i', '/', $path); // replace double // to signle /
	$path = preg_replace('/^\//i', '', $path);     // any path start by / should be remove
	$path = preg_replace('/\/$/i', '', $path);     // any path ended with / should be remove
	$path = preg_replace('/\/\.{2,}\//i', '/', $path); // replace /../ to signle /
	$path = preg_replace('/^\.{2,}\//i', '', $path); // replace start with ../ to signle
	$c1 = !preg_match('/[^a-z0-9_\-\.\/]/i', $path); // contain only safe characters
	//$c2 = preg_match('/\.php$/i', $path);            // should end with .php

	if ($c3 && $path !== '' && $c1 && strlen($path) <= 2000) {
		$test_path = '';
		if (psch_stringIfNotEmpty_is($GLOBALS[$fsBase_type.'_fsRef'])) {
			$test_path = $GLOBALS[$fsBase_type.'_fsRef'].'/'.$path.'.php';
		}
		if ($test_path != '') {
			if (is_file($test_path)) {
				$r = $test_path;
			}
		}
	}
	return $r;
}

function psch__parsePHPFile($fsBase_type, $path) {
	global $app_tag, $m, $projectRoot_fsRef, $appRoot_fsRef, $typeRoot_fsRef, $locRoot_fsRef, $root_webRef;
	$r = '';
	ob_start();
	include psch__safeLocalPHPFile($fsBase_type, $path);
	$r = ob_get_contents();
	ob_end_clean();
	return $r;
}

function psch_getHost() {
	// support apache's HTTP_X_FORWARDED_HOST
	$r = '';
	if (isset($_SERVER["HTTP_X_FORWARDED_HOST"])) {
		$xfh = $_SERVER["HTTP_X_FORWARDED_HOST"];
		if (is_array($xfh) && count($xfh) >= 1) {
			$r = $xfh[0];
		} elseif (is_string($xfh)) {
			$r = explode(',', $xfh);
			$r = $r[0];
		}
	} elseif (isset($_SERVER["HTTP_HOST"])) {
		$r = $_SERVER["HTTP_HOST"];
	}
	return trim($r);
}

// multiply var checking for isset or not
function psch__allset_is(&$param0 = null, &$param1 = null, &$param2 = null, &$param3 = null, &$param4 = null, &$param5 = null, &$param6 = null, &$param7 = null, &$param8 = null, &$param9 = null) {
	$r = true;
	$argc = func_num_args();
	for ($i = 0; $i < $argc; $i++) {
		$name = 'param'.$i;
		$r = ($r && isset($$name));
	}
	return $r;
}

// check if string is defined and is not empty
function psch_stringIfNotEmpty_is(&$str_var) {
	if (isset($str_var) && is_string($str_var) && $str_var != '') {
		return true;
	} else {
		return false;
	}
}

// add/change parameters in existing url
function psch_urlAddParam($url, $addParam_list = array()) {
	$r = $url;
	if (is_array($addParam_list) && count($addParam_list) > 0) {
		$url_obj = parse_url($url);
		if ($url_obj !== false && is_array($url_obj)) {
			$r = '';
			if (isset($url_obj['scheme'])) {
				$r .= $url_obj['scheme']."//";
			}
			if (isset($url_obj['user']) && isset($url_obj['pass'])) {
				$r .= $url_obj['user'].':'.$url_obj['pass'].'@';
			}
			if (isset($url_obj['host'])) {
				$r .= $url_obj['host'];
			}
			if (isset($url_obj['port'])) {
				$r .= ':'.$url_obj['port'];
			}
			if (isset($url_obj['path'])) {
				$r .= $url_obj['path'];
			}
			$param_list = array();
			if (isset($url_obj['query'])) {
				parse_str($url_obj['query'], $param_list);
			}
			foreach ($addParam_list as $param_name => $param_val) {
				$param_list[$param_name] = $param_val;
			}
			$newQueryStr = http_build_query($param_list);
			if ($newQueryStr != '') {
				$r .= '?'.$newQueryStr;
			}
			if (isset($url_obj['fragment'])) {
				$r .= '#'.$url_obj['fragment'];
			}
		}
	}
	return $r;
}



?>

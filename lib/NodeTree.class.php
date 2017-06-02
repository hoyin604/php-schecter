<?php

class NodeTree {

	public static $preg = array(
		'int'     => '/^[\+\-0-9]{1,11}$/',
		'boolean' => '/^(true|false)$/',
		'is'      => '/^[0-1]$/',          // 0:false, 1:true
		'key'     => '/^[a-z0-9\_]{0,255}$/i',  // key literal
		'path' => '/^[a-z0-9\_\/]{0,6000}$/i',  // path only contain key literal and cannot have dot
		//'keyList' => '/^[a-z\_\,]{0,6000}$/i', // keys separated by comma
		'id'      => '/^[0-9]{1,20}$/',      // bigint numeric id
		'email'   => '/^[a-z0-9\_\-\.\@]{0,200}$/i',
		// ...
	);

	public static $base_fsRef = '';
	public static $exportString = array();
	public static $defaultNewObjectType = 'array'; // array|stdClass

	function __construct($init_array = array()) {
		if (is_array($init_array) || is_object($init_array)) {
			foreach($init_array as $p => $val) {
				$this->{$p} = $val;
			}
		} elseif (is_scalar($init_array)) {
			$this->{'__default__'} = $init_array;
		}
	}


	public function geta($path, $castType_key = 'string', $default = '__default__') {
		return self::_getaNodeVal($this, $path, $castType_key, $default);
	}


	public function seta($path, $val) {
		return self::_setaNodeVal($this, $path, $val);
	}

	public function setaIfPathUndefined($path, $val, $emptyAsUndefined_is = true) {
		$node = self::_getNode($this, $path);
		if (!$node->exist_is || ($emptyAsUndefined_is && $node->exist_is && empty($node->val))) {
			return self::_setaNodeVal($this, $path, $val);
		}
		return false;
	}
	public function setaIfValNotDefault($path, $val, $emptyAsDefault_is = true) {
		$node = self::_getNode($this, $path);
		if (!($val === '__default__' || ($emptyAsDefault_is && $val === ''))) {
			return self::_setaNodeVal($this, $path, $val);
		}
		return false;
	}

	public function setaFile($path) {
		$r = false;
		$path = self::toSafePath($path);
		if (self::$base_fsRef !== '' && $path !== '') {
			$path_fsRef = self::$base_fsRef.'/'.$path.'.php';
			if (file_exists($path_fsRef)) {
				$r = self::_setaNodeVal($this, $path, include $path_fsRef);
			}
		}
		return $r;
	}


	public function mergeArrayR($path, $val) {
		$r = false;
		if (is_array($val)) {
			if (self::is_assoArray($val)) {
				$a = $this->geta($path, 'assoArray', array());
			} else {
				$a = $this->geta($path, 'indexArray', array());
			}
			$a = array_replace_recursive($a, $val);
			$r = $this->seta($path, $a);
		}
		return $r;
	}




	// static
	public static function _getaNodeVal($tree_array, $path, $castType_key = 'string', $default = '__default__') {
		// $dataType: boolean|integer|double|string|array|object|resource|NULL
		// extend type:
		//   string: key|html|email|...
		//   number: count|...
		$node = self::_getNode($tree_array, $path);
		if ($node->exist_is) {
			if ($castType_key != '__noCasting__') {
				$default = self::toCastType($node->val, $castType_key, $default);
			} else {
				$default = $node->val;
			}
		}
		return $default;
	}




	public static function _getNode(&$tree_array, $test_path) {
		/* $test_path e.g.
			`/`                 : the $tree_array inself
			`title` or `/title` : key name `title` in array or property name `title` in object
		*/
		$r = new stdClass();

		$exist_is = true;
		$level = 0;
		$path = '';
		$val = $tree_array;
		$node_list = self::pathToNodeName_list($test_path);

		foreach($node_list as $i => $node_name) {
			// setup $exist_is and $val
			if (is_array($val) || is_object($val)) {
				if ((is_array($val) && isset($val[$node_name])) || (is_object($val) && isset($val->{$node_name}))) {
					// tree node(array/object) and have key
					$exist_is = true;
					$path .= '/'.$node_name;
					$level++;
					if (is_array($val) && isset($val[$node_name])) {
						$val = $val[$node_name];
					} elseif (is_object($val) && isset($val->{$node_name})) {
						$val = $val->{$node_name};
					}
				} else {
					// dont have those key in array/object
					$exist_is = false;
					$val = '';
					break;
				}
			} else {
				// the node is scalar value
				$exist_is = true;
				break;
			}
		}
		if ($level < count($node_list)) {
			// cannot reach the distinate node
			$exist_is = false;
		}

		$r->exist_is = $exist_is;
		if ($exist_is) {
			$r->type = gettype($val);
			if ($r->type == 'array' && self::is_assoArray($val)) {
				$r->type = 'assoArray';
			}
			$r->level = $level;
			$r->path = $path;
			$r->val = $val;
		} else {
			$r->type ='';
			$r->level = 0;
			$r->path = '';
			$r->val = '';
		}
		return $r;
	}


	public static function _setaNodeVal(&$tree_array, $path, $val) {
		// *** on support to set new node as array type
		// *** if it try to traverse through non-collection node, false will be return
		$r = false;
		if (self::node_is($tree_array)) {
			$curParent_node = &$tree_array;
			$nodeName_list = self::pathToNodeName_list($path);
			if (count($nodeName_list) > 0) { // it should at least have one level
				foreach ($nodeName_list as $i => $key) {
					if ($i == count($nodeName_list) -1) {
						// the last key
						$r = self::setNodeByKey($curParent_node, $key, $val);
					} else {
						$ref = &self::traverseNode($curParent_node, $key);
						if ($ref === false) {
							break;
						} else {
							$curParent_node = &$ref;
						}
					}
				}
			}
		}
		return $r;
	}

	public static function &traverseNode(&$node, $key) {
		$r = false;
		if (self::node_is($node)) {
			// create new property if it not exist
			if (is_object($node) && !isset($node->{$key})) {
				if (self::$defaultNewObjectType == 'array') {
					$node->{$key} = array();
				} elseif (self::$defaultNewObjectType == 'stdClass') {
					$node->{$key} = new stdClass();
				}
			} elseif (is_array($node) && !isset($node[$key])) {
				if (self::$defaultNewObjectType == 'array') {
					$node[$key] = array();
				} elseif (self::$defaultNewObjectType == 'stdClass') {
					$node[$key] = new stdClass();
				}
			}
			if (self::node_is($node, $key)) {
				// successful traverse
				if (is_object($node) && isset($node->{$key})) {
					$r = &$node->{$key};
				} elseif (is_array($node) && isset($node[$key])) {
					$r = &$node[$key];
				}
			}
		}
		return $r;
	}

	public static function setNodeByKey(&$node, $key, $val) {
		$r = false;
		if (self::node_is($node) && $key != '' && preg_match(self::$preg['key'], $key) && isset($val)) {
			if (is_object($node)) {
				$node->{$key} = $val;
				$r = true;
			} elseif (is_array($node)) {
				$node[$key] = $val;
				$r = true;
			}
		}
		return $r;
	}

	public static function node_is(&$node, $key = '') {
		/* usage:
			node_is($node) : check whether $node is node or not
			node_is($node, $key) : check whether $node[$key] or $node->key is node or not
		*/
		$r = false;
		if ($key == '') {
			$r = (!is_scalar($node) && !is_null($node) && (is_object($node) || is_array($node)))?true:false;
		} elseif (preg_match(self::$preg['key'], $key)) {
			if (self::node_is($node)) {
				if (is_object($node) && isset($node->{$key})) {
					$r = self::node_is($node->{$key});
				} elseif (is_array($node) && isset($node[$key])) {
					$r = self::node_is($node[$key]);
				}
			}
		}
		return $r;
	}

	public static function toCastType(&$nodeVal, $castType_key = 'string', $default = '__default__') {
		$r = $default;

		$nodeType = gettype($nodeVal);
		$assoArray_is  = self::is_assoArray($nodeVal);
		$indexArray_is = self::is_indexArray($nodeVal);
		$nodeTree_is   = ($nodeType == 'object' && $nodeVal instanceof self);
		$scalarType_is     = in_array($nodeType, array('boolean', 'integer', 'double', 'string'));
		$stringCastType_is = in_array($castType_key, array('string', 'html', 'key', 'path', 'email'));
		$pregCastType_is   = in_array($castType_key, array('key', 'path', 'email'));
		$numCanCastType_is = in_array($castType_key, array('string', 'html', 'key'));
		$nodeCastType_is   = ($castType_key == 'assoArray' || $castType_key == 'object');
		$indexArrayCastType_is = ($castType_key == 'array' || $castType_key == 'indexArray');

		if (
			($nodeType == 'boolean' && $castType_key == 'boolean')
			|| ($nodeType == 'integer' && $castType_key == 'integer')
			|| ($nodeType == 'double' && $castType_key == 'double')
			|| ($nodeType == 'string' && ($castType_key == 'string' || $castType_key == 'html'))
			|| ($nodeType == 'object' && $castType_key == 'object')
			|| ($nodeTree_is && $castType_key == 'NodeTree')
			|| ($assoArray_is && $castType_key == 'assoArray')
			|| ($indexArray_is && $indexArrayCastType_is)
		) {
			// no convertion
			$r = $nodeVal;
		} elseif ($nodeType == 'string' && $pregCastType_is) {
			// string -> key,path,email
			if (preg_match(self::$preg[$castType_key], $nodeVal)) {
				$r = $nodeVal;
			}
		} elseif ($nodeType == 'object' && ($castType_key == 'assoArray')) {
			// object -> assoArray
			$r = get_object_vars($nodeType);
		} elseif ($assoArray_is && ($castType_key == 'object')) {
			// assoArray -> object
			$r = new stdClass();
			foreach ($nodeVal as $k => $v) {
				$r->{$k} = $nodeVal[$k];
			}
		} elseif ($assoArray_is && ($castType_key == 'NodeTree')) {
			// assoArray -> NodeTree
			$r = new self($nodeVal);
		} elseif ($scalarType_is && $indexArrayCastType_is) {
			// boolean,integer,double,string -> indexArray
			$r = array($nodeVal);
		} elseif ($scalarType_is && $castType_key == 'assoArray') {
			// boolean,integer,double,string -> assoArray
			$r = array('__default__' => $nodeVal);
		} elseif ($scalarType_is && $castType_key == 'object') {
			// boolean,integer,double,string -> object
			$r = new stdClass();
			$r->__default__ =  $nodeVal;
		} elseif ($scalarType_is && $castType_key == 'listOfNodeTree') {
			// boolean,integer,double,string -> nt_array (array of NodeTree)
			$nodeTree_obj = new self($nodeVal);
			$r = array($nodeTree_obj);
		} elseif (($nodeType == 'integer' || $nodeType == 'double') && $castType_key == 'boolean') {
			// integer,double -> boolean
			$r = ($nodeVal > 0)?true:false;
		} elseif ($nodeType == 'string' && $castType_key == 'boolean') {
			// string -> boolean
			$v = strtolower($nodeVal);
			$r = ($v == 'yes' || $v == 'y' || $v == 'true' || $v == 't' || $v == '1' || $v == 'ok')?true:false;
		} elseif ($scalarType_is && $castType_key == 'integer') {
			// boolean,double,string -> integer
			$r = intval($nodeVal, 10);
		} elseif ($scalarType_is && $castType_key == 'double') {
			// boolean,integer,string -> double
			$r = floatval($nodeVal);
		} elseif ($nodeType == 'boolean' && $numCanCastType_is) {
			// boolean -> string,html,key,path
			$r = ($nodeVal)?'y':'n';
		} elseif (($nodeType == 'integer' || $nodeType == 'double') && $numCanCastType_is) {
			// integer,double -> string,html,key,path
			$r = strval($nodeVal);
		} elseif ($nodeType == 'array' && $castType_key == 'listOfNodeTree') {
			// indexArray, assoArray -> indexArray of NodeTree, assoArray of NodeTree
			$r = array();
			foreach ($nodeVal as $k => $v) {
				if (gettype($v) == 'object' && $v instanceof self) {
					$r[$k] = $v;
				} else {
					$nodeTree_obj = new self($v);
					$r[$k] = $nodeTree_obj;
				}
			}
		} elseif ($nodeType == 'object' && isset($nodeVal->{'__default__'})) {
			$r = self::toCastType($nodeVal->{'__default__'}, $castType_key, $default);
		} elseif ($nodeType == 'object' && isset($nodeVal->{'__defaultKey__'}) && isset($nodeVal->{$nodeVal->{'__defaultKey__'}})) {
			$r = self::toCastType($nodeVal->{$nodeVal->{'__defaultKey__'}}, $castType_key, $default);
		} elseif ($assoArray_is && isset($nodeVal['__default__'])) {
			$r = self::toCastType($nodeVal['__default__'], $castType_key, $default);
		} elseif ($assoArray_is && isset($nodeVal['__defaultKey__']) && isset($nodeVal[$nodeVal['__defaultKey__']])) {
			$r = self::toCastType($nodeVal[$nodeVal['__defaultKey__']], $castType_key, $default);
		} elseif (($nodeType == 'object' || $assoArray_is)) {
			// the first non-__*__ property
			foreach ($nodeVal as $k => $v) {
				if (!preg_match('/^__[a-z0-9_]+__$/i', $k)) {
					$r = self::toCastType($v, $castType_key, $default);
					break;
				}
			}
		} elseif ($indexArray_is) {
			// indexArray -> *
			// loop the array until a item's value not equal to __default__
			foreach ($nodeVal as $i => $v) {
				$t = self::toCastType($v, $castType_key, $default);
				if ($t != $default) {
					$r = $t;
					break;
				}
			}
		}

		return $r;
	}

	public static function getTypeProcessor($param = array(), $default = array(
		'itemProc_list' => array(),
		'flattenArray_is' => false,
		'listPrefix' => '',
		'listSubfix' => '',
		'itemDelimiter_list' => array(),
	)) {
		if (isset($param['itemProc_list']) && self::is_indexArray($param['itemProc_list'])) $default['itemProc_list'] = $param['itemProc_list'];
		if (count($default['itemProc_list']) > 0) {
			foreach ($default['itemProc_list'] as $i => $item) {
				$default['itemProc_list'][$i] = self::getTypeItemProcessor($item);
			}
		} else {
			$default['itemProc_list'][0] = self::getTypeItemProcessor();
		}
		if (isset($param['flattenArray_is']) && is_bool($param['flattenArray_is'])) $default['flattenArray_is'] = $param['flattenArray_is'];
		if (isset($param['listPrefix']) && is_string($param['listPrefix'])) $default['listPrefix'] = $param['listPrefix'];
		if (isset($param['listSubfix']) && is_string($param['listSubfix'])) $default['listSubfix'] = $param['listSubfix'];

		if (isset($param['itemDelimiter_list']) && self::is_indexArray($param['itemDelimiter_list'])) {
			foreach ($param['itemDelimiter_list'] as $i => $item) {
				if (self::is_indexArray($item)) {
					$default['itemDelimiter_list'][$i] = $item;
				}
			}
		}
		if (count($default['itemDelimiter_list']) == 0) {
			$default['itemDelimiter_list'][0] = array('');
		}

		return $default;

	}

	public static function getTypeItemProcessor($param = array(), $default = array(
		'castedRealType' => '',
		'notEmpty_is' => true,
		'valid_preg' => '',
		'invalid_preg' => '',
		'ifInvalidPref_action' => '',
		'maxLength' => 0,
		'ifMaxLength_action' => 'trim',
		'replace_preg' => '',
		'replace_replacement' => '',
		'prefix' => '',
		'subfix' => '',
	)) {

		// castedRealType : empty means do not cast type [string|boolean|integer|double|array|NodeTree]
		if (isset($param['castedRealType']) && is_string($param['castedRealType']) && ($param['castedRealType'] == '' || in_array($param['castedRealType'], array('string','boolean','integer','double','array','NodeTree'), true))) $default['castedRealType'] = $param['castedRealType'];
		if (isset($param['notEmpty_is']) && is_bool($param['notEmpty_is'])) $default['notEmpty_is'] = $param['notEmpty_is'];
		if (isset($param['valid_preg']) && is_string($param['valid_preg'])) $default['valid_preg'] = $param['valid_preg'];
		if (isset($param['invalid_preg']) && is_string($param['invalid_preg'])) $default['invalid_preg'] = $param['invalid_preg'];
		// ifInvalidPref_action : empty means no action [empty|trim|skip]
		if (isset($param['ifInvalidPref_action']) && ($param['ifInvalidPref_action'] == '' || in_array($param['ifInvalidPref_action'], array('empty','trim','skip'), true))) $default['ifInvalidPref_action'] = $param['ifInvalidPref_action'];
		// maxLength : 0 means no limit length
		if (isset($param['maxLength']) && is_numeric($param['maxLength']) && $param['maxLength'] >= 0) $default['maxLength'] = intval($param['maxLength'], 10);
		// ifMaxLength_action : [empty|trim|skip]
		if (isset($param['ifMaxLength_action']) && ($param['ifMaxLength_action'] == '' || in_array($param['ifMaxLength_action'], array('empty','trim','skip'), true))) $default['ifMaxLength_action'] = $param['ifMaxLength_action'];
		// replace_preg : empty means do not replace anything
		if (isset($param['replace_preg']) && (is_string($param['replace_preg']) || is_array($param['replace_preg']))) $default['replace_preg'] = $param['replace_preg'];
		if (isset($param['replace_replacement']) && (is_string($param['replace_replacement']) || is_array($param['replace_replacement']))) $default['replace_replacement'] = $param['replace_replacement'];
		if (isset($param['prefix']) && is_string($param['prefix'])) $default['prefix'] = $param['prefix'];
		if (isset($param['subfix']) && is_string($param['subfix'])) $default['subfix'] = $param['subfix'];
		return $default;
	}





	public static function pathToNodeName_list($path) {
		$r = array();
		$path = self::toSafePath($path);
		if ($path != '') {
			$r = explode("/", $path);
		}
		return $r;
	}




	public static function toSafePath($path, $r = '') {
		$path = preg_replace('/\/{2,}/i', '/', $path); // replace double // to signle /
		$path = preg_replace('/^\//i', '', $path);     // any path start by / should be remove
		$path = preg_replace('/\/$/i', '', $path);     // any path ended with / should be remove
		if ($path != '' && isset(self::$preg['path']) && preg_match(self::$preg['path'], $path)) {
			$r = $path;
		}
		return $r;
	}

	public static function is_assoArray(&$array) {
		$r = false;
		if (is_array($array)) {
			$c = count(array_filter(array_keys($array), 'is_string'));
			$r = ($c > 0)?true:false;
		}
		return $r;
	}

	public static function is_indexArray(&$array) {
		return (is_array($array) && !self::is_assoArray($array))?true:false;
	}

	public static function getaStringIfNotEmpty(&$array, $key = '__default__', $default = '__default__') {
		if (isset($array[$key]) && is_string($array[$key]) && $array[$key] != '') {
			$default = $array[$key];
		}
		return $default;
	}
}


?>

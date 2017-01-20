<?php

class NodeTree {

	public static $preg = array(
		'int'     => '/^[\+\-0-9]{1,11}$/',
		'boolean' => '/^(true|false)$/',
		'is'      => '/^[0-1]$/',          // 0:false, 1:true
		'key'     => '/^[a-z0-9\_]{0,255}$/i',  // key literal
		'keyPath' => '/^[a-z0-9\_\/]{0,6000}$/i',  // path only contain key literal
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


	public function setaFile($path) {
		$r = false;
		$path = self::toSafeKeyPath($path);
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
			$a = $this->geta($path, 'array', array());
			$a = array_merge_recursive($a, $val);
			//var_dump($a);
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
		$r = self::toCastType($node, $castType_key, $default);
		return $r;
	}




	public static function _getNode(&$tree_array, $test_path) {
		$r = new stdClass();

		$valid_is = true;
		$level = 0;
		$path = '';
		$val = &$tree_array;
		$node_list = self::pathToNodeName_list($test_path);

		foreach($node_list as $i => $node_name) {
			// setup $valid_is and $val
			if (is_array($val) || is_object($val)) {
				if ((is_array($val) && isset($val[$node_name])) || (is_object($val) && isset($val->{$node_name}))) {
					// tree node(array/object) and have key
					$valid_is = true;
					$path .= '/'.$node_name;
					$level++;
					if (is_array($val) && isset($val[$node_name])) {
						$val = $val[$node_name];
					} elseif (is_object($val) && isset($val->{$node_name})) {
						$val = $val->{$node_name};
					}
				} else {
					// dont have those key in array/object
					$valid_is = false;
					$val = '';
					break;
				}
			} else {
				// the node is scalar value
				$valid_is = true;
				break;
			}
		}
		if ($level < count($node_list)) {
			// cannot reach the distinate node
			$valid_is = false;
		}

		$r->valid_is = $valid_is;
		if ($valid_is) {
			$r->type = gettype($val);
			$r->level = $level;
			$r->path = $path;
			$r->val = $val;
			// return __default__ property(if exist) when refer to the object inself
			if ($level == 0 && $path == '' && (is_array($tree_array) || (is_object($tree_array)))) {
				$default_key = '__default__';
				if (is_array($tree_array) && isset($tree_array['__defaultKey__'])) {
					$default_key = $tree_array['__defaultKey__'];
				} elseif (is_object($tree_array) && isset($tree_array->{'__defaultKey__'})) {
					$default_key = $tree_array->{'__defaultKey__'};
				}

				if (is_array($tree_array) && isset($tree_array[$default_key])) {
					$r->val = $tree_array[$default_key];
				} elseif (is_object($tree_array) && isset($tree_array->{$default_key})) {
					$r->val = $tree_array->{$default_key};
				}

				if (is_scalar($r->val)) {
					$r->type = gettype($r->val);
				}
			}
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
						$ref = self::traverseNode($curParent_node, $key);
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

	public static function traverseNode(&$node, $key) {
		$r = false;
		if (self::node_is($node)) {
			// create new
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
			} else {
				break;
			}
		}
		return $r;
	}

	public static function setNodeByKey(&$node, $key, $val) {
		$r = false;
		if (self::node_is($node) && $key != '' && preg_match(self::$preg['key'], $key)) {
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

	public static function toCastType($node, $castType_key = 'string', $default = '__default__') {

		$r = $default;

		if ($castType_key == 'string' && ($node->type == 'string' || $node->type == 'boolean' || $node->type == 'integer' || $node->type == 'double')) {
			$r = strval($node->val);
		} elseif (in_array($castType_key, array('key', 'email')) && $node->type == 'string') {
			$v = strval($node->val);
			if (preg_match(self::$preg[$castType_key], $v)) {
				$r = $v;
			}
		} elseif ($castType_key == 'boolean') {
			if ($node->type == 'boolean') {
				$r = $node->val;
			} elseif ($node->type == 'integer' || $node->type == 'double') {
				$r = ($node->val > 0)?true:false;
			} elseif ($node->type == 'string') {
				$v = strtolower($node->val);
				$r = ($v == 'yes' || $v == 'y' || $v == 'true' || $v == 't' || $node->val == '1' || $v == 'ok')?true:false;
			}
		} elseif ($castType_key == 'array') {
			if ($node->type == 'array') {
				$r = $node->val;
			} elseif ($node->type == 'object') {
				$r = get_object_vars($node->val);
			} elseif ($node->type == 'string' || $node->type == 'boolean' || $node->type == 'integer' || $node->type == 'double') {
				$r = array($node->val);
			}
		} elseif ($castType_key == 'ta_array') {
			$r = array();
			if ($node->type == 'array') {
				foreach ($node->val as $key => $value) {
					$ta = new self($value);
					$r[$key] = $ta;
				}
			}
		}
		return $r;
	}




	public static function pathToNodeName_list($path) {
		$r = array();
		$path = self::toSafeKeyPath($path);
		if ($path != '') {
			$r = explode("/", $path);
		}
		return $r;
	}




	public static function toSafeKeyPath($path) {
		$r = '';
		$path = preg_replace('/\/{2,}/i', '/', $path); // replace double // to signle /
		$path = preg_replace('/^\//i', '', $path);     // any path start by / should be remove
		$path = preg_replace('/\/$/i', '', $path);     // any path ended with / should be remove
		if ($path != '' && isset(self::$preg['keyPath']) && preg_match(self::$preg['keyPath'], $path)) {
			$r = $path;
		}
		return $r;
	}




	public static function is_assoArray($array) {
		$c = count(array_filter(array_keys($array), 'is_string'));
	  return ($c > 0)?true:false;
	}
}


?>

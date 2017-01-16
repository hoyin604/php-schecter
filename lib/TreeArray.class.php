<?php

class TreeArray {

	public static $preg = array(
		'int'     => '/^[\+\-0-9]{1,11}$/',
		'boolean' => '/^(true|false)$/',
		'is'      => '/^[0-1]$/',          // 0:false, 1:true
		'key'     => '/^[a-z0-9\_]{0,255}$/i',  // key literal
		//'keyList' => '/^[a-z\_\,]{0,6000}$/i', // keys separated by comma
		'id'      => '/^[0-9]{1,20}$/',      // bigint numeric id
		'email'   => '/^[a-z0-9\_\-\.\@]{0,200}$/i',
		// ...
	);

	function __construct($init_array = array()) {
		if (is_array($init_array)) {
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




	public static function _setaNodeVal(&$tree_array, $path, $val) {
		// *** on support to set new node as array type
		// *** if it try to traverse through non-collection node, false will be return
		$r = false;
		$curParent_node = null;
		$node_name = '';
		$parentNodeName_list = self::pathToNodeName_list($path);

		if (count($parentNodeName_list) > 0) {
			$curParent_node = &$tree_array;
			$node_name = array_pop($parentNodeName_list);
			if (count($parentNodeName_list) > 0) {
				foreach ($parentNodeName_list as $i => $parentNode_name) {
					if (is_array($curParent_node) && isset($curParent_node[$parentNode_name]) && (is_array($curParent_node[$parentNode_name]) || is_object($curParent_node[$parentNode_name]))) {
						// array parent, have reference node
						$curParent_node = &$curParent_node[$parentNode_name];
					} elseif (is_object($curParent_node) && isset($curParent_node->{$parentNode_name}) && (is_array($curParent_node->{$parentNode_name}) || is_object($curParent_node->{$parentNode_name}))) {
						// object parent, have reference node
						$curParent_node = &$curParent_node->{$parentNode_name};
					} elseif (is_array($curParent_node) && !isset($curParent_node[$parentNode_name])) {
						// array parent, no existing node, and need to create a new one
						$curParent_node[$parentNode_name] = array();
						$curParent_node = &$curParent_node[$parentNode_name];
					} elseif (is_object($curParent_node) && !isset($curParent_node->{$parentNode_name})) {
						// object parent, no existing node, and need to create a new one
						$curParent_node->{$parentNode_name} = array();
						$curParent_node = &$curParent_node->{$parentNode_name};
					} else {
						// there already have some scalar node which conflict with the name needed
						$curParent_node = null;
						$node_name = '';
						break;
					}
				}
			}
			if ($node_name != '' && !is_null($curParent_node)) {
				if (is_object($curParent_node)) {
					$curParent_node->{$node_name} = $val;
					$r = true;
				} elseif (is_array($curParent_node)) {
					$curParent_node[$node_name] = $val;
					$r = true;
				}
			}
		} else {
			// should replace property in root'
			if (is_array($val) || is_object($val)) {
				foreach($val as $k => $v) {
					if (is_array($tree_array)) {
						$tree_array[$k] = $v;
					} elseif (is_object($tree_array)) {
						$tree_array->{$k} = $v;
					}
				}
				$r = true;
			}
		}
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
				if (is_array($tree_array) && isset($tree_array['__default_key__'])) {
					$default_key = $tree_array['__default_key__'];
				} elseif (is_object($tree_array) && isset($tree_array->{'__default_key__'})) {
					$default_key = $tree_array->{'__default_key__'};
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
		$path = preg_replace('/\/{2,}/i', '/', $path);
		$path = preg_replace('/^\//i', '', $path);
		$path = preg_replace('/\/$/i', '', $path);
		return ($path != '')?explode("/", $path):array();
	}




	public static function is_assoArray($array) {
		$c = count(array_filter(array_keys($array), 'is_string'));
	  return ($c > 0)?true:false;
	}
}


?>

<?php
	/*
	 * This file is part of the openerplib.
	 *
	 * (c) Benito Rodriguez <brarcos@gmail.com>
	 *
	 * https://github.com/b3ni/openerplib
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	require_once(dirname(__FILE__) . '/xmlrpc.inc');
	//require_once(dirname(__FILE__) . '/openerplib.inc.php');

    class OpenERPField {
        private $name;
        private $data = array();
        private $change = false;

        public function __construct($name) {
            $this->name = $name;
        }

        public function __set($name, $value) {
            if ($name == 'name')
                return;

            if ($name == 'value' AND is_string($value))
                $value = utf8_encode($value);

            $this->data[$name] = $value;
            $this->change = true;
        }

        public function __get($name) {
            return $name == 'name' ? $this->name : array_key_exists($name, $this->data) ? $this->data[$name] : null;
        }
    }

	/**
	 * OpenERPObject Object
	 *
	 * @author Benito Rodriguez
	 */
	class OpenERPObject {
        private $id;

        private $config;
        private $client;

        private $model;
        private $load = false;
        private $fields = array();

		public function __construct($model, $config, $client=NULL) {
            $this->model = $model;
            $this->config = $config;
            $this->client = isset($client) ? $client : new xmlrpc_client($this->config['url']);

            if (!$this->client)
                throw new Exception("No se ha podido crear el cliente XMLRPC");
		}

		public function __set($name, $value) {
            if ($name == 'id')
                throw new Exception("ID PROPERTY CAN NOT BE CHANGED");

            $f = $this->_getField($name);
            $f->value = $value;
		}

        public function __get($name) {
            if ($name == 'id')
                return $this->id;

            $method_call = new ReflectionMethod('OpenERPObject', '__call');
            return $method_call->invoke($this, $name, null);
        }

        public function __call($method, $args) {
            $fields_read = count($args) == 1 AND is_array($args[0]) ? $args[0] : $args;
            $this->_read_model($fields_read);

            $name_field = $this->_getNameField($method);


        }

        private function _read_model($fields_read) {
            if ($fields_read === null OR $this->load)
                return;

            $msg = new xmlrpcmsg('execute');
            $msg->addParam(new xmlrpcval($this->config['bd'], "string"));
            $msg->addParam(new xmlrpcval($this->config['uid'], "int"));
            $msg->addParam(new xmlrpcval($this->config['pass'], "string"));
            $msg->addParam(new xmlrpcval($this->model, "string"));
            $msg->addParam(new xmlrpcval("read", "string"));

            // key
            $arr[] = new xmlrpcval($this->id, "int");
            $msg->addParam(new xmlrpcval($arr, "array"));

            // fields
            $fields_rpc = array();
            foreach($fields_read as $value) {
                if (!is_array($value))
                    $v = new xmlrpcval($value, "string");
                else
                    $v = new xmlrpcval($value[0], $value[1]);
                $fields_rpc[] = $v;
            }
            $msg->addParam(new xmlrpcval($fields_rpc, "array"));

            $resp = $this->client->send($msg);
            $this->checkError($resp);

            if (!$resp->value())
                return array();

            $fields_return = array();
            foreach($resp->value()->scalarval() as $key => $r) {
                $fields_return[$key] = array();
                foreach ($r->scalarval() as $f => $v)
                    $fields_return[$key][$f] = $v->scalarval();
            }
        }

        private function _getNameField($name) {
            $name_ralation = str_replace("_", ".", $name);

            // chequemos relaciones
            foreach($this->fields as $key => $f)
                if ($f['relation'] == $name_ralation)
                    return $key;

            // objetos simples
            if (!array_key_exists($name, $this->fields))
                throw new Exception("OBJECT HAS NO PROPERTY '".$name."'");

            return $name;
        }

		// public function __call($method, $args) {
		//     // fields a leer
		//     if (count($args) == 0)
  //               $this->fields_only = array('id'); // solo id
  //           else if (count($args) == 1 AND is_array($args[0]) AND count($args[0]) > 0)
  //               $this->fields_only = $args[0];
  //           else if (count($args) == 1 AND $args[0] == '__ALL')
  //               $this->fields_only = null;
  //           else
  //               $this->fields_only = $args;

		// 	// si no está cargado lee el modelo
		// 	if (!$this->_isLoad()) {
  //               $this->_loadModel($method);
  //               return $this;
  //           }

		// 	// leemos fields
		// 	$name_field = $this->_getNameField($method);
		// 	$type_field = $this->fields[$name_field]['type'];

		// 	switch($type_field) {
		// 		case 'many2one':
		// 			$model = str_replace(".", "_", $this->fields[$name_field]['relation']);

		// 			// si no tiene enlace al objeto
		// 			if (!$this->fields[$name_field]['value'])
		// 				return NULL;

		// 			$method_call = new ReflectionMethod('OpenERPObject', '__call');
		// 			$method_get = new ReflectionMethod('OpenERPObject', 'get');

		// 			$many = new OpenERPObject(NULL, $this->client);
		// 			$many = $method_call->invoke($many, $model, $args);
		// 			$many = $method_get->invoke($many, $this->fields[$name_field]['value']);

		// 			return $many;
		// 			break;

  //               case 'one2many':
  //                   $model = str_replace(".", "_", $this->fields[$name_field]['relation']);

  //                   $method_call = new ReflectionMethod('OpenERPObject', '__call');
  //                   $method_get = new ReflectionMethod('OpenERPObject', 'get');

  //                   $manys = array();

  //                   foreach ($this->fields[$name_field]['value'] as $v) {
  //                       $obj = new OpenERPObject(NULL, $this->client);
  //                       $obj = $method_call->invoke($obj, $model, $args);
  //                       $obj = $method_get->invoke($obj, $v);

  //                       $manys[] = $obj;
  //                   }

  //                   return $manys;
  //                   break;

		// 		default:
		// 			if (!in_array($name_field, $this->_getFields2Read()))
		// 				throw new Exception("FIELD '".$name_field."' NOT READ");

		// 			return $this->fields[$name_field]['value'];
		// 			break;
		// 	}

		// 	parent::__call($method, $args);
		// }



        public function __toString() {
            return (string) $this->id;
        }

		/**
		 * Lee un objeto de la bd
		 * @param int $id
		 * @param mas parametros
		 * @return OpenERPObject or FALSE si no se ha encontrado
		 */
		public function get() {
		    $this->_loadModel($this->model);

			// id a leer
			$id = func_get_arg(0);
			if (!$id)
				throw new Exception("ARGS ERRORS: ID OBJECT NOT FOUND");

            // leemos los campos del modelos
            $this->fields['id']['value'] = $id;
            $this->_readModel();

			return $this;
		}

		/**
		 * Devuelve la información del objeto actual completo o de un fields concreto
		 * @param unknown_type $field
		 */
		public function info($field=null) {
			if ($field)
				return print_r($this->fields[$field], true);

			if ($this->fields_only) {
				$a = array();
				foreach ($this->fields_only as $f)
					$a[$f] = print_r($this->fields[$f], true);

				return print_r($a, true);
			}

			return print_r($this->fields, true);
		}

		/**
		 * Salva el objeto actual en el openerp
		 * @return boolean
		 */
		public function save() {
			if (!$this->_isLoad())
			    $this->_loadModel($this->model);

            $msg = new xmlrpcmsg('execute');
            $msg->addParam(new xmlrpcval($this->bd, "string"));
            $msg->addParam(new xmlrpcval($this->uid, "int"));
            $msg->addParam(new xmlrpcval($this->pass, "string"));
            $msg->addParam(new xmlrpcval($this->model, "string"));

            // save or create
            if ($this->id) {
                $msg->addParam(new xmlrpcval("write", "string"));
                $msg->addParam(new xmlrpcval(array(new xmlrpcval($this->id, "int")), "array"));
            } else
                $msg->addParam(new xmlrpcval("create", "string"));

            // values
            $values = array();
            foreach($this->fields as $name => $field) {
                if (!$field['changed'])
                    continue;

                $values[$name] = new xmlrpcval($field['value'], $this->_getType2Save($field));
            }

            $msg->addParam(new xmlrpcval($values, "struct"));

            $resp = $this->client->send($msg);
            $ok = $this->checkError($resp);

            if (!$ok)
                return FALSE;

            // marcamos todos los fields modificados
            foreach($this->fields as $name => $field)
                $field['changed'] = false;

            // salvamos el id si es un create
            if (!$this->id)
                $this->fields['id']['value'] = $resp->value()->scalarval();

            return $this->id;
		}

		/**
         * Busca objetos
         */
		public function search($key, $operator, $value) {
            $method_call    = new ReflectionMethod('OpenERPObject', '__call');
            $method_get     = new ReflectionMethod('OpenERPObject', 'get');
            $fields         = $this->_getFields2Read();

		    $obj = array();

            foreach($this->_search($this->model, $key, $operator, $value) as $id) {
                $obj[$id] = new OpenERPObject(NULL, $this->client);

                $obj[$id] = $method_call->invoke($obj[$id], $this->model, $fields);
                $obj[$id] = $method_get->invoke($obj[$id], $id);
            }

            return $obj;
		}

        /**
         * Ejecuta una función del modelo
         */
        public function workflow($method) {
            $msg = new xmlrpcmsg('execute');

            $msg->addParam(new xmlrpcval($this->bd, "string"));
            $msg->addParam(new xmlrpcval($this->uid, "int"));
            $msg->addParam(new xmlrpcval($this->pass, "string"));
            $msg->addParam(new xmlrpcval($this->model, "string"));
            $msg->addParam(new xmlrpcval($method, "string"));

            $msg->addParam(new xmlrpcval(array(new xmlrpcval($this->id, "int")), "array"));

            $resp = $this->client->send($msg);
            $this->checkError($resp);

            if ($resp->faultCode() != 0)
                return false;

            return $resp->value()->scalarval();
        }

        /**
         * Añade un archivo adjunto al modelo
         */
        public function addAttachment($filetitle, $filename, $filepath) {
            $data = file_get_contents($filepath);

            $a = new OpenERPObject(NULL, $this->client);
            $a = $a->ir_attachment('__ALL');

            $a->user_id = $this->uid;
            $a->name = $filetitle;
            $a->title = false;
            $a->datas = base64_encode($data);
            $a->datas_fname = $filename;
            $a->description = false;
            $a->index_content = false;
            $a->partner_id = false;
            $a->res_model = $this->model;
            $a->res_id = $this->id;

            return $a->save();
        }

		/**
		 * Devuelve el error de la operación previa
		 */
		public function getError() {
            if (!$this->error)
                return null;

			return $this->error . "[".$this->traceback."][".print_r($this, true)."]";
		}

        private function _getField($name) {
            if (!array_key_exists($name, $this->fields))
                $this->fields[$name] = new OpenERPField($name);
            return $this->fields[$name];
        }

		private function _read($model, $ids, $fields) {
			$msg = new xmlrpcmsg('execute');
			$msg->addParam(new xmlrpcval($this->bd, "string"));
			$msg->addParam(new xmlrpcval($this->uid, "int"));
			$msg->addParam(new xmlrpcval($this->pass, "string"));
			$msg->addParam(new xmlrpcval($model, "string"));
			$msg->addParam(new xmlrpcval("read", "string"));

			// keys
			$arr = array();
			if (is_array($ids))
				foreach ($ids as $id)
					$arr[] = new xmlrpcval($id, "int");
			else
				$arr[] = new xmlrpcval($ids, "int");
			$msg->addParam(new xmlrpcval($arr, "array"));

			// fields
			$fields_rpc = array();
			foreach($fields as $value) {
				if (!is_array($value))
					$v = new xmlrpcval($value, "string");
				else
					$v = new xmlrpcval($value[0], $value[1]);
				$fields_rpc[] = $v;
			}
			$msg->addParam(new xmlrpcval($fields_rpc, "array"));

			$resp = $this->client->send($msg);
			$this->checkError($resp);

			if (!$resp->value())
				return array();

			$fields_return = array();
			foreach($resp->value()->scalarval() as $key => $r) {
				$fields_return[$key] = array();
				foreach ($r->scalarval() as $f => $v)
					$fields_return[$key][$f] = $v->scalarval();
			}

			return $fields_return;
		}

		private function _search($model, $field, $operator, $value) {
			$msg = new xmlrpcmsg('execute');
			$msg->addParam(new xmlrpcval($this->bd, "string"));
			$msg->addParam(new xmlrpcval($this->uid, "int"));
			$msg->addParam(new xmlrpcval($this->pass, "string"));

			$msg->addParam(new xmlrpcval($model, "string"));
			$msg->addParam(new xmlrpcval("search", "string"));

			// key
			$key = array(
				new xmlrpcval(
					array(
						new xmlrpcval($field, "string"),
						new xmlrpcval($operator, "string"),
						new xmlrpcval($value, $this->is_load ? $this->_getType2Save($this->fields[$field]) : "string")
					),
					"array"
				),
			);
			$msg->addParam(new xmlrpcval($key, "array"));

			$resp = $this->client->send($msg);
			$this->checkError($resp);

			if ($resp->faultCode() != 0)
				throw new Exception($this->error);

			$ids = array();
			foreach ($resp->value()->scalarval() as $v)
				$ids[] = $v->scalarval();

			return $ids;
		}

        /**
         * Lee la estructura del modelo
         */
        private function _loadModel($method) {
            if ($this->is_load)
                return;

            $this->model = str_replace("_", ".", $method);

            // leemos los fields del modelo
            $ids = $this->_search('ir.model.fields', "model", "=", $this->model);
            $fields = $this->_read('ir.model.fields', $ids, array('name', 'relation', 'ttype'));

            $this->fields = array();
            $has_id = false;
            foreach($fields as $f) {
                $this->fields[$f['name']] = array('type' => $f['ttype'], 'relation' => $f['relation'], 'value' => NULL, 'changed' => false);
                if ($f['name'] == 'id')
                    $has_id = true;
            }

            if (!$has_id)
                $this->fields['id'] = array('type' => 'integer', 'relation' => NULL, 'value' => NULL, 'changed' => false);

            $this->is_load = true;
        }

        /**
         * Devuelve si los metadatos del modelo están leidos
         */
        private function _isLoad() {
            return $this->is_load;
        }

        /**
         * Leemos los fields del modelo que se quieren
         */
        private function _readModel() {
            if ($this->is_read)
                return;

            if (!$this->is_load)
                throw new Exception("OBJECT NOT LOAD");

            if (!isset($this->fields['id']) OR !$this->fields['id']['value'])
                throw new Exception("ID FIELD NOT VALID");

            $id = $this->fields['id']['value'];

            // leemos un objeto en concreto
            $fields = $this->_read($this->model, $id, $this->_getFields2Read());
            if (count($fields) != 1)
                throw new Exception("OBJECT NOT FOUND");

            $fields = $fields[0];

            // campos
            foreach($fields as $key => $value) {
                switch ($this->fields[$key]['type']) {
                    case 'many2one':
                        $value_proc = $value ? $value[0]->scalarval() : NULL;
                        break;

                    case 'one2many':
                        $arr_value = array();

                        if (count($value) > 0)
                            foreach ($value as $v)
                                $arr_value[] = $v->scalarval();

                        $value_proc = $arr_value;
                        break;

                    default:
                        $value_proc = $value;
                        break;
                }

                $this->fields[$key]['value'] = $value_proc;
            }

            $this->is_read = true;
        }

        /**
         * Devuelve si el modelo está leido
         */
        private function _isRead() {
            return $this->is_read;
        }

		/**
		 * Lista con los valores a leer del objeto open
		 * @return array:string
		 */
		private function _getFields2Read() {
			if (isset($this->fields_2_read))
				return $this->fields_2_read;

			$fields_2_read = array();

			if ($this->fields_only)
				$fields_2_read = $this->fields_only;
			else
				foreach ($this->fields as $key => $value)
					$fields_2_read[] = $key;

			if (!in_array('id', $fields_2_read))
				$fields_2_read[] = 'id';

            $this->fields_2_read = $fields_2_read;

			return $fields_2_read;
		}

		/**
		 * Devuelve el tipo de datos open para poder guardar en el openerp
		 * @param Array $field
		 * @return Ambigous <NULL, string>
		 */
		private function _getType2Save($field) {
			$type = null;

            switch ($field['type']) {
                case 'char':
                case 'text':
                case 'selection':
                case 'binary':
                    $type = "string";
                    break;
                case 'integer':
                case 'many2one':
                    $type = "int";
                    break;
                case 'one2many':
                    $type = "array";
                    break;
                default:
                    throw new Exception("OBJECT TYPE NOT RECOGNIZED: ". $field['type']);
            }

			return $type;
		}

		/**
		 * Comprueba si el objeto tiene un nombre de propiedad pasado
		 * @param String $name nombre de la propiedad
		 * @throws Exception, si no existe un nombre de propiedad en el objeto
		 * @return String $nombre de la propiedad
		 */
		private function _getNameField($name) {
			$name_ralation = str_replace("_", ".", $name);

			// chequemos relaciones
			foreach($this->fields as $key => $f)
				if ($f['relation'] == $name_ralation)
					return $key;

			// objetos simples
			if (!array_key_exists($name, $this->fields))
				throw new Exception("OBJECT HAS NO PROPERTY '".$name."'");

			return $name;
		}

		/**
		 * Comprueba error de petición al open
		 * @param unknown_type $resp
		 * @throws Exception
		 */
		private function checkError($resp) {
			// 35573, not found

			if ($resp->faultCode() == 0)
				return TRUE;

			if ($resp->faultCode() == 35573)	// not found
				return TRUE;


			$this->error = $resp->faultCode() . ": ". $resp->faultString();
			$this->traceback = print_r($resp, true);

			return FALSE;
		}
	}

	/**
	 * OpenERPObject Object
	 *
	 * Gestor que inicia la comunicación en el servidor OpenERP
	 *
	 * @author Benito Rodriguez
	 */
    class OpenERP {
        protected $config;
        protected $client = NULL;

        public function __construct($config, $client=NULL) {
            $this->config = $config;
            $this->client = isset($client) ? $client : new xmlrpc_client($this->config['url']);

			if (!$this->client)
				throw new Exception("No se ha podido crear el cliente XMLRPC");
        }

        public function __call($method, $args) {
            $erpobj = new OpenERPObject($this->config, $this->client);

            $method_call = new ReflectionMethod('OpenERPObject', '__call');
            $erpobj = $method_call->invoke($erpobj, $method, $args);

            return $erpobj;
        }

        // public function __get($name) {
        //     $erpobject = new OpenERPObject(null, $this->client);

        //     $method_call = new ReflectionMethod('OpenERPObject', '__call');
        //     $erpobject = $method_call->invoke($erpobject, $name, null);

        //     $this->_lastobject = $erpobject;

        //     return $this->_lastobject;
        // }

		/**
         * Devuelve el error de la operación previa
         */
        public function getError() {
            if (!$this->_lastobject)
                return NULL;

            return $this->_lastobject->getError();
        }
    }

    $config = array(
        'bd'        => 'mybdname',
        'uid'       => 1212,
        'passwd'    => 'foo',
        'url'       => 'http://openerp/xmlrpc',
    );

    $open = new OpenERP($config);
?>
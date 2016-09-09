<?php

namespace Sberned\CurlORM;

use anlutro\cURL\cURL;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use League\Flysystem\Exception;
use LogicException;
use stdClass;


abstract class Model
{
    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [];
    protected $newAttributes = [];

    public $url;
    public $fields;
    public $includes;
    public $version;
    public $host;
    public $https = true;
    public $json = true;
    public $basicAuth = false;
    public $loginCurl = 'api';
    public $passCurl = 'pass';
    private $select = [];
    private $result = [];
    private $where = [];
    private $exist = false;

    private $className = '';

    protected static $_values = array();



    /**
     * Create a new Eloquent query builder instance.
     *
     * @param array|$attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->setClassName();
    }

    public function save()
    {
      if($this->exist) {
          return $this->updateThis();
      } else {
          return $this->insertThis();
      }
    }

    private function updateThis()
    {
        if(!empty($this->newAttributes)) {

            $newQuery = new Builder($this->className, $this->getUrl(), $this->getLink() . '/' . $this->attributes['id'], 'PATCH', true, $this->newAttributes);
            $res = $newQuery->send();
            $className = $this->className;
            $data = self::convertToObject($res->$className);

            if(!empty($data)) {
                foreach ($data as $key => $val) {
                    $this->attributes = array_add($this->attributes, $key, $val);
                }
                return true;
            }
        }
        return false;
    }

    private function insertThis()
    {
        if(!empty($this->attributes)) {

            $newQuery = new Builder($this->className, $this->getUrl(), $this->getLink(), 'POST', true, $this->attributes);
            $res = $newQuery->send();
            $className = $this->className;
            $data = self::convertToObject($res->$className);

            if(!empty($data)) {
                foreach ($data as $key => $val) {
                    $this->attributes = array_add($this->attributes, $key, $val);
                }
                return true;
            }
        }
        return false;
    }

    public function first(array $columns = [])
    {
        if(!empty($columns)) {
            self::setSelect($columns);
        }
        $newQuery = new Builder($this->className, $this->getUrl(), $this->getLink(), 'get', true, []);
        $newQuery->limit(1, 1);
        $newQuery->setValues(self::$_values);
        $res = $newQuery->send();
        $className = $this->url;

        return self::convertToObject(array_first($res->$className));
    }
    public function get(array $columns = [])
    {
        if(!empty($columns)) {
            self::setSelect($columns);
        }

        $newQuery = new Builder($this->className, $this->getUrl(), $this->getLink(), 'get', true, []);
        $newQuery->setValues(self::$_values);
        $res = $newQuery->send();
        $className = $this->url;

        return self::convertToObject($res->$className);
    }

    public function all(array $columns = [])
    {
        if(!empty($columns)) {
            self::setSelect($columns);
        }

        $newQuery = new Builder($this->className, $this->getUrl(), $this->getLink(), 'get', true, []);
        $newQuery->setValues(self::$_values);
        $res = $newQuery->send();
        $className = $this->url;

        return self::convertToObject($res->$className);
    }

    public static function find(int $id)
    {
        if(!empty($columns)) {
            self::setSelect($columns);
        }
        $class = self::getCallClass();

        $newQuery = new Builder($class->getClassName(), $class->getUrl(), $class->getLink() . '/' . $id, 'get', true, []);
        $newQuery->setValues(self::$_values);
        $res = $newQuery->send();

        $className = $class->className;

        $data =  $res->$className;
        if(!empty($data)) {
            foreach ($data as $key => $val) {
                $class->setAttribute($key, $val);
            }
        }
        $class->exist = true;
        return $class;
    }

    public static function select(array $select)
    {
        self::$_values['Select'] = $select;

        return self::getCallClass();
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        switch ($operator) {
            case '==' || null:
                self::setWhere($column, $value, '@');
                break;
            case '<>' || '!=':

                break;
            case '<':

                break;
            case '<=':

                break;
            case '>':

                break;
            case '>=':

                break;
        }

        return self::getCallClass();
    }

    public static function limit($per_page = 15, $page = 1)
    {
        self::$_values['Limit'] = ['per_page' => $per_page, 'page' => $page];

        return self::getCallClass();
    }


    public static function with($array)
    {
        if(is_array($array)){
            foreach ($array as $arr) {
                self::$_values['With'][] =  $arr;
            }
        } else {
            self::$_values['With'][] =  $array;
        }

        return self::getCallClass();
    }

    public static function setSelect(array $query)
    {
        self::$_values['Select'] = $query;
    }

    private static function setWhere($column, $value, $operator)
    {
        self::$_values['Where'][] = ['column' => $column, 'search' => $value, 'operator' => $operator];

    }

    public function setClassName()
    {

        $r = explode("\\", get_class($this));
        return $this->className = mb_strtolower(array_last($r));
    }

    public static function className($th)
    {
        $r = explode("\\", $th);

        return mb_strtolower(array_last($r));
    }


    function convertToObject($array) {
        $object = new stdClass();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $value = self::convertToObject($value);
            }
            $object->$key = $value;
        }
        return $object;
    }
    public function __get($name) {
        if(isset($this->attributes[$name])) {
            return $this->attributes[$name];
        } else {
            return null;
        }

    }

    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
        $this->setNewAttribute($key, $value);

    }
    public function getUrl()
    {
        $http = $this->https ? 'https://' : 'http://';

        return $http . $this->host . '/' . ($this->version ? $this->version . '/' : '');

    }

    public function getLink()
    {
        return $this->url;
    }

    public function getClassName()
    {
        return $this->className;
    }


    public static function getCallClass()
    {
        $cName = get_called_class();//Получем название класса
        return new $cName();
    }

    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    public function setNewAttribute($key, $value)
    {
        $this->newAttributes[$key] = $value;
    }


}
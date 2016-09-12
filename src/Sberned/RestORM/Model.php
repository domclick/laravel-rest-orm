<?php
/**
 * @author: Igor Shesteryakov <gatewayuo@gmail.com>
 */

namespace Sberned\RestORM;

use anlutro\cURL\cURL;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use League\Flysystem\Exception;
use LogicException;
use stdClass;


/**
 * Class Model
 * @package Sberned\RestORM
 */
abstract class Model
{
    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [];
    /**
     * @var array
     */
    protected $newAttributes = [];

    /**
     * @var
     */
    public $url;
    /**
     * @var
     */
    public $fields;
    /**
     * @var
     */
    public $includes;
    /**
     * @var
     */
    public $version;
    /**
     * @var
     */
    public $host;
    /**
     * @var bool
     */
    public $https = true;
    /**
     * @var bool
     */
    public $json = true;
    /**
     * @var bool
     */
    public $basicAuth = false;
    /**
     * @var string
     */
    public $loginCurl = '';
    /**
     * @var string
     */
    public $passCurl = '';

    /**
     * @var bool
     */
    private $exist = false;

    /**
     * @var string
     */
    private $className = '';

    /**
     * @var array
     */
    protected static $_values = array();

    /**
     * Model constructor.
     */
    public function __construct()
    {
        $this->setClassName();
    }

    /**
     * @return bool
     */
    public function save()
    {
      if($this->exist) {
          return $this->updateThis();
      } else {
          return $this->insertThis();
      }
    }

    /**
     * @return bool
     */
    private function updateThis()
    {
        if(!empty($this->newAttributes)) {

            $newQuery = new Builder($this->className, $this->getUrl(), $this->getLink() . '/' . $this->attributes['id'], 'PATCH', true, $this->newAttributes);
            $res = $newQuery->send();
            $className = $this->className;
            $data = self::convertToObject($res->$className);

            if(!empty($data)) {
                foreach ($data as $key => $val) {
                    $this->setAttribute($key, $val);
                }
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    private function insertThis()
    {
        if(!empty($this->attributes)) {

            $newQuery = new Builder($this->className, $this->getUrl(), $this->getLink(), 'POST', true, $this->attributes);
            $res = $newQuery->send();
            $className = $this->className;
            $data = self::convertToObject($res->$className);

            if(!empty($data)) {
                foreach ($data as $key => $val) {
                    $this->setAttribute($key, $val);
                }
                return true;
            }
        }
        return false;
    }

    /**
     * @param array $columns
     * @return stdClass
     */
    public function first(array $columns = [])
    {
        if(!empty($columns)) {
            self::setSelect($columns);
        }

        $class = self::getCallClass();
        $className = $class->className;
        $aliasList = $class->alias_list;

        $newQuery = new Builder($this->className, $this->getUrl(), $this->getLink(), 'get', true, []);
        $newQuery->limit(1, 1);
        $newQuery->setValues(self::$_values);
        $res = $newQuery->send();

        if (isset($res->$className)) {
            $dataObj = $className;
        } else {
            $dataObj = $aliasList;
        }

        if (empty($res->$dataObj)) {
            return null;
            $data = array_first($res->$dataObj);
            if(!empty($data)) {
                foreach ($data as $key => $val) {
                    $class->setAttribute($key, $val);
                }
            }
            $class->exist = true;
            return $class;
        }
    }

    /**
     * @param array $columns
     * @return stdClass
     */
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

    /**
     * @param array $columns
     * @return stdClass
     */
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

    /**
     * @param int $id
     * @return mixed
     */
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

    /**
     * @param array $select
     * @return mixed
     */
    public static function select(array $select)
    {
        self::$_values['Select'] = $select;

        return self::getCallClass();
    }

    /**
     * @param $column
     * @param null $operator
     * @param null $value
     * @param false boolean $fulltext_search
     * @param string $boolean
     * @return mixed
     */
    public static function where($column, $operator = '=', $value = null, $fulltext_search = false,  $boolean = 'and')
    {
        if ($fulltext_search) {
            $prefix = '@';
        } else {
            $prefix = '';
        }

        switch ($operator) {
            case '==' || '=':
                self::setWhere($column, $value, $prefix);
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

    /**
     * @param int $per_page
     * @param int $page
     * @return mixed
     */
    public static function limit($per_page = 15, $page = 1)
    {
        self::$_values['Limit'] = ['per_page' => $per_page, 'page' => $page];

        return self::getCallClass();
    }


    /**
     * @param $array
     * @return mixed
     */
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

    /**
     * @return string
     */
    public function getUrl()
    {
        $http = $this->https ? 'https://' : 'http://';

        return $http . $this->host . '/' . ($this->version ? $this->version . '/' : '');

    }

    /**
     * @return mixed
     */
    public function getLink()
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }


    /**
     * @return mixed
     */
    public static function getCallClass()
    {
        $cName = get_called_class();//Получем название класса
        return new $cName();
    }

    /**
     * @param array $query
     */
    public static function setSelect(array $query)
    {
        self::$_values['Select'] = $query;
    }

    /**
     * @param $column
     * @param $value
     * @param $operator
     */
    private static function setWhere($column, $value, $operator)
    {
        self::$_values['Where'][] = ['column' => $column, 'search' => $value, 'operator' => $operator];

    }

    /**
     * @return string
     */
    public function setClassName()
    {

        $r = explode("\\", get_class($this));
        return $this->className = mb_strtolower(array_last($r));
    }

    /**
     * @param $key
     * @param $value
     */
    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    /**
     * @param $key
     * @param $value
     */
    public function setNewAttribute($key, $value)
    {
        $this->newAttributes[$key] = $value;
    }

    /**
     * @param $array
     * @return stdClass
     */
    public static function convertToObject($array) {
        $object = new stdClass();
        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $value = self::convertToObject($value);
            }
            $object->$key = $value;
        }
        return $object;
    }

    /**
     * @param $th
     * @return string
     */
    public static function className($th)
    {
        $r = explode("\\", $th);

        return mb_strtolower(array_last($r));
    }

    /**
     * @param $name
     * @return mixed|null
     */
    public function __get($name) {
        if(isset($this->attributes[$name])) {
            return $this->attributes[$name];
        } else {
            return null;
        }

    }

    /**
     * @param $key
     * @param $value
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
        $this->setNewAttribute($key, $value);

    }
}
<?php
/**
 * @author: Igor Shesteryakov <gatewayuo@gmail.com>
 */

namespace Sberned\RestORM;

use anlutro\cURL\cURL;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use ArrayAccess;
use Illuminate\Support\Collection;
use JsonSerializable;
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
abstract class Model implements Arrayable, ArrayAccess, Jsonable, JsonSerializable
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
    protected $_values = array();

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
    public function delete()
    {
        return $this->deleteThis();
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
            $data = $this->convertToObject($res->$className);

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
            $data = $this->convertToObject($res->$className);

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
    private function deleteThis()
    {
        $objects = $this->all();
        foreach ($objects as $object) {
            if (property_exists($object, "id")) {
                $newQuery = new Builder($this->className, $this->getUrl(), $this->getLink() . '/' . $object->id,
                    'DELETE', true, [], false);
                $res = $newQuery->send();
            }
        }
        return true;
    }

    /**
     * @param array $columns
     * @return stdClass
     */
    public function first(array $columns = [])
    {
        if(!empty($columns)) {
            $this->setSelect($columns);
        }

        $className = $this->className;
        $aliasList = $this->alias_list;

        $newQuery = new Builder($this->className, $this->getUrl(), $this->getLink(), 'get', true, []);
        $newQuery->limit(1, 1);
        $newQuery->setValues($this->_values);
        $res = $newQuery->send();

        if (isset($res->$className)) {
            $dataObj = $className;
        } else {
            $dataObj = $aliasList;
        }

        if (!empty($res->$dataObj)) {
            $data = array_first($res->$dataObj);
            if(!empty($data)) {
                foreach ($data as $key => $val) {
                    $this->setAttribute($key, $val);
                }
            }
            $this->exist = true;

            return $this;
        } else {
            return null;
        }
    }

    /**
     * @param array $columns
     * @return stdClass
     */
    public function get(array $columns = [])
    {
        if(!empty($columns)) {
            $this->setSelect($columns);
        }

        $newQuery = new Builder($this->className, $this->getUrl(), $this->getLink(), 'get', true, []);
        $newQuery->setValues($this->_values);
        $res = $newQuery->send();
        $className = $this->alias_list;

        return collect($res->$className);
    }

    /**
     * @param array $columns
     * @return stdClass
     */
    public function all(array $columns = [])
    {
        if(!empty($columns)) {
            $this->setSelect($columns);
        }

        $newQuery = new Builder($this->className, $this->getUrl(), $this->getLink(), 'get', true, []);
        $newQuery->setValues($this->_values);
        $res = $newQuery->send();
        $className = $this->alias_list;

        return collect($res->$className);
    }

    /**
     * @param int $id
     * @return mixed
     */
    public function findOne(int $id)
    {
        if(!empty($columns)) {
            $this->setSelect($columns);
        }

        $newQuery = new Builder($this->getClassName(), $this->getUrl(), $this->getLink() . '/' . $id, 'get', true, []);
        $newQuery->setValues($this->_values);

        try {
            $res = $newQuery->send();
        } catch (MassAssignmentException $e) {
            return null;
        }

        $className = $this->className;

        $data =  $res->$className;
        if(!empty($data)) {
            foreach ($data as $key => $val) {
                $this->setAttribute($key, $val);
            }
        }
        $this->exist = true;
        return $this;
    }

    public function paginate($limit, $page)
    {
        $this->limit($limit, $page);
        $newQuery = new Builder($this->className, $this->getUrl(), $this->getLink(), 'get', true, []);
        $newQuery->setValues($this->_values);
        $res = $newQuery->send();
        $className = $this->url;

        return [
            'data' => collect($res->$className),
            'meta' => $this->convertToObject($res->meta)
        ];
    }

    /**
     * @param array $select
     * @return mixed
     */
    public function addSelect($select)
    {
        if (!is_array($select)) {
            $select = func_get_args();
        }

        $this->_values['Select'] = $select;

        return $this;
    }

    /**
     * @param $column
     * @param null $operator
     * @param null $value
     * @param false boolean $fulltext_search
     * @param string $boolean
     * @return mixed
     */
    public function addWhere($column, $operator = '=', $value = null)
    {
        if (count(func_get_args()) == 2) {
            $value = $operator;
            $operator = '=';
        }

        switch ($operator) {
            case '=':
                $this->setWhere($column, $value, '');
                break;
            case '<>':
            case '!=':
                $this->setWhere($column, $value, '^');
                break;
            case '<':
                $this->setWhere($column . '__lt', $value, '');
                break;
            case '<=':
                $this->setWhere($column . '__lte', $value, '');
                break;
            case '>':
                $this->setWhere($column . '__gt', $value, '');
                break;
            case '>=':
                $this->setWhere($column . '__gte', $value, '');
                break;
            case 'like':
                $this->setWhere($column, $value . ':*', '@');
                break;
        }

        return $this;
    }

    public function addWhereIn($column, $values)
    {
        if ($values instanceof Collection) {
            $values = $values->all();
        }

        $values = array_filter($values, function($value) {
            return $value > 0;
        });
        $values = array_unique($values);

        $this->setWhere($column, implode(',', $values), '');

        return $this;
    }

    public function orderBy($column, $order)
    {
        $this->_values['Orderby'][] = strtolower($order) == 'asc' ? $column : '-' . $column;

        return $this;
    }

    /**
     * @param int $per_page
     * @param int $page
     * @return mixed
     */
    public function limit($per_page = 15, $page = 1)
    {
        $this->_values['Limit'] = ['per_page' => $per_page, 'page' => $page];

        return $this;
    }


    /**
     * @param $array
     * @return mixed
     */
    public function addWith($array)
    {
        if (!is_array($array)) {
            $array = func_get_args();
        }

        if(is_array($array)){
            foreach ($array as $arr) {
                $this->_values['With'][] =  $arr;
            }
        } else {
            $this->_values['With'][] =  $array;
        }

        return $this;
    }

    public function addSearch($query)
    {
        return $this->addWhere('search', $query . ':*');
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
    public function setSelect($query)
    {
        if (!is_array($query)) {
            $query = func_get_args();
        }

        $this->_values['Select'] = $query;
    }

    /**
     * @param $column
     * @param $value
     * @param $operator
     */
    private function setWhere($column, $value, $operator)
    {
        $this->_values['Where'][] = ['column' => $column, 'search' => $value, 'operator' => $operator];

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
    public function convertToObject($array) {
        $object = new stdClass();
        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $value = $this->convertToObject($value);
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

    /**
     * Convert the model instance to JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    public function toArray()
    {
        return $this->attributes;
    }

    /**
     * Determine if the given attribute exists.
     *
     * @param  mixed  $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->$offset);
    }

    /**
     * Get the value for a given offset.
     *
     * @param  mixed  $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    /**
     * Set the value for a given offset.
     *
     * @param  mixed  $offset
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->$offset = $value;
    }

    /**
     * Unset the value for a given offset.
     *
     * @param  mixed  $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->$offset);
    }

    public function __call($name, $arguments)
    {
        if ($name == 'find') {
            return call_user_func_array([$this, 'findOne'], $arguments);
        }

        if ($name == 'findMany') {
            return call_user_func_array([$this, 'findMany'], $arguments);
        }

        $method = 'add' . ucfirst($name);
        if (!method_exists($this, $method)) {
            throw new \Exception('Method ' . $method . ' does not exists');
        }

        return call_user_func_array([$this, $method], $arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        $instance = new static;

        return call_user_func_array([$instance, $name], $arguments);
    }
}
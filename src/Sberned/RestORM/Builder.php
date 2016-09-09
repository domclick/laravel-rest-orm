<?php

namespace Sberned\CurlORM;

use anlutro\cURL\cURL;
use stdClass;

class Builder
{

    protected $url;

    protected $link;

    protected $method;

    protected $json = true;

    protected $data;

    protected $basicAuth = false;

    protected $authLogin;
    protected $authPass;

    protected $pagination;

    protected $scopes;

    protected $className;

    protected $result = [];
    protected $fields = [];

    /**
     * Builder constructor.
     * @param string $className
     * @param string $url
     * @param string $link
     * @param string $method
     * @param bool $json
     * @param array $data
     */
    public function __construct(string $className, string $url, string $link, string $method = 'get', bool $json, array $data = [])
    {
        $this->className = $className;
        $this->url = $url;
        $this->link = $link;
        $this->method = $method;
        $this->json = $json;
        $this->data = $data;
    }

    public function send()
    {
        $cli = new cURL();

        $link = $this->url  . $this->getLink();

        if ( $this->isJson() ) {
            $result = $cli->newJsonRequest($this->method, $link, $this->data);
        } else {
            $result = $cli->newRequest($this->method, $link, $this->data);

        }
        if($this->isBasicAuth()) {
            $result->setUser($this->authLogin())->setPass($this->authPass());
        }

        $res = $result->send();
        if($res->statusCode < 400) {
           $this->result = $this->convertToObject(json_decode($res->body));
            if($this->result->success) {
                return $this->result->data;
            }
        } else {
            throw (new MassAssignmentException)->setError($this->url, $res->body);
        }
    }



    public function orderBy($attribute, $order = 'ASC')
    {
        if ($order == 'ASC') {
            $this->addToOrdering($attribute);
        } else {
            $this->addToOrdering(-$attribute);
        }
    }

    public function groupBy($q, $w)
    {

    }

    public function limit($per_page, $page)
    {
        $this->pagination = "per_page={$per_page}&page={$page}";
    }

    public function setLimit($arr)
    {
        $this->pagination = "per_page={$arr['per_page']}&page={$arr['page']}";
    }

    /**
     * @param array $select
     */
    public function setSelect(array $select)
    {
        $this->fields = $select;
    }


    /**
     * Get the hydrated models without eager loading.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Model[]
     */
    public function getModels($columns = ['*'])
    {
        $results = $this->query->get($columns);

        $connection = $this->model->getConnectionName();

        return $this->model->hydrate($results, $connection)->all();
    }

    public function applyScopes()
    {
        if (! $this->scopes) {
            return $this;
        }

        $builder = clone $this;

        return $builder;
    }
    public function all()
    {
        $result = $this->send();

        $url = $this->link;

        return $result->$url;
    }
    /**
     * @return mixed
     */
    public function getLink()
    {
        return $this->link . "?" . $this->getFields() . $this->getIncludes()  . $this->getOrdering()  . $this->pagination . $this->getWhereis();
    }

    /**
     * @param mixed $url
     */
    public function setLink($url)
    {
        $this->url = $url;
    }

    public function setOrderby($value)
    {
        $this->ordering[] = $value;
    }

    public function setWith(array $value)
    {
        $this->includes = $value;

    }

    public function getOrdering() : string
    {
        $res = "";
        if(!empty($this->ordering)) {
            $res = "ordering=";
            foreach ($this->ordering as $key => $field) {
                $res .= "{$field}";

                if (isset($this->ordering[$key + 1])) {
                    $res .= ',';
                }
            }
            $res .= '&';

        }

        return $res;
    }

    public function getFields() : string
    {
        $res ='';
        if(!empty($this->fields)) {
            $res = "fields[{$this->className}]=";
            foreach ($this->fields as $key => $field) {
                $res .= "{$field}";

                if (isset($this->fields[$key + 1])) {
                    $res .= ',';
                }
            }
            $res .= '&';

        }

        return $res;
    }

    public function getIncludes() : string
    {
        $res = "";
        if(!empty($this->includes)) {
            foreach ($this->includes as $key => $field) {
                $res .= "includes[]={$field}";

                if (isset($this->includes[$key + 1])) {
                    $res .= ',';
                }
            }
            $res .= '&';
        }

        return $res;
    }

    /**
     * @return array
     */
    public function getWhere(): array
    {
        return $this->where;
    }

    /**
     * @param array $where
     */
    public function setWhere(array $array)
    {
        foreach ($array as $key => $item) {
            $this->where[] = ['key' => $item['column'], 'search' => $item['search'], 'operator' => $item['operator']];
        }
    }

    public function getWhereis(): string
    {
        $res = "";
        if(!empty($this->where)) {
            foreach ($this->where as $key => $field) {
                $res .= $field['key'] . "={$field['operator']}{$field['search']}";

                if (isset($this->where[$key + 1])) {
                    $res .= ',';
                }
            }
            $res .= '&';
        }

        return $res;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @param string $method
     */
    public function setMethod(string $method)
    {
        $this->method = $method;
    }

    /**
     * @return boolean
     */
    public function isJson(): bool
    {
        return $this->json;
    }

    /**
     * @param boolean $json
     */
    public function setJson(bool $json)
    {
        $this->json = $json;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array $data
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return boolean
     */
    public function isBasicAuth(): bool
    {
        return $this->basicAuth;
    }

    /**
     * @param boolean $basicAuth
     */
    public function setBasicAuth(bool $basicAuth)
    {
        $this->basicAuth = $basicAuth;
    }

    /**
     * @return mixed
     */
    public function getAuthLogin()
    {
        return $this->authLogin;
    }

    /**
     * @param mixed $authLogin
     */
    public function setAuthLogin($authLogin)
    {
        $this->authLogin = $authLogin;
    }

    /**
     * @return mixed
     */
    public function getAuthPass()
    {
        return $this->authPass;
    }

    /**
     * @param mixed $authPass
     */
    public function setAuthPass($authPass)
    {
        $this->authPass = $authPass;
    }


    public function setValues(array $values)
    {
        foreach ($values as $key => $value) {
            $nameMethod = 'set' . $key;
            $this->$nameMethod($value);
        }
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

}
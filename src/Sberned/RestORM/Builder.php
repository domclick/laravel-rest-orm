<?php

/**
 * @author: Igor Shesteryakov <gatewayuo@gmail.com>
 */

namespace Sberned\RestORM;

use anlutro\cURL\cURL;
use stdClass;
use Illuminate\Support\Facades\File;

/**
 * Class Builder
 * @package Sberned\RestORM
 */
class Builder
{

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $link;

    /**
     * @var string
     */
    protected $method;

    /**
     * @var bool
     */
    protected $json = true;

    /**
     * @var array
     */
    protected $data;

    /**
     * @var bool
     */
    protected $basicAuth = false;

    /**
     * @var
     */
    protected $authLogin;
    /**
     * @var
     */
    protected $authPass;

    /**
     * @var
     */
    protected $pagination;

    /**
     * @var
     */
    protected $scopes;

    /**
     * @var string
     */
    protected $className;

    /**
     * @var array
     */
    protected $result = [];
    /**
     * @var array
     */
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

    /**
     * @return mixed
     */
    public function send()
    {
        $cli = new cURL();

        $link = $this->url . $this->getLink();

        if ( $this->json ) {
            $result = $cli->newJsonRequest($this->method, $link, $this->data);
        } else {
            $result = $cli->newRequest($this->method, $link, $this->data);

        }
        if($this->basicAuth) {
            $result->setUser($this->authLogin())->setPass($this->authPass());
        }
        $result->setOption(CURLOPT_SSL_VERIFYPEER, false);
        $res = $result->send();
        if($res->statusCode < 400) {
            $this->result = Model::convertToObject(json_decode($res->body));
            if($this->result->success) {
                return $this->result->data;
            }
        } else {
            throw (new MassAssignmentException)->setError($this->url, $res->body);
        }
    }

    /**
     * @param $per_page
     * @param $page
     */
    public function limit($per_page, $page)
    {
        $this->pagination = "per_page={$per_page}&page={$page}";
    }

    /**
     * @return string
     */
    public function getLink(): string
    {
        return $this->link . "?" . $this->getFields() . $this->getIncludes()  . $this->getOrdering()  . $this->pagination .
            '&' . $this->getWhereis();
    }

    /**
     * @return string
     */
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

    /**
     * @return string
     */
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

    /**
     * @return string
     */
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
     * @return string
     */
    public function getWhereis(): string
    {
        $res = "";
        if(!empty($this->where)) {
            foreach ($this->where as $key => $field) {
                $res .= $field['key'] . "={$field['operator']}{$field['search']}";

                if (isset($this->where[$key + 1])) {
                    $res .= '&';
                }
            }
            $res .= '&';
        }

        return $res;
    }

    /**
     * @param $arr
     */
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
     * @param $url
     */
    public function setLink($url)
    {
        $this->url = $url;
    }

    /**
     * @param $value
     */
    public function setOrderby($value)
    {
        $this->ordering[] = $value;
    }

    /**
     * @param array $value
     */
    public function setWith(array $value)
    {
        $this->includes = $value;

    }

    /**
     * @param array $array
     */
    public function setWhere(array $array)
    {
        foreach ($array as $key => $item) {
            $this->where[] = ['key' => $item['column'], 'search' => $item['search'], 'operator' => $item['operator']];
        }
    }


    /**
     * @param array $values
     */
    public function setValues(array $values)
    {
        foreach ($values as $key => $value) {
            $nameMethod = 'set' . $key;
            $this->$nameMethod($value);
        }
    }

}
<?php

namespace Sberned\RestORM;

use RuntimeException;

class MassAssignmentException extends RuntimeException
{
    public $error;
    /***
     * @param  string   $error
     * @return $this
     */
    public function setError($error, $message)
    {
        $this->error = $error;

        $this->message = "Есть проблемы с ресурсом: '{$error}', ошибка: {$message}].";

        return $this;
    }

    /**
     *
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }
}

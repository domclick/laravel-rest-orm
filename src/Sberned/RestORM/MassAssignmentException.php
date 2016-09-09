<?php

namespace Sberned\CurlORM;

use RuntimeException;

class MassAssignmentException extends RuntimeException
{
    public $error;
    /**
     * Set the affected Eloquent model.
     *
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
     * Get the affected Eloquent model.
     *
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }
}

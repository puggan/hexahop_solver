<?php

namespace Puggan\Solver\Entities\JSON;

abstract class Template
{
    /**
     * @param \stdClass|self $data
     */
    public function __construct(Template|\stdClass $data)
    {
        foreach (get_object_vars($data) as $key => $value) {
            $this->$key = $value;
        }
    }
}

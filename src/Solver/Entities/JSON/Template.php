<?php

	namespace Puggan\Solver\Entities\JSON;

	abstract class Template
	{
		public function __construct($data)
		{
			foreach(get_object_vars($data) as $key => $value)
			{
				$this->$key = $value;
			}
		}
	}

<?php

namespace CaT\TRTFixtures;

use CaT\Filter as Filters;
use CaT\TableRelations as TR;

class SqlQueryInterpreterWrap extends TR\SqlQueryInterpreter {

	public function __construct()
	{

	}

	public function _interpretField(Filters\Predicates\Field $field) {
		return $this->interpretField($field);
	}

	public function _interpretTable(TR\Tables\AbstractTable $table) {
		return $this->interpretTable($table);
	}
}


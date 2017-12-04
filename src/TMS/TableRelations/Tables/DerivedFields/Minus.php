<?php

namespace ILIAS\TMS\TableRelations\Tables\DerivedFields;
use ILIAS\TMS\TableRelations\Tables as T;
use ILIAS\TMS\Filter as Filters;

/**
 * Form the difference between two fieldentries in the same row.
 */
class Minus extends T\DerivedField {
	public function __construct(Filters\PredicateFactory $f, $name, Filters\Predicates\Field $left, Filters\Predicates\Field $right) {
		$this->derived_from[] = $left;
		$this->derived_from[] = $right;
		$this->left = $left;
		$this->right = $right;
		parent::__construct($f, $name);
	}

	/**
	 * Minuend field.
	 * @return AbstractField
	 */
	public function left() {
		return $this->left;
	}

	/**
	 * Subtrahend field.
	 * @return AbstractField
	 */
	public function right() {
		return $this->right;
	}
}
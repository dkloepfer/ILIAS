<?php
use CaT\TableRelations as TR;
use CaT\Filter as Filters;
class SqlQueryInterpreterTest extends PHPUnit_Framework_TestCase {
	public function setUp() {
		ilUnitUtil::performInitialisation();
		global $ilDB;
		$this->db = $ilDB;

		$this->gf = new TR\GraphFactory();
		$this->pf = new Filters\PredicateFactory();
		$this->tf = new TR\TableFactory($this->pf, $this->gf);
		$this->i = new  SqlQueryInterpreter( new Filters\SqlPredicateInterpreter($this->db), $this->pf, $this->db);
	}

	protected function field($id) {
		return $this->tf->field($id);
	}

	protected function table($id) {
		return $this->tf->table('foo',$id);
	}



	public function test_field() {

		$i = $this->i;
		$f1 = $this->field('f1');
		$f2 = $this->field('f2');
		$f3 = $this->field('f3');
		$tf = $this->tf;
		$table = $this->table('a')->addField($f1)->addField($f2)->addField($f3);
		$this->assertRegExp('#\\s*a\\.f1\\s*#',$i->_interpretField($table->field('f1')));
		$this->assertRegExp('#\\s*a\\.f1\\s*\\+\\s*a\\.f2\\s*#',$i->_interpretField($tf->plus('plus',$table->field('f1'),$table->field('f2'))));
		$this->assertRegExp('#\\s*a\\.f1\\s*/\\s*a\\.f2\\s*#',$i->_interpretField($tf->quot('quot',$table->field('f1'),$table->field('f2'))));
		$this->assertRegExp('#\\s*a\\.f1\\s*\\-\\s*a\\.f2\\s*#',$i->_interpretField($tf->minus('quot',$table->field('f1'),$table->field('f2'))));
		$this->assertRegExp('#\\s*a\\.f1\\s*\\*\\s*a\\.f2\\s*#',$i->_interpretField($tf->times('quot',$table->field('f1'),$table->field('f2'))));
		$this->assertRegExp('#\\s*SUM\\(\\s*a\\.f1\\s*\\)\\s*#',$i->_interpretField($tf->sum('sum',$table->field('f1'))));
		$this->assertRegExp('#\\s*COUNT\\(\\s*\\*\\s*\\)#',$i->_interpretField($tf->countAll('bal')));
		$this->assertRegExp('#\\s*MAX\\(\\s*a\\.f1\\s*\\)\\s*#',$i->_interpretField($tf->max('max',$table->field('f1'))));
		$this->assertRegExp('#\\s*MIN\\(\\s*a\\.f1\\s*\\)\\s*#',$i->_interpretField($tf->min('min',$table->field('f1'))));
		$this->assertRegExp('#\\s*MIN\\(\\s*a\\.f1\\s*\\)\\s*#',$i->_interpretField($tf->min('foo',$table->field('f1'))));
		$this->assertRegExp('#\\s*SUM\\(\\s*MIN\\(\\s*a\\.f1\\s*\\)\\s*/\\s*MAX\\(\\s*a\\.f2\\s*\\)\\s*\\)\\s*#',
			$i->_interpretField(
				$this->tf->sum('sum',
					$this->tf->quot('quot',$this->tf->min('min',$table->field('f1')),
						$this->tf->max('max',$table->field('f2'))))));
	}

	public function test_table() {
		$v = '[\\n\\r\\s]';
		$i = $this->i;
		$tf = $this->tf;
		$f1 = $this->field('f1');
		$f2 = $this->field('f2');
		$f3 = $this->field('f3');
		$table = $this->table('a')->addField($f1)->addField($f2)->addField($f3);
		$this->assertRegExp('#\\s*foo\\s+AS\\s+a\\s*#',$i->_interpretTable($table));
		$space = $tf->TableSpace()->addTablePrimary($table)->setRootTable($table);
		$space->request($tf->times('times',$f1,$f2));
		$derived = $tf->DerivedTable($space,'derived');
		$this->assertRegExp(
			str_replace(PHP_EOL, ' ', '#\\s*\\(SELECT\\s+a\\.f1\\s*\\*\\s*a\\.f2\\s+AS\\s+times[\\s\\r\\n]+FROM\\s+foo\\s+AS\\s+a\\s*\\)\\s*AS\\s+derived\\s*#')
			,$i->_interpretTable($derived));
	}
}

class SqlQueryInterpreter extends TR\SqlQueryInterpreter {

	public function _interpretField(Filters\Predicates\Field $field) {
		return $this->interpretField($field);
	}

	public function _interpretTable(TR\Tables\AbstractTable $table) {
		return $this->interpretTable($table);
	}
}
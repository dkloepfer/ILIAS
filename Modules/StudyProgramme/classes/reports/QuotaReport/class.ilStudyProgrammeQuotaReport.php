<?php

class ilStudyProgrammeQuotaReport
{
	protected $db;


	public function __construct(
		ilDBInterface $db
	)
	{
		$this->db = $db;
	}

	public function getData() : array
	{
		$res = $this->db->query($this->getSql());
		$return = [];
		while($rec = $this->db->fetchAssoc($res)) {
			$rec["prg_memships"] = (int)$rec["prg_memships"];
			$rec["prg_in_progress_memships"] = (int)$rec["prg_in_progress_memships"];
			$rec["prg_completed_memships"] = (int)$rec["prg_completed_memships"];
			$rec["prg_failed_memships"] = (int)$rec["prg_failed_memships"];
			$rec["prg_distinct_qualified"] = (int)$rec["prg_distinct_qualified"];
			$rec["prg_distinct_members"] = (int)$rec["prg_distinct_members"];
			$rec["prg_in_progress_memships_rel"] = (int)$rec["prg_memships"] > 0 ?
				 100*(int)$rec["prg_in_progress_memships"]/(int)$rec["prg_memships"] : 0;
			$rec["prg_completed_memships_rel"] = (int)$rec["prg_memships"] > 0 ?
				 100*(int)$rec["prg_completed_memships"]/(int)$rec["prg_memships"] : 0;
			$rec["prg_failed_memships_rel"] = (int)$rec["prg_memships"] > 0 ?
				 100*(int)$rec["prg_failed_memships"]/(int)$rec["prg_memships"] : 0;
			$rec["prg_distinct_members_qualified_rel"] = (int)$rec["prg_distinct_members"] > 0 ?
				 100*(int)$rec["prg_distinct_qualified"]/(int)$rec["prg_distinct_members"] : 0;
			$return[] = $rec;
		}
		return $return;
	}

	/**
	 * id => lang_String
	 */
	public function fields() : array
	{
		return [
			'prg_title'
				=> 'prg_title_column',
			'prg_memships'
				=> 'prg_memships_column',
			'prg_distinct_members'
				=> 'prg_distinct_members_column',
			'prg_in_progress_memships'
				=> 'prg_in_progress_memships_column',
			'prg_in_progress_memships_rel'
				=> 'prg_in_progress_memships_rel_column',
			'prg_completed_memships'
				=> 'prg_completed_memships_column',
			'prg_completed_memships_rel'
				=> 'prg_completed_memships_rel_column',
			'prg_failed_memships'
				=> 'prg_failed_memships_column',
			'prg_failed_memships_rel'
				=> 'prg_failed_memships_rel_column',
			'prg_distinct_members_qualified_rel'
				=> 'prg_distinct_members_qualified_rel_column'
		];
	}

	protected function getSql() : string
	{
		$s_inprogress_q = $this->db->quote(
			ilStudyProgrammeProgress::STATUS_IN_PROGRESS,
			"integer"
		);
		$s_failed_q = $this->db->quote(
			ilStudyProgrammeProgress::STATUS_FAILED,
			"integer"
		);
		$s_completed_q = $this->db->quote(
			ilStudyProgrammeProgress::STATUS_COMPLETED,
			"integer"
		);
		$s_accredited_q = $this->db->quote(
			ilStudyProgrammeProgress::STATUS_ACCREDITED,
			"integer"
		);
		return "SELECT prg.title as prg_title"
				." ,parent.obj_id as parent"
				." ,prg_ref.ref_id"
				." ,COUNT(*) as prg_memships"
				." ,COUNT(DISTINCT progress.usr_id) as prg_distinct_members"
				." ,SUM(IF(progress.status = $s_inprogress_q,1,0)) as prg_in_progress_memships"
				." ,SUM(IF(progress.status = $s_failed_q,1,0)) as prg_failed_memships"
				." ,SUM(IF(progress.status IN($s_completed_q,$s_accredited_q),1,0)) as prg_completed_memships"
				." ,COUNT(DISTINCT qualified.usr_id) as prg_distinct_qualified"
				."	FROM object_data prg"
				."	JOIN object_reference prg_ref ON prg.obj_id = prg_ref.obj_id"
				."	JOIN tree prg_tree ON prg_ref.ref_id = child"
				." 	JOIN object_reference parent_ref ON parent_ref.ref_id = prg_tree.parent"
				."	LEFT JOIN object_data parent ON parent.obj_id = parent_ref.obj_id AND parent.type = 'prg'"
				."	JOIN prg_usr_progress progress ON prg.obj_id = progress.prg_id"
				."	LEFT JOIN"
				."		("
				."			SELECT ass.usr_id,ass.root_prg_id prg_id FROM prg_usr_assignments ass"
				."				JOIN prg_usr_progress prgrs ON"
				."					prgrs.assignment_id = ass.id AND prgrs.prg_id = ass.root_prg_id"
				."				WHERE (prgrs.vq_date IS NULL OR prgrs.vq_date > NOW())"
				."					AND prgrs.status IN($s_completed_q,$s_accredited_q)"
				."		) qualified"
				."		 ON qualified.prg_id = prg.obj_id"
				."	WHERE prg.type = 'prg'"
				."		AND prg_ref.deleted IS NULL"
				."	GROUP BY prg.obj_id"
				."	HAVING prg_memships > 0 OR parent IS NULL";
	}
}
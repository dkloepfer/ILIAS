<?php

require_once("Services/Cron/classes/class.ilCronManager.php");
require_once("Services/Cron/classes/class.ilCronJob.php");
require_once("Services/Cron/classes/class.ilCronJobResult.php");

class gevReminderVirtualTrainingJob extends ilCronJob {

	public function getId() {
		return "gev_mail_reminder_virtual_training";
	}
	
	public function getTitle() {
		return "Versendet eine Erinnerung eine Stunde bevor das virtuelle Training beginnt.";
	}

	public function hasAutoActivation() {
		return true;
	}
	
	public function hasFlexibleSchedule() {
		return false;
	}
	
	public function getDefaultScheduleType() {
		return ilCronJob::SCHEDULE_TYPE_IN_MINUTES;
	}
	
	public function getDefaultScheduleValue() {
		return 1;
	}
	
	public function run() {
		require_once("Services/GEV/Utils/classes/class.gevCourseUtils.php");
		require_once("Services/GEV/Utils/classes/class.gevObjectUtils.php");
		require_once("Services/GEV/Mailing/classes/class.gevVirtualTrainingAutoMails.php");
		
		global $ilLog, $ilDB;
		
		$cron_result = new ilCronJobResult();
		$cron_result->setStatus(ilCronJobResult::STATUS_OK);

		$ilLog->write("gevReminderVirtualTrainingJob::run: collect crs_ids.");

		$today_date = date("Y-m-d");
		$today_time = date("H:i:00");

		$query = "SELECT crs.crs_id, ml.id\n"
				." FROM hist_course crs\n"
				." LEFT JOIN mail_log ml ON crs.crs_id = ml.obj_id\n"
				."       AND ml.mail_id = ".$ilDB->quote("reminder_virtual_training","text")."\n"
				." WHERE crs.crs_id > 0\n"
				."       AND crs.hist_historic = 0\n"
				."       AND crs.begin_date = ".$ilDB->quote($today_date,"text")."\n"
				."       AND crs.type = ".$ilDB->quote("Virtuelles Training","text")."\n"
				." HAVING ml.id IS NULL";

		$res = $ilDB->query($query);
		ilCronManager::ping($this->getId());

		while($row = $ilDB->fetchAssoc($res)) {
			$crs_id = $row["crs_id"];
			$auto_mails = new gevVirtualTrainingAutoMails($crs_id);
			$mail = $auto_mails->getAutoMail("reminder_virtual_training");
			ilCronManager::ping($this->getId());

			if($mail->getScheduledFor() && $mail->getScheduledFor()->format("Y-m-d") == $today_date && $mail->getScheduledFor()->format("H:i:s") <= $today_time && !$mail->getCourseIsStarted()) {
				$ilLog->write("gevReminderVirtualTrainingJob::run: Sending mail to $crs_id");

				try {
					$mail->send();
				}
				catch (Exception $e) {
					$ilLog->write("gevReminderVirtualTrainingJob::run: error when sending mail reminder_virtual_training. ".$e->getMessage());
				}
				// i'm alive!
				ilCronManager::ping($this->getId());
			} else {
				$ilLog->write("gevReminderVirtualTrainingJob::run: not send to $crs_id");
			}
		}

		$cron_result->setStatus(ilCronJobResult::STATUS_OK);
		return $cron_result;
	}
}
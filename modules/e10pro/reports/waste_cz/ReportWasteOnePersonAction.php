<?php

namespace e10pro\reports\waste_cz;

use \Shipard\Base\DocumentAction, \Shipard\Report\MailMessage, \Shipard\Utils\Utils;


/**
 * Class RequestForPaymentAction
 * @package e10doc\balance
 */
class ReportWasteOnePersonAction extends DocumentAction
{
	var $testRun = 0;
	var $debug = 0;

	public function init ()
	{
		parent::init();
	}

	public function actionName ()
	{
		return 'Rozeslat přehled odpadů';
	}

	public function sendOne ($personNdx)
	{
		$emailsTo = $this->loadEmails($personNdx);
		if ($emailsTo === '')
			return;

		$documentTable = $this->app()->table ('e10.persons.persons');

		$person = $documentTable->loadItem ($personNdx);

		$report = new \e10pro\reports\waste_cz\ReportWasteOnePerson($documentTable, $person);
		$report->calendarYear = intval($this->params['data-param-calendar-year']);
		$report->init();
		$report->renderReport ();
		$report->createReport ();
		$msgSubject = $report->createReportPart('emailSubject');
		$msgBody = $report->createReportPart('emailBody');

		$msg = new MailMessage($this->app());

		$msg->setFrom ($this->app->cfgItem ('options.core.ownerFullName'), $this->app->cfgItem ('options.core.ownerEmail'));
		$msg->setTo($emailsTo);
		$msg->setSubject($msgSubject);
		$msg->setBody($msgBody);
		$msg->setDocument ('e10.persons.persons', $personNdx, $report);

		$attachmentFileName = Utils::safeChars($report->createReportPart ('fileName'));
		if ($attachmentFileName === '')
			$attachmentFileName = 'priloha';

		$msg->addAttachment($report->fullFileName, $attachmentFileName.'.pdf', 'application/pdf');

		if ($this->debug)
		{
			echo $person['fullName'] . " [" . $emailsTo . "]" . "\n";
			echo " -> " . $report->fullFileName . "\n";
		}
		if ($this->testRun)
		{
			if (!is_readable($report->fullFileName))
			{
				echo "   -> ERROR: file not found\n";
			}
		}
		else
		{
			$msg->sendMail();
			$msg->saveToOutbox();
		}
	}

	public function run ()
	{
		$report = new \e10pro\reports\waste_cz\libs\ReportWasteCompanies($this->app());
		$report->subReportId = 'companiesIn';
		$report->calendarYear = intval($this->params['data-param-calendar-year']);
		$report->periodBegin = $this->params['data-param-period-begin'];
		$report->periodEnd = $this->params['data-param-period-end'];
		$report->createPdf();

		foreach ($report->persons as $personNdx)
		{
			$this->sendOne($personNdx);
		}
	}

	public function runFromCli($year)
	{
		$this->setParams([
			'data-param-calendar-year' => $year,
			'data-param-period-begin' => $year.'-01-01',
			'data-param-period-end' => $year.'-12-31',
		]);
		$this->run();
	}

	public function loadEmails ($personNdx)
	{
		$sql = 'SELECT valueString FROM [e10_base_properties] where [tableid] = %s AND [recid] = %i AND [property] = %s AND [group] = %s ORDER BY ndx';
		$emailsRows = $this->db()->query ($sql, 'e10.persons.persons', $personNdx, 'email', 'contacts')->fetchPairs ();
		return implode (', ', $emailsRows);
	}
}

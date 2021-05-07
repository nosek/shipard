<?php

namespace e10doc\taxes;

use \e10\utils, \e10\Utility, \e10\TableForm, \e10\Wizard;


/**
 * Class TaxReportRebuildWizard
 * @package e10doc\taxes
 */
class TaxReportRebuildWizard extends Wizard
{
	var $tableReports;
	var $taxReportNdx = 0;
	var $taxReportRecData;
	var $taxReportType;

	function init()
	{
		$this->tableReports = $this->app()->table('e10doc.taxes.reports');
		$this->taxReportNdx = ($this->focusedPK) ? $this->focusedPK : $this->recData['taxReportNdx'];
		$this->taxReportRecData = $this->tableReports->loadItem($this->taxReportNdx);
		$this->taxReportType = $this->app()->cfgItem('e10doc.taxes.reportTypes.'.$this->taxReportRecData['reportType'], NULL);
	}

	public function doStep ()
	{
		if ($this->pageNumber == 1)
		{
			$this->rebuild();
		}
	}

	public function renderForm ()
	{
		switch ($this->pageNumber)
		{
			case 0: $this->renderFormWelcome (); break;
			case 1: $this->renderFormDone (); break;
		}
	}

	public function renderFormWelcome ()
	{
		$this->setFlag ('formStyle', 'e10-formStyleSimple');
		$this->recData['taxReportNdx'] = $this->focusedPK;

		$this->openForm ();
			$this->addInput('taxReportNdx', '', self::INPUT_STYLE_STRING, TableForm::coHidden, 120);
		$this->closeForm ();
	}


	public function rebuild ()
	{
		$this->init();

		$trEngine = $this->app()->createObject($this->taxReportType['engine']);
		$trEngine->init();
		$trEngine->doRebuild($this->taxReportRecData);

		$this->stepResult ['close'] = 1;
	}

	public function createHeader ()
	{
		$this->init();

		$hdr = [];
		$hdr ['icon'] = 'icon-refresh';

		$hdr ['info'][] = ['class' => 'title', 'value' => 'Přegenerovat '.$this->taxReportType['name']];
		$hdr ['info'][] = ['class' => 'info', 'value' => $this->taxReportRecData['title']];

		return $hdr;
	}


}

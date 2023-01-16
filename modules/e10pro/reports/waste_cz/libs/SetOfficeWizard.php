<?php

namespace e10pro\reports\waste_cz\libs;
use \Shipard\Form\Wizard;


/**
 * class SetOfficeWizard
 */
class SetOfficeWizard extends Wizard
{
	public function doStep ()
	{
		if ($this->pageNumber == 1)
		{
			$this->doIt ();
		}
	}

	public function createHeader ()
	{
		$hdr = [];
		$hdr ['icon'] = 'system/actionPlay';

		$hdr ['info'][] = ['class' => 'title', 'value' => 'Načíst provozovny'];


		$hdr ['info'][] = ['class' => 'info', 'value' => 'TEST1'];
		$hdr ['info'][] = ['class' => 'info', 'value' => 'IČ: '.'TEST2'];

		return $hdr;
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

		$this->recData['personNdx'] = $this->app->testGetParam('personNdx');

		$this->openForm ();
			$this->addInput('personNdx', '', self::INPUT_STYLE_STRING, self::coHidden, 30);
			$this->initWelcome();
		$this->closeForm ();
	}

	function initWelcome ()
	{
    $this->addViewerWidget ('e10.persons.personsContacts', 'e10.persons.libs.viewers.ViewPersonContactsOffices',
                            ['personNdx' => strval($this->recData['personNdx'])],
                            TRUE, TRUE);
	}

	public function doIt ()
	{
		$personNdx = intval($this->recData['personNdx']);
		$officeNdx = intval($this->recData['viewersPks'][0] ?? 0);

    if (!$personNdx || !$officeNdx)
    {
      return;
    }

    $wre = new \e10pro\reports\waste_cz\libs\WasteReturnEngine($this->app);

    $q = ['SELECT * FROM [e10doc_core_heads]'];
    array_push($q, ' WHERE [person] = %i', $personNdx);
    array_push($q, ' AND [dateAccounting] >= %d', '2022-01-01');
    array_push($q, ' AND [docType] IN %in', ['purchase']);
    array_push($q, ' AND ([otherAddress1] IS NULL OR [otherAddress1] = %i)', 0);
    $rows = $this->app()->db()->query($q);
    foreach ($rows as $r)
    {
      $this->app()->db()->query('UPDATE [e10doc_core_heads] SET otherAddress1 = %i', $officeNdx, ' WHERE [ndx] = %i', $r['ndx']);
      $wre->year = 2022;

      $wre->resetDocument($r['ndx']);
    }

		$this->stepResult ['close'] = 1;
	}
}


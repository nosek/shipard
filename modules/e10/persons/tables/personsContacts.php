<?php

namespace e10\persons;


use \Shipard\Table\DbTable, \Shipard\Form\TableForm, \Shipard\Viewer\TableView, \Shipard\Utils\Str;
use \Shipard\Utils\World;
use \e10\base\libs\UtilsBase;


/**
 * class TablePersonsContacts
 */
class TablePersonsContacts extends DbTable
{
	public function __construct ($dbmodel)
	{
		parent::__construct ($dbmodel);
		$this->setName ('e10.persons.personsContacts', 'e10_persons_personsContacts', 'Adresy Osob');
	}

	public function checkBeforeSave (&$recData, $ownerData = NULL)
	{
		parent::checkBeforeSave ($recData, $ownerData);

		$recData['systemOrder'] = 99;
		if ($recData['flagMainAddress'])
			$recData['systemOrder'] = 1;
		elseif ($recData['flagOffice'])
			$recData['systemOrder'] = 20;
	}

	public function checkNewRec (&$recData)
	{
		parent::checkNewRec($recData);

		if (!isset($recData['adrCountry']) || $recData['adrCountry'] == 0)
		{
			$thc = $this->app()->cfgItem ('options.core.ownerDomicile', 'cz');
			$recData['adrCountry'] = World::countryNdx($this->app(), $thc);
		}
	}

	public function columnRefInputTitle ($form, $srcColumnId, $inputPrefix)
	{
		$pk = isset ($form->recData [$srcColumnId]) ? $form->recData [$srcColumnId] : 0;
		if (!$pk)
			return '';

		$recData = $this->loadItem($pk);
		if (!$recData)
			return '';

		$refTitle = [];
		if ($recData['adrStreet'] !== '')
			$refTitle[] = ['text' => $recData['adrStreet']];
		if ($recData['adrCity'] !== '')
			$refTitle[] = ['text' => $recData['adrCity']];
		if ($recData['adrZipCode'] !== '')
			$refTitle[] = ['text' => $recData['adrZipCode']];

		return $refTitle;
	}

	public function createHeader ($recData, $options)
	{
		$hdr = parent::createHeader ($recData, $options);

		/** @var \e10\persons\TablePersons $tablePersons */
		$tablePersons = $this->app()->table('e10.persons.persons');
		$personRecData = $tablePersons->loadItem($recData['person']);

		if ($personRecData)
		{
			$hdr ['info'][] = ['class' => 'title', 'value' => [
				[
					'text' => ($personRecData ['fullName'] !== '') ? $personRecData ['fullName'] : '!!!'.$recData['person'],
					'icon' => $tablePersons->tableIcon($personRecData),
					'docAction' => 'edit', 'table' => 'e10.persons.persons', 'pk' => strval($recData['person'])
				],
				['text' => '#'.$personRecData['id'], 'class' => 'pull-right']
			],
		];
		}

		return $hdr;
	}
}


/**
 * class ViewPersonsContactsCombo
 */

class ViewPersonsContactsCombo extends TableView
{
	var $personNdx = 0;
	var $classification = [];

	public function init ()
	{
		$this->enableDetailSearch = TRUE;
		$this->objectSubType = TableView::vsDetail;

    $this->personNdx = intval($this->queryParam('personNdx'));
		$this->addAddParam('person', $this->personNdx);

		$this->toolbarTitle = ['text' => 'Adresy', 'class' => 'h2 e10-bold'/*, 'icon' => 'system/iconMapMarker'*/];
		$this->setMainQueries();

		parent::init();
	}

	public function selectRows ()
	{
		$fts = $this->fullTextSearch ();

		$q [] = 'SELECT [contacts].* ';
		array_push ($q, ' FROM [e10_persons_personsContacts] AS [contacts]');
		array_push ($q, ' WHERE 1');
		array_push ($q, ' AND [contacts].[person] = %i', $this->personNdx);

		// -- fulltext
		if ($fts != '')
		{
			array_push ($q, ' AND (');
			array_push ($q, ' [contacts].adrCity LIKE %s', '%'.$fts.'%');
			array_push ($q, ' OR [contacts].adrStreet LIKE %s', '%'.$fts.'%');
			array_push ($q, ' OR [contacts].adrSpecification LIKE %s', '%'.$fts.'%');
			array_push ($q, ')');
		}

		$this->queryMain ($q, '[contacts].', ['[adrCity]', '[ndx]']);
		$this->runQuery ($q);

		$this->runQuery ($q);
	}

	public function selectRows2 ()
	{
		if (!count ($this->pks))
			return;

		$this->classification = UtilsBase::loadClassification ($this->table->app(), $this->table->tableId(), $this->pks);
	}

	public function renderRow ($item)
	{
		$at = $this->addressTypes[$item['type']];

		$listItem ['pk'] = $item ['ndx'];
		//$listItem ['icon'] = $this->table->tableIcon ($item);

    $address = '';
    $addressFlags = [];

    if ($item['flagAddress'])
    {
      $ap = [];

      if ($item['adrSpecification'] != '')
        $ap[] = $item['adrSpecification'];
      if ($item['adrStreet'] != '')
        $ap[] = $item['adrStreet'];
      if ($item['adrCity'] != '')
        $ap[] = $item['adrCity'];
      if ($item['adrZipCode'] != '')
        $ap[] = $item['adrZipCode'];

      $country = World::country($this->app(), $item['adrCountry']);
      $ap[] = /*$country['f'].' '.*/$country['t'];

      $address = implode(', ', $ap);

      if ($item['flagMainAddress'])
        $addressFlags[] = ['text' => 'Sídlo', 'class' => 'label label-default'];
      if ($item['flagPostAddress'])
        $addressFlags[] = ['text' => 'Korespondenční', 'class' => 'label label-default'];
      if ($item['flagOffice'])
        $addressFlags[] = ['text' => 'Provozovna', 'class' => 'label label-default'];

      if ($item['id1'] !== '')
        $addressFlags[] = ['text' => 'IČP: '.$item['id1'], 'class' => 'label label-default'];

      $listItem['t1'] = $address;

      if (count($addressFlags))
        $listItem['t2'] = $addressFlags;
    }

    if ($item['flagContact'])
    {
      $cf = [];
      if ($item['contactName'] != '')
        $cf[] = ['text' => $item['contactName'], 'class' => 'label label-default'];
      if ($item['contactRole'] != '')
        $cf[] = ['text' => $item['contactRole'], 'class' => 'label label-default'];
      if ($item['contactEmail'] != '')
        $cf[] = ['text' => $item['contactEmail'], 'class' => 'label label-default', 'icon' => 'system/iconEmail'];
      if ($item['contactPhone'] != '')
        $cf[] = ['text' => $item['contactPhone'], 'class' => 'label label-default', 'icon' => 'system/iconPhone'];

      if (count($addressFlags))
        $listItem['t3'] = $cf;
    }

		return $listItem;
	}

	function decorateRow (&$item)
	{
		if (isset ($this->classification [$item ['pk']]))
		{

			forEach ($this->classification [$item ['pk']] as $clsfGroup)
				$item ['t2'] = array_merge ($item ['t2'], $clsfGroup);
		}
	}
}

/**
 * class FormPersonContact
 */
class FormPersonContact extends TableForm
{
	var $idsOptions = NULL;

	public function renderForm ()
	{
		$this->loadContactIdsOptions();

		$this->setFlag ('formStyle', 'e10-formStyleSimple');
		$this->setFlag ('sidebarPos', TableForm::SIDEBAR_POS_RIGHT);

		$tabs ['tabs'][] = ['text' => 'Kontakt', 'icon' => 'formContacts'];
		$tabs ['tabs'][] = ['text' => 'Nastavení', 'icon' => 'system/formSettings'];

		$this->openForm ();
			$this->openTabs ($tabs);
				$this->openTab ();
					$this->openRow();
						$this->addColumnInput ('flagAddress', self::coRightCheckbox);
						if ($this->recData['flagAddress'])
						{
							$this->addColumnInput ('flagMainAddress', self::coRightCheckbox);
							$this->addColumnInput ('flagPostAddress', self::coRightCheckbox);
							$this->addColumnInput ('flagOffice', self::coRightCheckbox);
						}
					$this->closeRow();
					$needSep = 0;
					if ($this->recData['flagAddress'])
					{
						$this->addColumnInput ('adrSpecification');
						$this->addColumnInput ('adrStreet');
						$this->addColumnInput ('adrCity');
						$this->addColumnInput ('adrZipCode');
						$this->addColumnInput ('adrCountry');

						if ($this->idsOptions && (isset($this->idsOptions['id1']) || isset($this->idsOptions['id2'])))
						{
							$this->addSeparator(self::coH4);
							if (isset($this->idsOptions['id1']))
								$this->addColumnInput ('id1');
							if (isset($this->idsOptions['id2']))
								$this->addColumnInput ('id2');
							$needSep = 1;
						}
					}

					$this->addSeparator(self::coH4);
					$this->addColumnInput ('flagContact', self::coRightCheckbox);
					if ($this->recData['flagContact'])
					{
						$this->addColumnInput ('contactName');
						$this->addColumnInput ('contactRole');
						$this->addColumnInput ('contactEmail');
						$this->addColumnInput ('contactPhone');
					}

					$this->addSeparator(self::coH4);
					$this->addList ('clsf', '', TableForm::loAddToFormLayout);
				$this->closeTab ();
				$this->openTab ();
				$this->closeTab ();
				$this->closeTabs ();
		$this->closeForm ();
	}

	public function loadContactIdsOptions()
	{
		if (!($this->recData['flagOffice'] ?? 0))
			return;

		$cid = World::countryId($this->app(), $this->recData['adrCountry']);
		if ($cid === '')
			return;

		$idsOptions = $this->app()->cfgItem('e10.persons.contactsIds.'.$cid, NULL);
		if (!$idsOptions)
			return;

		$personTypeRec = $this->app()->db()->query('SELECT [company] FROM [e10_persons_persons] WHERE [ndx] = %i', $this->recData['person'])->fetch();
		if (!$personTypeRec)
			return;

		if ($personTypeRec['company'] && isset($idsOptions['company']))
		{
			$this->idsOptions = $idsOptions['company'];
			return;
		}
	}

	function columnLabel ($colDef, $options)
  {
    switch ($colDef ['sql'])
    {
      case	'id1': if ($this->idsOptions && isset($this->idsOptions['id1'])) return $this->idsOptions['id1']['label']; break;
      case	'id2': if ($this->idsOptions && isset($this->idsOptions['id2'])) return $this->idsOptions['id2']['label']; break;
    }

		return parent::columnLabel ($colDef, $options);
  }
}

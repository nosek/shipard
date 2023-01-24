<?php
namespace e10pro\reports\waste_cz;

use \Shipard\Utils\Utils;

/**
 * class ReportWasteOnePerson
 */
class ReportWasteOnePerson extends \e10doc\core\libs\reports\DocReportBase
{
	var $calendarYear = 0;
	var $periodBegin = NULL;
  var $periodEnd = NULL;

	var $sumData = [];
	var $itemsData = [];

	function init ()
	{
		parent::init();
		$this->setReportId('e10pro.reports.waste_cz.reportWasteOnePerson');
	}

	public function setOutsideParam ($param, $value)
	{
		if ($param === 'data-param-period-begin')
		{
			$this->periodBegin = Utils::createDateTime($value);
		}
		elseif ($param === 'data-param-period-end')
		{
			$this->periodEnd = Utils::createDateTime($value);
		}
		elseif ($param === 'data-param-calendar-year')
		{
			$this->calendarYear = intval($value);
			$this->data['calendarYear'] = strval ($this->calendarYear);
		}
	}

	public function checkDocumentInfo (&$documentInfo)
	{
		$documentInfo['messageDocKind'] = 'outbox-default';
	}

	protected function initParams()
	{
		if ($this->app()->testGetParam('data-param-calendar-year') !== '')
			$this->setOutsideParam('data-param-calendar-year', $this->app()->testGetParam('data-param-calendar-year'));
		if ($this->app()->testGetParam('data-param-period-begin') !== '')
			$this->setOutsideParam('data-param-period-begin', $this->app()->testGetParam('data-param-period-begin'));
		if ($this->app()->testGetParam('data-param-period-end') !== '')
			$this->setOutsideParam('data-param-period-end', $this->app()->testGetParam('data-param-period-end'));

		if ($this->calendarYear)
		{
			$this->data['periodName'] = 'Rok';
			$this->data['periodNameL'] = 'rok';
			$this->data['periodValue'] = strval($this->calendarYear);
		}
		else
		{
			$this->data['periodName'] = 'Období';
			$this->data['periodNameL'] = 'období';
			$this->data['periodValue'] = Utils::datef($this->periodBegin, '%d').' - '.Utils::datef($this->periodEnd, '%d');
		}

	}

	public function loadData ()
	{
		parent::loadData();
		$this->loadData_DocumentOwner ();
		$this->initParams();
	}

	public function loadData2 ()
	{
		$this->recData['person'] = $this->recData['ndx'];
		$this->loadDataPerson('person');

		$this->initParams();
		$this->outboxLinkId = 'waste-suppliers-'.$this->calendarYear;

		$tablePersons = $this->app->table ('e10.persons.persons');

		// -- author
		$authorNdx = $this->app->user()->data ('id');
		$this->data ['author'] = $this->table->loadItem ($authorNdx, 'e10_persons_persons');
		$this->data ['author']['lists'] = $tablePersons->loadLists ($authorNdx);

		$authorAtt = \E10\Base\getAttachments ($this->table->app(), 'e10.persons.persons', $authorNdx, TRUE);
		$this->data ['author']['signature'] = \E10\searchArray ($authorAtt, 'name', 'podpis');

		if (isset($this->data ['author']['lists']['address'][0]))
			$this->data ['author']['address'] = $this->data ['author']['lists']['address'][0];

		$this->loadData_Rows ();
	}

	public function loadData_Rows ()
	{
		$tableHeads = $this->app()->table ('e10doc.core.heads');

		$q = [];

    array_push ($q, 'SELECT [rows].person, [rows].personOffice, [rows].wasteCodeNomenc, SUM([rows].quantityKG) as quantityKG,');
    array_push ($q, ' nomencItems.fullName, nomencItems.itemId,');
    array_push ($q, ' persons.fullName AS personFullName,');
    array_push ($q, ' addrs.adrSpecification, addrs.adrCity, addrs.adrZipCode, addrs.adrStreet, addrs.id1,');
		array_push ($q, ' [heads].docNumber, [heads].dateAccounting');
		array_push ($q, ' FROM e10pro_reports_waste_cz_returnRows AS [rows]');
    array_push ($q, ' LEFT JOIN [e10_base_nomencItems] AS nomencItems ON [rows].wasteCodeNomenc = nomencItems.ndx');
    array_push ($q, ' LEFT JOIN [e10_persons_personsContacts] AS addrs ON [rows].personOffice = addrs.ndx');
    array_push ($q, ' LEFT JOIN [e10_persons_persons] AS persons ON [rows].person = persons.ndx');
		array_push ($q, ' LEFT JOIN [e10doc_core_heads] AS heads ON [rows].document = heads.ndx');
		array_push ($q, ' WHERE 1');
		array_push ($q, ' AND [rows].personType = %i', 2);
    array_push ($q, ' AND [rows].[dir] = %i', 0);
		array_push ($q, ' AND [rows].[person] = %i', $this->recData ['ndx']);
    if ($this->periodBegin)
      array_push ($q, ' AND [rows].[dateAccounting] >= %d', $this->periodBegin);
    if ($this->periodEnd)
      array_push ($q, ' AND [rows].[dateAccounting] <= %d', $this->periodEnd);
		array_push ($q, ' GROUP BY [heads].docNumber, [rows].person, [rows].personOffice, wasteCodeNomenc');
    array_push ($q, ' ORDER BY persons.fullName, addrs.id1, wasteCodeNomenc');

		$rows = $this->app->db()->query ($q);
		forEach ($rows as $r)
		{
			$wasteCode = $r['itemId'];
			$wasteName = $r['fullName'];

			$quantity = $r['quantityKG'] / 1000; // tons

			$docIdentifiers = $tableHeads->docAdditionsOur ($r, $r);
			$id_icz_our = '';
			$id_icp_our = '';
			foreach ($docIdentifiers as $di)
			{
				if ($di['id'] === 'icz')
					$id_icz_our = $di['identifier'];
				elseif ($di['id'] === 'icp')
					$id_icp_our = $di['identifier'];
			}

			$ap = [];
			if ($r['adrSpecification'] != '')
				$ap[] = $r['adrSpecification'];
			if ($r['adrStreet'] != '')
				$ap[] = $r['adrStreet'];
			if ($r['adrCity'] != '')
				$ap[] = $r['adrCity'];
			if ($r['adrZipCode'] != '')
				$ap[] = $r['adrZipCode'];

				$id_icp_theirs = ($r['id1'] != '') ? $r['id1'] : '1';
				$id_icp_theirs_text = [
					['text' => 'IČP: '.$id_icp_theirs, 'class' => ''],
					['text' => implode(', ', $ap), 'class' => 'e10-small break']
				];

			$sumRowId = $wasteCode.'-'.$id_icp_our.'-'.$id_icz_our.'-'.$id_icp_theirs;
			if (!isset($this->sumData[$sumRowId]))
			{
				$this->sumData[$sumRowId] = [
					'weight' => 0, 'code' => $wasteCode, 'title' => $wasteName,
					'icp_our' => $id_icp_our, 'icz_our' => $id_icz_our,
					'icp_theirs' => $id_icp_theirs_text
				];
			}
			$this->sumData[$sumRowId]['weight'] += $quantity;

			$itemsRowId = $wasteCode.'-'.$r['docNumber'];
			if (!isset($this->itemsData[$itemsRowId]))
			{
				$this->itemsData[$itemsRowId] = [
					'weight' => 0, 'code' => $wasteCode, 'title' => $wasteName,
					'docNumber' => $r['docNumber'], 'date' => $r['dateAccounting'], 'o' => $r['docNumber'].'-'.$wasteCode
				];
			}
			$this->itemsData[$itemsRowId]['weight'] += $quantity;
		}

		$headerSum = [
			'icp_our' => 'Naše IČP', 'icz_our' => 'Naše IČZ', 'icp_theirs' => 'VAŠE IČP/ORP',
			'code' => 'Kód odpadu', 'title' => 'Název', 'weight' => '+Hmotnost [t]'
		];

		$periodTitle = '';
		if ($this->calendarYear)
		{
			$periodTitle = 'Celková množství odpadů, které jsme odebrali v roce '.$this->calendarYear;
		}
		else
		{
			$periodTitle = 'Celková množství odpadů, které jsme odebrali od '.Utils::datef($this->periodBegin, '%d').' do '.Utils::datef($this->periodEnd, '%d');
		}

		$this->data['sumRows'] = [
			[
				'type' => 'table', 'title' => $periodTitle,
				'table' => \e10\sortByOneKey($this->sumData, 'code'), 'header' => $headerSum,
				'params' => ['precision' => 3]
			]
		];

		$headerItems = ['docNumber' => 'Č. dokladu', 'date' => 'Datum', 'code' => 'Kód odpadu', 'title' => 'Název', 'weight' => '+Hmotnost [t]'];
		$this->data['itemsRows'] = [
			[
				'type' => 'table', 'title' => 'Položkový soupis',
				'table' => \e10\sortByOneKey($this->itemsData, 'o'), 'header' => $headerItems,
				'params' => ['precision' => 3, 'tableClass' => 'rowsSmall']]
		];
	}
}

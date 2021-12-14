<?php

namespace hosting\core;

use E10\json;
use \E10\utils, \E10\TableView, \E10\TableViewDetail, \E10\TableForm, \Shipard\Viewer\TableViewPanel, \E10\DbTable, \Shipard\Form\TableFormShow;
use \e10\base\libs\UtilsBase;

/**
 * Class TableDataSources
 */
 class TableDataSources extends DbTable
{
	public function __construct($dbmodel)
	{
		parent::__construct($dbmodel);
		$this->setName('hosting.core.dataSources', 'hosting_core_dataSources', 'Zdroje dat');
	}

	public function checkBeforeSave(&$recData, $ownerData = NULL)
	{
		if (isset($recData['gid']) && $recData['gid'] === '')
			$recData ['gid'] = Utils::createToken(4).'-'.Utils::createToken(4).'-'.Utils::createToken(4).'-'.Utils::createToken(4);

		if (isset($recData ['ndx']) && $recData ['ndx'])
		{
			// -- ds image
			$image = UtilsBase::getAttachmentDefaultImage($this->app(), 'hosting.core.dataSources', $recData ['ndx']);
			if (isset($image['fileName']))
			{
				//$recData['imageUrl'] = $this->app()->cfgItem('hostingServerUrl') . 'imgs/-w256/att/' . $image['fileName'];
				//$recData['dsIconServerUrl'] = $this->app()->cfgItem('hostingServerUrl');
				//$recData['dsIconFileName'] = 'att/' . $image['fileName'];
			}
			else
			{
			//	$recData['imageUrl'] = '';
			//	$recData['dsIconServerUrl'] = '';
			//	$recData['dsIconFileName'] = '';
			}
		}

		parent::checkBeforeSave($recData, $ownerData);
	}

	public function createHeader($recData, $options)
	{
		$hdr = parent::createHeader($recData, $options);
		$topInfo = [['text' => '#' . $recData ['gid']]];

		if ($recData['server'])
		{
			$serverRec = $this->app()->loadItem($recData['server'], 'hosting.core.servers');
			$topInfo[] = ['icon' => 'x-server', 'text' => $serverRec['id']];
		}

		$hdr ['info'][] = ['class' => 'info', 'value' => $topInfo];


		$hdr ['info'][] = ['class' => 'title', 'value' => $recData ['name']];

		$info = [];
		$ownerFullName = '';

		if ($recData['owner'])
		{
			$ownerRec = $this->app()->loadItem($recData['owner'], 'e10.persons.persons');
			$ownerFullName = $ownerRec['fullName'];
			$info [] = ['icon' => 'system/iconBuilding', 'text' => $ownerFullName];
		}
		if ($recData['admin'])
		{
			$adminRec = $this->app()->loadItem($recData['admin'], 'e10.persons.persons');
			if ($adminRec['fullName'] != $ownerFullName)
				$info [] = ['icon' => 'system/actionSettings', 'text' => $adminRec['fullName']];
		}
		if ($recData['payer'])
		{
			$payerRec = $this->app()->loadItem($recData['admin'], 'e10.persons.persons');
			if ($payerRec['fullName'] != $ownerFullName)
				$info [] = ['icon' => 'icon-money', 'text' => $payerRec['fullName']];
		}

		$hdr ['info'][] = ['class' => 'info', 'value' => $info];

		/*
		$image = UtilsBase::getAttachmentDefaultImage($this->app(), $this->tableId(), $recData ['ndx']);
		if (isset($image ['smallImage']))
		{
			$hdr ['image'] = $image ['smallImage'];
			unset ($hdr ['icon']);
		}*/
		if ($recData ['imageUrl'])
		{
			$hdr ['image'] = $recData ['imageUrl'];
			//unset ($hdr ['icon']);
		}

		

		return $hdr;
	}

	public function dsStateLabels($recData)
	{
		$labels = [];

		$dsTypes = $this->columnInfoEnum('dsType', 'cfgText');
		$dsTypesClasses = ['label-default', 'label-warning', 'label-primary', 'label-primary', 'label-default'];
		$labels['dsType'] = ['text' => $dsTypes[$recData['dsType']], 'class' => 'label ' . $dsTypesClasses[$recData['dsType']], 'icon' => $this->tableIcon($recData)];

		//"enumValues": {"0": "Čeká na založení", "1": "Zkušební lhůta", "2": "Ostrý provoz", "3": "Expirováno", "4": "Pozastaveno", "5": "Smazáno"}},
		$conditions = $this->columnInfoEnum('condition', 'cfgText');
		$conditionsClasses = [
			0 => 'label-warning',
			1 => 'label-warning',
			2 => 'label-default',
			3 => 'label-danger',
			4 => 'label-info',
			5 => 'label-info'
		];
		$conditionsIcons = [
			0 => 'system/iconWarning',
			1 => 'system/docStateUnknown',
			2 => 'system/iconCheck',
			3 => 'system/iconWarning',
			4 => 'icon-pause-circle-o',
			5 => 'icon-refresh'
		];
		$labels['condition'][] = [
			'text' => $conditions[$recData['condition']], 'class' => 'label ' . $conditionsClasses[$recData['condition']],
			'icon' => $conditionsIcons[$recData['condition']]
		];

		$today = utils::today();
		if ($recData['condition'] === 0 && !utils::dateIsBlank($recData['dateTrialEnd']) && $recData['dateTrialEnd'] < $today)
			$labels['condition'][] = ['text' => utils::datef($recData['dateTrialEnd']), 'class' => 'label label-danger', 'icon' => 'system/iconWarning'];

		return $labels;
	}

	public function tableIcon($recData, $options = NULL)
	{
		$iconSet = ['system/iconCheck', 'icon-flask', 'icon-eye', 'icon-user-secret', 'icon-question'];
		return $iconSet[$recData['dsType']];
	}

	public function getPlan($data)
	{
		$plans = $this->app()->cfgItem('e10pro.hosting.pricePlans');
		$statsData = json::decode ($data['data']);
		if (!$statsData)
			$statsData = [];

		$totalSize = $data['usageTotal'];
		$cntDocs12m = $data['cntDocuments12m'];
		$cntCashRegs12m = $data['cntCashRegs12m'];
		$cntDocs = $cntDocs12m - $cntCashRegs12m + intval($cntCashRegs12m / 10);

		$plan = NULL;

		foreach ($plans as $p)
		{
			if ($cntDocs >= $p['docs'])
			{
				$plan = $p;
				break;
			}
		}

		$plan['extModulesPoints'] = 0;

		if (isset($statsData['extModules']))
		{
			$extModulesLabels = [];
			foreach ($statsData['extModules'] as $emId => $em)
			{
				if ($emId === 'mac')
				{
					if (isset($em['lan']) && isset($em['lan']['countDevices']['ALL']))
					{
						$extModulesLabels[] = [
							'text' => 'Počítačová síť',
							'suffix' => utils::nf($em['lan']['countDevices']['ALL']).' zařízení',
							'icon' => 'system/iconSitemap', 'class' => 'label label-info'
						];
						$plan['extModulesPoints'] += intval($em['lan']['countDevices']['ALL']);
					}
					if (isset($em['lan']) && isset($em['lan']['countDevices']['10']))
					{
						$extModulesLabels[] = [
							'text' => 'Kamerový systém',
							'suffix' => utils::nf($em['lan']['countDevices']['10']).' kamer',
							'icon' => 'icon-video-camera', 'class' => 'label label-info'
						];
					}
				}
			}

			//$info[] = ['p1' => 'Rozšíření', 't1' => $extModulesLabels];
		}



		$plan['priceDocs'] = $plan['price'];
		$plan['priceUsage'] = 0;
		$usageLimit = $plan['maxUsage'];
		$usageBlockPrice = 100;
		$usageBlockSize = 10;
		$usageNow = round($data['usageTotal'] / (1024 * 1024 * 1024), 1);
		if ($usageNow > $usageLimit)
		{
			$usageBlocksToPay = intval(($usageNow - $usageLimit) / $usageBlockSize + 1);
			$plan['priceUsage'] = $usageBlockPrice * $usageBlocksToPay;
		}

		$plan['extModulesPrice'] = $plan['extModulesPoints'] * 10;
		$plan['priceTotal'] = $plan['priceDocs'] + $plan['priceUsage'] + $plan['extModulesPrice'];

		return $plan;
	}

	public function getPlansLegend()
	{
		$t = [];

		$plans = $this->app()->cfgItem('e10pro.hosting.pricePlans');

		$prevPlan = NULL;
		foreach ($plans as $p)
		{
			$docs = utils::nf($p['docs']) . ' a více';


			if ($prevPlan)
				$docs = utils::nf($p['docs']) . ' až ' . utils::nf($prevPlan['docs'] - 1);


			$item = [
				'title' => $p['title'],
				'maxDocs' => $docs, //$p['docs'],
				'price' => $p['price'],
				'maxUsage' => $p['maxUsage'],
				'usageBlockPrice' => 100,
			];
			$t[] = $item;

			$prevPlan = $p;
		}



		$h = [
			'title' => 'Tarif',
			'maxDocs' => ' Počet dokladů / rok',
			'price' => ' Cena',
			'maxUsage' => ' Max. velikost v GB',
			'usageBlockPrice' => ' Příplatek za každých 10 GB',
		];

		return ['table' => \e10\sortByOneKey($t, 'title'), 'header' => $h];
	}
}


/**
 * Class ViewDataSources
 */
class ViewDataSources extends TableView
{
	var $modules;
	var $dsStats = [];

	var $partner = 0;

	public function init ()
	{
		parent::init();

		if (!$this->partner)
			$this->setPanels (TableView::sptQuery|TableView::sptReview);

		$mq [] = ['id' => 'valid', 'title' => 'Platné', 'icon' => 'system/iconDatabase'];
		$mq [] = ['id' => 'active', 'title' => 'Aktivní', 'side' => 'left', 'icon' => 'icon-check-square'];
		$mq [] = ['id' => 'online', 'title' => 'Online', 'side' => 'left', 'icon' => 'icon-bolt'];

		$mq [] = ['id' => 'nonactive', 'title' => 'Neaktivní', 'icon' => 'icon-ban'];
		$mq [] = ['id' => 'archive', 'title' => 'Archív', 'icon' => 'system/filterArchive'];
		$mq [] = ['id' => 'all', 'title' => 'Vše', 'icon' => 'system/filterAll'];
		$mq [] = ['id' => 'trash', 'title' => 'Koš', 'icon' => 'system/filterTrash'];

		$this->setMainQueries ($mq);

		$this->modules = $this->table->app()->cfgItem ('e10pro.hosting.modules');
	}

	public function renderRow ($item)
	{
		$listItem ['pk'] = $item ['ndx'];

		if ($item['imageUrl'] !== '')
		{
			$listItem ['svgIcon'] = $item['imageUrl'];
		}
		elseif ($item['dsEmoji'] !== '')
		{
			$listItem ['emoji'] = $item['dsEmoji'];
		}
		elseif ($item['dsIcon'] !== '')
		{
			$listItem ['icon'] = $item['dsIcon'];
		}
		else
		{
			$listItem ['icon'] = 'system/iconDatabase';
		}

		$listItem ['t1'] = $item['name'];
		$listItem ['i1'] = ['text' => '#'.$item['gid'], 'class' => 'id'];

		if ($item['appWarning'] != 0)
			$listItem ['class'] = 'e10-row-minus';

		$props = [];
		$props3 = [];

		$stateLabels = $this->table->dsStateLabels($item);
		$props3 [] = $stateLabels['condition'];

		$listItem ['i2'] = [];

		if ($item['partnerName'])
			$listItem ['i2'][] = ['icon' => 'tables/hosting.core.partners', 'text' => $item['partnerName'], 'class' => ''];

		if ($item['serverName'])
			$listItem ['i2'][] = ['icon' => 'tables/hosting.core.servers', 'text' => $item['serverName'], 'class' => ''];
		else
			$listItem ['i2'][] = ['icon' => 'tables/hosting.core.servers', 'text' => '---', 'class' => 'e10-warning2'];

		if ($item['dsId1'] !== '')
		{
			$dsId = ['text' => '@'.$item['dsId1'], 'class' => ''];

			if (($item['dsId2'] !== ''))
				$dsId['suffix'] = $item['dsId2'];

			$props[] = $dsId;
		}

		if (count($props))
			$listItem ['t2'] = $props;
		if (count($props3))
			$listItem ['t3'] = $props3;

		if ($item['inProgress'])
			$listItem ['class'] = 'e10-row-this';

		return $listItem;
	}

	function decorateRow (&$item)
	{
		if (isset($this->dsStats[$item ['pk']]))
		{
			//$plan = $this->table->getPlan($this->dsStats[$item ['pk']]);
			//$item ['t3'][] = ['text' => $plan['title'], 'class' => 'label label-info pull-right', 'icon' => 'icon-money'];
			//$item ['t3'][] = ['text' => utils::memf($this->dsStats[$item ['pk']]['usageTotal']), 'class' => 'label label-primary pull-right', 'icon' => 'icon-hdd-o'];
		}
	}

	public function selectRows ()
	{
		$fts = $this->fullTextSearch ();
		$mainQuery = $this->mainQueryId ();

		$q [] = 'SELECT ds.*, owners.fullName as ownerFullName, admins.fullName as adminFullName, partners.name as partnerName,';
		array_push ($q, ' payers.fullName as payerFullName, servers.id as serverId, servers.name as serverName');
		array_push ($q, ' FROM [hosting_core_dataSources] AS ds');
		array_push ($q, ' LEFT JOIN e10_persons_persons as owners ON ds.owner = owners.ndx');
		array_push ($q, ' LEFT JOIN e10_persons_persons as admins ON ds.admin = admins.ndx');
		array_push ($q, ' LEFT JOIN e10_persons_persons as payers ON ds.payer = payers.ndx');
		array_push ($q, ' LEFT JOIN hosting_core_servers AS servers ON ds.server = servers.ndx');
		array_push ($q, ' LEFT JOIN hosting_core_partners AS partners ON ds.partner = partners.ndx');
		array_push ($q, ' WHERE 1');

		if ($this->partner)
			array_push ($q, ' AND ds.[partner] = %i', $this->partner);

		// -- fulltext
		if ($fts != '')
		{
			$ascii = TRUE;
			if(preg_match('/[^\x20-\x7f]/', $fts))
				$ascii = FALSE;

 			array_push ($q, ' AND (');
			array_push ($q, ' ds.[name] LIKE %s OR ds.[gid] LIKE %s', '%'.$fts.'%', $fts.'%');
			array_push ($q, ' OR servers.id LIKE %s', $fts.'%');
			if ($ascii)
			{
				array_push($q, ' OR ds.dsId1 LIKE %s', '%' . $fts . '%');
				array_push($q, ' OR ds.dsId2 LIKE %s', '%' . $fts . '%');
			}
			array_push ($q, ')');
		}

    // -- aktuální
		if ($mainQuery === 'valid' || $mainQuery === '' || $mainQuery === 'active' || $mainQuery === 'online'|| $mainQuery === 'nonactive' )
			array_push ($q, " AND ds.[docStateMain] < 4");

		$today = new \DateTime();
		if ($mainQuery === 'active')
		{
			$today->sub (new \DateInterval('P3M'));
			//array_push ($q, ' AND ds.lastLogin > %t', $today);
		}
		if ($mainQuery === 'online')
		{
			$today->sub (new \DateInterval('PT30M'));
		//	array_push ($q, ' AND ds.lastLogin > %t', $today);
		}
		if ($mainQuery === 'nonactive')
		{
			$today->sub (new \DateInterval('P3M'));
			//array_push ($q, ' AND ds.lastLogin < %t', $today);
		}

		// archive
		if ($mainQuery == 'archive')
      array_push ($q, " AND ds.[docStateMain] = 5");

		// koš
		if ($mainQuery == 'trash')
      array_push ($q, " AND ds.[docStateMain] = 4");

		// -- special queries
		$qv = $this->queryValues ();

		if (isset($qv['partners']))
			array_push ($q, ' AND [partner] IN %in', array_keys($qv['partners']));
		if (isset($qv['servers']))
			array_push ($q, ' AND ds.[server] IN %in', array_keys($qv['servers']));
		if (isset($qv['conditions']))
			array_push ($q, ' AND [condition] IN %in', array_keys($qv['conditions']));
		if (isset($qv['pricePlanKind']))
			array_push ($q, ' AND [pricePlanKind] IN %in', array_keys($qv['pricePlanKind']));
		if (isset($qv['invoicingTo']))
			array_push ($q, ' AND [invoicingTo] IN %in', array_keys($qv['invoicingTo']));
		if (isset($qv['dsTypes']))
			array_push ($q, ' AND [dsType] IN %in', array_keys($qv['dsTypes']));
		if (isset($qv['installModules']))
			array_push ($q, ' AND [installModule] IN %in', array_keys($qv['installModules']));

		if ($mainQuery == 'all')
			array_push ($q, ' ORDER BY ds.[name]' . $this->sqlLimit());
		else
			array_push ($q, ' ORDER BY ds.[docStateMain], ds.[name]' . $this->sqlLimit());

		$this->runQuery ($q);
	}

	public function selectRows2 ()
	{
	//	if (!count ($this->pks))
			return;

		// -- data source stats
		$dsStats = $this->db()->query ('SELECT * FROM e10pro_hosting_server_datasourcesStats WHERE datasource IN %in', $this->pks);
		foreach ($dsStats as $r)
			$this->dsStats[$r['datasource']] = $r->toArray();
	}

	public function createPanelContentQry (TableViewPanel $panel)
	{
		$qry = [];

		// -- dsTypes
		$dsTypes = $this->table->columnInfoEnum('dsType');
		$this->qryPanelAddCheckBoxes($panel, $qry, $dsTypes, 'dsTypes', 'Typ');

		// -- condition
		$conditions = $this->table->columnInfoEnum('condition');
		$this->qryPanelAddCheckBoxes($panel, $qry, $conditions, 'conditions', 'Stav');

		// -- pricePlanKind
		$conditions = $this->table->columnInfoEnum('pricePlanKind');
		$this->qryPanelAddCheckBoxes($panel, $qry, $conditions, 'pricePlanKind', 'Druh tarifu');

		// -- invoicingTo
		$conditions = $this->table->columnInfoEnum('invoicingTo');
		$this->qryPanelAddCheckBoxes($panel, $qry, $conditions, 'invoicingTo', 'Fakturovat');

		// -- partners
		$partners = $this->db()->query ('SELECT ndx, name FROM hosting_core_partners WHERE docStateMain <= 2 ORDER BY name')->fetchPairs ('ndx', 'name');
		$this->qryPanelAddCheckBoxes($panel, $qry, $partners, 'partners', 'Partneři');

		// -- servers
		$servers = $this->db()->query ('SELECT ndx, name FROM hosting_core_servers WHERE docStateMain <= 2 ORDER BY name')->fetchPairs ('ndx', 'name');
		$this->qryPanelAddCheckBoxes($panel, $qry, $servers, 'servers', 'Servery');

		// -- modules
		/*
		$modules = $this->db()->query ('SELECT ndx, name FROM hosting_core_modules WHERE docStateMain != 4')->fetchPairs ('ndx', 'name');
		$modules['0'] = 'Žádný';
		$this->qryPanelAddCheckBoxes($panel, $qry, $modules, 'installModules', 'Instalační moduly');
		*/

		$panel->addContent(['type' => 'query', 'query' => $qry]);
	}

	public function createPanelContentReview (TableViewPanel $panel)
	{
		//$panel->setContent('e10pro.hosting.server.HostingReviewDataSources');

		///** @var \e10pro\hosting\server\HostingReviewDataSources $o */

		/*
		$o = new \e10pro\hosting\server\HostingReviewDataSources($this->app());//$this->table->app()->createObject($classId);
		$o->partner = $this->partner;
		$o->create();
		foreach ($o->content['body'] as $cp)
			$panel->addContent($cp);
		*/	
	}
}


/**
 * Class ViewDetailDatasources
 * @package E10pro\Hosting\Server
 */
class ViewDetailDatasources extends TableViewDetail
{
	public function createDetailContent ()
	{
		$this->addDocumentCard('hosting.core.libs.dc.DocumentCardDataSource');
	}

	function createToolbar()
	{
		if (!$this->app()->hasRole('hstng'))
			return [];

		return parent::createToolbar();
	}
}


/**
 * Class ViewDetailDataSourceUsers
 * @package E10pro\Hosting\Server
 */
class ViewDetailDataSourceUsers extends TableViewDetail
{
	public function createDetailContent ()
	{
		$this->addContentViewer ('hosting.core.dsUsers', 'hosting.core.ViewDSUsers',
														 array ('dataSource' => $this->item ['ndx']));
	}
}


/**
 * Class FormDataSource
 */
class FormDataSource extends TableForm
{
	public function renderForm ()
	{
		$this->setFlag ('formStyle', 'e10-formStyleSimple');
		$this->setFlag ('sidebarPos', TableForm::SIDEBAR_POS_RIGHT);

		$this->openForm ();
			$tabs ['tabs'][] = ['text' => 'Základní', 'icon' => 'system/formHeader'];
			$tabs ['tabs'][] = ['text' => 'Nastavení', 'icon' => 'system/formSettings'];
			$tabs ['tabs'][] = ['text' => 'Přílohy', 'icon' => 'system/formAttachments'];
			$this->openTabs ($tabs, TRUE);

				$this->openTab ();
					$this->addColumnInput ('name');
					$this->addColumnInput ('shortName');
					$this->addColumnInput ('dsId1');
					$this->addSeparator(self::coH2);
					$this->addColumnInput ('installModule');
					$this->addColumnInput ('owner');
					$this->addColumnInput ('partner');
					$this->addColumnInput ('admin');
					$this->addSeparator(self::coH2);
					$this->addColumnInput ('dsType');
					$this->addColumnInput ('condition');
					$this->addColumnInput ('appWarning');
					$this->addSeparator(self::coH2);
					$this->addColumnInput ('pricePlanKind');
					$this->addColumnInput ('invoicingTo');
				$this->closeTab ();

				$this->openTab ();
					$this->addColumnInput ('dsId2');
					$this->addColumnInput ('server');
					$this->addColumnInput ('urlApp');
					$this->addColumnInput ('urlApp2');
					$this->addColumnInput ('gid');
					$this->addColumnInput ('dateStart');
					$this->addColumnInput ('dateTrialEnd');
					$this->addColumnInput ('dsIcon');
					$this->addColumnInput ('dsEmoji');
					$this->addColumnInput ('shpGeneration');
				$this->closeTab ();

				$this->openTab (TableForm::ltNone);
					$this->addAttachmentsViewer();
				$this->closeTab ();

			$this->closeTabs ();
		$this->closeForm ();
	}
}


/**
 * Class FormDataSourceShow
 */
class FormDataSourceShow extends TableFormShow
{
	public function renderForm ()
	{
		$this->setFlag ('formStyle', 'e10-formStyleSimple');

		$this->openForm (TableForm::ltNone);
			$this->addDocumentCard('e10pro.hosting.server.DocumentCardDataSource');
		$this->closeForm ();
	}
}

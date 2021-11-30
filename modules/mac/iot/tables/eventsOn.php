<?php

namespace mac\iot;

use \Shipard\Form\TableForm, \Shipard\Table\DbTable, \Shipard\Viewer\TableView, \Shipard\Viewer\TableViewDetail;


/**
 * Class TableEventsOn
 */
class TableEventsOn extends DbTable
{
	public function __construct ($dbmodel)
	{
		parent::__construct ($dbmodel);
		$this->setName ('mac.iot.eventsOn', 'mac_iot_eventsOn', 'Události Když');
	}

	public function createHeader ($recData, $options)
	{
		$hdr = parent::createHeader ($recData, $options);

		//$hdr ['info'][] = ['class' => 'title', 'value' => $recData ['fullName']];

		return $hdr;
	}

	public function tableIcon ($recData, $options = NULL)
	{
		/*
		if (isset($recData['icon']) && $recData['icon'] !== '')
			return $recData['icon'];

		$thingType = $this->app()->cfgItem ('mac.iot.things.types.'.$recData['thingType'], NULL);

		if ($thingType)
			return $thingType['icon'];
		*/
		return parent::tableIcon ($recData, $options);
	}

	public function columnInfoEnum ($columnId, $valueType = 'cfgText', TableForm $form = NULL)
	{
		$iotDevicesUtils = new \mac\iot\libs\IotDevicesUtils($this->app());

		if ($columnId === 'iotDeviceEvent')
		{
			$events = $iotDevicesUtils->deviceEvents($form->recData['iotDevice']);
			if (!$events)
				return [];

			$enum = [];
			foreach ($events as $key => $value)
				$enum[$key] = $key;

			return $enum;
		}

		if ($columnId === 'iotDeviceEventValueEnum')
		{
			$events = $iotDevicesUtils->deviceEvents($form->recData['iotDevice']);
			if (!$events)
				return [];

			$event = $events[$form->recData['iotDeviceEvent']] ?? NULL;	
			if (!$event)
				return [];

			$srcEnum = $event['enum'] ?? $event['enumGet'] ?? NULL;
			if (!$srcEnum)
				return [];

			$enum = [];
			foreach ($srcEnum as $key => $value)
				$enum[$key] = $key;

			return $enum;
		}

		return parent::columnInfoEnum ($columnId, $valueType, $form);
	}

	public function getEventLabels($eventRow, &$dest)
	{
		if ($eventRow['eventType'] === 'deviceAction')
		{
			$dest[] = ['text' => $eventRow['deviceFriendlyId'], 'class' => 'label label-default'];
			$dest[] = ['text' => $eventRow['iotDeviceEvent'], 'class' => 'label label-default'];
			$dest[] = ['text' => ' = ', 'class' => 'label label-default'];
			$dest[] = ['text' => $eventRow['iotDeviceEventValueEnum'], 'class' => 'label label-info'];
		}
		elseif ($$eventRow['eventType'] === 'mqttMsg')
		{
			//$this->addColumnInput ('mqttTopic');
			//$this->addColumnInput ('mqttTopicPayloadItemId');
			//$this->addColumnInput ('mqttTopicPayloadValue');
		}
	}
}


/**
 * Class ViewSetsDevices
 */
class ViewEventsOn extends TableView
{
}

/**
 * Class ViewEventsOnForm
 */
class ViewEventsOnForm extends TableView
{
	var $dstTableId = '';
	var $dstRecId = 0;
	var $eventsDo = [];

	/** @var $tableEventsDo \mac\iot\TableEventsDo */
	var $tableEventsDo;

	public function init ()
	{
		$this->tableEventsDo = $this->app()->table('mac.iot.eventsDo');

		$this->enableDetailSearch = TRUE;
		$this->objectSubType = TableView::vsDetail;
		$this->toolbarTitle = ['text' => 'Obsluha událostí', 'class' => 'h2 e10-bold'/*, 'icon' => 'system/iconMapMarker'*/];

		$this->dstTableId = $this->queryParam('dstTableId');
		$this->dstRecId = intval($this->queryParam('dstRecId'));

		$this->addAddParam('tableId', $this->dstTableId);
		$this->addAddParam('recId', $this->dstRecId);

		$this->setMainQueries();

		parent::init();
	}

	public function renderRow ($item)
	{
		$listItem ['pk'] = $item ['ndx'];
		$listItem ['icon'] = 'system/iconCogs';

		$listItem ['type'] = [];

		$listItem ['i1'] = ['text' => '#'.$item['ndx'], 'class' => 'id'];
		$listItem ['t1'] = $item['fullName'];
		$listItem ['t2'] = [];
		$this->table->getEventLabels($item, $listItem ['t2']);

		return $listItem;
	}

	function decorateRow (&$item)
	{
		if (isset($this->eventsDo [$item ['pk']]))
		{
			foreach ($this->eventsDo [$item ['pk']] as $ed)
			{
				$this->tableEventsDo->getEventLabels($ed, $item ['t2'], ['text' => ' ➜ ', 'class' => 'clear break']);
			}	
		}
	}

	public function selectRows ()
	{
		$fts = $this->fullTextSearch ();

		$q [] = 'SELECT eventsOn.*,';
		array_push ($q, ' iotDevices.friendlyId AS deviceFriendlyId, iotDevices.fullName AS deviceFullName');
		array_push ($q, ' FROM [mac_iot_eventsOn] AS [eventsOn]');
		array_push ($q, ' LEFT JOIN [mac_iot_devices] AS iotDevices ON eventsOn.iotDevice = iotDevices.ndx');

		array_push ($q, ' WHERE 1');
		array_push ($q, ' AND [eventsOn].[tableId] = %s', $this->dstTableId);
		array_push ($q, ' AND [eventsOn].[recId] = %i', $this->dstRecId);

		// -- fulltext
		/*
		if ($fts != '')
		{
			array_push ($q, ' AND (');
			array_push ($q,
				' ports.[fullName] LIKE %s', '%'.$fts.'%',
				' OR devices.[fullName] LIKE %s', '%'.$fts.'%'
			);
			array_push ($q, ')');
		}
		*/
		array_push ($q, ' ORDER BY eventsOn.[rowOrder] ' . $this->sqlLimit ());

		$this->runQuery ($q);
	}

	public function selectRows2 ()
	{
		if (!count ($this->pks))
			return;

		// -- eventsDo
		$q = [];
		$q [] = 'SELECT eventsDo.*,';
		array_push ($q, ' iotDevices.friendlyId AS deviceFriendlyId, iotDevices.fullName AS deviceFullName,');
		array_push ($q, ' devicesGroups.shortName AS devicesGroupName');
		array_push ($q, ' FROM [mac_iot_eventsDo] AS [eventsDo]');
		array_push ($q, ' LEFT JOIN [mac_iot_devices] AS iotDevices ON eventsDo.iotDevice = iotDevices.ndx');
		array_push ($q, ' LEFT JOIN [mac_iot_devicesGroups] AS devicesGroups ON eventsDo.iotDevicesGroup = devicesGroups.ndx');

		array_push ($q, ' WHERE 1');
		array_push ($q, ' AND [eventsDo].[tableId] = %s', 'mac.iot.eventsOn');
		array_push ($q, ' AND [eventsDo].[recId] IN %in', $this->pks);
		array_push ($q, ' AND [eventsDo].[docStateMain] <= %i', 2);

		$rows = $this->db()->query($q);
		foreach ($rows as $r)
		{
			$this->eventsDo[$r['recId']][] = $r->toArray();
		}
	}
}


/**
 * Class FormEventOn
 */
class FormEventOn extends TableForm
{
	public function renderForm ()
	{
		$this->setFlag ('formStyle', 'e10-formStyleDefault viewerFormList');
		$this->setFlag ('sidebarPos', TableForm::SIDEBAR_POS_RIGHT);
		//$this->setFlag ('maximize', 1);

		$tabs ['tabs'][] = ['text' => 'Data', 'icon' => 'system/iconDatabase'];

		$this->openForm ();
			$this->openTabs ($tabs, TRUE);
				$this->openTab ();
					$this->addColumnInput ('fullName');
					$this->addColumnInput ('eventType');
					if ($this->recData['eventType'] === 'deviceAction')
					{
						$this->addColumnInput ('iotDevice');
						$this->addColumnInput ('iotDeviceEvent');
						$this->addColumnInput ('iotDeviceEventValueEnum');
					}
					elseif ($this->recData['eventType'] === 'mqttMsg')
					{
						$this->addColumnInput ('mqttTopic');
						$this->addColumnInput ('mqttTopicPayloadItemId');
						$this->addColumnInput ('mqttTopicPayloadValue');
					}

					$this->addViewerWidget ('mac.iot.eventsDo', 'form', ['dstTableId' => 'mac.iot.eventsOn', 'dstRecId' => $this->recData['ndx']], TRUE);
				$this->closeTab ();
			$this->closeTabs ();
		$this->closeForm ();
	}
}


/**
 * Class ViewDetailEventOn
 */
class ViewDetailEventOn extends TableViewDetail
{
}


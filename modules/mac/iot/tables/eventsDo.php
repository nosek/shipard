<?php

namespace mac\iot;

use \Shipard\Form\TableForm, \Shipard\Table\DbTable, \Shipard\Viewer\TableView, \Shipard\Viewer\TableViewDetail;


/**
 * Class TableEventsDo
 */
class TableEventsDo extends DbTable
{
	public function __construct ($dbmodel)
	{
		parent::__construct ($dbmodel);
		$this->setName ('mac.iot.eventsDo', 'mac_iot_eventsDo', 'Události Co');
	}

	public function createHeader ($recData, $options)
	{
		$hdr = parent::createHeader ($recData, $options);

		$hdr ['info'][] = ['class' => 'title', 'value' => $recData ['fullName']];

		return $hdr;
	}

	public function checkBeforeSave (&$recData, $ownerData = NULL)
	{
		parent::checkBeforeSave ($recData, $ownerData);
		$iotDevicesUtils = new \mac\iot\libs\IotDevicesUtils($this->app());

		if ($recData['eventType'] === 'setDeviceProperty')
		{
			$recData['mqttTopic'] = '';
			$recData['mqttTopicPayloadValue'] = '';
			if ($recData['iotDevice'] || $recData['useGroup'])
			{
				if ($recData['useGroup'])
					$properties = $iotDevicesUtils->devicesGroupProperties($recData['iotDevicesGroup']);
				else
					$properties = $iotDevicesUtils->deviceProperties($recData['iotDevice']);
			
				$dp = $properties[$recData['iotDeviceProperty']] ?? NULL;
				if ($dp)
				{
					if ($dp['data-type'] === 'binary' || $dp['data-type'] === 'enum')
						$recData['iotDevicePropertyValue'] = $recData['iotDevicePropertyValueEnum'];
				}
			}
			else
			{
				$recData['iotDevicePropertyValueEnum'] = '';
				$recData['iotDevicePropertyValue'] = '';
			}
		}
		elseif ($recData['eventType'] === 'sendMqttMsg')
		{
			$recData['iotDevicePropertyValueEnum'] = '';
			$recData['iotDevicePropertyValue'] = '';
		}
	}

	public function subColumnsInfo ($recData, $columnId)
	{
		if ($columnId === 'eventValueCfg')
		{
			$iotDevicesUtils = new \mac\iot\libs\IotDevicesUtils($this->app());

			if ($recData['useGroup'])
				$properties = $iotDevicesUtils->devicesGroupProperties($recData['iotDevicesGroup']);
			else
				$properties = $iotDevicesUtils->deviceProperties ($recData['iotDevice']);
			$dp = $properties[$recData['iotDeviceProperty']] ?? NULL;
			if ($dp)
			{
				$enumSetValue = $dp['enumSet'][$recData['iotDevicePropertyValueEnum']] ?? NULL;

				if (isset($enumSetValue['fields']))	
					return $enumSetValue['fields'];


					//error_log("---subColumnsInfo-`{$recData['iotDeviceProperty']}`--:".json_encode($properties[$recData['iotDeviceProperty']]));

			}

			/*
			$cfgFileName = __SHPD_MODULES_DIR__ . 'mac/iot/config/ioPorts/' . $recData['portType'] . '.json';
			$cfg = utils::loadCfgFile($cfgFileName);
			if ($cfg)
				return $cfg['fields'];
			*/
			return FALSE;
		}

		return parent::subColumnsInfo ($recData, $columnId);
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

		if ($columnId === 'iotDeviceProperty')
		{
			if ($form->recData['useGroup'])
				$events = $iotDevicesUtils->devicesGroupProperties($form->recData['iotDevicesGroup']);
			else
				$events = $iotDevicesUtils->deviceProperties($form->recData['iotDevice']);
			if (!$events)
				return [];

			$enum = [];
			foreach ($events as $key => $value)
				$enum[$key] = $key;

			return $enum;
		}

		if ($columnId === 'iotDevicePropertyValueEnum')
		{
			if ($form->recData['useGroup'])
				$events = $iotDevicesUtils->devicesGroupProperties($form->recData['iotDevicesGroup']);
			else
				$events = $iotDevicesUtils->deviceProperties($form->recData['iotDevice']);

			if (!$events)
				return [];

			$event = $events[$form->recData['iotDeviceProperty']] ?? NULL;	
			if (!$event)
				return [];

			$enum = [];
			if (isset($event['enumSet']))
			{
				foreach ($event['enumSet'] as $key => $value)
					$enum[$key] = $key;
			}
			elseif (isset($event['enum']))
			{
				foreach ($event['enum'] as $key => $value)
					$enum[$key] = $key;
			}

			return $enum;
		}

		if ($columnId === 'iotSetupRequest')
		{
			$enum = [];

			$requests = $iotDevicesUtils->iotSetupRequests($form->recData['iotSetup']);
			foreach ($requests as $requestId => $req)
			{
				$enum[$requestId] = $req['fn'];
			}

			return $enum;
		}

		return parent::columnInfoEnum ($columnId, $valueType, $form);
	}

	public function getEventLabels($eventRow, &$dest, $prefixLabel = NULL)
	{
		if ($prefixLabel)
			$dest[] = $prefixLabel;

		if ($eventRow['eventType'] === 'sendMqttMsg')
		{
			//$this->addColumnInput ('mqttTopic');
			//$this->addColumnInput ('mqttTopicPayloadValue');

			return;
		}
		elseif ($eventRow['eventType'] === 'sendSetupRequest')
		{
			$dest[] = ['text' => $eventRow['iotSetupId'], 'class' => 'label label-default', 'icon' => 'tables/mac.iot.setups'];
			$dest[] = ['text' => $eventRow['iotSetupRequest'], 'class' => 'label label-info'];
			return;
		}
	
		if ($eventRow['useGroup'])
		{
			$dest[] = ['text' => $eventRow['devicesGroupName'], 'class' => 'label label-primary'];
		}
		else
		{
			$dest[] = ['text' => $eventRow['deviceFriendlyId'], 'class' => 'label label-primary'];

		}

		if ($eventRow['eventType'] === 'setDeviceProperty')
		{
			$dest[] = ['text' => $eventRow['iotDeviceProperty'], 'class' => 'label label-warning'];
			$dest[] = ['text' => ' = ', 'class' => 'label label-default'];
			$dest[] = ['text' => $eventRow['iotDevicePropertyValue'], 'class' => 'label label-danger'];
		}
		elseif ($eventRow['eventType'] === 'incDeviceProperty')
		{
			$dest[] = ['text' => $eventRow['iotDeviceProperty'], 'class' => 'label label-warning'];
			$dest[] = ['text' => ' + ', 'class' => 'label label-danger'];
			$dest[] = ['text' => $eventRow['iotDevicePropertyValue'], 'class' => 'label label-default'];
		}
		elseif ($eventRow['eventType'] === 'decDeviceProperty')
		{
			$dest[] = ['text' => $eventRow['iotDeviceProperty'], 'class' => 'label label-warning'];
			$dest[] = ['text' => ' - ', 'class' => 'label label-danger'];
			$dest[] = ['text' => $eventRow['iotDevicePropertyValue'], 'class' => 'label label-default'];
		}
	}
}


/**
 * Class ViewEventsDo
 */
class ViewEventsDo extends TableView
{
}

/**
 * Class ViewEventsDoForm
 */
class ViewEventsDoForm extends TableView
{
	var $dstTableId = '';
	var $dstRecId = 0;

	public function init ()
	{
		$this->enableDetailSearch = TRUE;
		$this->objectSubType = TableView::vsDetail;
		$this->toolbarTitle = ['text' => 'Provést nastavení', 'class' => 'h3 __e10-bold'/*, 'icon' => 'system/iconMapMarker'*/];

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

		$listItem ['i1'] = ['text' => '#'.$item['ndx'], 'class' => 'id'];
		$listItem ['t1'] = $item['fullName'];

		$listItem ['t2'] = [];
		$this->table->getEventLabels($item, $listItem ['t2']);

		return $listItem;
	}

	function decorateRow (&$item)
	{
	}

	public function selectRows ()
	{
		$fts = $this->fullTextSearch ();

		$q [] = 'SELECT eventsDo.*,';
		array_push ($q, ' iotDevices.friendlyId AS deviceFriendlyId, iotDevices.fullName AS deviceFullName,');
		array_push ($q, ' devicesGroups.shortName AS devicesGroupName,');
		array_push ($q, ' iotSetups.id AS iotSetupId, iotSetups.fullName AS iotSetupFullName');
		array_push ($q, ' FROM [mac_iot_eventsDo] AS [eventsDo]');
		array_push ($q, ' LEFT JOIN [mac_iot_devices] AS iotDevices ON eventsDo.iotDevice = iotDevices.ndx');
		array_push ($q, ' LEFT JOIN [mac_iot_devicesGroups] AS devicesGroups ON eventsDo.iotDevicesGroup = devicesGroups.ndx');
		array_push ($q, ' LEFT JOIN [mac_iot_setups] AS iotSetups ON eventsDo.iotSetup = iotSetups.ndx');
		array_push ($q, ' WHERE 1');
		array_push ($q, ' AND [eventsDo].[tableId] = %s', $this->dstTableId);
		array_push ($q, ' AND [eventsDo].[recId] = %i', $this->dstRecId);

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
		$this->queryMain ($q, 'eventsDo.', ['[rowOrder]', '[ndx]']);

		$this->runQuery ($q);
	}

	public function selectRows2 ()
	{
		if (!count ($this->pks))
			return;
	}
}


/**
 * Class FormEventDo
 */
class FormEventDo extends TableForm
{
	public function renderForm ()
	{
		$this->setFlag ('formStyle', 'e10-formStyleDefault viewerFormList');
		$this->setFlag ('sidebarPos', TableForm::SIDEBAR_POS_RIGHT);
		//$this->setFlag ('maximize', 1);

		$tabs ['tabs'][] = ['text' => 'Základní', 'icon' => 'system/formHeader'];

		$iotDevicesUtils = new \mac\iot\libs\IotDevicesUtils($this->app());

		$this->openForm ();
			$this->openTabs ($tabs);
				$this->openTab ();
					$this->addColumnInput ('fullName');

					if ($this->recData['eventType'] !== 'sendSetupRequest' && $this->recData['eventType'] !== 'sendMqttMsg')
					{
						$this->openRow();
							$this->addColumnInput ('eventType');
							$this->addColumnInput ('useGroup');
						$this->closeRow();
					}
					else
					$this->addColumnInput ('eventType');

					if ($this->recData['eventType'] === 'setDeviceProperty')
					{
						if ($this->recData['useGroup'])
							$this->addColumnInput ('iotDevicesGroup');
						else
							$this->addColumnInput ('iotDevice');
						$this->addColumnInput ('iotDeviceProperty');

						if ($this->recData['useGroup'])
							$properties = $iotDevicesUtils->devicesGroupProperties($this->recData['iotDevicesGroup']);
						else
							$properties = $iotDevicesUtils->deviceProperties($this->recData['iotDevice']);

						$dp = $properties[$this->recData['iotDeviceProperty']] ?? NULL;
						if ($dp)
						{
							if ($dp['data-type'] === 'binary' || $dp['data-type'] === 'enum')
								$this->addColumnInput ('iotDevicePropertyValueEnum');
							else
								$this->addColumnInput ('iotDevicePropertyValue');

							$this->addSubColumns('eventValueCfg');
						}
					}
					elseif ($this->recData['eventType'] === 'incDeviceProperty' || $this->recData['eventType'] === 'decDeviceProperty')
					{
						if ($this->recData['useGroup'])
							$this->addColumnInput ('iotDevicesGroup');
						else
							$this->addColumnInput ('iotDevice');
						$this->addColumnInput ('iotDeviceProperty');
					}
					elseif ($this->recData['eventType'] === 'sendSetupRequest')
					{
						$this->addColumnInput ('iotSetup');
						$this->addColumnInput ('iotSetupRequest');
					}
					elseif ($this->recData['eventType'] === 'sendMqttMsg')
					{
						$this->addColumnInput ('mqttTopic');
						$this->addColumnInput ('mqttTopicPayloadValue');
					}
				$this->closeTab ();
			$this->closeTabs ();
		$this->closeForm ();
	}
}


/**
 * Class ViewDetailEventDo
 */
class ViewDetailEventDo extends TableViewDetail
{
}


<?php

namespace mac\iot;
use \Shipard\Form\TableForm, \Shipard\Table\DbTable, \Shipard\Viewer\TableView, \Shipard\Viewer\TableViewDetail;


/**
 * Class TableCams
 */
class TableCams extends DbTable
{
	CONST
		ctLanIP = 30,
		ctIBEsp32 = 31;

	public function __construct ($dbmodel)
	{
		parent::__construct ($dbmodel);
		$this->setName ('mac.iot.cams', 'mac_iot_cams', 'Kamery');
	}

	public function checkBeforeSave (&$recData, $ownerData = NULL)
	{
		parent::checkBeforeSave($recData, $ownerData);
	}

	public function createHeader ($recData, $options)
	{
		$hdr = parent::createHeader ($recData, $options);

		$hdr ['info'][] = ['class' => 'title', 'value' => $recData ['fullName']];
		//$hdr ['info'][] = ['class' => 'info', 'value' => '#'.$recData['ndx'].'.'.$recData['uid']];

		return $hdr;
	}

	function copyDocumentRecord ($srcRecData, $ownerRecord = NULL)
	{
		$recData = parent::copyDocumentRecord ($srcRecData, $ownerRecord);

		//$recData['uid'] = '';

		return $recData;
	}

	public function tableIcon ($recData, $options = NULL)
	{
		//return $this->app()->cfgItem ('mac.control.controlsKinds.'.$recData['controlKind'].'.icon', 'x-cog');

		return parent::tableIcon($recData, $options);
	}

	public function camInfo($cameraNdx)
	{
		$camRecData = $this->loadItem($cameraNdx);
		if (!$camRecData)
		{
			error_log("!!!NO-CAMERA!!!");
			return NULL;
		}

    $lanNdx = $camRecData['lan'];
		$lanRecData = $this->app()->loadItem($lanNdx, 'mac.lan.lans');
		$camServerNdx = $lanRecData['mainServerCameras'];

		$camInfo = [
			'ndx' => $cameraNdx,
			'camRecData' => $camRecData,
			'camServerNdx' => $camServerNdx,
		];

		$server = $this->app->cfgItem('mac.localServers.'.$camInfo['camServerNdx'], NULL);
		if (!$server)
		{
			error_log("!!!NO-SERVER!!!");
			return NULL;
		}

		$camInfo['serverInfo'] = [
			'camUrl' => $server['camerasURL']
		];

		return $camInfo;
	}
}


/**
 * Class ViewCams
 */
class ViewCams extends TableView
{
	/** @var \mac\lan\TableDevices */
	var $tableDevices;

	var $devicesKinds;

	public function init ()
	{
		parent::init();

		//$this->objectSubType = TableView::vsDetail;
		$this->enableDetailSearch = TRUE;

		$this->devicesKinds = $this->app()->cfgItem ('mac.lan.devices.kinds');
		$this->tableDevices = $this->app()->table('mac.lan.devices');

		$this->setMainQueries ();
	}

	public function renderRow ($item)
	{
		$listItem ['pk'] = $item ['ndx'];
		$listItem ['i1'] = ['text' => '#'.$item['ndx']/*.'.'.substr($item['uid'], 0, 3).'...'.substr($item['uid'],-3)*/, 'class' => 'id'];
		$listItem ['t1'] = $item['fullName'];
		$listItem ['icon'] = $this->table->tableIcon ($item);

		return $listItem;
	}

	public function selectRows ()
	{
		$fts = $this->fullTextSearch ();

		$q [] = 'SELECT [cams].* ';
		//array_push ($q, ' ioPortsDevices.fullName AS [ioPortDeviceFullName], ioPortsDevices.deviceKind AS [ioPortDeviceKind],');
		//array_push ($q, ' ioPorts.portId AS [ioPortId], ioPorts.fullName AS [ioPortFullName]');
		array_push ($q, ' FROM [mac_iot_cams] AS [cams]');
		//array_push ($q, ' LEFT JOIN [mac_lan_devicesIOPorts] AS ioPorts ON [controls].dstIOPort = ioPorts.ndx');
		//array_push ($q, ' LEFT JOIN [mac_lan_devices] AS ioPortsDevices ON ioPorts.device = ioPortsDevices.ndx');
		array_push ($q, ' WHERE 1');

		// -- fulltext
		if ($fts != '')
		{
			array_push ($q, ' AND (');
			array_push ($q, ' [cams].[fullName] LIKE %s', '%'.$fts.'%');
			array_push ($q, ')');
		}

		$this->queryMain ($q, 'cams.', ['[fullName]', '[ndx]']);
		$this->runQuery ($q);
	}
}


/**
 * Class FormCam
 */
class FormCam  extends TableForm
{
	public function renderForm ()
	{
		$this->setFlag ('sidebarPos', TableForm::SIDEBAR_POS_RIGHT);

		$tabs ['tabs'][] = ['text' => 'Základní', 'icon' => 'system/formHeader'];
		$tabs ['tabs'][] = ['text' => 'Přílohy', 'icon' => 'system/formAttachments'];

		//$iotDevicesUtils = new \mac\iot\libs\IotDevicesUtils($this->app());


		$this->openForm ();
			$this->openTabs ($tabs);
				$this->openTab ();
					$this->addColumnInput ('camType');
					$this->addSeparator(self::coH2);
					$this->addColumnInput ('fullName');
					$this->addColumnInput ('lan');
					//if ($this->recData['controlType'] !== 'sendSetupRequest')
						$this->addColumnInput ('iotDevice');
            $this->addColumnInput ('lanDevice');
				$this->closeTab ();

				$this->openTab (TableForm::ltNone);
					$this->addAttachmentsViewer();
				$this->closeTab ();
			$this->closeTabs ();
		$this->closeForm ();
	}
}


/**
 * Class ViewDetailCam
 */
class ViewDetailCam extends TableViewDetail
{
}

/**
 * Class ViewDetailCamCfg
 */
class ViewDetailCamCfg extends TableViewDetail
{
	public function createDetailContent ()
	{
		$this->addDocumentCard('mac.iot.libs.dc.CamCfg');
	}
}

<?php


namespace e10\ui;
use \Shipard\Viewer\TableView, \Shipard\Form\TableForm, \Shipard\Table\DbTable;
use \Shipard\Application\DataModel;
use \e10\base\libs\UtilsBase;
use \Shipard\Utils\Utils;


/**
 * class TableUIs
 */
class TableUIs extends DbTable
{
  const uitTemplate = 9;

	public function __construct ($dbmodel)
	{
		parent::__construct ($dbmodel);
		$this->setName ('e10.ui.uis', 'e10_ui_uis', 'Uživatelská rozhraní');
	}

	public function checkBeforeSave (&$recData, $ownerData = NULL)
	{
		parent::checkBeforeSave ($recData, $ownerData);
	}

	public function checkNewRec (&$recData)
	{
		parent::checkNewRec ($recData);
	}

	public function createHeader ($recData, $options)
	{
		$hdr = parent::createHeader ($recData, $options);


		$hdr ['info'][] = ['class' => 'title', 'value' => $recData['fullName']];

		return $hdr;
	}

	public function saveConfig ()
	{
		$uis = [];
		$domains = [];
		$rows = $this->app()->db->query ('SELECT * FROM [e10_ui_uis] WHERE docState != 9800 ORDER BY [urlId]');

		foreach ($rows as $r)
		{
      $uiItem = [
				'ndx' => $r['ndx'],
        'uiType' => $r ['uiType'],
				'fn' => $r ['fullName'],
				'pwaStartUrlBegin' => $r['pwaStartUrlBegin'],
			];

			if ($r['domain'] !== '')
			{
				$uiItem['domain'] = $r['domain'];
				$domains[$r['domain']] = $r['urlId'];
			}
			if ($r['uiType'] === 4)
			{
				$uiItem['appType'] = $r['appType'];
			}

      $uis [$r['urlId']] = $uiItem;
		}

		// -- save to file
		$cfg ['e10']['ui']['uis'] = $uis;
		if (count($domains))
			$cfg ['e10']['ui']['domains'] = $domains;

		file_put_contents(__APP_DIR__ . '/config/_e10.ui.uis.json', utils::json_lint (json_encode ($cfg)));

		$this->createNginxConfigs();
	}

	function createNginxConfigs()
	{
		$webServers = Utils::loadCfgFile(__APP_DIR__ . '/config/_e10.ui.uis.json');
		if (!$webServers || !count($webServers['e10']['ui']['domains']))
			return;

		$systemDomainsCerts = ['shipard.app', 'shipard.pro', 'shipard.cz'];
		$dsid = $this->app()->cfgItem('dsid');

		array_map ("unlink", glob (__APP_DIR__.'/config/nginx/'.$dsid.'-ui*'));

		foreach ($webServers['e10']['ui']['domains'] as $domain => $uiId)
		{
			$cfg = '';

			$cfg .= '# '.$domain;
			$cfg .= '; ui cfg ver 0.1'."\n\n";

			$domainParts = explode('.', $domain);
			$cntAllDomainParts = count($domainParts);
			while(count($domainParts) > 2)
				array_shift($domainParts);
			$coreDomain = implode('.', $domainParts);

			$isSystemCert = 0;
			$certId = $isSystemCert ? 'all.'.$coreDomain : $domain;
			$certPath = $isSystemCert ? '/var/lib/shipard/certs' : __APP_DIR__.'/config/nginx/certs';

			// -- web via https
			$cfg .= "server {\n";
			$cfg .= "\tlisten 443 ssl http2;\n";
			$cfg .= "\tserver_name $domain;\n";
			$cfg .= "\troot /var/lib/shipard/data-sources/$dsid;\n";
			$cfg .= "\tindex index.php;\n";

			$cfg .= "\tssl_certificate $certPath/$certId/chain.pem;\n";
			$cfg .= "\tssl_certificate_key $certPath/$certId/privkey.pem;\n";
			if (is_readable('/etc/ssl/dhparam.pem'))
				$cfg .= "\tssl_dhparam /etc/ssl/dhparam.pem;\n";
			$cfg .= "\tinclude /usr/lib/shipard/etc/nginx/shpd-one-app.conf;\n";
			$cfg .= "\tinclude /usr/lib/shipard/etc/nginx/shpd-https.conf;\n";
			$cfg .= "}\n\n";

			// -- save
			$configFileName = __APP_DIR__.'/config/nginx/'.$dsid.'-ui-'.$domain.'.conf';
			file_put_contents($configFileName, $cfg);
		}
	}
}


/**
 * Class ViewUIs
 */
class ViewUIs extends TableView
{
	var $toReports;

	public function init ()
	{
		parent::init();

		$this->objectSubType = TableView::vsDetail;
		$this->enableDetailSearch = TRUE;

		$this->setMainQueries ();
	}

	public function renderRow ($item)
	{
		$listItem ['pk'] = $item ['ndx'];
		$listItem ['i1'] = ['text' => '#'.$item['ndx'], 'class' => 'id'];

		$listItem ['t1'] = $item['fullName'];
		$listItem ['t2'] = [];

		$listItem ['t2'][] = ['text' => $item['urlId'], 'class' => 'label label-default'];
		if ($item['domain'] !== '')
		$listItem ['t2'][] = ['text' => $item['domain'], 'class' => 'label label-default', 'icon' => 'system/iconGlobe'];

		$listItem ['icon'] = $this->table->tableIcon ($item);

		return $listItem;
	}

	public function selectRows ()
	{
		$fts = $this->fullTextSearch ();

		$q [] = 'SELECT * FROM [e10_ui_uis]';
		array_push ($q, ' WHERE 1');

		// -- fulltext
		if ($fts != '')
		{
			array_push ($q, ' AND (');
			array_push ($q, ' [fullName] LIKE %s', '%'.$fts.'%', ' OR [urlId] LIKE %s', '%'.$fts.'%');
			array_push ($q, ')');
		}

		$this->queryMain ($q, '', ['[order]', '[fullName]']);
		$this->runQuery ($q);
	}
}


/**
 * class FormUI
 */
class FormUI extends TableForm
{
	public function renderForm ()
	{
		$this->setFlag ('formStyle', 'e10-formStyleSimple');
		$this->setFlag ('sidebarPos', TableForm::SIDEBAR_POS_RIGHT);
		$this->setFlag ('maximize', 1);

		$this->openForm ();
			$tabs ['tabs'][] = ['text' => 'Základní', 'icon' => 'system/formHeader'];

			if ($this->recData['uiType'] === 9)
			{
      	$tabs ['tabs'][] = ['text' => 'Šablona', 'icon' => 'formText'];
			}
			elseif ($this->recData['uiType'] === 5)
			{
				$tabs ['tabs'][] = ['text' => 'UI', 'icon' => 'formText'];
			}
			$tabs ['tabs'][] = ['text' => 'Styl', 'icon' => 'formText'];
			$tabs ['tabs'][] = ['text' => 'Přílohy', 'icon' => 'system/formAttachments'];
			$this->openTabs ($tabs, TRUE);
				$this->openTab ();
          $this->addColumnInput ('fullName');
					$this->openRow();
          	$this->addColumnInput ('uiType');
						if ($this->recData['uiType'] === 4)
							$this->addColumnInput ('appType');
					$this->closeRow();
          $this->addColumnInput ('urlId');
					$this->addColumnInput ('order');
					$this->addColumnInput ('pwaStartUrlBegin');
					$this->addColumnInput ('domain');
				$this->closeTab ();
				$this->openTab (TableForm::ltNone);
				if ($this->recData['uiType'] === 9)
				{
					$this->addInputMemo ('template', NULL, TableForm::coFullSizeY, DataModel::ctCode);
				}
				elseif ($this->recData['uiType'] === 5)
				{
					$this->addInputMemo ('uiStruct', NULL, TableForm::coFullSizeY, DataModel::ctCode);
				}
				$this->closeTab();
				$this->openTab (TableForm::ltNone);
          $this->addInputMemo ('style', NULL, TableForm::coFullSizeY, DataModel::ctCode);
				$this->closeTab();
				$this->openTab (TableForm::ltNone);
					$this->addAttachmentsViewer();
				$this->closeTab ();
			$this->closeTabs();
		$this->closeForm ();
	}
}

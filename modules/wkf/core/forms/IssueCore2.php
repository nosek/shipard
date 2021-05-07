<?php

namespace wkf\core\forms;

use E10\ContentRenderer;
use E10\FormSidebar;
use \e10\utils, \E10\TableForm, \e10\TableView, \E10\DbTable, \e10\uiutils, wkf\core\TableIssues;
use \translation\dicts\e10\base\system\DictSystem;


/**
 * Class IssueCore
 * @package wkf\core\forms
 */
class IssueCore2 extends TableForm
{
	CONST askYes = 1, askSettings = 0, askNone = 2;
	CONST askUsersMembers = 0, askUsersAdmins = 1;

	CONST fkDefault = 1, fkInbox = 2;
	var $formKind = self::fkDefault;

	CONST bmNone = 0, bmInfoPanel = 1, bmLayout = 2;
	var $bodyMode = self::bmNone;

	var $content = [];
	var $issueKind;

	/** @var \wkf\base\TableSections */
	var $tableSections;
	var $userAccessLevel = 0;

	public function renderForm ()
	{
		if ($this->recData['issueType'] == 1)
			$this->formKind = self::fkInbox;

		if ($this->formKind === self::fkInbox && $this->viewPortWidth < 1600)
			$this->bodyMode = self::bmLayout;
		elseif ($this->formKind === self::fkInbox)
			$this->bodyMode = self::bmInfoPanel;

		$this->tableSections = $this->app()->table ('wkf.base.sections');
		$this->userAccessLevel = $this->tableSections->userAccessToSection ($this->recData['section']);

		if ($this->renderFormReadOnly ())
			return;

		$this->renderFormReadWrite();
	}

	function renderFormReadWrite ()
	{
		$this->issueKind = $this->app()->cfgItem('wkf.issues.kinds.'.$this->recData['issueKind']);
		$topSection = $this->table->topSection($this->recData['section']);

		$askPersons = intval($this->issueKind['askPersons']);
		$askPersonsUsers = intval($this->issueKind['askPersonsUsers']);
		$askPersonsOptions = 0;
		if ($askPersons !== self::askNone && $askPersonsUsers === self::askUsersAdmins && $this->userAccessLevel !== 2)
			$askPersonsOptions = self::coReadOnly;

		$askWorkOrder = intval($this->issueKind['askWorkOrder']);
		$askKind = intval($this->issueKind['askKind']);

		$askDeadline = intval($this->issueKind['askDeadline']);
		$askDeadlineUsers = intval($this->issueKind['askDeadlineUsers']);
		$askDeadlineOptions = 0;
		if ($askDeadline !== self::askNone && $askDeadlineUsers === self::askUsersAdmins && $this->userAccessLevel !== 2)
			$askDeadlineOptions = self::coReadOnly;

		$askDateIncoming = intval($this->issueKind['askDateIncoming']);
		$enableConnectedIssues = intval($this->issueKind['enableConnectedIssues']);

		$this->setFlag ('sidebarPos', TableForm::SIDEBAR_POS_RIGHT);
		$this->setFlag ('formStyle', 'e10-formStyleSimple');
		$this->setFlag ('maximize', 1);
		//$this->setFlag ('sidebarWidth', '0.45');

		if ($this->bodyMode === self::bmInfoPanel)
		{
			$this->setFlag('infoPanelPos', 1);
			$this->setFlag('infoPanelWidth', '40vw');
		}

		$tabs ['tabs'][] = ['text' => 'Obsah', 'icon' => 'icon-pencil-square-o'];
		$tabs ['tabs'][] = ['text' => 'Nastavení', 'icon' => 'icon-sliders'];
		if ($enableConnectedIssues)
			$tabs ['tabs'][] = ['text' => 'Propojení', 'icon' => 'icon-link'];
		$tabs ['tabs'][] = ['text' => 'Přílohy', 'icon' => 'icon-paperclip'];

		$bigTextMode = 0;
		if ($this->formKind === self::fkDefault && $askPersons !== self::askYes && $askDeadline !== self::askYes && $askDateIncoming !== self::askYes && $askWorkOrder !== self::askYes)
			$bigTextMode = 1;

		$this->openForm ();
		$this->addColumnInput ('subject');
		$this->openTabs ($tabs);
		if ($bigTextMode)
		{
			$this->openTab(self::ltNone);
			$this->addTextInput2($bigTextMode);
			$this->closeTab();
		}
		else
		{
			$this->openTab();
				if ($this->bodyMode === self::bmLayout && $this->formKind === self::fkInbox)
				{
					$this->layoutOpen(self::ltHorizontal);
						$this->layoutOpen(self::ltForm);
							if ($askKind === self::askYes)
								$this->addColumnInput('issueKind');
							if ($askPersons === self::askYes)
								$this->addList('doclinksPersons', '', TableForm::loAddToFormLayout | $askPersonsOptions);
							if ($askDateIncoming === self::askYes || $askDeadline === self::askYes)
							{
								if ($askDateIncoming === self::askYes)
									$this->addColumnInput('dateIncoming');
								if ($askDeadline === self::askYes)
									$this->addColumnInput('dateDeadline', $askDeadlineOptions);
							}
							$this->addSubColumns('data');
							$this->addTextInput2($bigTextMode);
						$this->layoutClose('width40');
						$this->layoutOpen(self::ltForm);
							$this->addBody();
						$this->layoutClose('width60');
					$this->layoutClose();
				}
				else
				{
					if ($askKind === self::askYes)
						$this->addColumnInput('issueKind');
					if ($askPersons === self::askYes)
						$this->addList('doclinksPersons', '', TableForm::loAddToFormLayout | $askPersonsOptions);
					if ($askDateIncoming === self::askYes || $askDeadline === self::askYes)
					{
						if ($askDateIncoming === self::askYes)
							$this->addColumnInput('dateIncoming');
						if ($askDeadline === self::askYes)
							$this->addColumnInput('dateDeadline', $askDeadlineOptions);
					}
					$this->addSubColumns('data');
					$this->addTextInput2($bigTextMode);
					$this->addBody();
				}
			$this->closeTab();
		}
		$this->openTab();
		$this->addList('doclinksAssignment', '', TableForm::loAddToFormLayout);
		$this->addList('clsf', '', TableForm::loAddToFormLayout);
		if ($askDateIncoming === self::askSettings || $askDeadline === self::askSettings)
		{
			if ($askDateIncoming === self::askSettings && $askDeadline === self::askSettings)
				$this->openRow();
			if ($askDateIncoming === self::askSettings)
				$this->addColumnInput('dateIncoming');
			if ($askDeadline === self::askSettings)
				$this->addColumnInput('dateDeadline', $askDeadlineOptions);
			if ($askDateIncoming === self::askSettings && $askDeadline === self::askSettings)
				$this->closeRow();
		}
		if ($askPersons == self::askSettings)
		{
			$this->addList('doclinksPersons', '', TableForm::loAddToFormLayout | $askPersonsOptions);
		}
		$this->addColumnInput('priority');
		$this->addColumnInput('onTop');
		$this->addColumnInput('disableComments');
		if ($askKind === self::askSettings)
			$this->addColumnInput('issueKind');
		$this->addColumnInput('section');

		//if ($topSection['useStatuses'])
		//	$this->addColumnInput('status');
		$this->closeTab();
		if ($enableConnectedIssues)
		{
			$this->openTab(TableForm::ltNone);
			$this->addList ('connections');
			$this->closeTab();
		}
		$this->openTab(TableForm::ltNone);
		\E10\Base\addAttachmentsWidget($this);
		$this->closeTab();
		$this->closeTabs ();
		$this->closeForm ();
	}

	function addTextInput2 ($bigTextMode)
	{
		//if ($this->recData['source'] === TableIssues::msHuman)
		{
			//$this->addInputMemo('text', 'Text');
			if ($bigTextMode)
				$this->addInputMemo('text', NULL, TableForm::coFullSizeY);
			else
				$this->addColumnInput('text');
			//return;
		}

		//$this->addColumnInput('structVersion');

		$this->content = [];

		if ($this->recData ['text'] !== '' && $this->recData ['text'] !== '0')
		{
			if ($this->recData['source'] == TableIssues::msEmail)
			{
				$this->content [] = ['type' => 'text', 'subtype' => 'auto', 'text' => $this->recData ['text'],
					'iframeUrl' => $this->app()->urlRoot . '/api/call/wkf.core.issuePreview/' . $this->recData['ndx']];
			}
			else
			{
				if ($this->recData['source'] == TableIssues::msTest)
				{
					$contentData = json_decode($this->recData ['text'], TRUE);
					foreach ($contentData as $cdi)
						$this->content [] = $cdi;
				}
				else
				{
					$textRenderer = new \lib\core\texts\Renderer($this->app());
					$textRenderer->render($this->recData ['text']);
					$this->content [] = ['type' => 'text', 'subtype' => 'rawhtml', 'text' => $textRenderer->code, 'pane' => 'pageText e10-pane-table e10-pane-vitem'];
				}
			}
		}
	}

	public function renderFormReadOnly ()
	{
		if (!$this->readOnly || $this->formKind === self::fkInbox)
			return FALSE;

		$this->setFlag ('sidebarPos', TableForm::SIDEBAR_POS_RIGHT);
		$this->setFlag ('sidebarWidth', '0.45');
		$this->setFlag ('maximize', 1);

		$tabs ['tabs'][] = ['text' => 'Obsah', 'icon' => 'icon-pencil-square-o'];
		//$tabs ['tabs'][] = ['text' => 'Analýza', 'icon' => 'icon-search-plus'];
		$tabs ['tabs'][] = ['text' => 'Přílohy', 'icon' => 'icon-paperclip'];

		$this->openForm (TableForm::ltNone);
			$this->addColumnInput ('subject', TableForm::coHidden);
			$this->openTabs ($tabs);
				$this->openTab(TableForm::ltNone);
					$this->renderFormContent ();
				$this->closeTab();
				$this->openTab(TableForm::ltNone);
					$this->addAttachmentsViewer();
				$this->closeTab();
			$this->closeTabs();
		$this->closeForm ();

		return TRUE;
	}

	public function renderFormContent ()
	{
		$this->addDocumentCard('wkf.core.documentCards.Issue');
		$this->renderSidebarInfo ();
	}

	public function addAttachments ($toRecId, $tableId = FALSE, $title = FALSE, $downloadTitle = FALSE)
	{
		if ($tableId === FALSE)
			$tableId = $this->table->tableId();

		if ($title === FALSE)
			$title = ['icon' => 'icon-paperclip', 'text' => 'Přílohy'];
		if ($downloadTitle === FALSE)
			$downloadTitle = ['icon' => 'icon-download', 'text' => 'Soubory ke stažení'];

		$files = \E10\Base\loadAttachments ($this->table->app(), array($toRecId), $tableId);
		if (isset($files[$toRecId]))
			$this->content[] = ['type' => 'attachments', 'attachments' => $files[$toRecId], 'title' => $title, 'downloadTitle' => $downloadTitle];
	}

	public function addContentViewer ($tableId, $viewerId, $params)
	{
		$this->content [] = ['type' => 'viewer', 'table' => $tableId, 'viewer' => $viewerId, 'params' => $params];
	}

	protected function renderSidebarInfo ()
	{
		$vid = 'mainListView' . mt_rand () . '_' . TableView::$vidCounter++;

		$disableAddButtons = 0;
//		if ($this->recData['docStateMain'] > 1)
//			$disableAddButtons = 1;

		$issueAccessLevel = $this->table->checkAccessToDocument($this->recData);

		$this->content = [];
		$this->content [] = [
			'type' => 'viewer', 'table' => 'wkf.core.comments', 'viewer' => 'wkf.core.viewers.CommentsSidebar',
			'params' => [
				'issue' => $this->recData ['ndx'], 'disableAddButtons' => $disableAddButtons, 'issueAccessLevel' => $issueAccessLevel,
			],
			'vid' => $vid,
		];

		$cr = new \E10\ContentRenderer ($this->app());
		$cr->setForm($this);

		$c = '';
		$c .= "<div style='font-size:100%; background-color: #f0f0f0; padding: 0px;' class='e10-reportContent'>";
		$c .= $cr->createCode();
		$c .= '</div>';

		$this->sidebar = $c;
	}

	public function createToolbar ()
	{
		if (!$this->readOnly && $this->recData['source'] == 0)
			return parent::createToolbar();

		$b = [
			'type' => 'action', 'action' => 'saveform', 'text' => DictSystem::text(DictSystem::diBtn_Seen), 'docState' => '99000',
			'style' => 'stateSave', 'stateStyle' => 'done', 'icon' => 'icon-eye', 'buttonClass' => 'btn-default'
		];

		$toolbar [] = $b;

		$toolbar = array_merge ($toolbar, parent::createToolbar());
		return $toolbar;
	}

	public function docLinkEnabled ($docLink)
	{
		$issueKind = $this->app()->cfgItem ('wkf.issues.kinds.'.$this->recData['issueKind'], []);

		if ($docLink['linkid'] === 'wkf-issues-targets' && (!isset($issueKind['enableTargets']) || !$issueKind['enableTargets']))
			return FALSE;
		if ($docLink['linkid'] === 'wkf-issues-projects' && (!isset($issueKind['enableProjects']) || !$issueKind['enableProjects']))
			return FALSE;

		return parent::docLinkEnabled($docLink);
	}

	protected function XX__renderMainSidebar ($allRecData, $recData)
	{
		$card = $this->app()->createObject('wkf.core.documentCards.IssueBody');
		$card->setDocument($this->table, $this->recData);
		$card->createContent();

		$cr = new ContentRenderer($this->app());
		$cr->setDocumentCard($card);
		$code = "<div class='padd5'>".$cr->createCode('body').'</div>';


		$sideBar = new FormSidebar ($this->app());
		$sideBar->addTab('t1', 'Přílohy');
		$sideBar->setTabContent('t1', $code);
		$sideBar->addTab('t2', 'TEST2');
		$sideBar->setTabContent('t2', 'tst2');

		$this->sidebar = $sideBar->createHtmlCode();
	}

	protected function addBody()
	{
		$card = $this->app()->createObject('wkf.core.documentCards.IssueBody');
		$card->setDocument($this->table, $this->recData);
		$card->createContent();

		$cr = new ContentRenderer($this->app());
		$cr->setDocumentCard($card);

		if ($this->bodyMode === self::bmLayout)
		{
			$c = '';
			$c .= "<div class='e10-wsh-h2t' style='overflow-y: auto; padding-left: 2px; padding-right: 2px; border-left: 4px solid steelblue; height: 100%;'>";
			$c .= $cr->createCode('body');
			$c .= '</div>';
			$this->appendElement($c);
		}
		else
		{
			$c = '';
			$c .= "<div class='infoPanelContent' style='min-height: 100%; background-color: #FAFAFA; overflow-y: auto; padding-left: 2px; padding-right: 2px; border-left: 6px solid steelblue;'>";
			$c .= $cr->createCode('body');
			$c .= '</div>';
			$this->infoPanel = $c;
		}
	}
}

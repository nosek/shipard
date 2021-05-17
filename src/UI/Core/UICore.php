<?php

namespace Shipard\UI\Core;
use \Shipard\Utils\Utils;


class UICore extends \Shipard\Base\BaseObject
{
	protected \Shipard\UI\Core\Icons $icons;
	
	public function icons()
	{
		return $this->icons;
	}

	public function __construct (\Shipard\Application\Application $app)
	{
		parent::__construct($app);

		$this->icons = new \Shipard\UI\Core\Icons($app);
		$this->icons->init();
	}

	public function es ($s)
	{
		return htmlspecialchars ($s);
	}

	public function addAttachmentsInputCode ($tableId, $recId, $objectId)
	{
		$c = '';
		$c .= "<div class='e10-att-input-upload' data-table='{$tableId}' data-pk='{$recId}'";

		if ($objectId === NULL)
			$c .= " data-closewindow='1'";

		$c .= ">";

		$title = [];
		$title[] = ['text' => 'Přidat soubory', 'class' => 'h2', 'icon' => 'icon-plus-square'];

		$title [] = ['type' => 'action', 'action' => 'new', 'data-table' => 'e10.base.attachments',
			'actionClass' => 'btn-xs', 'class' => 'pull-right',
			'data-addparams' => '__tableid='.$tableId.'&__recid='.$recId, 'text' => 'Zástupce',
			'data-srcobjecttype' => 'viewer', 'data-srcobjectid' => $objectId];

		$c .= $this->composeTextLine($title);

		$c .= "
							<input class='e10-att-input-file' type='file' onchange='e10AttWidgetFileSelected(this)' multiple='multiple'/>
							<div class='e10-att-input-files'>vyberte soubor(y), které chcete nahrát a stiskněte Odeslat</div>
							<div class='e10-att-input-send'><input type='button' onclick='e10AttWidgetUploadFile($(this))' value='Odeslat'/></div>
					 </div>";

		return $c;
	}

	public function composeTextLine ($parts, $separator = ', ')
	{
		if (!isset($parts) || $parts === null)
			return '';
		if (!is_string($parts) && !is_array($parts))
			$parts = strval($parts);
		if (is_string($parts))
			return $this->es ($parts);

		if (isset ($parts['text']))
			return $this->renderTextLine ($parts);
		if (isset ($parts['code']))
			return $parts['code'];
		if (isset ($parts['table']))
			return $this->renderTableFromArray($parts['table'], $parts['header'], $parts['params'] ?? []);

		$t = '';
		forEach ($parts as $p)
		{
			if ($t != '' && !isset($p['class']) && isset($p['text']))
				$t .= $separator;
			$t .= $this->composeTextLine ($p);
		}

		return $t;
	}

	public function renderTextLine ($p)
	{
		if (is_string($p))
			return $this->es ($p);

		if (isset($p['mark']))
			return $this->renderDocMark($p);

		$t = '';
		$css = (isset($p['css'])) ? " style='{$p['css']}'" : '';

		if (isset ($p['img']))
		{
			if (isset ($p['url']))
			{
				$t .= "<a href='{$p['url']}' target='_new'>";
				$t .= "<img src='{$p['img']}'/>";
				$t .= '</a>';
			}
			else
				$t .= "<img src='{$p['img']}'/>";
		}
		if (isset ($p['prefix']))
			$t .= "<span class='pre'>".Utils::es($p['prefix']).'</span>';

		$i = '';
		if (isset ($p ['icon']))
		{
			$icn = $this->icons()->cssClass ($p ['icon']);
			$i = "<i class='$icn'></i>";
		}
		elseif (isset ($p ['i']))
			$i = "<i class='fa fa-{$p ['i']}'></i>";
		elseif (isset ($p ['icontxt']))
			$i = $p ['icontxt'];
		if ($i !== '' && isset($p ['text']) && $p ['text'] !== '')
			$i .= '&nbsp;';

		$title = '';
		if (isset ($p['title']))
			$title = " title=\"".Utils::es($p['title'])."\"";

		if (isset ($p['docAction']))
		{
			$element = Utils::param($p, 'type', 'a');
			$t .= "<$element";
			if ($element === 'a')
				$t .= " href='#'";
			$linkClass = '';
			$t .= Utils::elementActionParams ($p, $linkClass);
			if (isset($p['table']))
				$t.= " data-table='{$p['table']}'";
			$t .= " class='$linkClass' data-action='{$p['docAction']}' $title>$i" . Utils::es ($p ['text']) . "</$element>";
		}
		else
		if (isset($p['action']))
		{
			$t .= $this->actionCode ($p);
		}
		else
		if (isset ($p['url']))
		{
			$t .= "<a href='{$p['url']}'";
			$t .= " target='_new'";
			$t .= '>';

			$t .= $i.Utils::es ($p['text']);
			$t .= '</a>';
		}
		elseif (isset($p ['text']))
			$t .= $i . Utils::es($p ['text']);
		else
			$t .= $i;

		if (isset ($p['suffix']))
			$t .= "<span class='suf'>".$this->es($p['suffix']).'</span>';

		if (isset ($p['class']))
		{
			$params = (isset ($p['focusable'])) ? " tabindex='0'" : '';
			return "<span{$params} class='{$p['class']}'$css{$title}" . Utils::dataAttrs($p) . '>' . $t . '</span> ';
		}
		return $t;
	}

	public function actionCode ($button, $mode = 0)
	{
		$params = '';
		$c = '';

		if (isset ($button['subButtons']) || isset ($button['dropdownMenu']))
		{
			$bgClass = (isset ($button['dropUp'])) ? ' dropup' : '';
			$c .= "<div class='btn-group$bgClass'>";
		}

		$element = 'button';
		$elementClass = 'btn';
		if (isset ($button['href']))
		{
			$element = 'a';
			$elementClass = '';
			$params .= " href='{$button['href']}'";
			if (isset ($button['downloadFileName']))
				$params .= " download='{$button['downloadFileName']}'";
		}
		else
		if (isset ($button['element']))
		{
			$element = $button['element'];
			$elementClass = '';
		}
		else
		if ($mode === 1)
			$params .= " href='#'";

		if ($this->app()->remote !== '')
			$params .= " data-remote='".$this->app()->remote."'";

		$class = isset($button['type']) ? "df2-{$button['type']}-trigger" : '';
		if (isset ($button['actionClass']))
			$class .= ' '.$button['actionClass'];
		else
		if (isset ($button['class']))
			$class .= ' '.$button['class'];

		$icon = '';

		foreach ($button as $btnPartId => $btnPartValue)
		{
			if (substr($btnPartId, 0, 5) === 'data-')
				$params .= ' '.$btnPartId."='".$btnPartValue."'";
		}

		switch ($button['action'])
		{
			case 'editform':
			case 'edit-iframe-doc':
				$btnClass = 'btn-primary';
				$icon = $this->systemIcon(SystemIcons::actionOpen);
				break;
			case 'deleteform':
				$btnClass = 'btn-danger';
				$icon = $this->systemIcon(SystemIcons::actionDelete);
				break;
			case 'print':
			case 'printdirect':
				$icon = $this->systemIcon(SystemIcons::actionPrint);
				$btnClass = 'btn-default';
				break;
			case 'new':
				$btnClass = 'btn-success';
				$class .= ' e10-document-trigger';
				$icon = $this->systemIcon(SystemIcons::actionAdd);
				break;
			case 'new-iframe-doc':
				$btnClass = 'btn-success';
				$icon = $this->systemIcon(SystemIcons::actionAdd);
				break;
			case 'newform':
				$btnClass = 'btn-success';
				$class .= ' df2-action-trigger';
				$icon = $this->systemIcon(SystemIcons::actionAdd);
				break;
			case 'addwizard':
				$btnClass = 'btn-success';
				break;
			case 'wizard':
				$btnClass = 'btn-warning';
				$class .= ' e10-document-trigger';
				break;
			case 'window':
				$btnClass = '';
				break;
			case 'moveDown':
				$btnClass = 'btn-default';
				$icon = $this->systemIcon(SystemIcons::actionMoveDown);
				break;
			case 'moveUp':
				$btnClass = 'btn-default';
				$icon = $this->systemIcon(SystemIcons::actionMoveUp);
				break;
			default:
				$btnClass = 'btn-default';
				break;
		}

		if (isset($button['btnClass']))
			$btnClass = $button['btnClass'];

		if ($mode === 0)
			$class .= ' '.$btnClass;

		if (isset($button ['icon']))
			$icon = "<i class='fa-fw ".$this->icons()->cssClass($button ['icon'])."'></i> ";

		$btnText = (isset($button['text'])) ? $this->es ($button['text']) : '';
		if (isset($button['title']))
			$params .= " title='".$this->es ($button['title'])."'";

		if ($mode === 1)
			$c .= "<li><a class='$class' data-action='{$button['action']}' $params>{$icon}&nbsp;{$btnText}</a></li>";
		else
		{
			if ($button['action'] !== '')
				$c .= "<$element class='$elementClass $class' data-action='{$button['action']}' $params>{$icon}&nbsp;{$btnText}</$element>";
		}

		if (isset ($button['subButtons']))
		{
			foreach($button['subButtons'] as $subbtn)
				$c .= $this->actionCode($subbtn);
		}

		if (isset ($button['dropdownMenu']))
		{
			if ($button['action'] === '')
				$c .= "<button type='button' class='$class $btnClass dropdown-toggle' data-toggle='dropdown'>{$icon}{$btnText}<span class='caret'></span></button>";
			elseif (isset ($button['dropRightEl']))
				$c .= "<button type='button' class='$btnClass dropdown-toggle' data-toggle='dropdown'><span class='caret' style='border: none; vertical-align: top; width: 1ex;'><i class='fa fa-ellipsis-v'></i></span></button>";
			elseif (isset ($button['dropRight']))
				$c .= "<button type='button' class='btn $btnClass dropdown-toggle' data-toggle='dropdown'><span class='caret'></span></button>";
			else
				$c .= "<button type='button' class='btn $btnClass dropdown-toggle' data-toggle='dropdown'><span class='caret'></span></button>";

			$dmc = (isset ($button['dropRight'])) ? ' dropdown-menu-right' : '';
			$c .= "<ul class='dropdown-menu$dmc' role='menu'>";

			foreach($button['dropdownMenu'] as $subbtn)
				$c .= $this->actionCode($subbtn, 1);

			$c .= '</ul>';
		}

		if (isset ($button['subButtons']) || isset ($button['dropdownMenu']))
			$c .= '</div>';

		return $c;
	}

	public function renderDocMark($p)
	{
		$t = '';
		$class = '';
		if (isset($p['class']))
			$class = $p['class'];

		$paramsCode = Utils::elementActionParams ($p,$class);

		$markId = intval($p['mark']);
		$elementId = Utils::elementId('mark'.$markId);
		$paramsCode .= " id='$elementId'";
		$paramsCode .= " data-mark='$markId'";

		if (isset($p['mark-st']))
			$paramsCode .= " data-mark-st='{$p['mark-st']}'";

		if (isset($p['title']))
			$paramsCode .= " title=\"".$this->es($p['title'])."\"";

		$markValue = intval($p['value']);

		$markCfg = $this->app()->cfgItem('docMarks.'.$markId, NULL);
		if (!$markCfg)
			return '';

		if ($markCfg['type'] === 'check')
		{
			$paramsCode .= " data-mark-type='check' data-mark-button-value=''";
		}

		$t .= "<span{$paramsCode} class='{$class}'>";

		if ($markCfg['type'] === 'check')
		{
			$iconClass = $this->icons()->cssClass($markCfg['states'][$markValue]['icon']);
			if ($markCfg['states'][$markValue]['classOn'] !== '')
				$iconClass .= ' '.$markCfg['states'][$markValue]['classOn'];
			$t .= "<i class='fa-fw $iconClass'></i>";
		}
		$t .= '</span> ';

		return $t;
	}

	public function icon(string $i, string $addClass = '', string $element = 'i')
	{
		return $this->icons->icon($i, $addClass, $element);
	}

	public function systemIcon(int $i, string $addClass = '', string $element = 'i')
	{
		return $this->icons->systemIcon($i, $addClass, $element);
	}

	public function renderTableFromArray ($rows, $columns, $params = [])
	{
		$tr = new \Shipard\Utils\TableRenderer($rows, $columns, $params, $this->app());
		return $tr->render();
	}	
}

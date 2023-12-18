<?php

namespace e10pro\zus\libs\ezk;

use \Shipard\Utils\Utils, \Shipard\Viewer\TableView;


/**
 * class ViewExcuses
 */
class ViewExcuses extends TableView
{
  var $studentNdx = 0;
	var $duvodyOmluveni;

	public function init ()
	{
		$userContexts = $this->app()->uiUserContext ();
		$ac = $userContexts['contexts'][$this->app()->uiUserContextId] ?? NULL;
		if ($ac)
		{
			$this->studentNdx = $ac['studentNdx'] ?? 0;
			$this->addAddParam ('student', $this->studentNdx);
		}

		$this->duvodyOmluveni = $this->app()->cfgItem('zus.duvodyOmluveni');

    $this->classes = ['viewerWithCards'];

    $this->enableFullTextSearch = FALSE;

		parent::init();

    $this->objectSubType = TableView::vsDetail;
		$this->uiSubTemplate = 'modules/e10pro/zus/libs/ezk/subtemplates/excuseRow';
	}

	public function renderRow ($item)
	{
		$listItem ['pk'] = $item ['ndx'];
    $listItem['class'] = 'shpd-card ps-3 pt-2 pe-3';

		$listItem ['icon'] = $this->table->tableIcon($item);
		$listItem ['t1'] = $item ['studentFullName'];

    $listItem ['t2'] = Utils::dateFromTo($item['datumOd'], $item['datumDo'], NULL);

    $listItem ['dateFrom'] = Utils::datef($item['datumOd'], '%d');
    $listItem ['dateTo'] = Utils::datef($item['datumDo'], '%d');
    $listItem ['longTerm'] = intval($item['datumOd'] != $item['datumDo']);//$item['dlouhodoba'];
    $listItem ['text'] = $item['text'];
		$listItem ['reason'] = $this->duvodyOmluveni[$item['duvod']]['fn'];

		return $listItem;
	}

	public function selectRows ()
	{
		$fts = $this->fullTextSearch ();

    $q = [];
    array_push($q, 'SELECT omluvenky.*,');
    array_push($q, ' students.fullName AS studentFullName');
    array_push($q, ' FROM [e10pro_zus_omluvenky] AS omluvenky');
    array_push($q, ' LEFT JOIN e10_persons_persons AS students ON omluvenky.student = students.ndx');
    array_push($q, ' WHERE 1');
    array_push($q, ' AND omluvenky.student = %i', $this->studentNdx);

		// -- fulltext
		if ($fts != '')
		{
			array_push ($q, ' AND (');
			array_push ($q, ' students.[fullName] LIKE %s', '%'.$fts.'%');
			array_push ($q, ' OR omluvenky.[text] LIKE %s', '%'.$fts.'%');
			array_push ($q, ') ');
		}

		$this->queryMain ($q, 'omluvenky.', ['[datumOd] DESC', '[ndx]']);
		$this->runQuery ($q);
	}

  public function createToolbar_TMP()
	{
		return [];
	}
}

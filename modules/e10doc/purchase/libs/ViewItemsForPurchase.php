<?php

namespace e10doc\purchase\libs;


use e10doc\core\e10utils;
use E10\utils;



class ViewItemsForPurchase extends \e10\witems\ViewItems
{
	public function init ()
	{
		parent::init();

		$this->withInventory = FALSE;
		$this->showPrice = self::PRICE_BUY;
		$this->itemKind = FALSE;

		if (intval($this->table->app()->cfgItem ('options.e10doc-buy.purchItemComboSearch', 0)) === 0)
			$this->enableFullTextSearch = FALSE;

		unset ($this->mainQueries); // TODO: better way

		$comboByCats = intval($this->table->app()->cfgItem ('options.e10doc-buy.purchItemComboCats', 0));
		$defaultCat = intval($this->table->app()->cfgItem ('options.e10doc-buy.purchItemDefaultComboCat', 0));

		$allId = '';
		if ($comboByCats)
			$allId = 'c'.$comboByCats;

		$bt [] = ['id' => $allId, 'title' => 'Vše', 'active' => ($defaultCat === 0) ? 1 : 0];
		$comboByTypes = intval($this->table->app()->cfgItem ('options.e10doc-buy.purchItemComboByTypes', 0));
		if ($comboByTypes)
		{
			$itemTypes = $this->table->app()->cfgItem ('e10.witems.types');

			forEach ($itemTypes as $itemTypeId => $itemType)
			{
				if ($itemTypeId === 'none')
					continue;
				$bt [] = array ('id' => 't'.$itemTypeId, 'title' => $itemType['shortName'], 'active' => 0,
					'addParams' => array ('type' => $itemTypeId));
			}
		}

		if ($comboByCats !== 0)
		{
			$catPath = $this->table->app()->cfgItem ('e10.witems.categories.list.'.$comboByCats, '---');
			$cats = $this->table->app()->cfgItem ("e10.witems.categories.tree".$catPath.'.cats');
			forEach ($cats as $catId => $cat)
			{
				$bt [] = ['id' => 'c'.$cat['ndx'], 'title' => $cat['shortName'], 'active' => ($defaultCat == $cat['ndx']) ? 1 : 0];
			}
		}

		if (count ($bt) > 1)
			$this->setTopTabs ($bt);
	}

	public function qryColumns (array &$q)
	{
		if ($this->activeCategory !== FALSE && $this->activeCategory['si'] === 'top')
		{
			array_push($q, ', (SELECT cnt FROM e10doc_base_statsItemDocType WHERE docType = %s AND items.ndx = item) as cnt', 'purchase');
		}
		else
		if ($this->activeCategory !== FALSE && $this->activeCategory['si'] === 'person')
		{
			$person = $this->queryParam('person');
			if ($person)
			{
				array_push($q, ', (SELECT cnt FROM e10doc_base_statsPersonItemDocType WHERE docType = %s AND person = %i AND items.ndx = item) as cnt1', 'purchase', $person);
				array_push($q, ', (SELECT cnt FROM e10doc_base_statsItemDocType WHERE docType = %s AND items.ndx = item) as cnt2', 'purchase');
			}
			else
				array_push($q, ', (SELECT cnt FROM e10doc_base_statsItemDocType WHERE docType = %s AND items.ndx = item) as cnt', 'purchase');
		}
	}

	public function qryOrder (array &$q, $mainQueryId)
	{
		if ($this->activeCategory !== FALSE && $this->activeCategory['si'] === 'person')
		{
			$person = $this->queryParam('person');
			if ($person)
				array_push($q, ' ORDER BY cnt1 DESC, cnt2 DESC, [items].[fullName]');
			else
				array_push($q, ' ORDER BY cnt DESC, [items].[fullName]');
		}
		else
		if ($this->activeCategory !== FALSE && $this->activeCategory['si'] === 'top')
		{
			array_push($q, ' ORDER BY cnt DESC, [items].[fullName]');
		}
		else
		if ($this->activeCategory !== FALSE && $this->activeCategory['si'] === 'cashreg')
		{
			array_push($q, ' ORDER BY orderCashRegister, [items].[fullName]');
		}
		else
			parent::qryOrder($q, $mainQueryId);
	}

	public function renderRow ($item)
	{
		$thisItemType = $this->table->itemType ($item, TRUE);

		$listItem ['pk'] = $item ['ndx'];
		$listItem ['tt'] = $item['shortName'];
		$listItem ['icon'] = $this->table->icon ($item);

		if ($thisItemType['kind'] !== 2)
		{
			$listItem ['i2'] = ['text' => ''];

			if ($this->showPrice === self::PRICE_SALE)
			{
				if ($item['priceSell'])
					$listItem ['i2'] = ['text' => utils::nf($item['priceSell'], 2)];
			}
			else
			if ($this->showPrice === self::PRICE_BUY)
			{
				if ($item['priceBuy'])
					$listItem ['i2'] = ['text' => utils::nf($item['priceBuy'], 2)];
			}

			if ($item['defaultUnit'] !== '')
				$listItem ['i2']['prefix'] = $this->units[$item['defaultUnit']]['shortcut'];
		}
/*
		if (!isset ($this->defaultType))
		{
			$listItem ['t2'] = $this->table->itemType ($item);
			if ($item['useFor'] !== 0)
			{
				$useFor = $this->table->columnInfoEnum ('useFor', 'cfgText');
				$listItem ['t2'] .= ' / '.$useFor [$item ['useFor']];
			}
		}
*/

		if ($item['groupCashRegister'] !== '' && $this->activeCategory !== FALSE && $this->activeCategory['si'] === 'cashreg')
			$this->addGroupHeader ($item['groupCashRegister']);

		return $listItem;
	}

	function decorateRow (&$item)
	{
		if (isset ($this->itemsStates [$item ['pk']]))
			$item ['i2'] = \E10\nf ($this->itemsStates [$item ['pk']]['quantity'], 2).' '.$this->itemsStates [$item ['pk']]['unit'] .
					(isset($item ['i2']['text']) ? ' / '.$item ['i2']['text'] : '');
	}

} // class ViewItemsForPurchase


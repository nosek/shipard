<?php

namespace e10\witems\dc;
use E10\utils;


/**
 * Class Item
 * @package e10\witems\dc
 */
class Item extends \e10\DocumentCard
{
	var $dataSuppliers = [];
	var $relatedItems = [];

	public function createContentBody ()
	{
		$this->createContentBody_Suppliers();
		$this->createContentBody_Related();
		$this->addContent ('body', \E10\Base\getPropertiesDetail ($this->table, $this->recData));
		$this->createContentBody_Set ();
		$this->createContentBody_Annotations();
		$this->addContentAttachments ($this->recData ['ndx']);
	}

	public function createContentBody_Related ()
	{
		$q[] = 'SELECT [ir].*,';
		array_push($q, ' relatedKinds.ndx AS relKindNdx, relatedKinds.fullName AS relKindName, relatedKinds.icon AS relKindIcon,');
		array_push($q, ' relItems.ndx AS relItemNdx, relItems.fullName AS relItemName, relItems.id AS relItemId, relItems.[type] AS relItemType');
		array_push($q, ' FROM [e10_witems_itemRelated] AS [ir]');
		array_push($q, ' LEFT JOIN [e10_witems_relatedKinds] AS relatedKinds ON [ir].kind = relatedKinds.ndx');
		array_push($q, ' LEFT JOIN [e10_witems_items] AS relItems ON ir.relatedItem = relItems.ndx');
		array_push($q, ' WHERE ir.[srcItem] = %i', $this->recData['ndx']);
		array_push($q, ' ORDER BY relatedKinds.[order], relItems.fullName');

		$rows = $this->db()->query($q);
		foreach ($rows as $r)
		{
			$itemType = $this->app()->cfgItem ('e10.witems.types.'.$r['relItemType'], FALSE);

			$item = [
				'itemId' => ['text' => $r['relItemId'], 'docAction' => 'edit', 'table' => 'e10.witems.items', 'pk' => $r['relItemNdx']],
				'itemType' => $itemType['shortName'],
				'itemTitle' => ['text' => $r['relItemName']],
			];

			if (!isset($this->relatedItems[$r['relKindNdx']]))
			{
				$this->relatedItems[$r['relKindNdx']] = ['title' => $r['relKindName'], 'items' => []];
			}

			$this->relatedItems[$r['relKindNdx']]['items'][] = $item;
		}

		if (!count($this->relatedItems))
			return;


		$t = [];
		foreach ($this->relatedItems as $riKind)
		{
			$item = ['itemId' => $riKind['title'], '_options' => ['colSpan' => ['itemId' => 3], 'class' => 'subheader']];
			$t[] = $item;
			foreach ($riKind['items'] as $oneItem)
			{
				$t[] = $oneItem;
			}
		}

		$h = ['itemId' => 'Položka', 'itemType' => 'Typ', 'itemTitle' => 'Název',];
		$title = [['text' => 'Související položky', 'class' => 'h1']];
		$this->addContent ('body', [
			'pane' => 'e10-pane e10-pane-table', 'paneTitle' => $title,
			'type' => 'table', 'table' => $t, 'header' => $h,
			'params' => ['hideHeader' => 1]
		]);
	}

	public function createContentBody_Suppliers ()
	{
		$q[] = 'SELECT spl.*,';
		array_push($q, ' persons.fullName AS supplierName');
		array_push($q, ' FROM [e10_witems_itemSuppliers] AS spl');
		array_push($q, ' LEFT JOIN [e10_persons_persons] AS persons ON spl.supplier = persons.ndx');
		array_push($q, ' WHERE spl.[item] = %i', $this->recData['ndx']);
		array_push($q, ' ORDER BY spl.rowOrder, spl.ndx');

		$rows = $this->db()->query($q);
		foreach ($rows as $r)
		{
			$item = [
				'itemId' => $r['itemId'],
				'supplier' => ['text' => $r['supplierName'], 'url' => $r['url']],
			];

			$this->dataSuppliers[] = $item;
		}

		if (count($this->dataSuppliers))
		{
			$h = ['supplier' => 'Dodavatel', 'itemId' => 'Kód dod.',];
			$title = [['text' => 'Dodavatelé', 'class' => 'h1']];
			$this->addContent ('body', [
				'pane' => 'e10-pane e10-pane-table', 'paneTitle' => $title,
				'type' => 'table', 'table' => $this->dataSuppliers, 'header' => $h
			]);
		}
	}

	public function createContentBody_Set ()
	{
		if (!$this->recData['isSet'])
			return;

		$errors = [];

		$today = utils::today();

		$q = [];
		array_push ($q, 'SELECT [setRows].*,');
		array_push ($q, ' [dstItems].fullName AS dstFullName, [dstItems].[id] AS dstId,');
		array_push ($q, ' [dstItems].validFrom AS dstItemValidFrom, [dstItems].validTo AS dstItemValidTo,');
		array_push ($q, ' [dstTypes].fullName AS dstTypeName, [dstTypes].[type] AS dstTypeType');
		array_push ($q, ' FROM [e10_witems_itemsets] AS [setRows]');
		array_push ($q, ' LEFT JOIN [e10_witems_items] AS [dstItems] ON [setRows].[item] = [dstItems].[ndx]');
		array_push ($q, ' LEFT JOIN [e10_witems_itemtypes] AS [dstTypes] ON dstItems.itemType = [dstTypes].ndx');
		array_push ($q, ' WHERE [setRows].[itemOwner] = %i', $this->recData['ndx']);

		$t = [];
		$h = ['id' => 'Pol.', 'title' => 'Název', 'type' => 'Typ', 'quantity' => ' Množ.'];

		$cntInvalid = 0;
		$cntValid = 0;
		$rows = $this->db()->query($q);
		foreach ($rows as $r)
		{
			$rowIsValid = 1;

			$itm = [
				'id' => ['text' => $r['dstId'], 'docAction' => 'edit', 'table' => 'e10.witems.items', 'pk' => $r['item']],
				'title' => [['text' => $r['dstFullName'], 'class' => 'block']],
				'type' => [['text' => $r['dstTypeName'], 'class' => 'block']],
				'quantity' => $r['quantity'],
			];

			if ($r['setItemType'] === 0 && $r['dstTypeType'] != 1)
			{
				$itm['type'][] = ['text' => 'Položka není Zásoba', 'icon' => 'system/iconWarning', 'class' => 'e10-error label label-default'];
				$rowIsValid = 0;
			}

			if (!utils::dateIsBlank($r['validTo']) /*&& $r['validTo'] < $today*/)
			{
				$itm['title'][] = ['text' => 'Platné do '.utils::datef($r['validTo']), 'class' => 'label label-default'];

			}

			if (!utils::dateIsBlank($r['dstItemValidTo']) && $r['dstItemValidTo'] < $today && $rowIsValid)
			{
				if (utils::dateIsBlank($r['validTo']) || (!utils::dateIsBlank($r['validTo']) && $r['validTo'] > $r['dstItemValidTo']))
				{
					$itm['title'][] = ['text' => 'Položka je neplatná k ' . utils::datef($r['dstItemValidTo']), 'class' => 'e10-error block'];
					$rowIsValid = 0;
				}
			}

			if (!utils::dateIsBlank($r['validFrom']))
			{
				$itm['title'][] = ['text' => 'Platné od ' . utils::datef($r['validFrom']), 'class' => 'label label-default'];
			}

			if ($rowIsValid)
				$cntValid++;
			else
				$cntInvalid++;

			$t[] = $itm;
		}

		if (!$cntValid)
			$errors[] = ['text' => 'Sada neobsahuje žádnou platnou položku', 'class' => 'e10-error block'];
		elseif ($cntInvalid && !$cntValid)
			$errors[] = ['text' => 'Sada obsahuje vadné řádky', 'class' => 'e10-error block'];

		$title = [['text' => 'Sada', 'class' => 'h1']];
		if (count($errors))
			$title = array_merge($title, $errors);

		$this->addContent ('body', [
			'pane' => 'e10-pane e10-pane-table', 'paneTitle' => $title,
			'type' => 'table', 'table' => $t, 'header' => $h
		]);
	}

	public function createContentBody_Annotations ()
	{
		$annots = new \e10pro\kb\libs\AnnotationsList($this->app());
		$annots->addRecord($this->table->ndx, $this->recData['ndx']);
		$annots->load();
		$code = $annots->code();

		if ($code === '')
			return;

		$title = [['text' => 'Odkazy', 'class' => 'h1']];
		$this->addContent ('body', [
			'pane' => 'e10-pane e10-pane-table pageText', 'paneTitle' => $title,
			'type' => 'line', 'line' => ['code' => $code],
		]);

	}

	public function createContentTitle ()
	{
		$title = ['icon' => $this->table->icon ($this->recData), 'text' => $this->recData ['fullName']];
		$this->addContent('title', ['type' => 'line', 'line' => $title]);

		$itemsTypes = $this->app->cfgItem ('e10.witems.types');
		$subTitle = [];
		if (isset ($itemsTypes [$this->recData['type']]))
			$subTitle[] = ['text' => $itemsTypes [$this->recData['type']]['.text']];

		if ($this->recData['brand'])
		{
			$brand = $this->app()->loadItem($this->recData['brand'], 'e10.witems.brands');
			$subTitle[] = ['text' => $brand['fullName']];
		}

		$this->addContent('subTitle', ['type' => 'line', 'line' => $subTitle]);
	}

	public function createContent ()
	{
		$this->createContentBody ();
		$this->createContentTitle ();
	}
}

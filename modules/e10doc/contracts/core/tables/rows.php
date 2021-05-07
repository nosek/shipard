<?php

namespace E10Doc\Contracts\Core {

use \E10\Application;
use \E10\HeaderData;
use \E10\DbTable;
use \E10\TableForm;


/**
 * Řádky Smluv prodejních
 *
 */

class TableRows extends DbTable
{
	public function __construct ($dbmodel)
	{
		parent::__construct ($dbmodel);
		$this->setName ("e10doc.contracts.core.rows", "e10doc_contracts_rows", "Řádky smluv", 1101);
	}

	public function checkBeforeSave (&$recData, $ownerData = NULL)
	{
		if (!isset ($recData ['quantity']))
			$recData ['quantity'] = 1;
		$recData ['priceAll'] = $recData ['priceItem'] * $recData ['quantity'];
		if ((!isset($recData ['centre']) || $recData ['centre'] == 0) && isset($ownerData ['centre']) && $ownerData ['centre'] != 0)
			$recData ['centre'] = $ownerData ['centre'];
		parent::checkBeforeSave ($recData, $ownerData);
	}

	public function formId ($recData, $ownerRecData = NULL, $operation = 'edit')
	{
		return 'sale';
	}

	public function useBookingCapacity ($recData)
	{
		if (!isset ($recData['bookingPlace']) || !$recData['bookingPlace'])
			return FALSE;
		$placeRec = $this->app()->loadItem($recData['bookingPlace'], 'e10.base.places');
		if (!$placeRec)
			return FALSE;
		$bookingType = $this->app()->cfgItem ('e10pro.bookingTypes.'.$placeRec['bookingType'], FALSE);
		if (!$bookingType)
			return FALSE;
		if (!$bookingType['uc'])
			return FALSE;

		return $bookingType;
	}

	public function columnInfoEnum ($columnId, $valueType = 'cfgText', \E10\TableForm $form = NULL)
	{
		if ($columnId !== 'cntParts')
			return parent::columnInfoEnum ($columnId, $valueType, $form);

		$enum[100] = 'Celá kapacita';

		if ($form)
		{
			$bookingType = $this->useBookingCapacity($form->recData);
			if ($bookingType !== FALSE)
			{
				$placeRec = $this->app()->loadItem($form->recData['bookingPlace'], 'e10.base.places');
				for ($ii = $placeRec['bookingCapacity'] - 1; $ii > 0; $ii--)
					$enum[$ii] = strval($ii);
			}
		}


		return $enum;
	}
}

/**
 * Editační formulář Řádku smlouvy
 *
 */

class FormRow extends TableForm
{
	public function renderForm ()
	{
		$ownerRecData = $this->option ('ownerRecData');

		$this->openForm (TableForm::ltGrid);

			$this->openRow ();
				$this->addColumnInput ("item", TableForm::coColW12);
			$this->closeRow ();

			$this->openRow ();
				$this->addColumnInput ("text", TableForm::coColW12);
			$this->closeRow ();

			$this->openRow ();
				$this->addColumnInput ("quantity", TableForm::coColW4);
				$this->addColumnInput ("unit", TableForm::coColW4);
				$this->addColumnInput ("priceItem", TableForm::coColW4);
			$this->closeRow ();

			$this->openRow ();
				$this->addColumnInput ("start", TableForm::coColW4);
				$this->addColumnInput ("end", TableForm::coColW4);
			if ($this->table->app()->cfgItem ('options.core.useCentres', 0))
				$this->addColumnInput ("centre", TableForm::coColW4);
			$this->closeRow ();

			if (isset($ownerRecData['bookingPlaces']) && $ownerRecData['bookingPlaces'])
			{
				$this->openRow ();
					if ($this->table->useBookingCapacity ($this->recData))
					{
						$this->addColumnInput ('bookingPlace', TableForm::coColW9);
						$this->addColumnInput ('cntParts', TableForm::coColW3);
					}
					else
						$this->addColumnInput ('bookingPlace', TableForm::coColW12);
				$this->closeRow ();
			}

		$this->closeForm ();
	}
} // class FormContractSaleRows

} // namespace E10Doc\Contracts\Sale

